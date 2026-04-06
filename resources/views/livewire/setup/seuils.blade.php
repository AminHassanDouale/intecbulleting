<?php

use App\Models\SchoolSetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    // ── Seuil d'admission ─────────────────────────────────────────────────────
    public string $seuilAdmission = '10';

    // ── Mentions ──────────────────────────────────────────────────────────────
    public string $seuilPassable    = '10';
    public string $seuilAssezBien   = '12';
    public string $seuilBien        = '14';
    public string $seuilTresBien    = '16';

    public function mount(): void
    {
        $this->seuilAdmission = SchoolSetting::get('seuil_admission',  '10');
        $this->seuilPassable  = SchoolSetting::get('seuil_passable',   '10');
        $this->seuilAssezBien = SchoolSetting::get('seuil_assez_bien', '12');
        $this->seuilBien      = SchoolSetting::get('seuil_bien',       '14');
        $this->seuilTresBien  = SchoolSetting::get('seuil_tres_bien',  '16');
    }

    public function save(): void
    {
        $this->validate([
            'seuilAdmission' => 'required|numeric|min:0|max:20',
            'seuilPassable'  => 'required|numeric|min:0|max:20',
            'seuilAssezBien' => 'required|numeric|min:0|max:20',
            'seuilBien'      => 'required|numeric|min:0|max:20',
            'seuilTresBien'  => 'required|numeric|min:0|max:20',
        ], [
            'seuilAdmission.required' => 'Le seuil d\'admission est obligatoire.',
            'seuilAdmission.numeric'  => 'Doit être un nombre.',
        ]);

        SchoolSetting::set('seuil_admission',  $this->seuilAdmission);
        SchoolSetting::set('seuil_passable',   $this->seuilPassable);
        SchoolSetting::set('seuil_assez_bien', $this->seuilAssezBien);
        SchoolSetting::set('seuil_bien',       $this->seuilBien);
        SchoolSetting::set('seuil_tres_bien',  $this->seuilTresBien);

        $this->success('Paramètres enregistrés !', icon: 'o-check-circle', position: 'toast-top toast-end');
    }
}; ?>

<div class="space-y-5 max-w-2xl mx-auto">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-violet-600 to-purple-700 text-white px-6 py-5 shadow-lg">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">⚙️</div>
            <div>
                <h1 class="text-xl font-bold">Seuils d'admission</h1>
                <p class="text-white/70 text-sm">Configurer les moyennes minimales pour la promotion et les mentions</p>
            </div>
        </div>
    </div>

    {{-- Admission threshold --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-9 h-9 bg-success/10 text-success rounded-xl flex items-center justify-center text-lg">🎯</div>
                <div>
                    <h2 class="font-bold">Seuil d'admission (promotion)</h2>
                    <p class="text-xs text-base-content/50">Moyenne annuelle minimale pour passer en classe supérieure</p>
                </div>
            </div>

            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <label class="label label-text font-medium text-sm">Moyenne minimale /20</label>
                    <div class="flex items-center gap-2">
                        <input
                            type="number"
                            wire:model="seuilAdmission"
                            min="0" max="20" step="0.5"
                            class="input input-bordered w-32 text-center text-lg font-bold"
                        />
                        <span class="text-base-content/50 text-sm">/20</span>
                    </div>
                    @error('seuilAdmission')
                        <p class="text-error text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pb-1">
                    <div class="alert alert-info py-2 px-4 text-sm">
                        Un élève avec une moyenne <strong>≥ {{ $seuilAdmission }}/20</strong> est promu.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mention thresholds --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-9 h-9 bg-warning/10 text-warning rounded-xl flex items-center justify-center text-lg">🏅</div>
                <div>
                    <h2 class="font-bold">Seuils des mentions</h2>
                    <p class="text-xs text-base-content/50">Moyenne minimale pour chaque mention (sur 20)</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra w-full text-sm">
                    <thead>
                        <tr>
                            <th>Mention</th>
                            <th>Exemple d'affichage</th>
                            <th class="w-40">Seuil minimum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-semibold">Passable</td>
                            <td><span class="badge badge-warning badge-sm">Passable</span></td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <input type="number" wire:model="seuilPassable" min="0" max="20" step="0.5" class="input input-bordered input-sm w-20 text-center" />
                                    <span class="text-xs text-base-content/40">/20</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Assez Bien</td>
                            <td><span class="badge badge-info badge-sm">Assez Bien</span></td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <input type="number" wire:model="seuilAssezBien" min="0" max="20" step="0.5" class="input input-bordered input-sm w-20 text-center" />
                                    <span class="text-xs text-base-content/40">/20</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Bien</td>
                            <td><span class="badge badge-primary badge-sm">Bien</span></td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <input type="number" wire:model="seuilBien" min="0" max="20" step="0.5" class="input input-bordered input-sm w-20 text-center" />
                                    <span class="text-xs text-base-content/40">/20</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Très Bien</td>
                            <td><span class="badge badge-success badge-sm">Très Bien</span></td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <input type="number" wire:model="seuilTresBien" min="0" max="20" step="0.5" class="input input-bordered input-sm w-20 text-center" />
                                    <span class="text-xs text-base-content/40">/20</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Preview --}}
            <div class="bg-base-200 rounded-xl p-4 text-sm space-y-1.5">
                <p class="font-semibold text-base-content/60 text-xs uppercase tracking-wide mb-2">Aperçu de l'échelle</p>
                <div class="flex items-center justify-between">
                    <span class="text-base-content/60">Insuffisant</span>
                    <span class="text-error font-bold">0 → {{ $seuilPassable - 0.5 }}/20</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-base-content/60">Passable</span>
                    <span class="font-bold">{{ $seuilPassable }} → {{ $seuilAssezBien - 0.5 }}/20</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-base-content/60">Assez Bien</span>
                    <span class="font-bold">{{ $seuilAssezBien }} → {{ $seuilBien - 0.5 }}/20</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-base-content/60">Bien</span>
                    <span class="font-bold">{{ $seuilBien }} → {{ $seuilTresBien - 0.5 }}/20</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-base-content/60">Très Bien</span>
                    <span class="text-success font-bold">{{ $seuilTresBien }} → 20/20</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Save button --}}
    <x-button
        label="Enregistrer les paramètres"
        wire:click="save"
        class="btn-primary w-full"
        spinner="save"
        icon="o-check-circle"
        wire:confirm="Enregistrer les nouveaux seuils ?"
    />

</div>
