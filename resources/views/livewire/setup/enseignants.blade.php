<?php

use App\Exports\TeachersTemplateExport;
use App\Imports\TeachersImport;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast, WithPagination, WithFileUploads;

    public bool    $showModal   = false;
    public ?int    $editId      = null;
    public string  $search      = '';
    public ?int    $filterClass = null;
    public         $importFile  = null;

    // Form fields
    public string  $name        = '';
    public string  $email       = '';
    public string  $password    = '';
    public ?int    $classroom_id = null;

    public function updatedSearch(): void { $this->resetPage(); }

    public function openModal(?int $userId = null): void
    {
        $this->reset(['name','email','password','classroom_id','editId']);
        if ($userId) {
            $u = User::findOrFail($userId);
            $this->editId      = $u->id;
            $this->name        = $u->name;
            $this->email       = $u->email;
            $this->classroom_id = $u->classrooms()->first()?->id;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name'        => 'required|string|max:200',
            'email'       => 'required|email|unique:users,email,' . ($this->editId ?? 'NULL'),
            'classroom_id'=> 'nullable|exists:classrooms,id',
        ];

        if (!$this->editId) {
            $rules['password'] = 'nullable|string|min:6';
        }

        $this->validate($rules);

        if ($this->editId) {
            $user = User::findOrFail($this->editId);
            $user->name  = $this->name;
            $user->email = $this->email;
            if ($this->password) {
                $user->password = Hash::make($this->password);
            }
            $user->save();
            $this->success('Enseignant mis à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            $user = User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'password' => Hash::make($this->password ?: 'Intec@2026'),
            ]);
            $user->assignRole('teacher');
            $this->success('Enseignant créé. Mot de passe : ' . ($this->password ?: 'Intec@2026'), icon: 'o-academic-cap', position: 'toast-top toast-end');
        }

        // Assign classroom
        if ($this->classroom_id) {
            // Remove user from previous classrooms
            Classroom::where('teacher_id', $user->id)->update(['teacher_id' => null]);
            Classroom::findOrFail($this->classroom_id)->update(['teacher_id' => $user->id]);
        } else {
            Classroom::where('teacher_id', $user->id)->update(['teacher_id' => null]);
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);
        Classroom::where('teacher_id', $id)->update(['teacher_id' => null]);
        $user->delete();
        $this->warning('Enseignant supprimé.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    public function importTeachers(): void
    {
        if (!$this->importFile) {
            $this->error('Aucun fichier sélectionné.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        $importer = new TeachersImport();

        try {
            $stored   = $this->importFile->store('teacher-imports', 'local');
            $fullPath = Storage::disk('local')->path($stored);
            Excel::import($importer, $fullPath);
            Storage::disk('local')->delete($stored);
            $this->importFile = null;

            $stats   = $importer->getStats();
            $message = "{$stats['imported']} ajouté(s), {$stats['updated']} mis à jour, {$stats['skipped']} ignoré(s).";

            if (!empty($stats['errors'])) {
                $this->warning("Import terminé. {$message}", implode(' | ', array_slice($stats['errors'], 0, 3)), icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } else {
                $this->success("Import réussi ! {$message}", icon: 'o-arrow-up-tray', position: 'toast-top toast-end');
            }
        } catch (\Throwable $e) {
            $this->error('Erreur import', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
        }
    }

    #[Renderless]
    public function downloadTemplate(): mixed
    {
        return Excel::download(
            new TeachersTemplateExport(),
            'modele_import_enseignants.xlsx'
        );
    }

    public function with(): array
    {
        $teachers = User::role('teacher')
            ->with('classrooms')
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
            )
            ->orderBy('name')
            ->paginate(25);

        return [
            'teachers'   => $teachers,
            'total'      => User::role('teacher')->count(),
            'classrooms' => Classroom::orderBy('code')->get()
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->label . ' ' . $c->section])
                ->prepend(['id' => '', 'name' => '— Aucune classe assignée']),
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-violet-500 to-indigo-600 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">🧑‍🏫</div>
                <div>
                    <h1 class="text-xl font-bold">Gestion des Enseignants</h1>
                    <p class="text-white/70 text-sm">Créer et gérer les comptes enseignants</p>
                </div>
            </div>
            <x-button label="Nouvel enseignant" wire:click="openModal" class="btn-white text-violet-700 font-semibold" icon="o-plus" />
        </div>
    </div>

    {{-- Stats --}}
    <div class="flex flex-wrap gap-3">
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4">
            <div class="stat-title text-xs">Total enseignants</div>
            <div class="stat-value text-2xl text-violet-600">{{ $total }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <div class="flex-1">
            <x-input wire:model.live.debounce.300ms="search" placeholder="Rechercher par nom ou email…" icon="o-magnifying-glass" class="input-sm w-full" clearable />
        </div>
        <div class="flex gap-2 flex-wrap">
            {{-- Import --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-sm btn-outline btn-primary gap-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Importer
                </button>
                <div tabindex="0" class="dropdown-content z-50 mt-2 w-72 p-4 shadow-xl bg-white rounded-xl border border-slate-200">
                    <p class="font-semibold text-sm text-slate-700 mb-3">Importer via CSV/Excel</p>
                    <p class="text-xs text-slate-500 mb-3">
                        Colonnes requises : <strong>Nom, Email</strong><br>
                        Optionnelles : <strong>Mot de Passe, Code Classe</strong><br>
                        Mot de passe par défaut : <code class="bg-slate-100 px-1 rounded">Intec@2026</code>
                    </p>
                    <x-button label="Télécharger modèle CSV" wire:click="downloadTemplate" icon="o-document-arrow-down" class="btn-sm btn-ghost w-full mb-2 justify-start" />
                    <div class="flex gap-2">
                        <input type="file" wire:model="importFile" accept=".csv,.xlsx,.xls" class="file-input file-input-sm flex-1 min-w-0" />
                        <x-button label="OK" wire:click="importTeachers" icon="o-arrow-up-tray" class="btn-sm btn-success shrink-0" />
                    </div>
                    <div wire:loading wire:target="importFile" class="mt-2 text-xs text-slate-400">Chargement…</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Teachers table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="py-3 pl-4">Enseignant</th>
                        <th>Email</th>
                        <th>Classe assignée</th>
                        <th>Rôles</th>
                        <th class="text-right pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($teachers as $teacher)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="pl-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($teacher->name, 0, 1)) }}
                                </div>
                                <span class="font-medium text-slate-800">{{ $teacher->name }}</span>
                            </div>
                        </td>
                        <td class="text-slate-600 text-sm">{{ $teacher->email }}</td>
                        <td>
                            @if($teacher->classrooms->isNotEmpty())
                                @foreach($teacher->classrooms as $cls)
                                <span class="badge badge-sm badge-primary">{{ $cls->label }} {{ $cls->section }}</span>
                                @endforeach
                            @else
                                <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </td>
                        <td>
                            @foreach($teacher->getRoleNames() as $role)
                            <span class="badge badge-xs badge-outline">{{ $role }}</span>
                            @endforeach
                        </td>
                        <td class="text-right pr-4">
                            <div class="flex justify-end gap-1">
                                <x-button icon="o-pencil-square" wire:click="openModal({{ $teacher->id }})" class="btn-ghost btn-xs text-indigo-600" tooltip="Modifier" />
                                <x-button icon="o-trash" wire:click="delete({{ $teacher->id }})" wire:confirm="Supprimer cet enseignant ?" class="btn-ghost btn-xs text-red-500" tooltip="Supprimer" />
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400">
                            <div class="text-4xl mb-2">🧑‍🏫</div>
                            <p class="font-medium">Aucun enseignant trouvé</p>
                            <p class="text-sm mt-1">Créez ou importez des enseignants pour commencer</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($teachers->hasPages())
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $teachers->links() }}
        </div>
        @endif
    </div>

    {{-- Modal: create / edit --}}
    <x-modal wire:model="showModal" title="{{ $editId ? 'Modifier l\'enseignant' : 'Nouvel enseignant' }}" class="backdrop-blur-sm" separator>
        <div class="space-y-4 py-2">

            <x-input label="Nom complet" wire:model="name" placeholder="M. Coulibaly Ibrahim" required />

            <x-input label="Email" wire:model="email" type="email" placeholder="coulibaly@intec.ci" required />

            <x-input label="Mot de passe" wire:model="password" type="password"
                placeholder="{{ $editId ? 'Laisser vide pour conserver' : 'Défaut : Intec@2026' }}" />

            <x-select label="Classe assignée" wire:model="classroom_id" :options="$classrooms" placeholder="— Aucune" />

        </div>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showModal', false)" class="btn-ghost" />
            <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer' }}" wire:click="save" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-modal>

</div>
