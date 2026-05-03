<?php

use App\Models\Niveau;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public bool    $showModal = false;
    public ?int    $editId    = null;
    public ?string $code      = null;
    public ?string $label     = null;

    public function openModal(?int $id = null): void
    {
        $this->resetForm();
        if ($id) {
            $n = Niveau::findOrFail($id);
            $this->editId = $n->id;
            $this->code   = $n->code;
            $this->label  = $n->label;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code'  => 'required|string|max:10',
            'label' => 'required|string|max:60',
        ]);

        if ($this->editId) {
            Niveau::findOrFail($this->editId)->update($data);
            $this->success('Niveau mis à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            Niveau::create($data);
            $this->success('Niveau créé.', icon: 'o-check-circle', position: 'toast-top toast-end');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $niveau = Niveau::findOrFail($id);
        if ($niveau->classrooms()->exists() || $niveau->subjects()->exists()) {
            $this->error('Ce niveau est utilisé (classes ou matières).', icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }
        $niveau->delete();
        $this->warning('Niveau supprimé.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    protected function resetForm(): void
    {
        $this->editId = null;
        $this->code   = null;
        $this->label  = null;
    }

    public function with(): array
    {
        $niveaux = Niveau::withCount(['classrooms', 'subjects'])->orderBy('code')->get();
        return [
            'niveaux' => $niveaux,
            'headers' => [
                ['key' => 'code',       'label' => 'Code'],
                ['key' => 'label',      'label' => 'Libellé'],
                ['key' => 'classrooms', 'label' => 'Classes'],
                ['key' => 'subjects',   'label' => 'Matières'],
                ['key' => 'actions',    'label' => ''],
            ],
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Header --}}
    <div class="rounded-2xl bg-linear-to-r from-violet-600 to-purple-700 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">🎓</div>
                <div>
                    <h1 class="text-xl font-bold">Niveaux Scolaires</h1>
                    <p class="text-white/70 text-sm">Cycles et niveaux d'enseignement</p>
                </div>
            </div>
            <x-button label="Nouveau niveau" wire:click="openModal" class="btn-white text-violet-700 font-semibold" icon="o-plus" />
        </div>
    </div>

    {{-- Stats --}}
    <div class="flex gap-3 flex-wrap">
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4">
            <div class="stat-title text-xs">Total niveaux</div>
            <div class="stat-value text-2xl text-violet-600">{{ $niveaux->count() }}</div>
        </div>
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4">
            <div class="stat-title text-xs">Total classes</div>
            <div class="stat-value text-2xl text-blue-600">{{ $niveaux->sum('classrooms_count') }}</div>
        </div>
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4">
            <div class="stat-title text-xs">Total matières</div>
            <div class="stat-value text-2xl text-green-600">{{ $niveaux->sum('subjects_count') }}</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <x-table :headers="$headers" :rows="$niveaux" striped>
                @scope('cell_code', $niveau)
                    <span class="badge badge-primary font-mono font-bold">{{ $niveau->code }}</span>
                @endscope
                @scope('cell_label', $niveau)
                    <span class="font-medium">{{ $niveau->label }}</span>
                @endscope
                @scope('cell_classrooms', $niveau)
                    <span class="badge badge-outline badge-info badge-sm">{{ $niveau->classrooms_count }}</span>
                @endscope
                @scope('cell_subjects', $niveau)
                    <span class="badge badge-outline badge-success badge-sm">{{ $niveau->subjects_count }}</span>
                @endscope
                @scope('actions', $niveau)
                    <div class="flex gap-1">
                        <x-button wire:click="openModal({{ $niveau->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                        <x-button wire:click="delete({{ $niveau->id }})" class="btn-xs btn-error" icon="o-trash"
                            wire:confirm="Supprimer ce niveau ?" tooltip="Supprimer" />
                    </div>
                @endscope
            </x-table>
        </div>
    </div>

    {{-- Modal --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier le niveau' : '🎓 Nouveau niveau' }}"
             subtitle="Code et libellé du niveau scolaire"
             class="backdrop-blur">
        <x-form wire:submit="save" no-separator>
            <x-input label="Code" wire:model="code" placeholder="ex: GS, CE1, CM2" icon="o-hashtag"
                hint="Identifiant court unique (PS, MS, GS, CP, CE1, CE2, CM1, CM2)" />
            <x-input label="Libellé" wire:model="label" placeholder="ex: Grande Section" icon="o-academic-cap" />
        </x-form>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showModal', false)" />
            <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer' }}" wire:click="save" class="btn-primary" icon="o-check" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>
