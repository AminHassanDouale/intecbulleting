<?php

use App\Models\AcademicYear;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public bool    $showModal  = false;
    public ?int    $editId     = null;

    public ?string $label      = null;
    public ?string $start_date = null;
    public ?string $end_date   = null;
    public bool    $is_current = false;

    public function openModal(?int $id = null): void
    {
        $this->resetForm();
        if ($id) {
            $y = AcademicYear::findOrFail($id);
            $this->editId     = $y->id;
            $this->label      = $y->label;
            $this->start_date = $y->start_date->format('Y-m-d');
            $this->end_date   = $y->end_date->format('Y-m-d');
            $this->is_current = $y->is_current;
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label'      => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if ($data['is_current']) {
            AcademicYear::query()->update(['is_current' => false]);
        }

        if ($this->editId) {
            AcademicYear::findOrFail($this->editId)->update($data);
            $this->success('Année scolaire mise à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            AcademicYear::create($data);
            $this->success('Année scolaire créée.', icon: 'o-check-circle', position: 'toast-top toast-end');
        }

        $this->showModal = false;
    }

    public function setCurrent(int $id): void
    {
        AcademicYear::query()->update(['is_current' => false]);
        AcademicYear::findOrFail($id)->update(['is_current' => true]);
        $this->success('Année courante mise à jour.', icon: 'o-calendar', position: 'toast-top toast-end');
    }

    public function delete(int $id): void
    {
        $year = AcademicYear::findOrFail($id);
        if ($year->is_current) {
            $this->error('Impossible de supprimer l\'année courante.', icon: 'o-x-circle', position: 'toast-top toast-end');
            return;
        }
        $year->delete();
        $this->warning('Année scolaire supprimée.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    protected function resetForm(): void
    {
        $this->editId     = null;
        $this->label      = null;
        $this->start_date = null;
        $this->end_date   = null;
        $this->is_current = false;
    }

    public function with(): array
    {
        $years = AcademicYear::orderByDesc('start_date')->get();
        return [
            'years'   => $years,
            'headers' => [
                ['key' => 'label',      'label' => 'Libellé'],
                ['key' => 'start_date', 'label' => 'Début'],
                ['key' => 'end_date',   'label' => 'Fin'],
                ['key' => 'stats',      'label' => 'Statistiques'],
                ['key' => 'actions',    'label' => ''],
            ],
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Header --}}
    <div class="rounded-2xl bg-linear-to-r from-sky-600 to-blue-700 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">📅</div>
                <div>
                    <h1 class="text-xl font-bold">Années Scolaires</h1>
                    <p class="text-white/70 text-sm">Gestion des années scolaires</p>
                </div>
            </div>
            <x-button label="Nouvelle année" wire:click="openModal" class="btn-white text-sky-700 font-semibold" icon="o-plus" />
        </div>
    </div>

    {{-- Stats --}}
    <div class="flex gap-3 flex-wrap">
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4">
            <div class="stat-title text-xs">Total années</div>
            <div class="stat-value text-2xl text-sky-600">{{ $years->count() }}</div>
        </div>
        @php $current = $years->firstWhere('is_current', true); @endphp
        @if($current)
        <div class="stat bg-base-100 shadow rounded-xl py-2 px-4">
            <div class="stat-title text-xs">Année courante</div>
            <div class="stat-value text-lg text-green-600">{{ $current->label }}</div>
        </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <x-table :headers="$headers" :rows="$years">
                @scope('cell_label', $year)
                    <div class="flex items-center gap-2">
                        <span class="font-bold">{{ $year->label }}</span>
                        @if($year->is_current)
                            <span class="badge badge-success badge-sm">Courante</span>
                        @endif
                    </div>
                @endscope
                @scope('cell_start_date', $year)
                    <span class="text-sm">{{ $year->start_date->format('d/m/Y') }}</span>
                @endscope
                @scope('cell_end_date', $year)
                    <span class="text-sm">{{ $year->end_date->format('d/m/Y') }}</span>
                @endscope
                @scope('cell_stats', $year)
                    <div class="flex gap-2 flex-wrap text-xs">
                        <span class="badge badge-ghost">{{ $year->classrooms()->count() }} classes</span>
                        <span class="badge badge-ghost">{{ $year->students()->count() }} élèves</span>
                        <span class="badge badge-ghost">{{ $year->bulletins()->count() }} bulletins</span>
                    </div>
                @endscope
                @scope('actions', $year)
                    <div class="flex gap-1">
                        @unless($year->is_current)
                        <x-button wire:click="setCurrent({{ $year->id }})" class="btn-xs btn-success" icon="o-check" tooltip="Définir comme courante" />
                        @endunless
                        <x-button wire:click="openModal({{ $year->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                        @unless($year->is_current)
                        <x-button wire:click="delete({{ $year->id }})" class="btn-xs btn-error" icon="o-trash"
                            wire:confirm="Supprimer cette année scolaire ?" tooltip="Supprimer" />
                        @endunless
                    </div>
                @endscope
            </x-table>
        </div>
    </div>

    {{-- Modal --}}
    <x-modal wire:model="showModal"
             title="{{ $editId ? '✏️ Modifier l\'année' : '📅 Nouvelle année scolaire' }}"
             subtitle="Renseignez les informations de l'année"
             class="backdrop-blur">
        <x-form wire:submit="save" no-separator>
            <x-input label="Libellé" wire:model="label" placeholder="ex: 2025/2026" icon="o-tag" />
            <div class="grid grid-cols-2 gap-3">
                <x-input label="Date de début" wire:model="start_date" type="date" icon="o-calendar" />
                <x-input label="Date de fin"   wire:model="end_date"   type="date" icon="o-calendar" />
            </div>
            <x-checkbox wire:model="is_current" label="Année courante" hint="Définir comme année scolaire active" />
        </x-form>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showModal', false)" />
            <x-button label="{{ $editId ? 'Mettre à jour' : 'Créer' }}" wire:click="save" class="btn-primary" icon="o-check" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>
