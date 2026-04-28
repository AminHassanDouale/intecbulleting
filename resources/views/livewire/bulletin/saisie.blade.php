<?php

use App\Actions\Bulletin\CreateBulletinAction;
use App\Actions\Bulletin\SubmitTeacherSubjectsAction;
use App\Actions\Grade\SaveGradeAction;
use App\Enums\BulletinStatusEnum;
use App\Enums\CompetenceStatusEnum;
use App\Enums\PeriodEnum;
use App\Exports\GradeSheetExport;
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

    // ── Selectors ─────────────────────────────────────────────────────────────
    public ?int    $selectedYear      = null;
    public ?string $selectedNiveau    = null;
    public ?int    $selectedClassroom = null;
    public string  $selectedPeriod    = 'T1';

    // ── Grade form ────────────────────────────────────────────────────────────
    public ?int    $selectedStudent  = null;
    public ?int    $bulletinId       = null;
    public array   $grades           = [];
    public string  $teacherComment   = '';
    public $importFile    = null;
    public $importFileCsv = null;

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->selectedYear   = AcademicYear::current()?->id;
        $this->selectedPeriod = PeriodEnum::current()->value;
    }

    public function updatedSelectedNiveau(): void    { $this->selectedClassroom = null; $this->resetForm(); }
    public function updatedSelectedClassroom(): void { $this->resetForm(); }
    public function updatedSelectedPeriod(): void    { $this->resetForm(); }

    // ── Student selection ─────────────────────────────────────────────────────

    public function selectStudent(int $studentId): void
    {
        if ($this->selectedStudent === $studentId) {
            $this->resetForm();
            return;
        }

        $this->selectedStudent = $studentId;
        $this->grades          = [];
        $this->teacherComment  = '';
        $this->bulletinId      = null;

        if (! $this->isPeriodLocked($studentId)) {
            $this->loadOrCreateBulletin($studentId);
        }
    }

    protected function loadOrCreateBulletin(int $studentId): void
    {
        if (! $this->selectedPeriod || ! $this->selectedYear) return;

        $student  = Student::findOrFail($studentId);
        $bulletin = app(CreateBulletinAction::class)->execute($student, $this->selectedPeriod, $this->selectedYear);

        $this->bulletinId     = $bulletin->id;
        $this->teacherComment = $bulletin->teacher_comment ?? '';
        $this->grades         = [];

        foreach ($bulletin->grades as $grade) {
            $this->grades[$grade->competence_id] = $grade->score ?? $grade->competence_status?->value;
        }
    }

    protected function resetForm(): void
    {
        $this->selectedStudent = null;
        $this->bulletinId      = null;
        $this->grades          = [];
        $this->teacherComment  = '';
    }

    // ── Period lock check ─────────────────────────────────────────────────────

    public function isPeriodLocked(int $studentId): bool
    {
        if ($this->selectedPeriod === 'T1' || ! $this->selectedYear) return false;

        $prereq = match($this->selectedPeriod) {
            'T2' => 'T1',
            'T3' => 'T2',
            default => null,
        };

        if (! $prereq) return false;

        $previous = Bulletin::where('student_id', $studentId)
            ->where('academic_year_id', $this->selectedYear)
            ->where('period', $prereq)
            ->first();

        return ! $previous || ! in_array($previous->status->value, ['approved', 'published']);
    }

    // ── Save grades ───────────────────────────────────────────────────────────

    public function saveGrades(): void
    {
        if (! $this->bulletinId) return;

        $bulletin    = Bulletin::findOrFail($this->bulletinId);
        $isDirection = auth()->user()->hasAnyRole(['admin', 'direction']);

        if (! $bulletin->canTeacherEdit(auth()->id())) {
            $this->error('Lecture seule.', 'Ce bulletin est déjà validé.', icon: 'o-lock-closed', position: 'toast-top toast-end');
            return;
        }

        app(SaveGradeAction::class)->execute($bulletin, $this->grades);
        if ($this->teacherComment) {
            $bulletin->update(['teacher_comment' => $this->teacherComment]);
        }

        // Direction/admin: just save — do NOT auto-submit to workflow.
        // Use "Tout soumettre" button when ready to forward to pédagogie.
        if ($isDirection) {
            $this->success('Notes enregistrées.', icon: 'o-check-circle', position: 'toast-top toast-end');
            $this->loadOrCreateBulletin($this->selectedStudent);
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

    // ── Withdraw submission ───────────────────────────────────────────────────

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

    // ── Bulk submit ───────────────────────────────────────────────────────────

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

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function shouldFilterByTeacher(): bool
    {
        $user = auth()->user();
        return $user->hasRole('teacher') && $user->subjects()->exists();
    }

    protected function getTeacherIdForExportImport(): ?int
    {
        return $this->shouldFilterByTeacher() ? auth()->id() : null;
    }

    // ── Excel export ──────────────────────────────────────────────────────────

    #[Renderless]
    public function exportGrades(): mixed
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear || ! $this->selectedNiveau) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return null;
        }

        // Direction/admin get teacherId=null → all subjects on one sheet.
        // Teachers get their own teacherId → only their subjects.
        $export = new GradeSheetExport(
            $this->selectedClassroom,
            $this->selectedPeriod,
            $this->selectedYear,
            $this->selectedNiveau,
            $this->getTeacherIdForExportImport()
        );

        return Excel::download($export, $export->getFilename());
    }

    // ── CSV export URL builder (used in template <a> tag) ────────────────────
    // We do NOT use Livewire for the actual download — we build a plain URL
    // to GradeSheetCSVController::export() so the browser downloads a real
    // .csv file without Livewire/Maatwebsite interfering.

    public function getCsvExportUrl(): string
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear || ! $this->selectedNiveau) {
            return '#';
        }

        $params = [
            'classroom_id'     => $this->selectedClassroom,
            'period'           => $this->selectedPeriod,
            'academic_year_id' => $this->selectedYear,
            'niveau_code'      => $this->selectedNiveau,
        ];

        return route('grades.export-csv', $params);
    }

    // ── CSV import ────────────────────────────────────────────────────────────

    public function importGradesCSV(): void
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear || ! $this->selectedNiveau) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        if (! $this->importFileCsv) {
            $this->error('Fichier manquant.', 'Veuillez sélectionner un fichier CSV.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        // Lock check
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
                        $this->error('Import verrouillé.', 'Vous avez déjà soumis vos notes pour cette période.', icon: 'o-lock-closed', position: 'toast-top toast-end');
                        return;
                    }
                }
            }
        }

        $importer = new \App\Imports\GradeSheetImportCSV(
            $this->selectedClassroom,
            $this->selectedPeriod,
            $this->selectedYear,
            $this->selectedNiveau,
            $this->getTeacherIdForExportImport()
        );

        try {
            $stored   = $this->importFileCsv->store('grade-imports', 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($stored);

            // Uses SplFileObject internally — NOT Maatwebsite\Excel
            $importer->import($fullPath);

            \Illuminate\Support\Facades\Storage::disk('local')->delete($stored);
            $this->importFileCsv = null;

            $stats   = $importer->getStats();
            $message = "Import CSV réussi ! {$stats['imported']} élève(s) importé(s), {$stats['skipped']} ignoré(s).";

            if (! empty($stats['errors'])) {
                $this->warning($message, implode(' | ', array_slice($stats['errors'], 0, 3)), icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } else {
                $this->success($message, icon: 'o-arrow-up-tray', position: 'toast-top toast-end');
            }

            if ($this->selectedStudent) {
                $this->loadOrCreateBulletin($this->selectedStudent);
            }

        } catch (\Throwable $e) {
            $this->error('Erreur import CSV', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
            \Illuminate\Support\Facades\Log::error('CSV Grade import error', ['message' => $e->getMessage(), 'classroom' => $this->selectedClassroom, 'period' => $this->selectedPeriod]);
        }
    }

    // ── Excel import (unchanged) ──────────────────────────────────────────────

    public function importGrades(): void
    {
        if (! $this->selectedClassroom || ! $this->selectedPeriod || ! $this->selectedYear || ! $this->selectedNiveau) {
            $this->error('Sélection incomplète.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        if (! $this->importFile) {
            $this->error('Fichier manquant.', 'Veuillez sélectionner un fichier Excel.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
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
                        $this->error('Import verrouillé.', 'Vous avez déjà soumis vos notes pour cette période et les bulletins sont en cours de validation.', icon: 'o-lock-closed', position: 'toast-top toast-end');
                        return;
                    }
                }
            }
        }

        $teacherId = $this->getTeacherIdForExportImport();
        $importer  = new GradeSheetImport($this->selectedClassroom, $this->selectedPeriod, $this->selectedYear, $this->selectedNiveau, $teacherId);

        try {
            $stored   = $this->importFile->store('grade-imports', 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($stored);

            Excel::import($importer, $fullPath);

            \Illuminate\Support\Facades\Storage::disk('local')->delete($stored);
            $this->importFile = null;

            $message = "Import réussi ! {$importer->imported} élève(s), {$importer->skipped} ignoré(s).";
            if ($teacherId && $importer->imported > 0) {
                $message .= " Seules vos matières ont été importées.";
            }

            $this->success($message, icon: 'o-arrow-up-tray', position: 'toast-top toast-end');

            if ($this->selectedStudent) {
                $this->loadOrCreateBulletin($this->selectedStudent);
            }

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = collect($e->failures())->map(fn($f) => "Ligne {$f->row()}: " . implode(', ', $f->errors()))->toArray();
            $this->error('Erreur de validation', implode(' | ', array_slice($errors, 0, 3)), icon: 'o-x-circle', position: 'toast-top toast-end');
        } catch (\Throwable $e) {
            $this->error('Erreur import', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
            \Log::error('Grade import error', ['message' => $e->getMessage(), 'classroom' => $this->selectedClassroom, 'period' => $this->selectedPeriod, 'teacher_id' => $teacherId]);
        }
    }

    // ── Data ──────────────────────────────────────────────────────────────────

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

        $subjects = collect();
        if ($this->selectedStudent && $this->selectedNiveau && $this->selectedClassroom) {
            $classCode = Classroom::find($this->selectedClassroom)?->code;

            $q = Subject::whereHas('niveau', fn($q) => $q->where('code', $this->selectedNiveau))
                ->where(fn($q) => $q->whereNull('classroom_code')->orWhere('classroom_code', $classCode))
                ->with(['competences' => fn($q) => $q->orderBy('order')]);

            if ($this->shouldFilterByTeacher()) {
                $q->whereHas('teachers', fn($q) => $q->where('users.id', $userId));
            }

            $subjects = $q->orderBy('order')->get();
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

                    $current              = $bulletins[$period] ?? null;
                    $s->current_bulletin  = $current;
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
                $prev = match($this->selectedPeriod) {
                    'T2' => '1er Trimestre',
                    'T3' => '2ème Trimestre',
                    default => 'trimestre précédent',
                };
                $lockReason = "Le {$prev} doit être approuvé avant de saisir ce trimestre.";
            } elseif ($this->bulletinId) {
                $bulletin = Bulletin::with(['teacherSubmissions.teacher', 'approvals'])->find($this->bulletinId);
                if ($bulletin) {
                    $canEdit          = $bulletin->canTeacherEdit(auth()->id());
                    // Direction never enters the "submitted" read-only state — they can always re-edit
                    $teacherSubmitted = $isDirection ? false : $bulletin->isTeacherSubmitted(auth()->id());
                    $progress         = $bulletin->teacherSubmissionProgress();
                }
            }
        }

        return compact(
            'niveaux', 'classrooms', 'years', 'students', 'subjects',
            'bulletin', 'canEdit', 'teacherSubmitted',
            'periodLocked', 'lockReason', 'progress'
        ) + [
            'periodOptions'     => PeriodEnum::options(),
            'competenceOptions' => CompetenceStatusEnum::options(),
            'csvExportUrl'      => $this->getCsvExportUrl(),
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

    {{-- Export / Import toolbar --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body py-3 px-4 space-y-3">

            {{-- Row 1: Excel --}}
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-semibold text-xs text-base-content/50 w-12 shrink-0">EXCEL</p>

                {{-- Export Excel --}}
                <x-button label="⬇ Exporter" wire:click="exportGrades" class="btn-outline btn-sm" spinner="exportGrades" icon="o-arrow-down-tray" />

                {{-- Import Excel --}}
                <div class="flex items-end gap-2">
                    <div>
                        <label class="label-text text-xs mb-1 block">Fichier .xlsx</label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="file-input file-input-sm file-input-bordered w-48" />
                    </div>
                    <x-button label="⬆ Importer" wire:click="importGrades" class="btn-primary btn-sm" spinner="importGrades" icon="o-arrow-up-tray" />
                </div>

                <div class="border-l border-base-200 pl-3 ml-auto">
                    <x-button
                        label="✈ Tout soumettre"
                        wire:click="bulkSubmitMySubjects"
                        class="btn-success btn-sm"
                        spinner="bulkSubmitMySubjects"
                        icon="o-paper-airplane"
                        tooltip="Soumettre vos notes pour tous les élèves de la classe"
                        wire:confirm="Soumettre vos notes pour tous les élèves de {{ \App\Enums\PeriodEnum::from($selectedPeriod)->label() }} ?"
                    />
                </div>
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
                    {{ strtoupper(substr($student->full_name,0,1)) }}
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

                @elseif($bulletin && $bulletin->status === \App\Enums\BulletinStatusEnum::REJECTED)
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
                    @include('livewire.bulletin._grade-form')

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
                        <x-button label="↩ Retirer ma soumission" wire:click="withdrawSubmission" class="btn-warning btn-xs" spinner="withdrawSubmission" tooltip="Retirer votre soumission pour corriger vos notes" wire:confirm="Retirer votre soumission ? Vos notes resteront enregistrées mais vous pourrez les modifier." />
                        @endif
                    </div>

                    @include('livewire.bulletin._grade-form', ['canEdit' => false])

                @elseif($bulletin && ! in_array($bulletin->status->value, ['draft', 'rejected']))
                    <div class="flex items-center justify-between p-3 bg-info/10 border border-info/20 rounded-xl">
                        <span class="text-sm">Statut : <strong class="badge {{ $bulletin->status->color() }} badge-sm ml-1">{{ $bulletin->status->label() }}</strong></span>
                        <span class="text-xs text-base-content/50">Lecture seule</span>
                    </div>
                    @include('livewire.bulletin._grade-form', ['canEdit' => false])

                @else
                    @if($progress['submitted'] > 0)
                    <div class="flex items-center gap-2 p-2 bg-base-200 rounded-lg text-xs">
                        <span class="text-base-content/60">Autres enseignants :</span>
                        @foreach($progress['teachers'] as $t)
                        <span class="badge {{ $t['submitted'] ? 'badge-success' : 'badge-ghost' }} badge-xs">{{ $t['submitted'] ? '✓' : '⏳' }} {{ explode(' ', $t['name'])[0] }}</span>
                        @endforeach
                    </div>
                    @endif

                    @include('livewire.bulletin._grade-form', ['canEdit' => $canEdit])
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
