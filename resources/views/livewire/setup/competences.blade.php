<?php

use App\Enums\PeriodEnum;
use App\Models\Classroom;
use App\Models\Competence;
use App\Models\Niveau;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    // ── Filters ────────────────────────────────────────────────────────────
    public string $search         = '';
    public string $filterNiveau   = '';
    public string $filterSection  = '';
    public string $filterSubject  = '';
    public string $filterPeriod   = '';

    // ── Modal state ────────────────────────────────────────────────────────
    public bool   $showModal    = false;
    public ?int   $editId       = null;
    public string $modalNiveau  = '';
    public string $modalSection = '';

    // ── Form fields ────────────────────────────────────────────────────────
    public ?int    $subject_id   = null;
    public string  $code         = '';
    public string  $description  = '';
    public         $max_score    = null;
    public ?string $period       = null;
    public ?string $section_code = null;
    public int     $order        = 0;

    // ── Cascading resets ───────────────────────────────────────────────────
    public function updatedFilterNiveau(): void
    {
        $this->filterSection = '';
        $this->filterSubject = '';
    }

    public function updatedFilterSection(): void
    {
        $this->filterSubject = '';
    }

    public function updatedModalNiveau(): void
    {
        $this->modalSection  = '';
        $this->subject_id    = null;
        $this->section_code  = null;
    }

    public function updatedModalSection(): void
    {
        $this->subject_id   = null;
        $this->section_code = $this->modalSection ?: null;
    }

    public function openModal(?int $competenceId = null): void
    {
        $this->reset(['code','description','max_score','period','order','editId','subject_id','section_code']);
        $this->modalNiveau  = $this->filterNiveau ?: '';
        $this->modalSection = $this->filterSection ?: '';
        $this->section_code = $this->filterSection ?: null;

        if ($competenceId) {
            $c = Competence::with('subject.niveau')->findOrFail($competenceId);
            $this->editId       = $c->id;
            $this->subject_id   = $c->subject_id;
            $this->modalNiveau  = (string) ($c->subject->niveau_id ?? '');
            $this->modalSection = (string) ($c->section_code ?? $c->subject->section_code ?? '');
            $this->section_code = $c->section_code;
            $this->code         = $c->code;
            $this->description  = $c->description;
            $this->max_score    = $c->max_score;
            $this->period       = $c->period;
            $this->order        = $c->order;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'code'         => 'required|string|max:20',
            'description'  => 'required|string',
            'max_score'    => 'nullable|numeric|min:1',
            'period'       => 'nullable|in:T1,T2,T3',
            'section_code' => 'nullable|string|max:10',
            'order'        => 'integer|min:0',
        ]);

        $data = [
            'subject_id'   => $this->subject_id,
            'code'         => $this->code,
            'description'  => $this->description,
            'max_score'    => $this->max_score ?: null,
            'period'       => $this->period ?: null,
            'section_code' => $this->section_code ?: null,
            'order'        => $this->order,
        ];

        if ($this->editId) {
            Competence::findOrFail($this->editId)->update($data);
            $this->success('Compétence mise à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            Competence::create($data);
            $this->success('Compétence créée.', icon: 'o-check-circle', position: 'toast-top toast-end');
        }
        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Competence::findOrFail($id)->delete();
        $this->warning('Compétence supprimée.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    public function clearFilters(): void
    {
        $this->filterNiveau  = '';
        $this->filterSection = '';
        $this->filterSubject = '';
        $this->filterPeriod  = '';
        $this->search        = '';
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /** Distinct section codes for a niveau (from Classrooms). */
    private function sectionsForNiveau(?int $niveauId): array
    {
        if (! $niveauId) return [];

        return Classroom::whereHas('niveau', fn($q) => $q->where('id', $niveauId))
            ->whereNotNull('code')
            ->orderBy('code')
            ->pluck('code')
            ->unique()
            ->values()
            ->map(fn($c) => ['id' => $c, 'name' => $c])
            ->toArray();
    }

    /** Subjects matching niveau + optional section (shared subjects always included). */
    private function subjectsFor(?int $niveauId, ?string $section): array
    {
        if (! $niveauId) return [];

        return Subject::where('niveau_id', $niveauId)
            ->when($section, fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('section_code', $section)->orWhereNull('section_code')
                )
            )
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->name . ($s->section_code ? " ({$s->section_code})" : ' [tous]'),
            ])
            ->toArray();
    }

    public function with(): array
    {
        $niveaux = Niveau::orderBy('order')->get()
            ->map(fn($n) => ['id' => $n->id, 'name' => $n->label]);

        // ── Filter cascade ─────────────────────────────────────────────────
        $sectionsForFilter = $this->sectionsForNiveau(
            $this->filterNiveau ? (int) $this->filterNiveau : null
        );
        $subjectsForFilter = $this->subjectsFor(
            $this->filterNiveau ? (int) $this->filterNiveau : null,
            $this->filterSection ?: null
        );

        // ── Modal cascade ──────────────────────────────────────────────────
        $sectionsForModal = $this->sectionsForNiveau(
            $this->modalNiveau ? (int) $this->modalNiveau : null
        );
        $subjectsForModal = $this->subjectsFor(
            $this->modalNiveau ? (int) $this->modalNiveau : null,
            $this->modalSection ?: null
        );

        // ── Competences list ───────────────────────────────────────────────
        $competences = Competence::with('subject.niveau')
            ->when($this->search, fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('code', 'like', "%{$this->search}%")
                       ->orWhere('description', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterNiveau, fn($q) =>
                $q->whereHas('subject', fn($q2) => $q2->where('niveau_id', $this->filterNiveau))
            )
            ->when($this->filterSection, fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('section_code', $this->filterSection)
                       ->orWhereHas('subject', fn($q3) =>
                           $q3->where('section_code', $this->filterSection)
                       )
                )
            )
            ->when($this->filterSubject, fn($q) => $q->where('subject_id', $this->filterSubject))
            ->when($this->filterPeriod, fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('period', $this->filterPeriod)->orWhereNull('period')
                )
            )
            ->orderBy('subject_id')->orderBy('order')
            ->get();

        return [
            'competences'   => $competences,
            'totalCount'    => Competence::count(),
            'niveaux'       => $niveaux,
            'niveauxFilter' => array_merge(
                [['id' => '', 'name' => 'Tous les niveaux']],
                $niveaux->toArray()
            ),
            'niveauxModal' => array_merge(
                [['id' => '', 'name' => '— Sélectionner un niveau —']],
                $niveaux->toArray()
            ),
            'sectionsForFilter' => array_merge(
                [['id' => '', 'name' => $this->filterNiveau ? 'Toutes les sections' : '— Choisir un niveau —']],
                $sectionsForFilter
            ),
            'sectionsForModal' => array_merge(
                [['id' => '', 'name' => '— Toutes les sections —']],
                $sectionsForModal
            ),
            'subjectsForFilter' => array_merge(
                [['id' => '', 'name' => $this->filterNiveau ? 'Toutes les matières' : '— Choisir un niveau —']],
                $subjectsForFilter
            ),
            'subjectsForModal' => $subjectsForModal,
            'periods'     => PeriodEnum::options(),
            'periodsFilter' => array_merge(
                [['id' => '', 'name' => 'Toutes les périodes']],
                PeriodEnum::options()
            ),
            'headers' => [
                ['key' => 'subject',     'label' => 'Matière'],
                ['key' => 'section',     'label' => 'Section'],
                ['key' => 'code',        'label' => 'Code'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'max_score',   'label' => 'Barème'],
                ['key' => 'period',      'label' => 'Période'],
            ],
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-violet-600 to-purple-700 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">🎯</div>
                <div>
                    <h1 class="text-xl font-bold">Gestion des Compétences</h1>
                    <p class="text-white/70 text-sm">Compétences évaluées par matière, section et niveau</p>
                </div>
            </div>
            @unless(auth()->user()->hasRole('teacher'))
            <x-button label="Nouvelle compétence" wire:click="openModal" class="btn-white text-violet-700 font-semibold" icon="o-plus" />
            @endunless
        </div>
    </div>

    {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body py-3 px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

                {{-- Search --}}
                <x-input
                    wire:model.live.debounce.300="search"
                    placeholder="Code ou description…"
                    icon="o-magnifying-glass"
                    clearable />

                {{-- 1. Niveau --}}
                <x-choices
                    wire:key="filter-niveau"
                    wire:model.live="filterNiveau"
                    :options="$niveauxFilter"
                    single clearable
                    placeholder="Niveau…" />

                {{-- 2. Section — unlocks after niveau --}}
                <x-choices
                    wire:key="filter-section-{{ $filterNiveau }}"
                    wire:model.live="filterSection"
                    :options="$sectionsForFilter"
                    single clearable
                    :disabled="!$filterNiveau"
                    placeholder="{{ $filterNiveau ? 'Section…' : '— Niveau d\'abord —' }}" />

                {{-- 3. Subject — unlocks after niveau --}}
                <x-choices
                    wire:key="filter-subject-{{ $filterNiveau }}-{{ $filterSection }}"
                    wire:model.live="filterSubject"
                    :options="$subjectsForFilter"
                    single clearable
                    :disabled="!$filterNiveau"
                    placeholder="{{ $filterNiveau ? 'Matière…' : '— Niveau d\'abord —' }}" />

                {{-- 4. Period --}}
                <x-choices
                    wire:key="filter-period"
                    wire:model.live="filterPeriod"
                    :options="$periodsFilter"
                    single clearable
                    placeholder="Période…" />

            </div>
        </div>
    </div>

    {{-- Active filter breadcrumb --}}
    @if($filterNiveau || $filterSection || $filterSubject || $filterPeriod || $search)
    <div class="flex items-center gap-2 text-sm text-base-content/60 flex-wrap">
        <span>Filtre :</span>
        @if($search)
            <span class="badge badge-ghost badge-sm">🔍 {{ $search }}</span>
        @endif
        @if($filterNiveau)
            <span class="badge badge-outline badge-sm">
                {{ collect($niveauxFilter)->firstWhere('id', $filterNiveau)['name'] ?? $filterNiveau }}
            </span>
        @endif
        @if($filterSection)
            <span class="text-base-content/40">›</span>
            <span class="badge badge-info badge-sm">{{ $filterSection }}</span>
        @endif
        @if($filterSubject)
            <span class="text-base-content/40">›</span>
            <span class="badge badge-primary badge-sm">
                {{ collect($subjectsForFilter)->firstWhere('id', (int) $filterSubject)['name'] ?? '' }}
            </span>
        @endif
        @if($filterPeriod)
            <span class="text-base-content/40">›</span>
            <span class="badge badge-warning badge-sm">{{ $filterPeriod }}</span>
        @endif
        <button wire:click="clearFilters" class="btn btn-xs btn-ghost text-error">Effacer tout</button>
    </div>
    @endif

    {{-- Stats --}}
    <div class="flex gap-3 flex-wrap">
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
            <div class="stat-title text-xs">Total compétences</div>
            <div class="stat-value text-2xl text-violet-600">{{ $totalCount }}</div>
        </div>
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
            <div class="stat-title text-xs">Affichées</div>
            <div class="stat-value text-2xl text-base-content/60">{{ $competences->count() }}</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <x-table :headers="$headers" :rows="$competences" striped>

                @scope('cell_subject', $competence)
                    <div>
                        <p class="text-xs text-base-content/40">{{ $competence->subject->niveau->label }}</p>
                        <span class="badge badge-outline badge-sm font-medium">{{ $competence->subject->name }}</span>
                    </div>
                @endscope

                @scope('cell_section', $competence)
                    @php
                        $sec = $competence->section_code ?? $competence->subject->section_code;
                    @endphp
                    @if($sec)
                        <span class="badge badge-primary badge-sm font-mono">{{ $sec }}</span>
                    @else
                        <span class="text-base-content/30 text-xs italic">Toutes</span>
                    @endif
                @endscope

                @scope('cell_code', $competence)
                    <span class="font-mono font-bold text-violet-600 bg-violet-50 px-2 py-0.5 rounded text-sm">
                        {{ $competence->code }}
                    </span>
                @endscope

                @scope('cell_description', $competence)
                    <span class="text-sm">{{ Str::limit($competence->description, 75) }}</span>
                @endscope

                @scope('cell_max_score', $competence)
                    @if($competence->max_score)
                        <span class="badge badge-info badge-sm font-bold">/{{ $competence->max_score }}</span>
                    @else
                        <span class="badge badge-warning badge-sm">A/EVA/NA</span>
                    @endif
                @endscope

                @scope('cell_period', $competence)
                    @if($competence->period)
                        <span class="badge badge-ghost badge-sm">
                            {{ \App\Enums\PeriodEnum::from($competence->period)->label() }}
                        </span>
                    @else
                        <span class="text-base-content/30 text-xs italic">Tous</span>
                    @endif
                @endscope

                @scope('actions', $competence)
                    @unless(auth()->user()->hasRole('teacher'))
                    <div class="flex gap-1">
                        <x-button wire:click="openModal({{ $competence->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                        <x-button wire:click="delete({{ $competence->id }})" class="btn-xs btn-error" icon="o-trash"
                            wire:confirm="Supprimer cette compétence ?" tooltip="Supprimer" />
                    </div>
                    @endunless
                @endscope

            </x-table>
        </div>
    </div>

    {{-- ── Modal ──────────────────────────────────────────────────────────── --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier la compétence' : '🎯 Nouvelle compétence' }}"
             subtitle="Niveau → Section → Matière (cascade dépendant)"
             class="backdrop-blur"
             box-class="max-w-2xl">
        <x-form wire:submit="save" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />

            <div class="grid grid-cols-2 gap-3">
                {{-- Step 1 — Niveau --}}
                <x-choices
                    label="Niveau *"
                    wire:key="modal-niveau-{{ $modalNiveau }}"
                    wire:model.live="modalNiveau"
                    :options="$niveauxModal"
                    single clearable
                    icon="o-academic-cap" />

                {{-- Step 2 — Section --}}
                <x-choices
                    label="Section"
                    wire:key="modal-section-{{ $modalNiveau }}"
                    wire:model.live="modalSection"
                    :options="$sectionsForModal"
                    single clearable
                    :disabled="!$modalNiveau"
                    icon="o-tag"
                    placeholder="{{ $modalNiveau ? 'Toutes' : '— Niveau d\'abord —' }}"
                    hint="Vide = s'applique à toutes les sections" />
            </div>

            {{-- Step 3 — Matière --}}
            <div class="mt-3">
                <x-choices
                    label="Matière *"
                    wire:key="modal-subject-{{ $modalNiveau }}-{{ $modalSection }}"
                    wire:model="subject_id"
                    :options="$subjectsForModal"
                    single clearable
                    icon="o-book-open"
                    :disabled="!$modalNiveau"
                    placeholder="{{ $modalNiveau ? 'Sélectionner une matière…' : '— Choisir un niveau d\'abord —' }}" />
            </div>

            <div class="grid grid-cols-2 gap-3 mt-3">
                <x-input label="Code *" wire:model="code" placeholder="CB1" icon="o-tag"
                    hint="Code court de la compétence" />
                <x-input label="Note max (vide = A/EVA/NA)" wire:model="max_score"
                    type="number" min="1" icon="o-star"
                    hint="Laisser vide pour préscolaire" />
            </div>

            <div class="mt-3">
                <x-textarea label="Description *" wire:model="description" rows="3"
                    placeholder="Résoudre une situation-problème faisant intervenir…" />
            </div>

            <div class="grid grid-cols-2 gap-3 mt-3">
                <x-choices label="Période" wire:model="period" :options="$periods" single clearable icon="o-calendar"
                    hint="Vide = s'applique à tous les trimestres" />
                <x-input label="Ordre d'affichage" wire:model="order" type="number" min="0" icon="o-arrows-up-down" />
            </div>

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showModal = false" />
                <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer la compétence' }}"
                    class="btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>

</div>
