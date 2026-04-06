<?php

use App\Actions\Bulletin\ApproveBulletinAction;
use App\Actions\Bulletin\GenerateBulletinPdfAction;
use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public Bulletin $bulletin;
    public string   $approvalComment = '';

    // ── Direction grade editing ────────────────────────────────────────────────
    public bool  $editingGrades = false;
    public array $gradeEdits    = [];  // [gradeId => value]

    public function mount(Bulletin $bulletin): void
    {
        $this->bulletin = $bulletin->load([
            'student.classroom.niveau',
            'classroom.teacher',
            'grades.competence.subject',
            'approvals.user',
            'teacherSubmissions.teacher',
            'academicYear',
        ]);
    }

    public function approve(): void
    {
        $this->authorize('approve', $this->bulletin);

        $result = app(ApproveBulletinAction::class)->execute(
            $this->bulletin,
            auth()->user(),
            $this->approvalComment ?: null
        );

        if ($result) {
            $this->bulletin->refresh();
            $this->approvalComment = '';
            $this->success('Bulletin approuvé !', 'Le dossier passe à l\'étape suivante.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            $this->error('Impossible d\'approuver ce bulletin.', icon: 'o-x-circle', position: 'toast-top toast-end');
        }
    }

    public function reject(): void
    {
        $this->authorize('approve', $this->bulletin);

        $this->validate(['approvalComment' => 'required|string|min:5'], [
            'approvalComment.required' => 'Un motif de rejet est obligatoire.',
        ]);

        $this->bulletin->rejectWorkflow(auth()->id(), $this->approvalComment);
        $this->bulletin->refresh();
        $this->approvalComment = '';
        $this->warning('Bulletin rejeté.', 'L\'enseignant a été notifié.', icon: 'o-arrow-uturn-left', position: 'toast-top toast-end');
    }

    public function generatePdf(): void
    {
        $this->authorize('generatePdf', $this->bulletin);

        try {
            app(GenerateBulletinPdfAction::class)->execute($this->bulletin);
            $this->bulletin->refresh();
            $this->success('PDF généré et publié !', icon: 'o-document-check', position: 'toast-top toast-end');
        } catch (\Exception $e) {
            $this->error('Erreur PDF', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
        }
    }

    public function canAct(): bool
    {
        return auth()->user()->can('approve', $this->bulletin);
    }

    public function startEditGrades(): void
    {
        $this->gradeEdits = [];
        foreach ($this->bulletin->grades as $grade) {
            $this->gradeEdits[$grade->id] = $grade->score !== null
                ? (string) $grade->score
                : ($grade->competence_status?->value ?? '');
        }
        $this->editingGrades = true;
    }

    public function saveGrades(): void
    {
        $this->authorize('approve', $this->bulletin);

        foreach ($this->gradeEdits as $gradeId => $rawValue) {
            $grade = $this->bulletin->grades->find((int) $gradeId);
            if (! $grade) {
                continue;
            }

            if ($grade->score !== null || (is_numeric($rawValue) && $rawValue !== '')) {
                // Numeric grade
                $grade->update([
                    'score' => (is_numeric($rawValue) && $rawValue !== '')
                        ? min((float) $rawValue, $grade->competence->max_score ?? PHP_INT_MAX)
                        : null,
                ]);
            } else {
                // Competence-status grade
                $grade->update(['competence_status' => $rawValue !== '' ? $rawValue : null]);
            }
        }

        $this->bulletin->recalculateMoyenne();
        $this->bulletin->refresh()->load(['grades.competence.subject', 'student.classroom.niveau']);
        $this->editingGrades = false;

        $this->success('Notes mises à jour !', 'La moyenne a été recalculée.', icon: 'o-check-circle', position: 'toast-top toast-end');
    }

    public function with(): array
    {
        $steps = [
            'submitted'          => ['label' => 'Enseignant',  'icon' => '👨‍🏫'],
            'pedagogie_approved' => ['label' => 'Pédagogie',   'icon' => '📚'],
            'finance_approved'   => ['label' => 'Finance',     'icon' => '💰'],
            'approved'           => ['label' => 'Direction',   'icon' => '🏛️'],
            'published'          => ['label' => 'Publié',      'icon' => '✅'],
        ];

        $statusOrder = array_keys($steps);
        $currentIdx  = array_search($this->bulletin->status->value, $statusOrder) ?? -1;

        return [
            'steps'      => $steps,
            'currentIdx' => $currentIdx,
            'statusOrder'=> $statusOrder,
        ];
    }
}; ?>

