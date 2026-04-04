<?php

use App\Enums\PeriodEnum;
use App\Models\Competence;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public bool   $showModal     = false;
    public ?int   $editId        = null;
    public string $search        = '';
    public string $filterSubject = '';

    public ?int    $subject_id  = null;
    public string  $code        = '';
    public string  $description = '';
    public ?int    $max_score   = 20;
    public ?string $period      = null;
    public int     $order       = 0;

    public function openModal(?int $competenceId = null): void
    {
        $this->reset(['code','description','max_score','period','order','editId']);
        $this->max_score  = 20;
        $this->subject_id = null;
        if ($competenceId) {
            $c = Competence::findOrFail($competenceId);
            $this->editId      = $c->id;
            $this->subject_id  = $c->subject_id;
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
        $data = $this->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'code'        => 'required|string|max:20',
            'description' => 'required|string',
            'max_score'   => 'nullable|integer|min:1',
            'period'      => 'nullable|in:T1,T2,T3',
            'order'       => 'integer|min:0',
        ]);

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
        $competences = Competence::with('subject.niveau')
            ->when($this->search, fn($q) =>
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
            )
            ->when($this->filterSubject, fn($q) => $q->where('subject_id', $this->filterSubject))
            ->orderBy('subject_id')->orderBy('order')
            ->get();

        return [
            'competences'   => $competences,
            'totalCount'    => Competence::count(),
            'subjects'      => Subject::with('niveau')->get()->map(fn($s) => ['id' => $s->id, 'name' => $s->niveau->label . ' — ' . $s->name]),
            'subjectsFilter' => array_merge(
                [['id' => '', 'name' => 'Toutes les matières']],
                Subject::with('niveau')->get()->map(fn($s) => ['id' => $s->id, 'name' => $s->niveau->label . ' — ' . $s->name])->toArray()
            ),
            'periods' => array_merge([['id' => '', 'name' => '— Tous trimestres —']], PeriodEnum::options()),
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
                    <p class="text-white/70 text-sm">Définir les compétences évaluées par matière</p>
                </div>
            </div>
            @unless(auth()->user()->hasRole('teacher'))
            <x-button label="Nouvelle compétence" wire:click="openModal" class="btn-white text-violet-700 font-semibold" icon="o-plus" />
            @endunless
        </div>
    </div>

    {{-- Stats + filters --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="flex gap-3 flex-wrap">
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Total compétences</div>
                <div class="stat-value text-2xl text-violet-600">{{ $totalCount }}</div>
            </div>
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">A/EVA/NA</div>
                <div class="stat-value text-2xl text-amber-600">{{ $competences->whereNull('max_score')->count() }}</div>
            </div>
        </div>
        <div class="flex gap-2 flex-1 sm:max-w-md ml-auto">
            <x-input
                wire:model.live.debounce.300="search"
                placeholder="Code ou description…"
                icon="o-magnifying-glass"
                clearable
                class="flex-1"
            />
            <x-select
                wire:model.live="filterSubject"
                :options="$subjectsFilter"
                class="select-sm"
            />
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
            <div class="space-y-3">
                <x-select label="Matière" wire:model="subject_id" :options="$subjects" icon="o-book-open" />
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Code" wire:model="code" placeholder="CB1" icon="o-tag"
                        hint="Code court de la compétence" />
                    <x-input label="Note max (vide = A/EVA/NA)" wire:model="max_score"
                        type="number" min="1" icon="o-star"
                        hint="Laisser vide pour préscolaire" />
                </div>
                <x-textarea label="Description de la compétence" wire:model="description" rows="3"
                    placeholder="Résoudre une situation problème faisant intervenir…"
                    hint="Décrivez clairement la compétence attendue" />
                <div class="grid grid-cols-2 gap-3">
                    <x-select label="Période spécifique" wire:model="period" :options="$periods" icon="o-calendar" />
                    <x-input label="Ordre d'affichage" wire:model="order" type="number" min="0" icon="o-arrows-up-down" />
                </div>
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showModal = false" />
                <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer la compétence' }}" class="btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
