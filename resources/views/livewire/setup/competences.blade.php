<?php

use App\Enums\PeriodEnum;
use App\Models\Competence;
use App\Models\Niveau;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    // ── Filters ────────────────────────────────────────────────────────────
    public string $search        = '';
    public string $filterNiveau  = '';
    public string $filterSubject = '';

    // ── Modal state ────────────────────────────────────────────────────────
    public bool   $showModal    = false;
    public ?int   $editId       = null;
    public string $modalNiveau  = '';   // drives subject picker in modal

    // ── Form fields ────────────────────────────────────────────────────────
    public ?int    $subject_id   = null;
    public string  $code         = '';
    public string  $description  = '';
    public         $max_score    = null;
    public ?string $period       = null;
    public int     $order        = 0;

    // ── Cascading resets ───────────────────────────────────────────────────
    public function updatedFilterNiveau(): void
    {
        $this->filterSubject = '';
    }

    public function updatedModalNiveau(): void
    {
        $this->subject_id = null;
    }

    public function openModal(?int $competenceId = null): void
    {
        $this->reset(['code','description','max_score','period','order','editId','subject_id']);
        $this->modalNiveau = $this->filterNiveau; // inherit current niveau filter

        if ($competenceId) {
            $c = Competence::with('subject.niveau')->findOrFail($competenceId);
            $this->editId      = $c->id;
            $this->subject_id  = $c->subject_id;
            $this->modalNiveau = (string) ($c->subject->niveau_id ?? '');
            $this->code        = $c->code;
            $this->description = $c->description;
            $this->max_score   = $c->max_score;
            $this->period      = $c->period;
            $this->order       = $c->order;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'code'        => 'required|string|max:20',
            'description' => 'required|string',
            'max_score'   => 'nullable|numeric|min:1',
            'period'      => 'nullable|in:T1,T2,T3',
            'order'       => 'integer|min:0',
        ]);

        $data = [
            'subject_id'  => $this->subject_id,
            'code'        => $this->code,
            'description' => $this->description,
            'max_score'   => $this->max_score ?: null,
            'period'      => $this->period ?: null,
            'order'       => $this->order,
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

    public function with(): array
    {
        // ── Subjects for the list filter (driven by filterNiveau) ──────────
        $subjectsForFilter = Subject::with('niveau')
            ->when($this->filterNiveau, fn($q) => $q->where('niveau_id', $this->filterNiveau))
            ->orderBy('name')->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]);

        // ── Subjects for the modal picker (driven by modalNiveau) ──────────
        $subjectsForModal = Subject::with('niveau')
            ->when($this->modalNiveau, fn($q) => $q->where('niveau_id', $this->modalNiveau))
            ->orderBy('name')->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]);

        // ── Competences list ───────────────────────────────────────────────
        $competences = Competence::with('subject.niveau')
            ->when($this->search, fn($q) =>
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
            )
            ->when($this->filterNiveau, fn($q) =>
                $q->whereHas('subject', fn($q2) => $q2->where('niveau_id', $this->filterNiveau))
            )
            ->when($this->filterSubject, fn($q) => $q->where('subject_id', $this->filterSubject))
            ->orderBy('subject_id')->orderBy('order')
            ->get();

        $niveaux = Niveau::orderBy('order')->get()
            ->map(fn($n) => ['id' => $n->id, 'name' => $n->label]);

        return [
            'competences'      => $competences,
            'totalCount'       => Competence::count(),
            'niveaux'          => $niveaux,
            'niveauxFilter'    => array_merge(
                [['id' => '', 'name' => 'Tous les niveaux']],
                $niveaux->toArray()
            ),
            'subjectsForFilter' => array_merge(
                [['id' => '', 'name' => $this->filterNiveau ? 'Toutes les matières' : '— Choisir un niveau —']],
                $subjectsForFilter->toArray()
            ),
            'subjectsForModal' => $subjectsForModal->toArray(),
            'periods'          => array_merge([['id' => '', 'name' => '— Tous trimestres —']], PeriodEnum::options()),
            'niveauxModal'     => array_merge(
                [['id' => '', 'name' => '— Sélectionner un niveau —']],
                $niveaux->toArray()
            ),
            'headers' => [
                ['key' => 'subject',     'label' => 'Matière'],
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
                    <p class="text-white/70 text-sm">Définir les compétences évaluées par matière et niveau</p>
                </div>
            </div>
            @unless(auth()->user()->hasRole('teacher'))
            <x-button label="Nouvelle compétence" wire:click="openModal" class="btn-white text-violet-700 font-semibold" icon="o-plus" />
            @endunless
        </div>
    </div>

    {{-- Stats + cascading filters --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">

        {{-- Stats --}}
        <div class="flex gap-3 flex-wrap shrink-0">
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Total compétences</div>
                <div class="stat-value text-2xl text-violet-600">{{ $totalCount }}</div>
            </div>
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Affichées</div>
                <div class="stat-value text-2xl text-base-content/60">{{ $competences->count() }}</div>
            </div>
        </div>

        {{-- Cascading filters: search → niveau → subject --}}
        <div class="flex gap-2 flex-1 flex-wrap min-w-0">
            <x-input
                wire:model.live.debounce.300="search"
                placeholder="Code ou description…"
                icon="o-magnifying-glass"
                clearable
                class="flex-1 min-w-36" />

            {{-- 1. Niveau filter --}}
            <x-choices
                wire:key="filter-niveau"
                wire:model.live="filterNiveau"
                :options="$niveauxFilter"
                single clearable
                placeholder="Niveau…"
                class="min-w-36" />

            {{-- 2. Subject filter — resets when niveau changes --}}
            <x-choices
                wire:key="filter-subject-{{ $filterNiveau }}"
                wire:model.live="filterSubject"
                :options="$subjectsForFilter"
                single clearable
                :disabled="!$filterNiveau"
                placeholder="{{ $filterNiveau ? 'Matière…' : '— Choisir un niveau —' }}"
                class="min-w-44" />
        </div>
    </div>

    {{-- Active filter breadcrumb --}}
    @if($filterNiveau || $filterSubject)
    <div class="flex items-center gap-2 text-sm text-base-content/60">
        <span>Filtre :</span>
        @if($filterNiveau)
            <span class="badge badge-outline badge-sm">
                {{ collect($niveauxFilter)->firstWhere('id', $filterNiveau)['name'] ?? $filterNiveau }}
            </span>
        @endif
        @if($filterSubject)
            <span class="text-base-content/40">›</span>
            <span class="badge badge-outline badge-violet badge-sm">
                {{ collect($subjectsForFilter)->firstWhere('id', (int) $filterSubject)['name'] ?? '' }}
            </span>
        @endif
        <button wire:click="$set('filterNiveau', ''); $set('filterSubject', '')"
            class="btn btn-xs btn-ghost text-error">Effacer</button>
    </div>
    @endif

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
                @scope('cell_code', $competence)
                    <span class="font-mono font-bold text-violet-600 bg-violet-50 px-2 py-0.5 rounded text-sm">{{ $competence->code }}</span>
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
                        <span class="badge badge-ghost badge-sm">{{ \App\Enums\PeriodEnum::from($competence->period)->label() }}</span>
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

    {{-- Modal --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier la compétence' : '🎯 Nouvelle compétence' }}"
             subtitle="Associez la compétence à une matière"
             class="backdrop-blur">
        <x-form wire:submit="save" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />

            {{-- Step 1: Niveau selector (drives subject picker) --}}
            <x-choices
                label="Niveau"
                wire:key="modal-niveau-{{ $modalNiveau }}"
                wire:model.live="modalNiveau"
                :options="$niveauxModal"
                single clearable
                icon="o-academic-cap"
                hint="Sélectionnez d'abord le niveau pour filtrer les matières" />

            {{-- Step 2: Subject picker (filtered by modalNiveau) --}}
            <div class="mt-3">
                <x-choices
                    label="Matière *"
                    wire:key="modal-subject-{{ $modalNiveau }}"
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
                    placeholder="Résoudre une situation-problème faisant intervenir…"
                    hint="Décrivez clairement la compétence attendue" />
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <x-choices label="Période" wire:model="period" :options="$periods" single clearable icon="o-calendar" />
                <x-input label="Ordre d'affichage" wire:model="order" type="number" min="0" icon="o-arrows-up-down" />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showModal = false" />
                <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer la compétence' }}" class="btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
