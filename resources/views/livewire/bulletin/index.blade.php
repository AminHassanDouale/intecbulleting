<?php

use App\Actions\Bulletin\ApproveBulletinAction;
use App\Models\Bulletin;
use App\Models\Classroom;
use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination, Toast;

    public string $search          = '';
    public string $filterStatus    = '';
    public string $filterPeriod    = '';
    public string $filterClassCode = '';
    public string $filterSection   = '';
    public string $filterNiveau    = '';   // '' | 'PRESCOLAIRE' | 'PRIMAIRE'
    public bool   $showFilters     = false;
    public array  $selectedIds     = [];
    public bool   $selectAll       = false;

    // Bulk operation progress tracking
    public bool   $showProgressModal = false;
    public int    $progressTotal     = 0;
    public int    $progressCurrent   = 0;
    public string $progressOperation = '';
    public array  $progressResults   = [];

    // Single reject modal
    public bool    $showRejectModal = false;
    public ?int    $rejectId        = null;
    public string  $rejectReason    = '';

    // Bulk reject modal
    public bool   $showBulkRejectModal = false;
    public string $bulkRejectReason    = '';

    // Grade preview modal
    public bool    $showGradeModal  = false;
    public ?int    $viewId          = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->filterStatus = match(true) {
            $user->hasRole('pedagogie') => \App\Enums\BulletinStatusEnum::SUBMITTED->value,
            $user->hasRole('finance')   => \App\Enums\BulletinStatusEnum::PEDAGOGIE_APPROVED->value,
            $user->hasRole('direction') => \App\Enums\BulletinStatusEnum::FINANCE_APPROVED->value,
            $user->hasRole('admin')     => \App\Enums\BulletinStatusEnum::SUBMITTED->value,
            default                     => '',
        };

        $this->filterPeriod = \App\Enums\PeriodEnum::current()->value;
    }

    public function updatedSearch(): void          { $this->resetPage(); }
    public function updatedFilterStatus(): void    { $this->resetPage(); }
    public function updatedFilterPeriod(): void    { $this->resetPage(); }
    public function updatedFilterSection(): void   { $this->resetPage(); }
    public function updatedFilterNiveau(): void    { $this->selectedIds = []; $this->resetPage(); }

    public function updatedFilterClassCode(): void
    {
        $this->filterSection = '';
        $this->selectedIds   = [];
        $this->resetPage();
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            // Select all bulletins on current page
            $this->selectedIds = $this->getBulletins()->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            // Deselect all
            $this->selectedIds = [];
        }
    }

    public function updatedSelectedIds(): void
    {
        // Update selectAll checkbox state based on selection
        $totalOnPage = $this->getBulletins()->count();
        $this->selectAll = $totalOnPage > 0 && count($this->selectedIds) === $totalOnPage;
    }

    protected function getBulletins()
    {
        $query = Bulletin::with(['student', 'classroom.niveau', 'academicYear'])
            ->when($this->search, fn($q) =>
                $q->whereHas('student', fn($sq) =>
                    $sq->where('full_name', 'like', "%{$this->search}%")
                       ->orWhere('matricule', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterStatus,    fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPeriod,    fn($q) => $q->where('period', $this->filterPeriod))
            ->when($this->filterClassCode, fn($q) =>
                $q->whereHas('classroom', fn($cq) => $cq->where('code', $this->filterClassCode))
            )
            ->when($this->filterSection, fn($q) =>
                $q->whereHas('classroom', fn($cq) => $cq->where('section', $this->filterSection))
            )
            ->when($this->filterNiveau, fn($q) =>
                $q->whereHas('classroom.niveau', fn($nq) => $nq->where('code', $this->filterNiveau))
            );

        if (auth()->user()->hasRole('teacher')) {
            $query->where(fn($q) =>
                $q->whereHas('classroom', fn($q2) => $q2->where('teacher_id', auth()->id()))
                  ->orWhereHas('teacherSubmissions', fn($q2) => $q2->where('teacher_id', auth()->id()))
                  ->orWhereHas('classroom.niveau.subjects', fn($q2) =>
                      $q2->whereHas('teachers', fn($q3) => $q3->where('users.id', auth()->id()))
                  )
            );
        }

        return $query->latest()->paginate(20);
    }

    public function submitBulletin(int $id): void
    {
        $bulletin = Bulletin::findOrFail($id);

        if (! $bulletin->isEditable()) {
            $this->error('Bulletin verrouillé.', 'Ce bulletin ne peut plus être modifié.', icon: 'o-lock-closed', position: 'toast-top toast-end');
            return;
        }

        // Go through the action so multi-teacher tracking is respected:
        // 1-teacher class  → submits → auto-advances to pédagogie immediately
        // N-teacher class  → waits for ALL teachers before advancing
        $result = app(\App\Actions\Bulletin\SubmitTeacherSubjectsAction::class)
            ->execute($bulletin, auth()->user());

        if (! $result['success']) {
            $this->error('Erreur', $result['message'], icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }

        if ($result['fully_submitted']) {
            $this->success('Bulletin soumis !', 'Transmis à la pédagogie pour validation.', icon: 'o-paper-airplane', position: 'toast-top toast-end');
        } else {
            $remaining = ($result['progress']['total'] ?? 0) - ($result['progress']['submitted'] ?? 0);
            $this->warning(
                'Notes enregistrées.',
                "En attente de {$remaining} autre(s) enseignant(s) avant envoi à la pédagogie.",
                icon: 'o-clock',
                position: 'toast-top toast-end'
            );
        }
    }

    public function bulkSubmit(): void
    {
        if (empty($this->selectedIds)) {
            $this->warning('Aucune sélection.', 'Cochez au moins un bulletin.', icon: 'o-information-circle', position: 'toast-top toast-end');
            return;
        }

        $action         = app(\App\Actions\Bulletin\SubmitTeacherSubjectsAction::class);
        $fullySubmitted = 0;
        $partial        = 0;
        $errors         = 0;

        foreach ($this->selectedIds as $id) {
            $bulletin = Bulletin::find((int) $id);
            if (! $bulletin || ! $bulletin->isEditable()) {
                $errors++;
                continue;
            }

            $result = $action->execute($bulletin, auth()->user());

            if (! $result['success']) {
                $errors++;
            } elseif ($result['fully_submitted']) {
                $fullySubmitted++;
            } else {
                $partial++;
            }
        }

        $this->selectedIds = [];

        if ($fullySubmitted > 0) {
            $this->success(
                "{$fullySubmitted} bulletin(s) transmis à la pédagogie !",
                $partial > 0 ? "{$partial} en attente d'autres enseignants." : '',
                icon: 'o-paper-airplane',
                position: 'toast-top toast-end'
            );
        } elseif ($partial > 0) {
            $this->warning(
                "{$partial} note(s) enregistrée(s).",
                'En attente des autres enseignants avant transmission.',
                icon: 'o-clock',
                position: 'toast-top toast-end'
            );
        } else {
            $this->warning('Aucun bulletin soumis.', 'Les bulletins sélectionnés ne sont pas modifiables.', icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
        }
    }

    public function bulkApprove(): void
    {
        if (empty($this->selectedIds)) {
            $this->warning('Aucune sélection.', 'Cochez au moins un bulletin.', icon: 'o-information-circle', position: 'toast-top toast-end');
            return;
        }

        // Initialize progress tracking
        $this->progressTotal     = count($this->selectedIds);
        $this->progressCurrent   = 0;
        $this->progressOperation = 'Approbation en cours';
        $this->progressResults   = ['success' => [], 'errors' => []];
        $this->showProgressModal = true;

        $approved = 0;
        $action   = app(ApproveBulletinAction::class);

        foreach ($this->selectedIds as $index => $id) {
            $this->progressCurrent = $index + 1;

            try {
                $bulletin = Bulletin::with('student')->find((int) $id);

                if (!$bulletin) {
                    $this->progressResults['errors'][] = [
                        'id' => $id,
                        'message' => "Bulletin #{$id} introuvable"
                    ];
                    \Log::warning("Bulk approve: Bulletin not found", ['id' => $id]);
                    $this->dispatch('progress-updated'); // Trigger UI update
                    sleep(0.1); // Small delay for UI feedback
                    continue;
                }

                $studentName = $bulletin->student->full_name;

                // Check permission
                if (!auth()->user()->can('approve', $bulletin)) {
                    $this->progressResults['errors'][] = [
                        'id' => $id,
                        'student' => $studentName,
                        'message' => "Permission refusée pour {$studentName}"
                    ];
                    \Log::warning("Bulk approve: Permission denied", [
                        'bulletin_id' => $id,
                        'user_id' => auth()->id(),
                        'status' => $bulletin->status->value
                    ]);
                    $this->dispatch('progress-updated');
                    sleep(0.1);
                    continue;
                }

                // Use database transaction for data consistency
                \DB::beginTransaction();
                try {
                    $result = $action->execute($bulletin, auth()->user());

                    if ($result) {
                        $approved++;
                        $this->progressResults['success'][] = [
                            'id' => $id,
                            'student' => $studentName,
                            'status' => $bulletin->fresh()->status->label()
                        ];
                        \Log::info("Bulk approve: Success", [
                            'bulletin_id' => $id,
                            'student' => $studentName,
                            'new_status' => $bulletin->fresh()->status->value
                        ]);
                        \DB::commit();
                    } else {
                        \DB::rollBack();
                        $this->progressResults['errors'][] = [
                            'id' => $id,
                            'student' => $studentName,
                            'message' => "Échec de l'approbation pour {$studentName}"
                        ];
                        \Log::error("Bulk approve: Action returned false", [
                            'bulletin_id' => $id,
                            'student' => $studentName,
                            'status' => $bulletin->status->value
                        ]);
                    }
                } catch (\Throwable $e) {
                    \DB::rollBack();
                    throw $e;
                }

            } catch (\Throwable $e) {
                $this->progressResults['errors'][] = [
                    'id' => $id,
                    'student' => $bulletin->student->full_name ?? "ID {$id}",
                    'message' => $e->getMessage()
                ];
                \Log::error("Bulk approve: Exception", [
                    'bulletin_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->dispatch('progress-updated');
            sleep(0.1); // Small delay for better UX
        }

        // Close progress modal after completion
        sleep(0.5);
        $this->showProgressModal = false;
        $this->selectedIds = [];

        // Refresh the page data
        $this->dispatch('$refresh');

        // Show final results
        $errorCount = count($this->progressResults['errors']);

        if ($approved > 0 && $errorCount === 0) {
            $this->success(
                "✓ {$approved} bulletin(s) approuvé(s) !",
                'Validation effectuée avec succès.',
                icon: 'o-check-circle',
                position: 'toast-top toast-end',
                timeout: 3000
            );
        } elseif ($approved > 0 && $errorCount > 0) {
            $firstErrors = array_slice($this->progressResults['errors'], 0, 2);
            $errorMsg = implode(', ', array_map(fn($e) => $e['student'] ?? "#{$e['id']}", $firstErrors));

            $this->warning(
                "⚠️ {$approved} approuvé(s), {$errorCount} échec(s)",
                "Erreurs: {$errorMsg}" . ($errorCount > 2 ? '...' : ''),
                icon: 'o-exclamation-triangle',
                position: 'toast-top toast-end',
                timeout: 5000
            );
        } else {
            $firstErrors = array_slice($this->progressResults['errors'], 0, 2);
            $errorMsg = implode(', ', array_map(fn($e) => $e['message'], $firstErrors));

            $this->error(
                '✗ Aucun bulletin approuvé',
                $errorMsg . ($errorCount > 2 ? '...' : ''),
                icon: 'o-x-circle',
                position: 'toast-top toast-end',
                timeout: 5000
            );
        }
    }

    public function openBulkRejectModal(): void
    {
        if (empty($this->selectedIds)) {
            $this->warning('Aucune sélection.', icon: 'o-information-circle', position: 'toast-top toast-end');
            return;
        }

        $this->bulkRejectReason    = '';
        $this->showBulkRejectModal = true;
    }

    public function confirmBulkReject(): void
    {
        $this->validate(['bulkRejectReason' => 'required|string|min:5'], [
            'bulkRejectReason.required' => 'Un motif de rejet est obligatoire.',
            'bulkRejectReason.min'      => 'Le motif doit contenir au moins 5 caractères.',
        ]);

        // Close the reject modal and show progress
        $this->showBulkRejectModal = false;

        // Initialize progress tracking
        $this->progressTotal     = count($this->selectedIds);
        $this->progressCurrent   = 0;
        $this->progressOperation = 'Rejet en cours';
        $this->progressResults   = ['success' => [], 'errors' => []];
        $this->showProgressModal = true;

        $rejected = 0;

        foreach ($this->selectedIds as $index => $id) {
            $this->progressCurrent = $index + 1;

            try {
                $bulletin = Bulletin::with('student')->find((int) $id);

                if (!$bulletin) {
                    $this->progressResults['errors'][] = [
                        'id' => $id,
                        'message' => "Bulletin #{$id} introuvable"
                    ];
                    \Log::warning("Bulk reject: Bulletin not found", ['id' => $id]);
                    $this->dispatch('progress-updated');
                    sleep(0.1);
                    continue;
                }

                $studentName = $bulletin->student->full_name;

                // Check permission
                if (!auth()->user()->can('approve', $bulletin)) {
                    $this->progressResults['errors'][] = [
                        'id' => $id,
                        'student' => $studentName,
                        'message' => "Permission refusée pour {$studentName}"
                    ];
                    \Log::warning("Bulk reject: Permission denied", [
                        'bulletin_id' => $id,
                        'user_id' => auth()->id(),
                        'status' => $bulletin->status->value
                    ]);
                    $this->dispatch('progress-updated');
                    sleep(0.1);
                    continue;
                }

                // Use database transaction for data consistency
                \DB::beginTransaction();
                try {
                    $bulletin->rejectWorkflow(auth()->id(), $this->bulkRejectReason);
                    $rejected++;

                    $this->progressResults['success'][] = [
                        'id' => $id,
                        'student' => $studentName,
                        'status' => $bulletin->fresh()->status->label()
                    ];

                    \Log::info("Bulk reject: Success", [
                        'bulletin_id' => $id,
                        'student' => $studentName,
                        'reason' => $this->bulkRejectReason,
                        'new_status' => $bulletin->fresh()->status->value
                    ]);

                    \DB::commit();
                } catch (\Throwable $e) {
                    \DB::rollBack();
                    throw $e;
                }

            } catch (\Throwable $e) {
                $this->progressResults['errors'][] = [
                    'id' => $id,
                    'student' => $bulletin->student->full_name ?? "ID {$id}",
                    'message' => $e->getMessage()
                ];
                \Log::error("Bulk reject: Exception", [
                    'bulletin_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->dispatch('progress-updated');
            sleep(0.1);
        }

        // Close progress modal and clean up
        sleep(0.5);
        $this->showProgressModal = false;
        $this->bulkRejectReason  = '';
        $this->selectedIds       = [];

        // Refresh the page data
        $this->dispatch('$refresh');

        // Show final results
        $errorCount = count($this->progressResults['errors']);

        if ($rejected > 0 && $errorCount === 0) {
            $this->warning(
                "↩ {$rejected} bulletin(s) rejeté(s)",
                'Les enseignants ont été notifiés.',
                icon: 'o-arrow-uturn-left',
                position: 'toast-top toast-end',
                timeout: 3000
            );
        } elseif ($rejected > 0 && $errorCount > 0) {
            $firstErrors = array_slice($this->progressResults['errors'], 0, 2);
            $errorMsg = implode(', ', array_map(fn($e) => $e['student'] ?? "#{$e['id']}", $firstErrors));

            $this->warning(
                "⚠️ {$rejected} rejeté(s), {$errorCount} échec(s)",
                "Erreurs: {$errorMsg}" . ($errorCount > 2 ? '...' : ''),
                icon: 'o-exclamation-triangle',
                position: 'toast-top toast-end',
                timeout: 5000
            );
        } else {
            $firstErrors = array_slice($this->progressResults['errors'], 0, 2);
            $errorMsg = implode(', ', array_map(fn($e) => $e['message'], $firstErrors));

            $this->error(
                '✗ Aucun bulletin rejeté',
                $errorMsg . ($errorCount > 2 ? '...' : ''),
                icon: 'o-x-circle',
                position: 'toast-top toast-end',
                timeout: 5000
            );
        }
    }

    public function openRejectModal(int $id): void
    {
        $this->rejectId        = $id;
        $this->rejectReason    = '';
        $this->showRejectModal = true;
    }

    public function confirmReject(): void
    {
        $this->validate(['rejectReason' => 'required|string|min:5'], [
            'rejectReason.required' => 'Un motif de rejet est obligatoire.',
            'rejectReason.min'      => 'Le motif doit contenir au moins 5 caractères.',
        ]);

        $bulletin = Bulletin::findOrFail($this->rejectId);
        $this->authorize('approve', $bulletin);

        $bulletin->rejectWorkflow(auth()->id(), $this->rejectReason);

        $this->showRejectModal = false;
        $this->rejectId        = null;
        $this->rejectReason    = '';

        $this->warning('Bulletin rejeté.', 'L\'enseignant a été notifié.', icon: 'o-arrow-uturn-left', position: 'toast-top toast-end');
    }

    public function openGradeView(int $id): void
    {
        $this->viewId         = $id;
        $this->showGradeModal = true;
    }

    public function with(): array
    {
        $classCodes = Classroom::orderBy('code')->distinct()->pluck('code')
            ->map(fn($c) => ['id' => $c, 'name' => $c])->values()->toArray();

        $sections = $this->filterClassCode
            ? Classroom::where('code', $this->filterClassCode)
                ->orderBy('section')->distinct()->pluck('section')
                ->map(fn($s) => ['id' => $s, 'name' => 'Section ' . $s])->values()->toArray()
            : [];

        // Quick stats
        $base = Bulletin::query();
        if (auth()->user()->hasRole('teacher')) {
            $base->where(fn($q) =>
                $q->whereHas('classroom', fn($q2) => $q2->where('teacher_id', auth()->id()))
                  ->orWhereHas('teacherSubmissions', fn($q2) => $q2->where('teacher_id', auth()->id()))
            );
        }

        // Niveau tab counts
        $niveauCounts = [
            ''           => (clone $base)->count(),
            'PRESCOLAIRE'=> (clone $base)->whereHas('classroom.niveau', fn($q) => $q->where('code', 'PRESCOLAIRE'))->count(),
            'PRIMAIRE'   => (clone $base)->whereHas('classroom.niveau', fn($q) => $q->where('code', 'PRIMAIRE'))->count(),
        ];

        return [
            'bulletins'      => $this->getBulletins(),
            'niveauCounts'   => $niveauCounts,
            'statDraft'      => (clone $base)->where('status', BulletinStatusEnum::DRAFT)->count(),
            'statSubmitted'  => (clone $base)->where('status', BulletinStatusEnum::SUBMITTED)->count(),
            'statPending'    => (clone $base)->whereIn('status', [
                BulletinStatusEnum::PEDAGOGIE_APPROVED->value,
                BulletinStatusEnum::FINANCE_APPROVED->value,
            ])->count(),
            'statPublished'  => (clone $base)->where('status', BulletinStatusEnum::PUBLISHED)->count(),
            'statRejected'   => (clone $base)->where('status', BulletinStatusEnum::REJECTED)->count(),
            'statusOptions'  => (function () {
                $user    = auth()->user();
                $allowed = match(true) {
                    $user->hasRole('teacher')   => [
                        BulletinStatusEnum::DRAFT,
                        BulletinStatusEnum::SUBMITTED,
                        BulletinStatusEnum::REJECTED,
                    ],
                    $user->hasRole('pedagogie') => [
                        BulletinStatusEnum::SUBMITTED,
                        BulletinStatusEnum::PEDAGOGIE_REVIEW,
                        BulletinStatusEnum::PEDAGOGIE_APPROVED,
                        BulletinStatusEnum::REJECTED,
                    ],
                    $user->hasRole('finance')   => [
                        BulletinStatusEnum::PEDAGOGIE_APPROVED,
                        BulletinStatusEnum::FINANCE_REVIEW,
                        BulletinStatusEnum::FINANCE_APPROVED,
                        BulletinStatusEnum::REJECTED,
                    ],
                    $user->hasRole('direction') => [
                        BulletinStatusEnum::FINANCE_APPROVED,
                        BulletinStatusEnum::DIRECTION_REVIEW,
                        BulletinStatusEnum::APPROVED,
                        BulletinStatusEnum::PUBLISHED,
                        BulletinStatusEnum::REJECTED,
                    ],
                    default => BulletinStatusEnum::cases(),
                };
                return collect($allowed)->map(fn($s) => ['id' => $s->value, 'name' => $s->label()])->toArray();
            })(),
            'periodOptions'  => PeriodEnum::options(),
            'classCodes'     => $classCodes,
            'sections'       => $sections,
            'headers'        => [
                ['key' => 'student',   'label' => 'Élève'],
                ['key' => 'classroom', 'label' => 'Classe'],
                ['key' => 'period',    'label' => 'Période'],
                ['key' => 'moyenne',   'label' => 'Moyenne'],
                ['key' => 'status',    'label' => 'Statut'],
            ],
        ];
    }
}; ?>

<div class="space-y-4">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-blue-600 to-indigo-700 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">📋</div>
                <div>
                    <h1 class="text-xl font-bold">Bulletins scolaires</h1>
                    <p class="text-white/70 text-sm">Suivi et validation des carnets d'évaluation</p>
                </div>
            </div>
            @role('direction|admin')
            <a href="{{ route('bulletins.annual') }}" wire:navigate
               class="btn btn-sm bg-white/20 hover:bg-white/30 border-0 text-white gap-1 self-start sm:self-auto">
                📈 Bilan Annuel
            </a>
            @endrole
        </div>
    </div>

    {{-- Niveau tabs --}}
    <div class="flex gap-1 p-1 bg-base-200 rounded-xl w-fit">
        @foreach([
            [''            , 'Tous',         '📋', $niveauCounts['']],
            ['PRESCOLAIRE' , 'Préscolaire',   '🌱', $niveauCounts['PRESCOLAIRE']],
            ['PRIMAIRE'    , 'Primaire',      '📚', $niveauCounts['PRIMAIRE']],
        ] as [$val, $label, $icon, $count])
        <button
            wire:click="$set('filterNiveau', '{{ $val }}')"
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all
                {{ $filterNiveau === $val
                    ? 'bg-white shadow text-primary font-semibold'
                    : 'text-base-content/60 hover:text-base-content hover:bg-white/50' }}"
        >
            <span>{{ $icon }}</span>
            <span>{{ $label }}</span>
            <span class="badge badge-xs {{ $filterNiveau === $val ? 'badge-primary' : 'badge-ghost' }} font-bold">{{ $count }}</span>
        </button>
        @endforeach
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        @foreach([
            ['label' => 'Brouillons',  'value' => $statDraft,     'color' => 'text-base-content/60', 'bg' => 'bg-base-200',         'status' => 'draft'],
            ['label' => 'En attente',  'value' => $statSubmitted,  'color' => 'text-blue-600',        'bg' => 'bg-blue-50',          'status' => 'submitted'],
            ['label' => 'En cours',    'value' => $statPending,    'color' => 'text-amber-600',       'bg' => 'bg-amber-50',         'status' => 'pedagogie_approved'],
            ['label' => 'Publiés',     'value' => $statPublished,  'color' => 'text-emerald-600',     'bg' => 'bg-emerald-50',       'status' => 'published'],
            ['label' => 'Rejetés',     'value' => $statRejected,   'color' => 'text-red-600',         'bg' => 'bg-red-50',           'status' => 'rejected'],
        ] as $s)
        <button
            wire:click="$set('filterStatus', '{{ $s['status'] }}')"
            class="card {{ $filterStatus === $s['status'] ? 'ring-2 ring-primary shadow-md' : 'shadow-sm hover:shadow-md' }} {{ $s['bg'] }} border border-base-200 cursor-pointer transition-all">
            <div class="card-body py-3 px-4">
                <div class="text-2xl font-black {{ $s['color'] }}">{{ $s['value'] }}</div>
                <div class="text-xs text-base-content/50 font-medium">{{ $s['label'] }}</div>
            </div>
        </button>
        @endforeach
    </div>

    {{-- Filters --}}
    {{-- Search + filter button --}}
    <div class="flex gap-2">
        <x-input
            wire:model.live.debounce.300="search"
            placeholder="Rechercher un élève…"
            icon="o-magnifying-glass"
            clearable
            class="flex-1"
        />
        <div class="relative">
            <x-button icon="o-funnel" @click="$wire.showFilters = true" class="btn-outline" tooltip="Filtres" />
            @php $activeFilters = ($filterStatus ? 1 : 0) + ($filterPeriod ? 1 : 0) + ($filterClassCode ? 1 : 0) + ($filterSection ? 1 : 0); @endphp
            @if($activeFilters)
            <span class="absolute -top-1.5 -right-1.5 badge badge-warning badge-xs font-bold">{{ $activeFilters }}</span>
            @endif
        </div>
    </div>

    {{-- Filter drawer --}}
    <x-filter-drawer model="showFilters" title="Filtres" subtitle="Affiner la liste des bulletins">
        <x-choices label="Statut" wire:model.live="filterStatus" :options="$statusOptions" single clearable icon="o-flag" placeholder="Tous les statuts" />
        <x-choices label="Trimestre" wire:model.live="filterPeriod" :options="$periodOptions" single clearable icon="o-clock" placeholder="Tous les trimestres" />
        <x-choices label="Classe" wire:model.live="filterClassCode" :options="$classCodes" single clearable icon="o-building-library" placeholder="Toutes les classes" />
        <x-choices
            label="Section"
            wire:model.live="filterSection"
            :options="$sections"
            single clearable
            icon="o-tag"
            placeholder="{{ $filterClassCode ? 'Toutes les sections' : '— Choisir une classe —' }}"
            :disabled="!$filterClassCode"
        />
        <x-slot:actions>
            <x-button label="Réinitialiser" wire:click="$set('filterStatus',''); $set('filterPeriod',''); $set('filterClassCode',''); $set('filterSection',''); $set('filterNiveau','')" icon="o-arrow-path" />
            <x-button label="Fermer" @click="$wire.showFilters = false" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-filter-drawer>

    {{-- Bulk action bar --}}
    @if(!empty($selectedIds))
    <div class="flex flex-wrap items-center gap-2 p-3 bg-indigo-50 border border-indigo-200 rounded-xl shadow-sm">
        <span class="text-sm font-semibold text-indigo-800">
            {{ count($selectedIds) }} bulletin(s) sélectionné(s)
        </span>
        <div class="flex-1"></div>

        @role('teacher')
        <x-button label="✈ Soumettre la sélection" wire:click="bulkSubmit" class="btn-primary btn-sm"
            spinner="bulkSubmit" icon="o-paper-airplane"
            wire:confirm="Soumettre les {{ count($selectedIds) }} bulletin(s) à la pédagogie ?" />
        @endrole

        @role('pedagogie|finance|direction|admin')
        <x-button label="✓ Approuver" wire:click="bulkApprove" class="btn-success btn-sm"
            spinner="bulkApprove" icon="o-check-badge"
            wire:confirm="Approuver les {{ count($selectedIds) }} bulletin(s) sélectionné(s) ?" />
        <x-button label="✗ Rejeter" wire:click="openBulkRejectModal" class="btn-error btn-sm"
            icon="o-arrow-uturn-left" />
        @endrole

        <x-button label="Annuler" wire:click="$set('selectedIds', [])" class="btn-ghost btn-sm" icon="o-x-mark" />
    </div>
    @endif

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-12">
                                <x-checkbox
                                    wire:model.live="selectAll"
                                    wire:click="toggleSelectAll"
                                />
                            </th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Période</th>
                            <th>Moyenne</th>
                            <th>Statut</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bulletins as $bulletin)
                        <tr class="hover">
                            <td>
                                <x-checkbox
                                    wire:model.live="selectedIds"
                                    value="{{ $bulletin->id }}"
                                />
                            </td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                        {{ $bulletin->student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                                        {{ strtoupper(substr($bulletin->student->full_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-sm">{{ $bulletin->student->full_name }}</p>
                                        <p class="text-xs text-base-content/40 font-mono">{{ $bulletin->student->matricule }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-medium">
                                        {{ $bulletin->classroom->label ?? $bulletin->classroom->code }}
                                        <span class="text-base-content/40 text-xs">§{{ $bulletin->classroom->section }}</span>
                                    </span>
                                    @if($bulletin->classroom->niveau)
                                    <span class="badge badge-xs {{ $bulletin->classroom->niveau->code === 'PRESCOLAIRE' ? 'badge-warning' : 'badge-info' }} w-fit">
                                        {{ $bulletin->classroom->niveau->code === 'PRESCOLAIRE' ? '🌱 Préscolaire' : '📚 Primaire' }}
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">{{ \App\Enums\PeriodEnum::from($bulletin->period)->label() }}</span>
                            </td>
                            <td>
                                @if($bulletin->moyenne !== null)
                                    <span class="font-bold text-sm {{ $bulletin->moyenne >= 10 ? 'text-success' : 'text-error' }}">
                                        {{ $bulletin->moyenne }}/20
                                    </span>
                                @else
                                    <span class="text-base-content/25">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $bulletin->status->color() }} badge-sm font-medium">
                                    {{ $bulletin->status->label() }}
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-1 flex-wrap justify-end">
                                    {{-- 👁 Notes preview — visible to ALL roles --}}
                                    <x-button
                                        wire:click="openGradeView({{ $bulletin->id }})"
                                        class="btn-xs btn-ghost text-indigo-600"
                                        icon="o-eye"
                                        tooltip="Voir les notes"
                                    />
                                    {{-- Teacher: submit DRAFT/REJECTED --}}
                                    @if($bulletin->isEditable())
                                        <x-button label="Soumettre" wire:click="submitBulletin({{ $bulletin->id }})"
                                            class="btn-xs btn-primary" icon="o-paper-airplane" spinner="submitBulletin"
                                            wire:confirm="Soumettre ce bulletin à la pédagogie ?" />
                                    @endif
                                    {{-- Pedagogie / Finance / Direction: approve + reject --}}
                                    @can('approve', $bulletin)
                                        <a href="{{ route('bulletins.workflow', $bulletin->id) }}" wire:navigate
                                           class="btn btn-xs btn-warning">⚖️ Valider</a>
                                        <x-button label="Rejeter" wire:click="openRejectModal({{ $bulletin->id }})"
                                            class="btn-xs btn-error" icon="o-x-mark" />
                                    @endcan
                                    {{-- PDF download if published --}}
                                    @if($bulletin->status->value === 'published' && $bulletin->getPdfUrl())
                                        <a href="{{ route('bulletins.download', $bulletin->id) }}?download=1"
                                           class="btn btn-xs btn-outline">⬇️</a>
                                    @endif
                                    {{-- Carnet d'évaluation printable --}}
                                    @if($bulletin->status->value === 'published')
                                        <a href="{{ route('bulletins.carnet', $bulletin->student_id) }}"
                                           class="btn btn-xs btn-outline btn-success" title="Voir le carnet d'évaluation">📋</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-base-content/40">
                                Aucun bulletin trouvé
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($bulletins->hasPages())
            <div class="border-t border-base-200 px-4 py-3">
                {{ $bulletins->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- ── Modal: aperçu des notes — all roles ──────────────────────────── --}}
    <x-modal wire:model="showGradeModal"
             title="Aperçu des notes"
             subtitle="Compétences & classements"
             box-class="max-w-2xl !bg-white">

        @if($showGradeModal && $viewId)
        @php
            $pb = \App\Models\Bulletin::with([
                'student', 'classroom',
                'grades.competence.subject',
            ])->find($viewId);

            $classRankMap = $pb
                ? \App\Models\BulletinGrade::whereHas('bulletin', fn($q) =>
                        $q->where('classroom_id', $pb->classroom_id)
                          ->where('period', $pb->period)
                          ->where('academic_year_id', $pb->academic_year_id)
                    )->whereNotNull('score')
                     ->get(['competence_id', 'score'])
                     ->groupBy('competence_id')
                : collect();
        @endphp

        @if($pb)
        {{-- Student banner --}}
        <div class="rounded-xl bg-linear-to-r from-indigo-600 to-violet-700 text-white px-4 py-3 flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-lg font-bold shrink-0">
                    {{ strtoupper(substr($pb->student->full_name, 0, 1)) }}
                </div>
                <div>
                    <p class="font-bold text-sm leading-tight">{{ $pb->student->full_name }}</p>
                    <p class="text-xs text-white/60 mt-0.5">
                        {{ $pb->classroom->label ?? $pb->classroom->code }}
                        &bull; {{ \App\Enums\PeriodEnum::from($pb->period)->label() }}
                        &bull; <span class="font-mono">{{ $pb->student->matricule }}</span>
                    </p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="badge {{ $pb->status->color() }} badge-sm font-semibold">{{ $pb->status->label() }}</span>
                @if($pb->moyenne !== null)
                <div class="text-lg font-black mt-1 {{ $pb->moyenne >= 10 ? 'text-emerald-300' : 'text-red-300' }}">
                    {{ $pb->moyenne }}<span class="text-xs opacity-60">/20</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap gap-2 text-xs mb-3 px-1">
            <span class="badge badge-success badge-sm">Acquis</span>
            <span class="badge badge-warning badge-sm">Moyen</span>
            <span class="badge badge-error badge-sm">Très faible</span>
            <span class="ml-auto text-base-content/40 flex items-center gap-1">
                <span class="w-4 h-4 bg-amber-100 rounded text-center text-amber-700 font-bold text-xs leading-4">1</span> = 1er classement
            </span>
        </div>

        {{-- Grades by subject --}}
        <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
            @forelse($pb->grades->groupBy('competence.subject_id') as $subjectGrades)
            @php
                $subject    = $subjectGrades->first()->competence->subject;
                $numScores  = $subjectGrades->whereNotNull('score');
                $subjectAvg = $numScores->count() ? round($numScores->avg('score'), 1) : null;
            @endphp
            <div class="border border-base-200 rounded-xl overflow-hidden bg-white">
                <div class="flex items-center justify-between px-3 py-2 bg-indigo-50 border-b border-indigo-100">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-indigo-700">{{ $subject->name }}</span>
                        <span class="badge badge-outline badge-xs badge-info">{{ $subject->code }}</span>
                    </div>
                    @if($subjectAvg !== null)
                        <span class="text-xs font-bold {{ $subjectAvg >= ($subjectGrades->first()->competence->max_score / 2) ? 'text-success' : 'text-error' }}">
                            moy. {{ $subjectAvg }}
                        </span>
                    @endif
                </div>

                @foreach($subjectGrades as $grade)
                @php
                    $allScores    = $classRankMap[$grade->competence_id] ?? collect();
                    $totalInClass = $allScores->count();
                    $rank         = ($grade->score !== null && $totalInClass > 0)
                        ? $allScores->filter(fn($g) => (float)$g->score > (float)$grade->score)->count() + 1
                        : null;
                    $rankLabel    = match(true) {
                        $rank === 1    => '1er',
                        $rank === 2    => '2ème',
                        $rank === 3    => '3ème',
                        $rank !== null => "{$rank}ème",
                        default        => null,
                    };
                @endphp
                <div class="flex items-center gap-2 px-3 py-2 text-sm border-t border-base-100 hover:bg-slate-50 transition-colors">
                    <span class="flex-1 text-base-content/70 leading-tight min-w-0">
                        <span class="font-mono text-xs text-indigo-400 mr-1">{{ $grade->competence->code }}</span>
                        {{ \Illuminate\Support\Str::limit($grade->competence->description, 50) }}
                    </span>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($grade->score !== null)
                            @php $half = $grade->competence->max_score ? $grade->competence->max_score / 2 : 5; @endphp
                            <span class="font-bold tabular-nums {{ (float)$grade->score >= $half ? 'text-success' : 'text-error' }}">
                                {{ $grade->score }}<span class="text-base-content/30 font-normal text-xs">/{{ $grade->competence->max_score }}</span>
                            </span>
                        @elseif($grade->competence_status)
                            <span class="badge badge-sm font-semibold {{ $grade->competence_status->badgeClass() }}">
                                {{ $grade->competence_status->shortLabel() }}
                            </span>
                        @else
                            <span class="text-base-content/20">—</span>
                        @endif

                        @if($rankLabel && $totalInClass > 1)
                            <span class="text-xs px-1.5 py-0.5 rounded font-bold tabular-nums
                                {{ $rank === 1
                                    ? 'bg-amber-100 text-amber-700'
                                    : ($rank <= 3
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-base-200 text-base-content/40') }}">
                                {{ $rankLabel }}/{{ $totalInClass }}
                            </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @empty
            <div class="py-8 text-center text-base-content/40 text-sm">Aucune note saisie pour ce bulletin.</div>
            @endforelse
        </div>

        {{-- Overall average bar --}}
        @if($pb->moyenne)
        <div class="mt-4 flex justify-between items-center px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl font-bold text-sm">
            <span class="text-indigo-700">Moyenne générale</span>
            <span class="text-xl {{ $pb->moyenne >= 10 ? 'text-success' : 'text-error' }}">
                {{ $pb->moyenne }}<span class="text-base-content/40 text-sm font-normal">/20</span>
            </span>
        </div>
        @endif

        {{-- Teacher comment --}}
        @if($pb->teacher_comment)
        <div class="mt-3 flex items-start gap-2 px-3 py-2 bg-amber-50 border border-amber-100 rounded-xl text-sm">
            <span class="text-lg shrink-0">💬</span>
            <div>
                <p class="text-xs font-semibold text-amber-700 mb-0.5">Commentaire enseignant</p>
                <p class="text-amber-900 text-sm italic">{{ $pb->teacher_comment }}</p>
            </div>
        </div>
        @endif
        @endif
        @endif

        <x-slot:actions>
            <x-button label="Fermer" @click="$wire.showGradeModal = false" />
            @can('approve', optional(\App\Models\Bulletin::find($viewId ?? 0)))
            @if($viewId)
            <a href="{{ route('bulletins.workflow', $viewId) }}" wire:navigate class="btn btn-warning btn-sm">
                ⚖️ Valider ce bulletin
            </a>
            @endif
            @endcan
        </x-slot:actions>
    </x-modal>

    {{-- Modal: rejet groupé --}}
    <x-modal wire:model="showBulkRejectModal"
             title="✗ Rejeter la sélection"
             subtitle="Les bulletins repassent à l'étape précédente"
             box-class="!bg-white">
        <x-form wire:submit="confirmBulkReject" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <div class="space-y-3">
                <div class="flex items-start gap-3 p-3 bg-error/10 border border-error/20 rounded-xl text-sm">
                    <span class="text-lg">⚠️</span>
                    <span><strong>{{ count($selectedIds) }} bulletin(s)</strong> vont être renvoyés à l'étape précédente. Les enseignants concernés seront notifiés.</span>
                </div>
                <x-textarea
                    label="Motif du rejet (obligatoire)"
                    wire:model="bulkRejectReason"
                    placeholder="Ex : Notes incomplètes, compétences manquantes…"
                    rows="4"
                    hint="Ce motif sera envoyé à tous les enseignants concernés."
                />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showBulkRejectModal = false" />
                <x-button label="Confirmer le rejet groupé" class="btn-error" type="submit"
                    spinner="confirmBulkReject" icon="o-arrow-uturn-left" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Modal: rejet individuel --}}
    <x-modal wire:model="showRejectModal"
             title="✗ Rejeter le bulletin"
             subtitle="L'enseignant sera notifié et pourra corriger"
             box-class="!bg-white">
        <x-form wire:submit="confirmReject" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <x-textarea
                label="Motif du rejet"
                wire:model="rejectReason"
                placeholder="Ex : Notes incomplètes pour la matière MATHS, compétence CB2 manquante…"
                rows="4"
            />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showRejectModal = false" />
                <x-button label="Confirmer le rejet" class="btn-error" type="submit"
                    spinner="confirmReject" icon="o-x-mark" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Modal: Progression des opérations groupées --}}
    <x-modal wire:model="showProgressModal"
             title="{{ $progressOperation }}"
             subtitle="Traitement en cours..."
             box-class="!bg-white"
             persistent>
        <div class="space-y-4">
            {{-- Progress bar --}}
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="font-medium">Progression</span>
                    <span class="font-bold text-primary">{{ $progressCurrent }}/{{ $progressTotal }}</span>
                </div>
                <div class="w-full bg-base-200 rounded-full h-3 overflow-hidden">
                    <div class="bg-gradient-to-r from-primary to-secondary h-3 rounded-full transition-all duration-300 ease-out flex items-center justify-end"
                         style="width: {{ $progressTotal > 0 ? round(($progressCurrent / $progressTotal) * 100) : 0 }}%">
                        @if($progressCurrent > 0)
                        <span class="text-xs text-white font-bold pr-2">{{ round(($progressCurrent / $progressTotal) * 100) }}%</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Live results --}}
            <div class="max-h-64 overflow-y-auto space-y-2">
                {{-- Success items --}}
                @foreach($progressResults['success'] ?? [] as $item)
                <div class="flex items-start gap-2 p-2 bg-success/10 border border-success/20 rounded-lg text-sm" wire:key="success-{{ $item['id'] }}">
                    <span class="text-success text-lg shrink-0">✓</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-success truncate">{{ $item['student'] }}</p>
                        <p class="text-xs text-success/70">{{ $item['status'] }}</p>
                    </div>
                </div>
                @endforeach

                {{-- Error items --}}
                @foreach($progressResults['errors'] ?? [] as $item)
                <div class="flex items-start gap-2 p-2 bg-error/10 border border-error/20 rounded-lg text-sm" wire:key="error-{{ $item['id'] }}">
                    <span class="text-error text-lg shrink-0">✗</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-error truncate">{{ $item['student'] ?? "Bulletin #{$item['id']}" }}</p>
                        <p class="text-xs text-error/70">{{ $item['message'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Loading indicator --}}
            @if($progressCurrent < $progressTotal)
            <div class="flex items-center justify-center gap-2 py-3">
                <span class="loading loading-spinner loading-md text-primary"></span>
                <span class="text-sm text-base-content/60">Traitement en cours...</span>
            </div>
            @endif
        </div>
    </x-modal>
</div>
