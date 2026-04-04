<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ─── Connexion ────────────────────────────────────────────────────────────────
Volt::route('/connexion', 'auth.login')
    ->middleware('guest')
    ->name('login');

Volt::route('/inscription', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::post('/deconnexion', function () {
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect('/connexion');
})->middleware('auth')->name('logout');
