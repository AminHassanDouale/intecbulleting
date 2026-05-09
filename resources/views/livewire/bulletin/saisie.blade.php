<?php

use App\Actions\Bulletin\CreateBulletinAction;
use App\Actions\Bulletin\SubmitTeacherSubjectsAction;
use App\Actions\Grade\SaveGradeAction;
use App\Enums\BulletinStatusEnum;
use App\Enums\CompetenceStatusEnum;
use App\Enums\PeriodEnum;
use App\Exports\GradeSheetExport;
use App\Exports\GradeSheetDirectorExport;
use App\Imports\GradeSheetImport;
use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Student;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public ?int    $selectedYear      = null;
    public ?string $selectedNiveau    = null;
    public ?int    $selectedClassroom = null;
    public string  $selectedPeriod    = 'T1';

    public ?int    $selectedStudent  = null;
    public ?int    $bulletinId       = null;
    public array   $grades           = [];
    public string  $teacherComment   = '';
    public ?string $disciplineStatus = '';
    public string  $totalManuel      = '';
    public string  $moyenne10        = '';
    public string  $moyenneClasse    = '';
    public $importFile = null;

    public function mount(): void
    {
        $this->selectedYear   = AcademicYear::current()?->id;
        $this->selectedPeriod = PeriodEnum::current()->value;
    }

    public function updatedSelectedNiveau(): void    { $this->selectedClassroom = null; $this->resetForm(); }
    public function updatedSelectedClassroom(): void { $this->resetForm(); }
    public function updatedSelectedPeriod(): void    { $this->resetForm(); }

    public function selectStudent(int $studentId): void
    {
        if ($this->selectedStudent === $studentId) {
            $this->resetForm();
            return;
        }

        $this->selectedStudent  = $studentId;
        $this->grades           = [];
        $this->teacherComment   = '';
        $this->disciplineStatus = '';
        $this->totalManuel      = '';
        $this->moyenne10        = '';
        $this->moyenneClasse    = '';
        $this->bulletinId       = null;

        if (! $this->isPeriodLocked($studentId)) {
            $this->loadOrCreateBulletin($studentId);
        }
    }

    protected function loadOrCreateBulletin(int $studentId): void
    {
        if (! $this->selectedPeriod || ! $this->selectedYear) return;

        $student  = Student::findOrFail($studentId);
        $bulletin = app(CreateBulletinAction::class)->execute($student, $this->selectedPeriod, $this->selectedYear);

        $this->bulletinId       = $bulletin->id;
        $this->teacherComment   = $bulletin->teacher_comment ?? '';
        $this->disciplineStatus = $bulletin->discipline_status ?? '';
        $this->totalManuel      = (string) ($bulletin->total_manuel ?? '');
        $this->moyenne10        = (string) ($bulletin->moyenne_10 ?? '');
        $this->moyenneClasse    = (string) ($bulletin->moyenne_classe ?? '');
        $this->grades           = [];

        foreach ($bulletin->grades as $grade) {
            $this->grades[$grade->competence_id] = $grade->score ?? $grade->competence_status?->value;
        }
    }

    protected function resetForm(): void
    {
        $this->selectedStudent  = null;
        $this->bulletinId       = null;
        $this->grades           = [];
        $this->teacherComment   = '';
        $this->disciplineStatus = '';
        $this->totalManuel      = '';
        $this->moyenne10        = '';
        $this->moyenneClasse    = '';
    }

    public function isPeriodLocked(int $studentId): bool
    {
        if ($this->selectedPeriod === 'T1' || ! $this->selectedYear) return false;

        if ($this->selectedPeriod === 'T2') {
            $prereq = 'T1';
        } elseif ($this->selectedPeriod === 'T3') {
            $prereq = 'T2';
        } else {
            return false;
        }

        $previous = Bulletin::where('student_id', $studentId)
            ->where('academic_year_id', $this->selectedYear)
            ->where('period', $prereq)
            ->first();

        return ! $previous || ! in_array($previous->status->value, ['approved', 'published']);
    }

    /**
     * Centralised niveau → scale resolver.
     *
     * Handles section codes: CPA, CPB, CE1A, CE1B, CE2A, CE2B, CM1A, CM2A, etc.
     *
     * Summary column order is IDENTICAL for all levels:
     *   [Total sur X]  [Moyenne sur Y]  [Moyenne de la classe sur Y]
     *
     *   CP (A or B) → X=140, Y=10
     *   CE1/CE2/CM1/CM2 (any section) → X=200, Y=20
     */
    public static function resolveSummaryScale(?string $niveauCode): array
    {
        $upper = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $niveauCode)));

        $key = null;
        foreach (['CM2', 'CM1', 'CE2', 'CE1', 'CP'] as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                $key = $prefix;
                break;
            }
        }

        if ($key === 'CP') {
            return [
                'total_max'          => 140,
                'moyenne_max'        => 10,
                'moyenne_classe_max' => 10,
                'group_label'        => 'TOTAUX / MOYENNES',
                'matched_key'        => 'CP',
            ];
        }

        if (in_array($key, ['CE1', 'CE2', 'CM1', 'CM2'], true)) {
            return [
                'total_max'          => 200,
                'moyenne_max'        => 20,
                'moyenne_classe_max' => 20,
                'group_label'        => 'TOTAUX / MOYENNES',
                'matched_key'        => $key,
            ];
        }

        return [
            'total_max'          => 0,
            'moyenne_max'        => 20,
            'moyenne_classe_max' => 20,
            'group_label'        => 'TOTAUX / MOYENNES',
            'matched_key'        => null,
        ];
    }

    /** Magic property used by some blade references. */
    public function getSummaryScaleProperty(): array
    {
        return self::resolveSummaryScale($this->selectedNiveau);
    }

    public function saveGrades(): void
    {
        if (! $this->bulletinId) return;

        $bulletin    = Bulletin::findOrFail($this->bulletinId);
        $isDirection = auth()->user()->hasAnyRole(['admin', 'direction']);

        if (! $bulletin->canTeacherEdit(auth()->id())) {
            $this->error('Lecture seule.', 'Ce bulletin est déjà validé.', icon: 'o-lock-closed', position: 'toast-top toast-end');
            return;
        }

        // Validate only the summary fields (total / moyenne / moyenne_classe)
        // against their niveau-specific maximums to prevent DB decimal overflow.
        // Individual grade scores are NOT validated here.
        $scale     = self::resolveSummaryScale($this->selectedNiveau);
        $totalMax  = $scale['total_max'] ?: 9999;
        $moyMax    = $scale['moyenne_max'];
        $moyClsMax = $scale['moyenne_classe_max'];

        $totalVal     = $this->totalManuel   !== '' ? (float) $this->totalManuel   : null;
        $moyVal       = $this->moyenne10     !== '' ? (float) $this->moyenne10     : null;
        $moyClasseVal = $this->moyenneClasse !== '' ? (float) $this->moyenneClasse : null;

        if ($totalVal !== null && ($totalVal < 0 || $totalVal > $totalMax + 0.01)) {
            $this->error('Total invalide.', "La valeur doit être entre 0 et {$totalMax}.", icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }
        if ($moyVal !== null && ($moyVal < 0 || $moyVal > $moyMax + 0.01)) {
            $this->error('Moyenne invalide.', "La valeur doit être entre 0 et {$moyMax}.", icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }
        if ($moyClasseVal !== null && ($moyClasseVal < 0 || $moyClasseVal > $moyClsMax + 0.01)) {
            $this->error('Moyenne classe invalide.', "La valeur doit être entre 0 et {$moyClsMax}.", icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }

        app(SaveGradeAction::class)->execute($bulletin, $this->grades);

        $bulletin->update([
            'teacher_comment'   => $this->teacherComment   !== '' ? $this->teacherComment   : null,
            'discipline_status' => $this->disciplineStatus !== '' ? $this->disciplineStatus : null,
            'total_manuel'      => $totalVal,
            'moyenne_10'        => $moyVal,
            'moyenne_classe'    => $moyClasseVal,
        ]);

        if ($isDirection) {
            $now = now(); $uid = auth()->id();
            $bulletin->update([
                'status'                => BulletinStatusEnum::APPROVED,
                'submitted_by'          => $uid, 'submitted_at'          => $now,
                'pedagogie_approved_by' => $uid, 'pedagogie_approved_at' => $now,
                'finance_approved_by'   => $uid, 'finance_approved_at'   => $now,
                'direction_approved_by' => $uid, 'direction_approved_at' => $now,
            ]);
            $this->success('Bulletin approuvé !', 'Prêt à être publié.', icon: 'o-check-badge', position: 'toast-top toast-end');
            $this->resetForm();
            return;
        }

        $result = app(SubmitTeacherSubjectsAction::class)->execute($bulletin, auth()->user());

        if (! $result['success']) {
            $this->success('Notes enregistrées.', icon: 'o-check-circle', position: 'toast-top toast-end');
            return;
        }

        if ($result['fully_submitted']) {
            $this->success('Bulletin transmis à la pédagogie !', 'Tous les enseignants ont soumis leurs notes.', icon: 'o-paper-airplane', position: 'toast-top toast-end');
            $this->resetForm();
        } else {
            $this->success('Notes soumises.', $result['message'], icon: 'o-clock', position: 'toast-top toast-end');
            $this->loadOrCreateBulletin($this->selectedStudent);
        }
    }

    public function withdrawSubmission(): void
    {
        if (! $this->bulletinId) return;
        $bulletin = Bulletin::findOrFail($this->bulletinId);

        if (! in_array($bulletin->status->value, ['draft', 'submitted'])) {
            $this->error('Retrait impossible.', 'Ce bulletin est déjà en cours de validation par la pédagogie.', icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }

        $bulletin->teacherSubmissions()->where('teacher_id', auth()->id())->delete();
        if ($bulletin->status === BulletinStatusEnum::SUBMITTED) {
            $bulletin->update(['status' => BulletinStatusEnum::DRAFT, 'submitted_by' => null, 'submitted_at' => null]);
        }

        $this->success('Soumission retirée.', 'Vous pouvez maintenant corriger vos notes.', icon: 'o-arrow-uturn-left', position: 'toast-top toast-end');
        $this->loadOrCreateBulletin($this->selectedStudent);
    }

    public function bulkSubmitMySubjects(): void
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        $students  = Student::where('classroom_id', $this->selectedClassroom)->get();
        $action    = app(SubmitTeacherSubjectsAction::class);
        $submitted = 0;

        foreach ($students as $student) {
            if ($this->isPeriodLocked($student->id)) continue;

            $bulletin = Bulletin::firstOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $this->selectedYear, 'period' => $this->selectedPeriod],
                ['classroom_id' => $student->classroom_id, 'status' => BulletinStatusEnum::DRAFT]
            );

            if ($bulletin->canTeacherEdit(auth()->id())) {
                $result = $action->execute($bulletin, auth()->user());
                if ($result['success']) $submitted++;
            }
        }

        $this->resetForm();
        $submitted > 0
            ? $this->success("{$submitted} bulletin(s) soumis !", icon: 'o-paper-airplane', position: 'toast-top toast-end')
            : $this->warning('Aucun bulletin à soumettre.', 'Tous déjà soumis ou verrouillés.', icon: 'o-information-circle', position: 'toast-top toast-end');
    }

    public function resetTrimester(): void
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        $bulletins = Bulletin::where('classroom_id', $this->selectedClassroom)
            ->where('academic_year_id', $this->selectedYear)
            ->where('period', $this->selectedPeriod)
            ->where('status', BulletinStatusEnum::DRAFT)
            ->get();

        if ($bulletins->isEmpty()) {
            $this->warning('Rien à réinitialiser.',
                'Aucun bulletin en statut « Brouillon » pour ce trimestre.',
                icon: 'o-information-circle', position: 'toast-top toast-end');
            return;
        }

        $userId         = auth()->id();
        $isDirection    = auth()->user()->hasAnyRole(['admin', 'direction']);
        $bulletinsCount = 0;
        $gradesDeleted  = 0;

        \DB::transaction(function () use ($bulletins, $userId, $isDirection, &$bulletinsCount, &$gradesDeleted) {
            foreach ($bulletins as $bulletin) {
                if (! $isDirection && ! $bulletin->canTeacherEdit($userId)) continue;

                $gradesDeleted += $bulletin->grades()->count();
                $bulletin->grades()->delete();

                if (method_exists($bulletin, 'teacherSubmissions')) {
                    $bulletin->teacherSubmissions()->delete();
                }

                $bulletin->update([
                    'teacher_comment'       => null,
                    'discipline_status'     => null,
                    'total_manuel'          => null,
                    'moyenne_10'            => null,
                    'moyenne_classe'        => null,
                    'submitted_by'          => null,
                    'submitted_at'          => null,
                    'pedagogie_approved_by' => null,
                    'pedagogie_approved_at' => null,
                    'finance_approved_by'   => null,
                    'finance_approved_at'   => null,
                    'direction_approved_by' => null,
                    'direction_approved_at' => null,
                    'status'                => BulletinStatusEnum::DRAFT,
                ]);

                $bulletinsCount++;
            }
        });

        $this->resetForm();

        if ($bulletinsCount === 0) {
            $this->warning('Aucune réinitialisation effectuée.',
                'Vous n\'avez pas les droits ou les bulletins ne sont plus en brouillon.',
                icon: 'o-shield-exclamation', position: 'toast-top toast-end');
            return;
        }

        $this->success(
            "{$bulletinsCount} bulletin(s) réinitialisé(s).",
            "{$gradesDeleted} note(s) supprimée(s).",
            icon: 'o-arrow-path',
            position: 'toast-top toast-end'
        );
    }

    protected function shouldFilterByTeacher(): bool
    {
        $user = auth()->user();
        return $user->hasRole('teacher') && $user->subjects()->exists();
    }

    protected function getTeacherIdForExportImport(): ?int
    {
        return $this->shouldFilterByTeacher() ? auth()->id() : null;
    }

    #[Renderless]
    public function exportGrades(): mixed
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear || ! $this->selectedNiveau) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return null;
        }

        $isDirection = auth()->user()->hasAnyRole(['admin', 'direction']);

        $export = $isDirection
            ? new GradeSheetDirectorExport($this->selectedClassroom, $this->selectedPeriod, $this->selectedYear, $this->selectedNiveau)
            : new GradeSheetExport($this->selectedClassroom, $this->selectedPeriod, $this->selectedYear, $this->selectedNiveau, $this->getTeacherIdForExportImport());

        return Excel::download($export, $export->getFilename());
    }

    public function importGrades(): void
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear || ! $this->selectedNiveau) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }
        if (! $this->importFile) {
            $this->error('Fichier manquant.', 'Veuillez sélectionner un fichier Excel (.xlsx).', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        if ($this->shouldFilterByTeacher()) {
            $totalStudents = Student::where('classroom_id', $this->selectedClassroom)->count();
            if ($totalStudents > 0) {
                $submittedCount = Bulletin::where('classroom_id', $this->selectedClassroom)
                    ->where('academic_year_id', $this->selectedYear)
                    ->where('period', $this->selectedPeriod)
                    ->whereHas('teacherSubmissions', fn($q) => $q->where('teacher_id', auth()->id())->where('status', 'submitted'))
                    ->count();

                if ($submittedCount === $totalStudents) {
                    $anyBeyondDraft = Bulletin::where('classroom_id', $this->selectedClassroom)
                        ->where('academic_year_id', $this->selectedYear)
                        ->where('period', $this->selectedPeriod)
                        ->where('status', '!=', BulletinStatusEnum::DRAFT)
                        ->exists();

                    if ($anyBeyondDraft) {
                        $this->error('Import verrouillé.', 'Vous avez déjà soumis vos notes et les bulletins sont en cours de validation.', icon: 'o-lock-closed', position: 'toast-top toast-end');
                        return;
                    }
                }
            }
        }

        $isDirection = auth()->user()->hasAnyRole(['admin', 'direction']);
        $teacherId   = $this->getTeacherIdForExportImport();
        $importer    = new GradeSheetImport($this->selectedClassroom, $this->selectedPeriod, $this->selectedYear, $this->selectedNiveau, $teacherId);

        try {
            $stored   = $this->importFile->store('grade-imports', 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($stored);

            Excel::import($importer, $fullPath);

            \Illuminate\Support\Facades\Storage::disk('local')->delete($stored);
            $this->importFile = null;

            if ($isDirection && $importer->gradesTotal > 0) {
                $now = now(); $uid = auth()->id();
                Student::where('classroom_id', $this->selectedClassroom)
                    ->get()
                    ->each(function ($student) use ($now, $uid) {
                        $bulletin = Bulletin::where([
                            'student_id'       => $student->id,
                            'academic_year_id' => $this->selectedYear,
                            'period'           => $this->selectedPeriod,
                        ])->first();
                        if ($bulletin && $bulletin->grades()->count() > 0) {
                            $bulletin->update([
                                'status'                => BulletinStatusEnum::APPROVED,
                                'submitted_by'          => $uid, 'submitted_at'          => $now,
                                'pedagogie_approved_by' => $uid, 'pedagogie_approved_at' => $now,
                                'finance_approved_by'   => $uid, 'finance_approved_at'   => $now,
                                'direction_approved_by' => $uid, 'direction_approved_at' => $now,
                            ]);
                        }
                    });
            }

            $message = "{$importer->imported} élève(s) traité(s), {$importer->gradesTotal} note(s) enregistrée(s)";
            if ($importer->skipped > 0) $message .= ", {$importer->skipped} ignoré(s)";
            $message .= '.';
            if ($teacherId && $importer->imported > 0) $message .= ' Seules vos matières ont été importées.';
            if ($isDirection && $importer->gradesTotal > 0) $message .= ' Bulletins approuvés automatiquement.';

            if (! empty($importer->errors)) {
                $this->warning($message, implode(' | ', array_slice($importer->errors, 0, 3)), icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } elseif ($importer->imported === 0) {
                $hint = $importer->skipped > 0
                    ? 'Vérifiez que les matricules correspondent au fichier exporté.'
                    : 'Aucune ligne de données trouvée. Vérifiez le format du fichier.';
                $this->warning("0 élève importé, {$importer->skipped} ignoré(s).", $hint, icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } elseif ($importer->gradesTotal === 0) {
                $this->warning($message, 'Les cellules de notes étaient vides — remplissez le fichier Excel avant de ré-importer.', icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } else {
                $this->success($message, icon: 'o-arrow-up-tray', position: 'toast-top toast-end');
            }

            if ($this->selectedStudent) $this->loadOrCreateBulletin($this->selectedStudent);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = collect($e->failures())->map(fn($f) => "Ligne {$f->row()}: " . implode(', ', $f->errors()))->toArray();
            $this->error('Erreur de validation', implode(' | ', array_slice($errors, 0, 3)), icon: 'o-x-circle', position: 'toast-top toast-end');
        } catch (\Throwable $e) {
            $this->error('Erreur import', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
            \Log::error('Grade import error', [
                'message' => $e->getMessage(), 'classroom' => $this->selectedClassroom,
                'period'  => $this->selectedPeriod, 'teacher_id' => $teacherId,
            ]);
        }
    }

    public function with(): array
    {
        $isTeacher = auth()->user()->hasRole('teacher');
        $userId    = auth()->id();

        $niveauxQuery = Niveau::query();
        if ($isTeacher) {
            $niveauxQuery->whereHas('classrooms', fn($q) =>
                $q->where('teacher_id', $userId)->orWhereHas('teachers', fn($q2) => $q2->where('users.id', $userId))
            );
        }
        $niveaux = $niveauxQuery->get()->map(fn($n) => ['id' => $n->code, 'name' => $n->label]);

        $years = AcademicYear::all()->map(fn($y) => ['id' => $y->id, 'name' => $y->label]);

        $classrooms = collect();
        if ($this->selectedNiveau) {
            $classroomQuery = Classroom::whereHas('niveau', fn($q) => $q->where('code', $this->selectedNiveau))
                ->where('academic_year_id', $this->selectedYear);

            if ($isTeacher) {
                $classroomQuery->where(fn($q) =>
                    $q->where('teacher_id', $userId)->orWhereHas('teachers', fn($q2) => $q2->where('users.id', $userId))
                );
            }

            $classrooms = $classroomQuery->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->label . ' — ' . $c->section]);
        }

        $subjects     = collect();
        $totalMaxSum  = 0;
        if ($this->selectedStudent && $this->selectedNiveau && $this->selectedClassroom) {
            $classroom   = Classroom::find($this->selectedClassroom);
            $sectionCode = $classroom?->code;
            $levelCode   = preg_replace('/[AB]$/', '', (string) $sectionCode) ?: $sectionCode;

            $q = Subject::whereHas('niveau', fn($q) => $q->where('code', $this->selectedNiveau))
                ->where(function ($q) use ($sectionCode, $levelCode) {
                    $q->where('section_code', $sectionCode)
                      ->orWhere(function ($q2) use ($levelCode) {
                          $q2->whereNull('section_code')->where('classroom_code', $levelCode);
                      })
                      ->orWhere(function ($q3) {
                          $q3->whereNull('section_code')->whereNull('classroom_code');
                      });
                })
                ->with(['competences' => function ($q) use ($sectionCode) {
                    $q->where(fn($q2) => $q2->whereNull('section_code')->orWhere('section_code', $sectionCode))
                      ->orderBy('order');
                }]);

            if ($this->shouldFilterByTeacher()) {
                $q->whereHas('teachers', fn($q) => $q->where('users.id', $userId));
            }

            $subjects = $q->orderBy('order')->get();

            foreach ($subjects as $subject) {
                if ($subject->scale_type === 'competence') continue;
                $hasIndividualMax = $subject->competences->whereNotNull('max_score')->isNotEmpty();
                if ($hasIndividualMax) {
                    foreach ($subject->competences as $c) {
                        $totalMaxSum += (int) ($c->max_score ?? 0);
                    }
                } else {
                    $totalMaxSum += (int) ($subject->max_score ?? 0);
                }
            }
        }

        $students = collect();
        if ($this->selectedClassroom && $this->selectedYear) {
            $yearId = $this->selectedYear;
            $period = $this->selectedPeriod;

            $students = Student::where('classroom_id', $this->selectedClassroom)
                ->orderBy('full_name')
                ->get()
                ->map(function ($s) use ($yearId, $period, $userId) {
                    $bulletins = Bulletin::where('student_id', $s->id)
                        ->where('academic_year_id', $yearId)
                        ->whereIn('period', ['T1', 'T2', 'T3'])
                        ->with('teacherSubmissions')
                        ->get()
                        ->keyBy('period');

                    $s->t1 = $bulletins['T1'] ?? null;
                    $s->t2 = $bulletins['T2'] ?? null;
                    $s->t3 = $bulletins['T3'] ?? null;

                    $current             = $bulletins[$period] ?? null;
                    $s->current_bulletin = $current;
                    $s->teacher_submitted = (! auth()->user()->hasAnyRole(['admin', 'direction']))
                        && ($current?->isTeacherSubmitted($userId) ?? false);

                    return $s;
                });
        }

        $isDirection      = auth()->user()->hasAnyRole(['admin', 'direction']);
        $bulletin         = null;
        $canEdit          = false;
        $teacherSubmitted = false;
        $periodLocked     = false;
        $lockReason       = null;
        $progress         = ['total' => 0, 'submitted' => 0, 'teachers' => []];

        if ($this->selectedStudent && $this->selectedPeriod && $this->selectedYear) {
            $periodLocked = $this->isPeriodLocked($this->selectedStudent);

            if ($periodLocked) {
                if ($this->selectedPeriod === 'T2') {
                    $prev = '1er Trimestre';
                } elseif ($this->selectedPeriod === 'T3') {
                    $prev = '2ème Trimestre';
                } else {
                    $prev = 'trimestre précédent';
                }
                $lockReason = "Le {$prev} doit être approuvé avant de saisir ce trimestre.";
            } elseif ($this->bulletinId) {
                $bulletin = Bulletin::with(['teacherSubmissions.teacher', 'approvals'])->find($this->bulletinId);
                if ($bulletin) {
                    $canEdit          = $bulletin->canTeacherEdit(auth()->id());
                    $teacherSubmitted = $isDirection ? false : $bulletin->isTeacherSubmitted(auth()->id());
                    $progress         = $bulletin->teacherSubmissionProgress();
                }
            }
        }

        $resetCount = 0;
        if ($this->selectedClassroom && $this->selectedPeriod && $this->selectedYear) {
            $resetCount = Bulletin::where('classroom_id', $this->selectedClassroom)
                ->where('academic_year_id', $this->selectedYear)
                ->where('period', $this->selectedPeriod)
                ->where('status', BulletinStatusEnum::DRAFT)
                ->count();
        }

        $summaryScale = self::resolveSummaryScale($this->selectedNiveau);

        \Log::debug('Saisie summary scale', [
            'selectedNiveau' => $this->selectedNiveau,
            'matched_key'    => $summaryScale['matched_key'] ?? null,
            'total_max'      => $summaryScale['total_max'],
        ]);

        return compact(
            'niveaux', 'classrooms', 'years', 'students', 'subjects',
            'bulletin', 'canEdit', 'teacherSubmitted',
            'periodLocked', 'lockReason', 'progress', 'totalMaxSum',
            'resetCount', 'summaryScale'
        ) + [
            'periodOptions'     => PeriodEnum::options(),
            'competenceOptions' => CompetenceStatusEnum::options(),
            'isDirection'       => $isDirection,
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-orange-500 to-amber-500 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">✏️</div>
                <div>
                    <h1 class="text-xl font-bold">Saisie des Notes</h1>
                    <p class="text-white/70 text-sm">Remplissez vos matières par trimestre</p>
                </div>
            </div>
            @if($selectedClassroom)
            @php
                $publishedCount = \App\Models\Bulletin::where('classroom_id', $selectedClassroom)
                    ->where('academic_year_id', $selectedYear)
                    ->where('period', $selectedPeriod)
                    ->where('status', 'published')->count();
                $totalStudents = \App\Models\Student::where('classroom_id', $selectedClassroom)->count();
            @endphp
            <div class="flex items-center gap-3 self-start sm:self-auto">
                <div class="bg-white/20 rounded-xl px-4 py-2 text-center">
                    <div class="text-2xl font-black">{{ $publishedCount }}<span class="text-sm font-normal opacity-70">/{{ $totalStudents }}</span></div>
                    <div class="text-xs text-white/70">Publiés</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Selectors --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body py-4 px-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-choices label="Année scolaire" wire:model.live="selectedYear"   :options="$years"   single clearable placeholder="Sélectionner…" icon="o-calendar" />
                <x-select  label="Niveau"          wire:model.live="selectedNiveau" :options="$niveaux" placeholder="Sélectionner…" icon="o-academic-cap" class="select-bordered bg-base-100" />
                <x-select  label="Classe"
                    wire:key="classroom-select-{{ $selectedNiveau ?? 'none' }}"
                    wire:model.live="selectedClassroom"
                    :options="$classrooms->toArray()"
                    placeholder="{{ $selectedNiveau ? 'Sélectionner…' : '— Choisir un niveau —' }}"
                    :disabled="!$selectedNiveau"
                    icon="o-building-library"
                    class="select-bordered bg-base-100"
                />
            </div>
        </div>
    </div>

    @if($selectedClassroom)

    {{-- Period tabs --}}
    <div class="grid grid-cols-3 gap-2">
        @foreach(\App\Enums\PeriodEnum::cases() as $p)
        @if($p !== \App\Enums\PeriodEnum::ANNUEL)
        @php
            $pPublished = \App\Models\Bulletin::where('classroom_id', $selectedClassroom)->where('academic_year_id', $selectedYear)->where('period', $p->value)->where('status', 'published')->count();
            $pTotal     = \App\Models\Student::where('classroom_id', $selectedClassroom)->count();
            $pActive    = $selectedPeriod === $p->value;
            $pPercent   = $pTotal > 0 ? round($pPublished / $pTotal * 100) : 0;
        @endphp
        <button
            wire:click="$set('selectedPeriod', '{{ $p->value }}')"
            class="card shadow-sm cursor-pointer transition-all {{ $pActive ? 'border-2 border-primary bg-primary/5' : 'bg-base-100 hover:bg-base-200/60 border border-base-200' }}"
        >
            <div class="card-body py-3 px-4 items-center text-center gap-1">
                <span class="font-bold text-sm {{ $pActive ? 'text-primary' : '' }}">{{ $p->label() }}</span>
                @if($pTotal > 0)
                <div class="w-full bg-base-200 rounded-full h-1.5 mt-1">
                    <div class="h-1.5 rounded-full {{ $pPercent === 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $pPercent }}%"></div>
                </div>
                <span class="text-xs {{ $pActive ? 'text-primary' : 'text-base-content/50' }}">{{ $pPublished }}/{{ $pTotal }} publiés</span>
                @else
                <span class="text-xs text-base-content/40">En attente</span>
                @endif
            </div>
        </button>
        @endif
        @endforeach
    </div>

    {{-- Export / Import / Reset toolbar --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body py-3 px-4">
            <div class="flex flex-wrap items-end gap-3">
                <p class="font-semibold text-xs text-base-content/50 self-center w-12 shrink-0">EXCEL</p>

                <x-button label="⬇ Exporter" wire:click="exportGrades" class="btn-outline btn-sm" spinner="exportGrades" icon="o-arrow-down-tray"
                    tooltip="{{ $isDirection ? 'Export multi-feuilles (un onglet par enseignant + toutes matières)' : 'Exporter la feuille de notes' }}" />

                <div class="flex items-end gap-2">
                    <div>
                        <label class="label-text text-xs mb-1 block">Fichier .xlsx</label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="file-input file-input-sm file-input-bordered w-48" />
                    </div>
                    <x-button label="⬆ Importer" wire:click="importGrades" class="btn-primary btn-sm" spinner="importGrades" icon="o-arrow-up-tray" />
                </div>

                @if($resetCount > 0)
                <div class="border-l border-base-200 pl-3">
                    <x-button
                        label="🔄 Réinitialiser ({{ $resetCount }})"
                        wire:click="resetTrimester"
                        class="btn-error btn-outline btn-sm"
                        spinner="resetTrimester"
                        icon="o-arrow-path"
                        tooltip="Supprimer toutes les notes des bulletins en BROUILLON pour ce trimestre"
                        wire:confirm="Réinitialiser ce trimestre ? Toutes les notes des bulletins en Brouillon seront supprimées. Cette action est irréversible." />
                </div>
                @endif

                @unless($isDirection)
                <div class="border-l border-base-200 pl-3 ml-auto self-end">
                    <x-button label="✈ Tout soumettre" wire:click="bulkSubmitMySubjects" class="btn-success btn-sm" spinner="bulkSubmitMySubjects"
                        icon="o-paper-airplane"
                        tooltip="Soumettre vos notes pour tous les élèves de la classe"
                        wire:confirm="Soumettre vos notes pour tous les élèves de ce trimestre ?" />
                </div>
                @endunless
            </div>
        </div>
    </div>

    {{-- Student list --}}
    @if($students->isNotEmpty())
    <div class="space-y-2">
        @foreach($students as $student)
        @php $isOpen = $selectedStudent === $student->id; @endphp

        <div class="card bg-base-100 shadow transition-all {{ $isOpen ? 'border-2 border-primary shadow-md' : 'border border-base-200' }}">

            <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-base-200/40 transition-colors rounded-2xl" wire:click="selectStudent({{ $student->id }})">
                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0 {{ $student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                    {{ strtoupper(substr($student->full_name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate">{{ $student->full_name }}</p>
                    <p class="text-xs text-base-content/40 font-mono">{{ $student->matricule }}</p>
                </div>
                <div class="flex items-center gap-1.5 flex-wrap justify-end">
                    @foreach(['T1' => $student->t1, 'T2' => $student->t2, 'T3' => $student->t3] as $lbl => $b)
                    <span class="badge {{ $b ? $b->status->color() : 'badge-ghost' }} badge-xs">{{ $lbl }}{{ $b ? ' · ' . $b->status->label() : '' }}</span>
                    @endforeach

                    @if($student->teacher_submitted && !in_array($student->current_bulletin?->status?->value, ['submitted','pedagogie_approved','finance_approved','approved','published']))
                        <span class="badge badge-success badge-sm">✓ Soumis</span>
                    @elseif($student->current_bulletin?->status === \App\Enums\BulletinStatusEnum::REJECTED)
                        <span class="badge badge-error badge-sm">↩ Rejeté</span>
                    @endif

                    <svg class="w-4 h-4 text-base-content/40 shrink-0 transition-transform {{ $isOpen ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>

            @if($isOpen)
            <div class="border-t border-base-200 p-4 space-y-4">

                @if($periodLocked)
                    <div class="flex items-center gap-3 p-3 bg-warning/10 border border-warning/20 rounded-xl">
                        <span class="text-2xl">🔒</span>
                        <div>
                            <p class="font-semibold text-sm">Trimestre verrouillé</p>
                            <p class="text-xs text-base-content/60">{{ $lockReason }}</p>
                        </div>
                    </div>

                @else
                    @php $effectiveCanEdit = ($bulletin && in_array($bulletin->status->value, ['draft','rejected'])) ? $canEdit : false; @endphp
                    @include('livewire.bulletin._grade-form', ['canEdit' => $effectiveCanEdit])

                    {{-- ── TOTAUX / MOYENNES + DIM. PERS. + OBSERVATIONS ── --}}
                    @php
                        $matchedKey = $summaryScale['matched_key'] ?? null;
                        $totalMax   = (int) ($summaryScale['total_max'] ?? 0);
                        if ($totalMax === 0 && $matchedKey === null) {
                            $totalMax = $totalMaxSum;
                        }
                        $moyMax    = $summaryScale['moyenne_max'];
                        $moyClsMax = $summaryScale['moyenne_classe_max'];
                    @endphp

                    <div class="rounded-xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-50/60 to-purple-50/60 p-4 space-y-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xl">📊</span>
                            <h3 class="font-bold text-sm text-indigo-900">{{ $summaryScale['group_label'] }} &nbsp;·&nbsp; DIM. PERS. &nbsp;·&nbsp; OBSERVATIONS</h3>
                            <span class="badge badge-ghost badge-xs">Niveau {{ $selectedNiveau }}</span>
                        </div>

                        {{--
                            Column order is IDENTICAL for all levels:
                            [Total sur X]  [Moyenne sur Y]  [Moyenne de la classe sur Y]
                        --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <x-input
                                label="Total sur {{ $totalMax ?: '?' }}"
                                wire:model="totalManuel"
                                type="number" step="0.5" min="0" max="{{ $totalMax ?: 9999 }}"
                                placeholder="0"
                                icon="o-calculator"
                                :disabled="!$effectiveCanEdit" />

                            <x-input
                                label="Moyenne sur {{ $moyMax }}"
                                wire:model="moyenne10"
                                type="number" step="0.1" min="0" max="{{ $moyMax }}"
                                placeholder="0.0"
                                icon="o-chart-bar"
                                :disabled="!$effectiveCanEdit" />

                            <x-input
                                label="Moyenne de la classe sur {{ $moyClsMax }}"
                                wire:model="moyenneClasse"
                                type="number" step="0.1" min="0" max="{{ $moyClsMax }}"
                                placeholder="0.0"
                                icon="o-users"
                                :disabled="!$effectiveCanEdit" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <x-input
                                label="DISCIPLINE (Dim. Pers.)"
                                wire:model="disciplineStatus"
                                placeholder="Ex: A, B, B+…"
                                icon="o-shield-check"
                                :disabled="!$effectiveCanEdit" />

                            <x-textarea
                                label="OBSERVATIONS"
                                wire:model="teacherComment"
                                rows="2"
                                placeholder="Commentaire de l'enseignant…"
                                :disabled="!$effectiveCanEdit" />
                        </div>

                        @if($effectiveCanEdit)
                        <div class="flex justify-end gap-2 pt-2 border-t border-indigo-100">
                            <x-button label="💾 Enregistrer & Soumettre" wire:click="saveGrades" class="btn-primary btn-sm" spinner="saveGrades" icon="o-check" />
                        </div>
                        @endif
                    </div>

                    {{-- Status badges --}}
                    @if($bulletin && $bulletin->status === \App\Enums\BulletinStatusEnum::REJECTED)
                        @php $lastRejection = $bulletin->approvals->where('action','rejected')->last(); @endphp
                        <div class="flex items-center gap-3 p-3 bg-error/10 border border-error/20 rounded-xl">
                            <span class="text-2xl">↩</span>
                            <div>
                                <p class="font-semibold text-sm text-error">Bulletin rejeté — veuillez corriger</p>
                                @if($lastRejection?->comment)
                                <p class="text-xs text-base-content/60 italic">{{ $lastRejection->comment }}</p>
                                @endif
                            </div>
                        </div>

                    @elseif($teacherSubmitted)
                        @if($progress['total'] > 0)
                        <div class="p-3 bg-base-200 rounded-xl space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold">{{ $progress['submitted'] }}/{{ $progress['total'] }} enseignants ont soumis</p>
                                @if($progress['submitted'] === $progress['total'])
                                    <span class="badge badge-success badge-sm">✓ Transmis à la pédagogie</span>
                                @endif
                            </div>
                            <div class="w-full bg-base-300 rounded-full h-1.5">
                                <div class="bg-primary h-1.5 rounded-full transition-all" style="width: {{ $progress['total'] > 0 ? round($progress['submitted']/$progress['total']*100) : 0 }}%"></div>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($progress['teachers'] as $t)
                                <span class="badge {{ $t['submitted'] ? 'badge-success' : 'badge-warning' }} badge-xs gap-1">{{ $t['submitted'] ? '✓' : '⏳' }} {{ $t['name'] }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <div class="flex items-center justify-between gap-3 bg-success/10 px-3 py-2 rounded-lg">
                            <div class="flex items-center gap-2 text-xs text-success">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Vos notes sont soumises — lecture seule.
                            </div>
                            @if($bulletin && in_array($bulletin->status->value, ['draft', 'submitted']))
                            <x-button label="↩ Retirer ma soumission" wire:click="withdrawSubmission" class="btn-warning btn-xs" spinner="withdrawSubmission"
                                tooltip="Retirer votre soumission pour corriger vos notes"
                                wire:confirm="Retirer votre soumission ?" />
                            @endif
                        </div>

                    @elseif($bulletin && ! in_array($bulletin->status->value, ['draft', 'rejected']))
                        <div class="flex items-center justify-between p-3 bg-info/10 border border-info/20 rounded-xl">
                            <span class="text-sm">Statut : <strong class="badge {{ $bulletin->status->color() }} badge-sm ml-1">{{ $bulletin->status->label() }}</strong></span>
                            <span class="text-xs text-base-content/50">Lecture seule</span>
                        </div>

                    @else
                        @if($progress['submitted'] > 0)
                        <div class="flex items-center gap-2 p-2 bg-base-200 rounded-lg text-xs">
                            <span class="text-base-content/60">Autres enseignants :</span>
                            @foreach($progress['teachers'] as $t)
                            <span class="badge {{ $t['submitted'] ? 'badge-success' : 'badge-ghost' }} badge-xs">{{ $t['submitted'] ? '✓' : '⏳' }} {{ explode(' ', $t['name'])[0] }}</span>
                            @endforeach
                        </div>
                        @endif
                    @endif

                @endif

            </div>
            @endif
        </div>
        @endforeach
    </div>
    @else
        <x-alert title="Aucun élève dans cette classe." class="alert-info" icon="o-users" />
    @endif

    @elseif($selectedNiveau)
        <x-alert title="Sélectionnez une classe pour afficher les élèves." class="alert-info" icon="o-building-library" />
    @else
        <x-alert title="Sélectionnez un niveau puis une classe." class="alert-info" icon="o-academic-cap" />
    @endif
</div>
