<?php

use App\Actions\Student\CreateStudentAction;
use App\Exports\StudentsExport;
use App\Exports\StudentsTemplateExport;
use App\Imports\StudentsImport;
use App\Models\Classroom;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast, WithPagination, WithFileUploads;

    public bool   $showModal    = false;
    public ?int   $editId       = null;
    public string $search       = '';
    public ?string $filterGender = null;
    public ?string $filterClass  = null;
    public bool    $showFilters  = false;

    public string  $matricule    = '';
    public string  $full_name    = '';
    public string  $birth_date   = '';
    public string  $gender       = 'M';
    public ?int    $classroom_id = null;
    public         $importFile   = null;

    public function updatedSearch(): void     { $this->resetPage(); }
    public function updatedFilterGender(): void { $this->resetPage(); }
    public function updatedFilterClass(): void  { $this->resetPage(); }

    public function openModal(?int $studentId = null): void
    {
        $this->reset(['matricule','full_name','birth_date','gender','classroom_id','editId']);
        $this->gender = 'M';
        if ($studentId) {
            $s = Student::findOrFail($studentId);
            $this->editId       = $s->id;
            $this->matricule    = $s->matricule;
            $this->full_name    = $s->full_name;
            $this->birth_date   = $s->birth_date->format('Y-m-d');
            $this->gender       = $s->gender;
            $this->classroom_id = $s->classroom_id;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'matricule'    => 'nullable|string|unique:students,matricule,' . ($this->editId ?? 'NULL'),
            'full_name'    => 'required|string|max:200',
            'birth_date'   => 'required|date',
            'gender'       => 'required|in:M,F',
            'classroom_id' => 'required|exists:classrooms,id',
        ]);

        if ($this->editId) {
            Student::findOrFail($this->editId)->update($data);
            $this->success('Élève mis à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            app(CreateStudentAction::class)->execute($data);
            $this->success('Élève créé.', icon: 'o-academic-cap', position: 'toast-top toast-end');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Student::findOrFail($id)->delete();
        $this->warning('Élève supprimé.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    #[Renderless]
    public function downloadTemplate(): mixed
    {
        return Excel::download(
            new StudentsTemplateExport(),
            'modele_import_eleves.xlsx'
        );
    }

    #[Renderless]
    public function exportStudents(): mixed
    {
        return Excel::download(
            new StudentsExport($this->filterClass ?: null),
            'eleves_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function importStudents(): void
    {
        if (! $this->importFile) {
            $this->error('Aucun fichier sélectionné.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        $importer = new StudentsImport();

        try {
            $stored   = $this->importFile->store('student-imports', 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($stored);
            Excel::import($importer, $fullPath);
            \Illuminate\Support\Facades\Storage::disk('local')->delete($stored);
            $this->importFile = null;

            $stats   = $importer->getStats();
            $message = "{$stats['imported']} ajouté(s), {$stats['updated']} mis à jour, {$stats['skipped']} ignoré(s).";

            if (! empty($stats['errors'])) {
                $this->warning("Import terminé. {$message}", implode(' | ', array_slice($stats['errors'], 0, 3)), icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } else {
                $this->success("Import réussi ! {$message}", icon: 'o-arrow-up-tray', position: 'toast-top toast-end');
            }
        } catch (\Throwable $e) {
            $this->error('Erreur import', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
        }
    }

    public function with(): array
    {
        $students = Student::with(['classroom.niveau'])
            ->when($this->search, fn($q) =>
                $q->where('full_name', 'like', "%{$this->search}%")
                  ->orWhere('matricule', 'like', "%{$this->search}%")
            )
            ->when($this->filterGender, fn($q) => $q->where('gender', $this->filterGender))
            ->when($this->filterClass,  fn($q) => $q->where('classroom_id', $this->filterClass))
            ->orderBy('full_name')
            ->paginate(25);

        $totalCount  = Student::count();
        $garcons     = Student::where('gender', 'M')->count();
        $filles      = Student::where('gender', 'F')->count();

        return [
            'students'    => $students,
            'totalCount'  => $totalCount,
            'garcons'     => $garcons,
            'filles'      => $filles,
            'classrooms'  => Classroom::all()->map(fn($c) => ['id' => $c->id, 'name' => $c->label . ' ' . $c->section]),
            'classFilter' => array_merge(
                [['id' => '', 'name' => 'Toutes les classes']],
                Classroom::all()->map(fn($c) => ['id' => $c->id, 'name' => $c->label . ' ' . $c->section])->toArray()
            ),
            'genders'     => [['id' => 'M', 'name' => 'Masculin'], ['id' => 'F', 'name' => 'Féminin']],
            'genderFilter'=> [
                ['id' => '',  'name' => 'Tous'],
                ['id' => 'M', 'name' => '👦 Garçons'],
                ['id' => 'F', 'name' => '👧 Filles'],
            ],
            'headers'     => [
                ['key' => 'full_name',  'label' => 'Élève'],
                ['key' => 'birth_date', 'label' => 'Naissance'],
                ['key' => 'age',        'label' => 'Âge'],
                ['key' => 'gender',     'label' => 'Genre'],
                ['key' => 'classroom',  'label' => 'Classe'],
            ],
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-amber-500 to-orange-500 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">👥</div>
                <div>
                    <h1 class="text-xl font-bold">Gestion des Élèves</h1>
                    <p class="text-white/70 text-sm">Inscrire et gérer les élèves par classe</p>
                </div>
            </div>
            @unless(auth()->user()->hasRole('teacher'))
            <x-button label="Nouvel élève" wire:click="openModal" class="btn-white text-amber-700 font-semibold" icon="o-plus" />
            @endunless
        </div>
    </div>

    {{-- Stats + search + filter button --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="flex gap-3 flex-wrap">
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Total élèves</div>
                <div class="stat-value text-2xl text-amber-600">{{ $totalCount }}</div>
            </div>
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Garçons</div>
                <div class="stat-value text-2xl text-blue-500">{{ $garcons }}</div>
            </div>
            <div class="stat bg-base-100 shadow rounded-xl py-2 px-4 min-w-0">
                <div class="stat-title text-xs">Filles</div>
                <div class="stat-value text-2xl text-pink-500">{{ $filles }}</div>
            </div>
        </div>
        <div class="flex gap-2 flex-1 ml-auto">
            <x-input
                wire:model.live.debounce.300="search"
                placeholder="Nom, matricule…"
                icon="o-magnifying-glass"
                clearable
                class="flex-1"
            />
            <div class="relative">
                <x-button icon="o-funnel" @click="$wire.showFilters = true" class="btn-outline" tooltip="Filtres" />
                @php $activeFilters = ($filterGender ? 1 : 0) + ($filterClass ? 1 : 0); @endphp
                @if($activeFilters)
                <span class="absolute -top-1.5 -right-1.5 badge badge-warning badge-xs font-bold">{{ $activeFilters }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Filter drawer --}}
    <x-filter-drawer model="showFilters" title="Filtres" subtitle="Affiner la liste des élèves">
        <x-choices label="Genre" wire:model.live="filterGender" :options="$genderFilter" single clearable icon="o-user-circle" placeholder="Tous" />
        <x-choices label="Classe" wire:model.live="filterClass" :options="$classFilter" single clearable icon="o-building-library" placeholder="Toutes les classes" />
        <x-slot:actions>
            <x-button label="Réinitialiser" wire:click="$set('filterGender', null); $set('filterClass', null)" icon="o-arrow-path" />
            <x-button label="Fermer" @click="$wire.showFilters = false" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-filter-drawer>

    {{-- Export / Import toolbar --}}
    <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body py-3 px-4 space-y-3">

            {{-- Row 1: template + export --}}
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-semibold text-xs text-base-content/50 w-20 shrink-0">MODÈLE</p>
                <x-button
                    label="📄 Télécharger le modèle"
                    wire:click="downloadTemplate"
                    class="btn-outline btn-sm btn-success"
                    spinner="downloadTemplate"
                    icon="o-document-arrow-down"
                    tooltip="Télécharger le fichier Excel modèle à remplir pour l'import"
                />
                <span class="text-xs text-base-content/40 hidden sm:inline">
                    — Le fichier contient les colonnes requises avec des exemples + la liste des classes disponibles
                </span>
            </div>

            <div class="divider my-0"></div>

            {{-- Row 2: export current list --}}
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-semibold text-xs text-base-content/50 w-20 shrink-0">EXPORT</p>
                <x-button
                    label="⬇ Exporter la liste"
                    wire:click="exportStudents"
                    class="btn-outline btn-sm"
                    spinner="exportStudents"
                    icon="o-arrow-down-tray"
                    tooltip="Exporter les élèves affichés (selon filtres actifs)"
                />
                <span class="text-xs text-base-content/40 hidden sm:inline">— Exporte selon les filtres actifs (classe, genre)</span>
            </div>

            <div class="divider my-0"></div>

            {{-- Row 3: import --}}
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-semibold text-xs text-base-content/50 w-20 shrink-0">IMPORT</p>
                <div class="flex items-end gap-2">
                    <div>
                        <label class="label-text text-xs mb-1 block">Fichier .xlsx</label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls"
                               class="file-input file-input-sm file-input-bordered w-52" />
                    </div>
                    <x-button
                        label="⬆ Importer"
                        wire:click="importStudents"
                        class="btn-primary btn-sm"
                        spinner="importStudents"
                        icon="o-arrow-up-tray"
                        tooltip="Importer depuis un fichier Excel (format modèle)"
                    />
                </div>
                <span class="text-xs text-base-content/40 hidden sm:inline">— Utiliser le modèle ci-dessus. Les élèves existants (même matricule) seront mis à jour.</span>
            </div>

        </div>
    </div>

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <x-table :headers="$headers" :rows="$students" striped with-pagination>
                @scope('cell_full_name', $student)
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
                            {{ $student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                            {{ strtoupper(substr($student->full_name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-semibold text-sm">{{ $student->full_name }}</p>
                            <p class="text-xs text-base-content/40 font-mono">{{ $student->matricule }}</p>
                        </div>
                    </div>
                @endscope
                @scope('cell_birth_date', $student)
                    <span class="text-sm">{{ $student->birth_date->format('d/m/Y') }}</span>
                @endscope
                @scope('cell_age', $student)
                    <span class="badge badge-ghost badge-sm font-semibold">
                        {{ $student->birth_date->age }} ans
                    </span>
                @endscope
                @scope('cell_gender', $student)
                    <span class="badge {{ $student->gender === 'M' ? 'badge-info' : 'badge-secondary' }} badge-sm font-medium">
                        {{ $student->gender === 'M' ? '👦 Garçon' : '👧 Fille' }}
                    </span>
                @endscope
                @scope('cell_classroom', $student)
                    <div>
                        <p class="font-medium text-sm">{{ $student->classroom->label }}</p>
                        <p class="text-xs text-base-content/40">{{ $student->classroom->niveau->label ?? '' }}</p>
                    </div>
                @endscope
                @scope('actions', $student)
                    @unless(auth()->user()->hasRole('teacher'))
                    <div class="flex gap-1">
                        <x-button wire:click="openModal({{ $student->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                        <x-button wire:click="delete({{ $student->id }})" class="btn-xs btn-error" icon="o-trash"
                            wire:confirm="Supprimer cet élève ?" tooltip="Supprimer" />
                    </div>
                    @endunless
                @endscope
            </x-table>
        </div>
    </div>

    {{-- Modal --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier l\'élève' : '👤 Nouvel élève' }}"
             subtitle="Renseignez les informations de l'élève"
             class="backdrop-blur">
        <x-form wire:submit="save" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Matricule" wire:model="matricule"
                    placeholder="INTEC-2026-0001"
                    icon="o-identification"
                    hint="Laissez vide pour générer automatiquement" />
                <x-choices label="Genre" wire:model="gender" :options="$genders" single clearable icon="o-user" />
                <x-input label="Nom complet" wire:model="full_name"
                    placeholder="KOUASSI Amélie" icon="o-user" class="col-span-2" />
                <x-input label="Date de naissance" wire:model="birth_date"
                    type="date" icon="o-calendar" />
                <x-choices label="Classe" wire:model="classroom_id"
                    :options="$classrooms" single clearable icon="o-building-library" />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showModal = false" />
                <x-button label="{{ $editId ? 'Mettre à jour' : 'Inscrire l\'élève' }}" class="btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
