<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.guest')] class extends Component {
    use Toast;

    public string $email    = '';
    public string $password = '';
    public bool   $remember = false;

    public function login(): void
    {
        $this->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'Ces identifiants ne correspondent à aucun compte.');
            return;
        }

        session()->regenerate();
        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="w-full space-y-8">

    {{-- Logo / header --}}
    <div class="text-center space-y-3">
        <div class="w-20 h-20 bg-linear-to-br from-blue-600 to-blue-800 rounded-3xl flex items-center justify-center mx-auto shadow-2xl shadow-blue-500/40 ring-2 ring-yellow-400/30 overflow-hidden p-2">
            <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC Logo" class="w-full h-full object-contain">
        </div>
        <div>
            <h1 class="text-3xl font-black text-white"><span class="text-yellow-500">IN</span>TEC École</h1>
            <p class="text-blue-300/60 text-sm mt-1">Système de gestion des bulletins</p>
        </div>
    </div>

    {{-- Card --}}
    <div class="bg-blue-900/40 backdrop-blur-sm border border-blue-700/40 rounded-3xl p-8 shadow-2xl space-y-6">

        <div class="space-y-1">
            <h2 class="text-lg font-bold text-yellow-300">Connexion</h2>
            <p class="text-blue-300/60 text-sm">Entrez vos identifiants pour accéder à votre espace.</p>
        </div>

        {{-- Form --}}
        <x-form wire:submit="login" no-separator>
            <x-errors title="Identifiants incorrects." icon="o-exclamation-circle" />

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-blue-300/80 uppercase tracking-wider">Adresse e-mail</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-400/70">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <input
                            wire:model="email"
                            type="email"
                            placeholder="prenom.nom@intec.edu"
                            class="w-full pl-10 pr-4 py-3 bg-blue-950/60 border border-blue-700/50 rounded-xl text-white placeholder-blue-500/50
                                   focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50
                                   transition-all duration-200 text-sm"
                        >
                    </div>
                    @error('email') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-blue-300/80 uppercase tracking-wider">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-400/70">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input
                            wire:model="password"
                            type="password"
                            placeholder="••••••••"
                            class="w-full pl-10 pr-4 py-3 bg-blue-950/60 border border-blue-700/50 rounded-xl text-white placeholder-blue-500/50
                                   focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50
                                   transition-all duration-200 text-sm"
                        >
                    </div>
                    @error('password') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="w-4 h-4 rounded border-blue-600/50 bg-blue-950/60 text-yellow-400 focus:ring-yellow-400/30 cursor-pointer"
                    id="remember"
                >
                <label for="remember" class="text-sm cursor-pointer select-none text-blue-300/60 hover:text-blue-200 transition-colors">
                    Se souvenir de moi
                </label>
            </div>

            <x-slot:actions>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full py-3.5 px-6 bg-linear-to-r from-yellow-400 to-amber-500 hover:from-yellow-300 hover:to-amber-400
                           text-blue-950 font-bold rounded-xl shadow-lg shadow-yellow-400/20
                           focus:outline-none focus:ring-2 focus:ring-yellow-400/50
                           transition-all duration-200 flex items-center justify-center gap-2 text-sm
                           disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="login">
                        <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        Se connecter
                    </span>
                    <span wire:loading wire:target="login" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Connexion...
                    </span>
                </button>
            </x-slot:actions>
        </x-form>
    </div>

    {{-- Footer --}}
    <p class="text-center text-xs text-blue-800">
        INTEC École &copy; {{ date('Y') }} — Tous droits réservés
    </p>
</div>
