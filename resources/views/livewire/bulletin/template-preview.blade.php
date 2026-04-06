<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.print')] class extends Component {

    public string $niveau = 'prescolaire';

    public function with(): array
    {
        return [];
    }
}; ?>

<div class="max-w-[210mm] mx-auto bg-white p-8">

    {{-- ── Action bar (hidden when printing) ──────────────────────────────── --}}
    <div class="no-print flex items-center justify-between mb-5 p-3 bg-indigo-50 rounded-xl border border-indigo-100">
        <div>
            <p class="font-bold text-indigo-800">📋 Aperçu des modèles de bulletin — Direction</p>
            <p class="text-xs text-indigo-500 mt-0.5">Données fictives à titre illustratif</p>
        </div>
        <div class="flex gap-2">
            <button
                wire:click="$set('niveau', 'prescolaire')"
                class="btn btn-sm {{ $niveau === 'prescolaire' ? 'btn-primary' : 'btn-ghost border border-indigo-200' }}">
                🌱 Préscolaire
            </button>
            <button
                wire:click="$set('niveau', 'primaire')"
                class="btn btn-sm {{ $niveau === 'primaire' ? 'btn-primary' : 'btn-ghost border border-indigo-200' }}">
                📚 Primaire
            </button>
            <button onclick="window.print()" class="btn btn-sm btn-success">🖨 Imprimer</button>
            <a href="{{ route('bulletins.index') }}" class="btn btn-sm btn-ghost">← Retour</a>
        </div>
    </div>

    @if($niveau === 'prescolaire')
        @include('livewire.bulletin._template-prescolaire-print')
    @else
        @include('livewire.bulletin._template-primaire-print')
    @endif

</div>