<div class="space-y-5 max-w-4xl mx-auto">

    {{-- Page header --}}
    @php
        $isRejected  = $bulletin->status === \App\Enums\BulletinStatusEnum::REJECTED;
        $isPublished = $bulletin->status === \App\Enums\BulletinStatusEnum::PUBLISHED;
        $gradient    = $isRejected ? 'from-red-600 to-rose-700' : ($isPublished ? 'from-emerald-600 to-teal-600' : 'from-indigo-600 to-violet-700');
    @endphp
    <div class="rounded-2xl bg-linear-to-r {{ $gradient }} text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('bulletins.index') }}"
                   class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition-colors">
                    ←
                </a>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">
                    {{ $isRejected ? '↩' : ($isPublished ? '✅' : '🔄') }}
                </div>
                <div>
                    <h1 class="text-xl font-bold">Workflow de Validation</h1>
                    <p class="text-white/70 text-sm">
                        {{ $bulletin->student->full_name }}
                        &bull; {{ \App\Enums\PeriodEnum::from($bulletin->period)->label() }}
                        &bull; {{ $bulletin->classroom->label }}
                    </p>
                </div>
            </div>
            <span class="badge {{ $bulletin->status->color() }} badge-lg font-semibold px-4 py-2 text-sm self-start sm:self-auto">
                {{ $bulletin->status->label() }}
            </span>
        </div>
    </div>

    {{-- Workflow stepper --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body py-4">
            <div class="flex items-center justify-between gap-2 overflow-x-auto pb-1">
                @foreach($steps as $key => $step)
                @php
                    $idx      = array_search($key, $statusOrder);
                    $isDone   = $currentIdx >= $idx && !$isRejected;
                    $isCurrent= $currentIdx === $idx && !$isRejected;
                @endphp
                <div class="flex flex-col items-center gap-1 min-w-16 {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold border-2 transition-all
                        {{ $isDone   ? 'bg-primary border-primary text-primary-content shadow-md' :
                           ($isCurrent ? 'bg-primary/20 border-primary text-primary animate-pulse' :
                                         'bg-base-200 border-base-300 text-base-content/40') }}">
                        {{ $isDone ? '✓' : $step['icon'] }}
                    </div>
                    <span class="text-xs font-medium text-center leading-tight
                        {{ $isDone ? 'text-primary' : ($isCurrent ? 'text-primary' : 'text-base-content/40') }}">
                        {{ $step['label'] }}
                    </span>
                </div>
                @if(!$loop->last)
                <div class="flex-1 h-0.5 mb-5 rounded {{ $currentIdx > $idx && !$isRejected ? 'bg-primary' : 'bg-base-300' }}"></div>
                @endif
                @endforeach
            </div>

            @if($isRejected)
            <div class="mt-2 flex items-center gap-2 p-3 bg-error/10 border border-error/20 rounded-xl text-sm text-error">
                <span class="text-lg">⚠️</span>
                <span class="font-medium">Ce bulletin a été rejeté — en attente de correction par l'enseignant.</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Bulletin info + moyenne --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="card bg-base-100 shadow">
            <div class="card-body py-4">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-3">Informations</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Élève</span>
                        <strong>{{ $bulletin->student->full_name }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Matricule</span>
                        <span class="font-mono text-xs">{{ $bulletin->student->matricule }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Classe</span>
                        <span>{{ $bulletin->classroom->label }} — {{ $bulletin->classroom->section }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Période</span>
                        <span class="badge badge-ghost badge-sm">{{ \App\Enums\PeriodEnum::from($bulletin->period)->label() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Année</span>
                        <span>{{ $bulletin->academicYear->label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body py-4">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-3">Résultats</h3>
                <div class="flex items-center justify-center h-20">
                    @if($bulletin->moyenne !== null)
                    <div class="text-center">
                        <div class="text-4xl font-black {{ $bulletin->moyenne >= 10 ? 'text-success' : 'text-error' }}">
                            {{ $bulletin->moyenne }}<span class="text-xl text-base-content/40">/20</span>
                        </div>
                        <div class="text-xs text-base-content/50 mt-1">Moyenne individuelle</div>
                    </div>
                    @else
                    <span class="text-base-content/30 italic text-sm">Moyenne non calculée</span>
                    @endif
                </div>
                @if($bulletin->class_moyenne)
                <div class="flex justify-between text-sm border-t border-base-200 pt-3 mt-1">
                    <span class="text-base-content/60">Moyenne de classe</span>
                    <span class="font-semibold">{{ $bulletin->class_moyenne }}/20</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Teacher comment --}}
    @if($bulletin->teacher_comment)
    <div class="card bg-amber-50 border border-amber-200 shadow-sm">
        <div class="card-body py-3">
            <div class="flex items-start gap-3">
                <span class="text-xl mt-0.5">💬</span>
                <div>
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">Commentaire de l'enseignant</p>
                    <p class="text-sm text-amber-900">{{ $bulletin->teacher_comment }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Teacher submissions --}}
    @if($bulletin->teacherSubmissions->isNotEmpty())
    <div class="card bg-base-100 shadow">
        <div class="card-body py-4">
            <h3 class="font-bold text-sm mb-3">Soumissions des enseignants</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($bulletin->teacherSubmissions as $sub)
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border
                    {{ $sub->status === 'submitted' ? 'bg-success/10 border-success/30 text-success' : 'bg-warning/10 border-warning/30 text-warning' }}">
                    <span class="text-sm">{{ $sub->status === 'submitted' ? '✓' : '⏳' }}</span>
                    <span class="text-sm font-medium">{{ $sub->teacher->name }}</span>
                    @if($sub->submitted_at)
                    <span class="text-xs opacity-60">{{ $sub->submitted_at->format('d/m H:i') }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Grades summary --}}
    @if($bulletin->grades->isNotEmpty())
    <div class="card bg-base-100 shadow">
        <div class="card-body py-4">
            <h3 class="font-bold text-sm mb-4">Notes saisies</h3>
            <div class="space-y-3">
                @foreach($bulletin->grades->groupBy('competence.subject_id') as $subjectGrades)
                @php $subject = $subjectGrades->first()->competence->subject; @endphp
                <div>
                    <div class="flex items-center gap-2 bg-primary/10 px-3 py-1.5 rounded-lg mb-1">
                        <span class="text-sm font-bold text-primary">{{ $subject->name }}</span>
                        <span class="badge badge-outline badge-xs badge-primary">{{ $subject->code }}</span>
                    </div>
                    @foreach($subjectGrades as $grade)
                    <div class="flex justify-between items-center px-3 py-1.5 text-sm border-b border-base-100 last:border-0 hover:bg-base-50">
                        <span class="text-base-content/70 truncate flex-1 mr-3">
                            <span class="font-mono text-xs text-primary/70 mr-1">{{ $grade->competence->code }}</span>
                            {{ Str::limit($grade->competence->description, 60) }}
                        </span>
                        <span class="font-bold shrink-0">
                            @if($grade->score !== null)
                                <span class="{{ $grade->score >= ($grade->competence->max_score / 2) ? 'text-success' : 'text-error' }}">
                                    {{ $grade->score }}/{{ $grade->competence->max_score }}
                                </span>
                            @elseif($grade->competence_status)
                                <span class="badge badge-sm font-semibold {{ $grade->competence_status->badgeClass() }}">
                                    {{ $grade->competence_status->shortLabel() }}
                                </span>
                            @else
                                <span class="text-base-content/30">—</span>
                            @endif
                        </span>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Approval history timeline --}}
    @if($bulletin->approvals->isNotEmpty())
    <div class="card bg-base-100 shadow">
        <div class="card-body py-4">
            <h3 class="font-bold text-sm mb-4">Historique des validations</h3>
            <div class="relative pl-6">
                <div class="absolute left-2.5 top-0 bottom-0 w-0.5 bg-base-200"></div>
                @foreach($bulletin->approvals as $approval)
                <div class="relative mb-4 last:mb-0">
                    <div class="absolute -left-4 w-5 h-5 rounded-full border-2 border-base-100 flex items-center justify-center text-xs
                        {{ $approval->isApproved() ? 'bg-success text-success-content' : 'bg-error text-error-content' }}">
                        {{ $approval->isApproved() ? '✓' : '✗' }}
                    </div>
                    <div class="pl-3">
                        <p class="text-sm font-semibold">{{ $approval->user->name }}
                            <span class="text-base-content/50 font-normal">— {{ $approval->step }}</span>
                        </p>
                        @if($approval->comment)
                        <p class="text-xs text-base-content/60 italic mt-0.5 p-2 bg-base-200 rounded">{{ $approval->comment }}</p>
                        @endif
                        <p class="text-xs text-base-content/40 mt-1">{{ $approval->created_at->format('d/m/Y à H:i') }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Approval action panel --}}
    @if($this->canAct())
    <div class="card bg-base-100 shadow border-2 border-primary/20">
        <div class="card-body space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center text-xl">⚖️</div>
                <div>
                    <h3 class="font-bold">Votre décision</h3>
                    <p class="text-xs text-base-content/50">Un commentaire est obligatoire pour rejeter.</p>
                </div>
            </div>
            <x-textarea
                wire:model="approvalComment"
                placeholder="Commentaire ou motif de rejet…"
                rows="3"
            />
            <div class="flex gap-3">
                <x-button
                    label="✓ Approuver"
                    wire:click="approve"
                    class="btn-success flex-1"
                    spinner="approve"
                    wire:confirm="Confirmer l'approbation de ce bulletin ?"
                    icon="o-check-circle"
                />
                <x-button
                    label="✗ Rejeter"
                    wire:click="reject"
                    class="btn-error flex-1"
                    spinner="reject"
                    wire:confirm="Confirmer le rejet de ce bulletin ?"
                    icon="o-arrow-uturn-left"
                />
            </div>
        </div>
    </div>
    @endif

    {{-- Direction grade editing panel --}}
    @if(auth()->user()->hasAnyRole(['direction', 'admin']) && $bulletin->canDirectionEdit())
    <div class="card bg-base-100 shadow border border-amber-200">
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-100 text-amber-700 rounded-xl flex items-center justify-center text-xl">✏️</div>
                    <div>
                        <h3 class="font-bold">Modifier les notes</h3>
                        <p class="text-xs text-base-content/50">La direction peut corriger les notes directement avant approbation.</p>
                    </div>
                </div>
                @if(! $editingGrades)
                <x-button label="Modifier" wire:click="startEditGrades" class="btn-warning btn-sm" icon="o-pencil" />
                @endif
            </div>

            @if($editingGrades)
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                @foreach($bulletin->grades->groupBy('competence.subject_id') as $subjectGrades)
                @php $subject = $subjectGrades->first()->competence->subject; @endphp
                <div>
                    <div class="flex items-center gap-2 bg-amber-50 px-3 py-1.5 rounded-lg mb-1">
                        <span class="text-sm font-bold text-amber-700">{{ $subject->name }}</span>
                        <span class="badge badge-outline badge-xs">{{ $subject->code }}</span>
                    </div>
                    @foreach($subjectGrades as $grade)
                    <div class="flex items-center justify-between px-3 py-2 text-sm border-b border-base-100 gap-3">
                        <span class="text-base-content/70 flex-1 truncate">
                            <span class="font-mono text-xs text-primary/70 mr-1">{{ $grade->competence->code }}</span>
                            {{ Str::limit($grade->competence->description, 50) }}
                        </span>
                        @if($grade->score !== null || (isset($gradeEdits[$grade->id]) && is_numeric($gradeEdits[$grade->id])))
                        <div class="flex items-center gap-1 shrink-0">
                            <input
                                type="number"
                                wire:model="gradeEdits.{{ $grade->id }}"
                                min="0"
                                max="{{ $grade->competence->max_score }}"
                                step="0.5"
                                class="input input-bordered input-xs w-20 text-right font-bold"
                            />
                            <span class="text-xs text-base-content/40">/{{ $grade->competence->max_score }}</span>
                        </div>
                        @else
                        <select wire:model="gradeEdits.{{ $grade->id }}" class="select select-bordered select-xs w-32 shrink-0">
                            <option value="">— Choisir</option>
                            <option value="A">Acquis</option>
                            <option value="EVA">En voie</option>
                            <option value="NA">Non acquis</option>
                        </select>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
            <div class="flex gap-3 pt-2 border-t border-base-200">
                <x-button
                    label="Enregistrer les modifications"
                    wire:click="saveGrades"
                    class="btn-warning flex-1"
                    spinner="saveGrades"
                    icon="o-check"
                    wire:confirm="Enregistrer les notes modifiées ?"
                />
                <x-button
                    label="Annuler"
                    wire:click="$set('editingGrades', false)"
                    class="btn-ghost"
                    icon="o-x-mark"
                />
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- PDF generation --}}
    @can('generatePdf', $bulletin)
    <x-button
        label="{{ $bulletin->getPdfUrl() ? '🔄 Régénérer le PDF' : '📄 Générer et Publier le Bulletin PDF' }}"
        wire:click="generatePdf"
        class="{{ $bulletin->getPdfUrl() ? 'btn-outline btn-secondary' : 'btn-primary btn-lg' }} w-full"
        spinner="generatePdf"
        icon="o-document-text"
    />
    @endcan

    {{-- View / Download PDF --}}
    @if($bulletin->getPdfUrl())
    <div class="flex gap-3">
        <a href="{{ route('bulletins.download', $bulletin->id) }}"
           class="btn btn-success flex-1"
           target="_blank" rel="noopener">
            👁️ Voir le bulletin
        </a>
        <a href="{{ route('bulletins.download', $bulletin->id) }}?download=1"
           class="btn btn-outline btn-primary flex-1">
            ⬇️ Télécharger PDF
        </a>
    </div>
    @endif
</div>
