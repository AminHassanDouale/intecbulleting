<?php

namespace Database\Seeders;

use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\BulletinGrade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\BulletinTeacherSubmission;
use App\Models\WorkflowApproval;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BulletinsSeeder extends Seeder
{
    // T1 dates: mid-October submit → early November published
    // T2 dates: mid-February submit → early March published
    // T3 dates: mid-May submit → early June published
    private array $periodDates = [
        'T1' => ['submit' => '-8 months', 'publish' => '-7 months 20 days'],
        'T2' => ['submit' => '-4 months', 'publish' => '-3 months 20 days'],
        'T3' => ['submit' => '-1 month',  'publish' => '-20 days'],
    ];

    private User $pedagogie;
    private User $finance;
    private User $direction;

    public function run(): void
    {
        $year = AcademicYear::where('is_current', true)->firstOrFail();

        $this->pedagogie = User::role('pedagogie')->firstOrFail();
        $this->finance   = User::role('finance')->firstOrFail();
        $this->direction = User::role('direction')->firstOrFail();

        $students = Student::with(['classroom.niveau', 'classroom.teacher'])->get();

        // T1 → PUBLISHED (past, fully done)
        // T2 → SUBMITTED (pédagogie queue — ready to approve/reject)
        // T3 → DRAFT     (teachers still filling marks)
        $periods = [PeriodEnum::TRIMESTRE_1, PeriodEnum::TRIMESTRE_2, PeriodEnum::TRIMESTRE_3];

        $done = 0;

        foreach ($periods as $period) {
            $this->command->getOutput()->writeln(
                "  → <info>{$period->label()}</info> ({$students->count()} élèves)"
            );

            foreach ($students as $student) {
                $this->seedBulletin($student, $period, $year);
                $done++;
            }
        }

        $this->command->getOutput()->writeln("  ✓ <info>{$done} bulletins traités.</info>");
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedBulletin(Student $student, PeriodEnum $period, AcademicYear $year): void
    {
        $bulletin = Bulletin::firstOrCreate(
            [
                'student_id'       => $student->id,
                'classroom_id'     => $student->classroom_id,
                'academic_year_id' => $year->id,
                'period'           => $period->value,
            ],
            ['status' => BulletinStatusEnum::DRAFT]
        );

        // Idempotent: skip already-published bulletins
        if ($bulletin->status === BulletinStatusEnum::PUBLISHED) {
            return;
        }

        $this->fillGrades($bulletin, $student);

        $bulletin->recalculateMoyenne();
        $bulletin->refresh();

        $this->advanceToPublished($bulletin, $student, $period);
    }

    // ─── Grade filling ────────────────────────────────────────────────────────

    private function fillGrades(Bulletin $bulletin, Student $student): void
    {
        $subjects = Subject::whereHas('niveau', fn($q) =>
                $q->where('code', $student->classroom->niveau->code)
            )
            ->where(fn($q) =>
                $q->whereNull('classroom_code')
                  ->orWhere('classroom_code', $student->classroom->code)
            )
            ->with('competences')
            ->get();

        foreach ($subjects as $subject) {
            foreach ($subject->competences as $competence) {
                if ($subject->scale_type === 'competence') {
                    // Préscolaire: A 60% / EVA 30% / NA 10%
                    $status = $this->weightedPick(['A', 'EVA', 'NA'], [60, 30, 10]);

                    BulletinGrade::updateOrCreate(
                        [
                            'bulletin_id'   => $bulletin->id,
                            'competence_id' => $competence->id,
                            'period'        => $bulletin->period,
                        ],
                        ['score' => null, 'competence_status' => $status]
                    );
                } else {
                    $score = $this->realisticScore($competence->max_score, $student->id);

                    BulletinGrade::updateOrCreate(
                        [
                            'bulletin_id'   => $bulletin->id,
                            'competence_id' => $competence->id,
                            'period'        => $bulletin->period,
                        ],
                        ['score' => $score, 'competence_status' => null]
                    );
                }
            }
        }
    }

    // ─── Workflow: DRAFT → SUBMITTED → … → PUBLISHED ─────────────────────────

    private function advanceToPublished(Bulletin $bulletin, Student $student, PeriodEnum $period): void
    {
        $dates    = $this->periodDates[$period->value];
        $baseDate = Carbon::now()->modify($dates['submit']);
        $teacher  = $student->classroom->teacher ?? $this->direction;

        // Subject teachers for this classroom (via pivot + subject assignments)
        $subjectTeachers = User::whereHas('classrooms', fn($q) =>
            $q->where('classrooms.id', $student->classroom_id)
        )->get();

        // ── T3 → leave as DRAFT (teachers are still filling in marks) ────────
        if ($period === PeriodEnum::TRIMESTRE_3) {
            $bulletin->update([
                'status'          => BulletinStatusEnum::DRAFT,
                'teacher_comment' => null,
            ]);
            return;
        }

        // ── Create teacher submission records ─────────────────────────────────
        if (BulletinTeacherSubmission::where('bulletin_id', $bulletin->id)->doesntExist()) {
            foreach ($subjectTeachers as $subjectTeacher) {
                BulletinTeacherSubmission::create([
                    'bulletin_id'  => $bulletin->id,
                    'teacher_id'   => $subjectTeacher->id,
                    'status'       => 'submitted',
                    'submitted_at' => $baseDate->copy()->addHours(mt_rand(1, 8)),
                    'created_at'   => $baseDate->copy(),
                    'updated_at'   => $baseDate->copy(),
                ]);
            }
        }

        // ── T2 → SUBMITTED (waiting for pédagogie approval) ──────────────────
        if ($period === PeriodEnum::TRIMESTRE_2) {
            $bulletin->update([
                'status'          => BulletinStatusEnum::SUBMITTED,
                'teacher_comment' => $this->teacherComment($bulletin->moyenne),
                'submitted_by'    => $teacher->id,
                'submitted_at'    => $baseDate->copy(),
            ]);
            return;
        }

        // ── T1 → fully PUBLISHED with complete workflow history ───────────────
        if (WorkflowApproval::where('bulletin_id', $bulletin->id)->doesntExist()) {
            $approvalSteps = [
                ['step' => 'submitted',          'user' => $this->pedagogie, 'days' => 2,  'comment' => 'Notes vérifiées. Dossier validé.'],
                ['step' => 'pedagogie_approved', 'user' => $this->finance,   'days' => 5,  'comment' => 'Validé par le service finance.'],
                ['step' => 'finance_approved',   'user' => $this->direction, 'days' => 8,  'comment' => 'Approuvé par la direction.'],
                ['step' => 'approved',           'user' => $this->direction, 'days' => 10, 'comment' => 'Bulletin publié.'],
            ];

            foreach ($approvalSteps as $row) {
                WorkflowApproval::create([
                    'bulletin_id' => $bulletin->id,
                    'step'        => $row['step'],
                    'action'      => 'approved',
                    'user_id'     => $row['user']->id,
                    'comment'     => $row['comment'],
                    'created_at'  => $baseDate->copy()->addDays($row['days']),
                    'updated_at'  => $baseDate->copy()->addDays($row['days']),
                ]);
            }
        }

        $publishDate = Carbon::now()->modify($dates['publish']);

        $bulletin->update([
            'status'                => BulletinStatusEnum::PUBLISHED,
            'teacher_comment'       => $this->teacherComment($bulletin->moyenne),
            'submitted_by'          => $teacher->id,
            'submitted_at'          => $baseDate->copy(),
            'pedagogie_approved_by' => $this->pedagogie->id,
            'pedagogie_approved_at' => $baseDate->copy()->addDays(2),
            'finance_approved_by'   => $this->finance->id,
            'finance_approved_at'   => $baseDate->copy()->addDays(5),
            'direction_approved_by' => $this->direction->id,
            'direction_approved_at' => $baseDate->copy()->addDays(8),
            'published_at'          => $publishDate,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Generate a realistic score: normal distribution centred at 65% of max,
     * std-dev ≈ 15%.  Deterministically seeded per student so the same student
     * always gets roughly the same "performance profile".
     */
    private function realisticScore(int $max, int $studentId): float
    {
        // Box-Muller transform for approximate normal distribution
        $u1 = (($studentId * 1234567 + mt_rand(1, 9999)) % 100000) / 100000.0;
        $u2 = mt_rand(1, 100000) / 100000.0;

        $u1 = max(0.00001, $u1);
        $z  = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

        $mean  = $max * 0.65;
        $sigma = $max * 0.18;
        $score = round($mean + $sigma * $z, 1);

        return max(0, min((float) $max, $score));
    }

    /** Pick a value from $items using weighted probability. */
    private function weightedPick(array $items, array $weights): string
    {
        $total      = array_sum($weights);
        $rand       = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($items as $i => $item) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return $items[0];
    }

    private function teacherComment(?float $moyenne): string
    {
        if ($moyenne === null) {
            return 'Évaluation par compétences — voir détail ci-dessus.';
        }

        return match (true) {
            $moyenne >= 16 => 'Excellent trimestre ! Félicitations pour ces résultats remarquables.',
            $moyenne >= 14 => 'Très bon travail, continuez sur cette lancée.',
            $moyenne >= 12 => 'Bon trimestre dans l\'ensemble. Des progrès notables.',
            $moyenne >= 10 => 'Résultats satisfaisants. Persévérance encouragée.',
            $moyenne >= 8  => 'Des lacunes persistent. Un accompagnement est recommandé.',
            default        => 'Résultats insuffisants. Un effort soutenu est nécessaire.',
        };
    }
}
