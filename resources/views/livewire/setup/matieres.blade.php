<?php

use App\Enums\ClassroomEnum;
use App\Enums\ScaleTypeEnum;
use App\Models\Niveau;
use App\Models\Subject;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public bool   $showModal   = false;
    public ?int   $editId      = null;
    public string $search      = '';
    public string $filterNiveau = '';

    public string  $name           = '';
    public string  $code           = '';
    public ?int    $niveau_id      = null;
    public ?string $classroom_code = null;
    public int     $max_score      = 20;
    public string  $scale_type     = 'numeric';
    public int     $order          = 0;
    public array   $teacherIds     = [];

    public function updatedSearch(): void {}

    public function openModal(?int $subjectId = null): void
    {
        $this->reset(['name','code','niveau_id','classroom_code','max_score','scale_type','order','editId','teacherIds']);
        $this->max_score  = 20;
        $this->scale_type = 'numeric';
        if ($subjectId) {
            $s = Subject::with('teachers')->findOrFail($subjectId);
            $this->editId         = $s->id;
            $this->name           = $s->name;
            $this->code           = $s->code;
            $this->niveau_id      = $s->niveau_id;
            $this->classroom_code = $s->classroom_code;
            $this->max_score      = $s->max_score;
            $this->scale_type     = $s->scale_type;
            $this->order          = $s->order;
            $this->teacherIds     = $s->teachers->pluck('id')->toArray();
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name'           => 'required|string|max:100',
            'code'           => 'required|string|max:20',
            'niveau_id'      => 'required|exists:niveaux,id',
            'classroom_code' => 'nullable|string',
            'max_score'      => 'required|integer|min:1',
            'scale_type'     => 'required|in:numeric,competence',
            'order'          => 'integer|min:0',
        ]);

        if ($this->editId) {
            $subject = Subject::findOrFail($this->editId);
            $subject->update($data);
        } else {
            $subject = Subject::create($data);
        }

        $subject->teachers()->sync($this->teacherIds);

        $this->success($this->editId ? 'Matière mise à jour.' : 'Matière créée.', icon: 'o-check-circle', position: 'toast-top toast-end');
        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Subject::findOrFail($id)->delete();
        $this->warning('Matière supprimée.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    public function with(): array
    {
        $subjects = Subject::with(['niveau', 'teachers'])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%")
            )
            ->when($this->filterNiveau, fn($q) =>
                $q->whereHas('niveau', fn($q2) => $q2->where('code', $this->filterNiveau))
            )
            ->orderBy('niveau_id')->orderBy('order')
            ->get();

        return [
            'subjects'    => $subjects,
            'totalCount'  => Subject::count(),
            'niveaux'     => Niveau::all()->map(fn($n) => ['id' => $n->id, 'name' => $n->label]),
            'niveauxFilter' => array_merge(
                [['id' => '', 'name' => 'Tous les niveaux']],
                Niveau::all()->map(fn($n) => ['id' => $n->code, 'name' => $n->label])->toArray()
            ),
            'classCodes'  => collect(ClassroomEnum::cases())->map(fn($c) => ['id' => $c->value, 'name' => $c->label()])->prepend(['id' => '', 'name' => '— Toutes les classes —'])->toArray(),
            'scaleTypes'  => collect(ScaleTypeEnum::cases())->map(fn($s) => ['id' => $s->value, 'name' => $s->label()])->toArray(),
            'teachers'    => User::role('teacher')->get()->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
            'headers'     => [
                ['key' => 'niveau',     'label' => 'Niveau'],
                ['key' => 'name',       'label' => 'Matière'],
                ['key' => 'teachers',   'label' => 'Enseignant(s)'],
                ['key' => 'scale_type', 'label' => 'Barème'],
                ['key' => 'max_score',  'label' => 'Max'],
            ],
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-blue-600 to-indigo-600 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">📚</div>
                <div>
                    <h1 class="text-xl font-bold">Gestion des Matières</h1>
                    <p class="text-white/70 text-sm">Définir les matières, barèmes et enseignants assignés</p>
                </div>
            </div>
            @unless(auth()->user()->hasRole('teacher'))
            <x-button label="Nouvelle matière" wire:click="openModal" class="btn-white text-blue-700 font-semibold" icon="o-plus" />
            @endunless
        </div>
    </div>

    {{-- Stats + filters row --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="flex gap-3 flex-wrap">
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Total matières</div>
                <div class="stat-value text-2xl text-blue-600">{{ $totalCount }}</div>
            </div>
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Barème comp.</div>
                <div class="stat-value text-2xl text-amber-600">{{ $subjects->where('scale_type', 'competence')->count() }}</div>
            </div>
        </div>
        <div class="flex gap-2 flex-1 sm:max-w-md ml-auto">
            <x-input
                wire:model.live.debounce.300="search"
                placeholder="Rechercher…"
                icon="o-magnifying-glass"
                clearable
                class="flex-1"
            />
            <x-choices
                wire:model.live="filterNiveau"
                :options="$niveauxFilter"
                single clearable
                placeholder="Niveau…"
            />
        </div>
    </div>

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <x-table :headers="$headers" :rows="$subjects" striped>
                @scope('cell_niveau', $subject)
                    <span class="badge badge-outline badge-info badge-sm">{{ $subject->niveau->label }}</span>
                @endscope
                @scope('cell_name', $subject)
                    <div>
                        <p class="font-bold text-sm">{{ $subject->name }}</p>
                        <p class="text-xs text-base-content/50 font-mono">{{ $subject->code }}
                            @if($subject->classroom_code)
                                &bull; {{ $subject->classroom_code }}
                            @else
                                &bull; <span class="italic">Toutes classes</span>
                            @endif
                        </p>
                    </div>
                @endscope
                @scope('cell_teachers', $subject)
                    @forelse($subject->teachers as $t)
                        <span class="badge badge-ghost badge-sm">{{ $t->name }}</span>
                    @empty
                        <span class="text-base-content/30 text-xs italic">Non assigné</span>
                    @endforelse
                @endscope
                @scope('cell_scale_type', $subject)
                    <span class="badge badge-sm {{ $subject->scale_type === 'competence' ? 'badge-warning' : 'badge-info' }}">
                        {{ $subject->scale_type === 'competence' ? '🔤 A/EVA/NA' : '🔢 Numérique' }}
                    </span>
                @endscope
                @scope('cell_max_score', $subject)
                    <span class="font-bold text-base-content/70">/{{ $subject->max_score }}</span>
                @endscope
                @scope('actions', $subject)
                    @unless(auth()->user()->hasRole('teacher'))
                    <div class="flex gap-1">
                        <x-button wire:click="openModal({{ $subject->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                        <x-button wire:click="delete({{ $subject->id }})" class="btn-xs btn-error" icon="o-trash"
                            wire:confirm="Supprimer cette matière ?" tooltip="Supprimer" />
                    </div>
                    @endunless
                @endscope
            </x-table>
        </div>
    </div>

    {{-- Modal --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier la matière' : '📚 Nouvelle matière' }}"
             subtitle="Définissez le barème et assignez les enseignants"
             class="backdrop-blur"
             box-class="max-w-2xl">
        <x-form wire:submit="save" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom de la matière" wire:model="name" placeholder="MATHÉMATIQUES" icon="o-book-open" />
                <x-input label="Code" wire:model="code" placeholder="MATHS" icon="o-tag" hint="Code court unique" />
                <x-choices label="Niveau" wire:model="niveau_id" :options="$niveaux" single clearable icon="o-academic-cap" />
                <x-choices label="Classe spécifique" wire:model="classroom_code" :options="$classCodes" single clearable icon="o-building-library" hint="Vide = toutes classes" />
                <x-choices label="Type de barème" wire:model="scale_type" :options="$scaleTypes" single clearable icon="o-chart-bar" />
                <x-input label="Note maximale" wire:model="max_score" type="number" min="1" icon="o-star" />
                <div class="col-span-2">
                    <x-input label="Ordre d'affichage" wire:model="order" type="number" min="0" icon="o-arrows-up-down" hint="Les matières sont triées par cet ordre" />
                </div>
            </div>
            {{-- Teachers multi-select --}}
            <div class="mt-2">
                <x-choices
                    label="Enseignant(s) assigné(s)"
                    wire:model="teacherIds"
                    :options="$teachers"
                    icon="o-users"
                    hint="Sélectionnez un ou plusieurs enseignants"
                    clearable
                />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showModal = false" />
                <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer la matière' }}" class="btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
