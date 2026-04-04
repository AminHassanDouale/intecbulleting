<?php

use App\Enums\AcademicLevelEnum;
use App\Enums\ClassroomEnum;
use App\Enums\SectionEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public bool $showModal = false;
    public ?int $editId    = null;
    public string $search  = '';

    public ?string $code             = null;
    public ?string $label            = null;
    public ?string $section          = null;
    public ?int    $niveau_id        = null;
    public ?int    $academic_year_id = null;
    public ?int    $teacher_id       = null;

    public function openModal(?int $classroomId = null): void
    {
        $this->resetForm();
        if ($classroomId) {
            $c = Classroom::findOrFail($classroomId);
            $this->editId           = $c->id;
            $this->code             = $c->code;
            $this->label            = $c->label;
            $this->section          = $c->section;
            $this->niveau_id        = $c->niveau_id;
            $this->academic_year_id = $c->academic_year_id;
            $this->teacher_id       = $c->teacher_id;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code'             => 'required|string',
            'label'            => 'required|string|max:100',
            'section'          => 'required|string',
            'niveau_id'        => 'required|exists:niveaux,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'teacher_id'       => 'nullable|exists:users,id',
        ]);

        if ($this->editId) {
            Classroom::findOrFail($this->editId)->update($data);
            $this->success('Classe mise à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            Classroom::create($data);
            $this->success('Classe créée.', icon: 'o-check-circle', position: 'toast-top toast-end');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Classroom::findOrFail($id)->delete();
        $this->warning('Classe supprimée.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    protected function resetForm(): void
    {
        $this->editId           = null;
        $this->code             = null;
        $this->label            = null;
        $this->section          = null;
        $this->niveau_id        = null;
        $this->academic_year_id = AcademicYear::current()?->id;
        $this->teacher_id       = null;
    }

    public function with(): array
    {
        $classrooms = Classroom::with(['niveau', 'teacher', 'academicYear'])
            ->when($this->search, fn($q) =>
                $q->where('label', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%")
                  ->orWhere('section', 'like', "%{$this->search}%")
            )
            ->orderBy('niveau_id')->orderBy('code')
            ->get();

        return [
            'classrooms'  => $classrooms,
            'totalCount'  => Classroom::count(),
            'niveaux'     => Niveau::all()->map(fn($n) => ['id' => $n->id, 'name' => $n->label]),
            'years'       => AcademicYear::all()->map(fn($y) => ['id' => $y->id, 'name' => $y->label]),
            'teachers'    => User::role('teacher')->get()->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
            'sections'    => SectionEnum::options(),
            'classCodes'  => collect(ClassroomEnum::cases())->map(fn($c) => ['id' => $c->value, 'name' => $c->label()])->toArray(),
            'headers'     => [
                ['key' => 'niveau',        'label' => 'Niveau'],
                ['key' => 'label',         'label' => 'Classe'],
                ['key' => 'teacher',       'label' => 'Titulaire'],
                ['key' => 'academic_year', 'label' => 'Année'],
                ['key' => 'actions',       'label' => ''],
            ],
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-emerald-600 to-teal-600 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">🏛️</div>
                <div>
                    <h1 class="text-xl font-bold">Gestion des Classes</h1>
                    <p class="text-white/70 text-sm">Configuration par niveau et année scolaire</p>
                </div>
            </div>
            @unless(auth()->user()->hasRole('teacher'))
            <x-button label="Nouvelle classe" wire:click="openModal" class="btn-white text-emerald-700 font-semibold" icon="o-plus" />
            @endunless
        </div>
    </div>

    {{-- Stats + search row --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="flex gap-3 flex-wrap">
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Total classes</div>
                <div class="stat-value text-2xl text-emerald-600">{{ $totalCount }}</div>
            </div>
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Avec titulaire</div>
                <div class="stat-value text-2xl text-blue-600">{{ $classrooms->whereNotNull('teacher_id')->count() }}</div>
            </div>
        </div>
        <div class="flex-1 sm:max-w-xs ml-auto">
            <x-input
                wire:model.live.debounce.300="search"
                placeholder="Rechercher une classe…"
                icon="o-magnifying-glass"
                clearable
            />
        </div>
    </div>

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <x-table :headers="$headers" :rows="$classrooms" striped>
                @scope('cell_niveau', $classroom)
                    <span class="badge badge-outline badge-info badge-sm font-medium">{{ $classroom->niveau->label }}</span>
                @endscope
                @scope('cell_label', $classroom)
                    <div>
                        <p class="font-bold text-sm">{{ $classroom->label }}</p>
                        <p class="text-xs text-base-content/50">Section {{ $classroom->section }} &bull; {{ $classroom->code }}</p>
                    </div>
                @endscope
                @scope('cell_teacher', $classroom)
                    @if($classroom->teacher)
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center text-xs font-bold">
                                {{ strtoupper(substr($classroom->teacher->name, 0, 1)) }}
                            </div>
                            <span class="text-sm">{{ $classroom->teacher->name }}</span>
                        </div>
                    @else
                        <span class="text-base-content/30 text-xs italic">Non assigné</span>
                    @endif
                @endscope
                @scope('cell_academic_year', $classroom)
                    <span class="badge badge-ghost badge-sm">{{ $classroom->academicYear->label }}</span>
                @endscope
                @scope('actions', $classroom)
                    @unless(auth()->user()->hasRole('teacher'))
                    <div class="flex gap-1">
                        <x-button wire:click="openModal({{ $classroom->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                        <x-button wire:click="delete({{ $classroom->id }})" class="btn-xs btn-error" icon="o-trash"
                            wire:confirm="Supprimer cette classe ?" tooltip="Supprimer" />
                    </div>
                    @endunless
                @endscope
            </x-table>
        </div>
    </div>

    {{-- Modal --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier la classe' : '🏛️ Nouvelle classe' }}"
             subtitle="Renseignez les informations de la classe"
             class="backdrop-blur">
        <x-form wire:submit="save" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Niveau" wire:model="niveau_id" :options="$niveaux" icon="o-academic-cap" />
                <x-select label="Code classe" wire:model="code" :options="$classCodes" icon="o-tag" />
                <x-input label="Libellé" wire:model="label" placeholder="ex: CM1" icon="o-pencil" />
                <x-select label="Section" wire:model="section" :options="$sections" icon="o-queue-list" />
                <x-select label="Année scolaire" wire:model="academic_year_id" :options="$years" icon="o-calendar" />
                <x-select label="Enseignant(e) titulaire" wire:model="teacher_id" :options="$teachers"
                    placeholder="— Aucun —" icon="o-user" />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showModal = false" />
                <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer la classe' }}" class="btn-emerald btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
