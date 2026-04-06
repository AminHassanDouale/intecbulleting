<?php

namespace Database\Seeders;

use App\Enums\AcademicLevelEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * FullSchoolSeeder
 *
 * Seeds classrooms + ONE teacher per class.
 * No students — import them via the Excel template.
 */
class FullSchoolSeeder extends Seeder
{
    // ── PRÉSCOLAIRE — 1 teacher per class ────────────────────────────────────
    private array $prescoClassrooms = [
        ['code' => 'PS',  'section' => 'A', 'label' => 'PS A',  'subject_class_code' => null,
         'teacher_name' => 'Habibo HASSAN',   'teacher_email' => 'prof.ps.a@intec.edu'],

        ['code' => 'MS',  'section' => 'A', 'label' => 'MS A',  'subject_class_code' => null,
         'teacher_name' => 'Nasrin OMAR',     'teacher_email' => 'prof.ms.a@intec.edu'],

        ['code' => 'MS',  'section' => 'B', 'label' => 'MS B',  'subject_class_code' => null,
         'teacher_name' => 'Safia WAIS',      'teacher_email' => 'prof.ms.b@intec.edu'],

        ['code' => 'GS',  'section' => 'A', 'label' => 'GS A',  'subject_class_code' => null,
         'teacher_name' => 'Farhiya AHMED',   'teacher_email' => 'prof.gs.a@intec.edu'],

        ['code' => 'GS',  'section' => 'B', 'label' => 'GS B',  'subject_class_code' => null,
         'teacher_name' => 'Khadra OSMAN',    'teacher_email' => 'prof.gs.b@intec.edu'],
    ];

    // ── PRIMAIRE — 1 teacher per class ───────────────────────────────────────
    private array $primaireClassrooms = [
        ['code' => 'CP',  'section' => 'A', 'label' => 'CP A',  'subject_class_code' => 'CP',
         'teacher_name' => 'Hawa IBRAHIM',    'teacher_email' => 'prof.cp.a@intec.edu'],

        ['code' => 'CP',  'section' => 'B', 'label' => 'CP B',  'subject_class_code' => 'CP',
         'teacher_name' => 'Safia WARSAME',   'teacher_email' => 'prof.cp.b@intec.edu'],

        ['code' => 'CE1', 'section' => 'A', 'label' => 'CE1 A', 'subject_class_code' => 'CE1',
         'teacher_name' => 'Aminata SOUMAH',  'teacher_email' => 'prof.ce1.a@intec.edu'],

        ['code' => 'CE1', 'section' => 'B', 'label' => 'CE1 B', 'subject_class_code' => 'CE1',
         'teacher_name' => 'Nasteho YUSUF',   'teacher_email' => 'prof.ce1.b@intec.edu'],

        ['code' => 'CE2', 'section' => 'A', 'label' => 'CE2 A', 'subject_class_code' => 'CE2',
         'teacher_name' => 'Fatou BAMBA',     'teacher_email' => 'prof.ce2.a@intec.edu'],

        ['code' => 'CE2', 'section' => 'B', 'label' => 'CE2 B', 'subject_class_code' => 'CE2',
         'teacher_name' => 'Rashid GULED',    'teacher_email' => 'prof.ce2.b@intec.edu'],

        ['code' => 'CM1', 'section' => 'A', 'label' => 'CM1 A', 'subject_class_code' => 'CM1',
         'teacher_name' => 'Amadou DIALLO',   'teacher_email' => 'prof.cm1.a@intec.edu'],

        ['code' => 'CM2', 'section' => 'A', 'label' => 'CM2 A', 'subject_class_code' => 'CM2',
         'teacher_name' => 'Deeqa FARAH',     'teacher_email' => 'prof.cm2.a@intec.edu'],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $year = AcademicYear::where('is_current', true)->firstOrFail();

        $prescoNiveau   = Niveau::where('code', AcademicLevelEnum::PRESCOLAIRE->value)->first();
        $primaireNiveau = Niveau::where('code', AcademicLevelEnum::PRIMAIRE->value)->first();

        $count = 0;

        if ($prescoNiveau) {
            foreach ($this->prescoClassrooms as $cfg) {
                $this->seedClassroom($cfg, $prescoNiveau, $year);
                $count++;
            }
        }

        if ($primaireNiveau) {
            foreach ($this->primaireClassrooms as $cfg) {
                $this->seedClassroom($cfg, $primaireNiveau, $year);
                $count++;
            }
        }

        $this->command->info("✓ {$count} classrooms seeded (1 teacher each, no students).");
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedClassroom(array $cfg, Niveau $niveau, AcademicYear $year): void
    {
        // Create teacher
        $teacher = User::firstOrCreate(
            ['email' => $cfg['teacher_email']],
            ['name' => $cfg['teacher_name'], 'password' => Hash::make('password')]
        );

        if (!$teacher->hasRole('teacher')) {
            $teacher->assignRole('teacher');
        }

        // Create classroom
        $classroom = Classroom::updateOrCreate(
            ['code' => $cfg['code'], 'section' => $cfg['section'], 'academic_year_id' => $year->id],
            ['label' => $cfg['label'], 'teacher_id' => $teacher->id, 'niveau_id' => $niveau->id]
        );

        // Assign teacher to all subjects of this class
        $subjects = Subject::where('niveau_id', $niveau->id)
            ->when(
                $cfg['subject_class_code'],
                fn($q) => $q->where('classroom_code', $cfg['subject_class_code']),
                fn($q) => $q->whereNull('classroom_code')
            )
            ->get();

        foreach ($subjects as $subject) {
            if (!$subject->teachers()->where('users.id', $teacher->id)->exists()) {
                $subject->teachers()->attach($teacher->id);
            }
            if (!$classroom->teachers()->where('users.id', $teacher->id)->exists()) {
                $classroom->teachers()->attach($teacher->id);
            }
        }
    }
}
