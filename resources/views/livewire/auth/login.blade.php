<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component {

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

<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h2 class="text-2xl font-black text-white leading-tight">
            Connexion
        </h2>
        <p class="text-white/45 text-sm mt-1.5">
            Accédez à votre espace de gestion scolaire.
        </p>
    </div>

    {{-- Error alert --}}
    @if($errors->has('email') || $errors->has('password'))
    <div class="error-box flex items-start gap-2.5">
        <svg class="w-4 h-4 mt-0.5 shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span>{{ $errors->first('email') ?: $errors->first('password') }}</span>
    </div>
    @endif

    {{-- Form --}}
    <form wire:submit.prevent="login" class="space-y-4" novalidate>

        {{-- Email --}}
        <div>
            <label class="field-label">Adresse e-mail</label>
            <div class="input-wrap">
                <span class="input-icon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <input wire:model="email"
                       type="email"
                       placeholder="prenom.nom@intec.edu"
                       autocomplete="email"
                       class="input-field @error('email') border-red-400/60 @enderror">
            </div>
        </div>

        {{-- Password --}}
        <div>
            <label class="field-label">Mot de passe</label>
            <div class="input-wrap" x-data="{ show: false }">
                <span class="input-icon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input wire:model="password"
                       :type="show ? 'text' : 'password'"
                       placeholder="••••••••"
                       autocomplete="current-password"
                       class="input-field pr-11 @error('password') border-red-400/60 @enderror">
                {{-- Toggle visibility --}}
                <button type="button"
                        @click="show = !show"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-white/30 hover:text-white/60 transition-colors focus:outline-none">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="display:none">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Remember me --}}
        <div class="flex items-center gap-2.5 pt-1">
            <input type="checkbox"
                   wire:model="remember"
                   id="remember"
                   class="check-custom">
            <label for="remember" class="text-sm text-white/45 hover:text-white/65 cursor-pointer select-none transition-colors">
                Se souvenir de moi
            </label>
        </div>

        {{-- Submit --}}
        <div class="pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="btn-submit">
                <span wire:loading.remove wire:target="login" class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Se connecter
                </span>
                <span wire:loading wire:target="login" class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Connexion en cours…
                </span>
            </button>
        </div>

    </form>


</div>
