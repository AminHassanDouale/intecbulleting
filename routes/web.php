<?php

use App\Http\Controllers\BulletinDownloadController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Redirection racine
Route::redirect('/', '/tableau-de-bord');

Route::middleware(['auth'])->group(function () {

    // ─── Tableau de bord ─────────────────────────────────────────────────────
    Volt::route('/tableau-de-bord', 'dashboard.index')
        ->name('dashboard');

    // ─── Bulletins ───────────────────────────────────────────────────────────
    Route::prefix('bulletins')->name('bulletins.')->group(function () {

        Volt::route('/', 'bulletin.index')
            ->name('index');

        Volt::route('/saisie', 'bulletin.saisie')
            ->middleware('role:teacher|admin|direction')
            ->name('grade-form');

        Volt::route('/{bulletin}/workflow', 'bulletin.workflow')
            ->middleware('role:pedagogie|finance|direction|admin')
            ->name('workflow');

        Volt::route('/suivi', 'bulletin.suivi')
            ->middleware('role:pedagogie|finance|direction|admin')
            ->name('suivi');

        Volt::route('/bilan-annuel', 'bulletin.annual')
            ->middleware('role:direction|admin')
            ->name('annual');

        Volt::route('/modeles', 'bulletin.template-preview')
            ->middleware('role:direction|admin')
            ->name('template-preview');

        Volt::route('/carnet/{student}', 'bulletin.carnet')
            ->name('carnet');

        // Téléchargement PDF (contrôleur classique)
        Route::get('/{bulletin}/pdf', [BulletinDownloadController::class, 'download'])
            ->name('download');
    });

    // ─── Export CSV notes (hors prefix bulletins pour éviter le conflit de nom) ──
    Route::get('/grades/export-csv', [\App\Http\Controllers\GradeSheetCSVController::class, 'export'])
        ->name('grades.export-csv');

    // ─── Configuration ───────────────────────────────────────────────────────
    Route::prefix('configuration')->name('setup.')->middleware('role:admin|direction')->group(function () {

        Volt::route('/classes', 'setup.classes')
            ->name('classrooms');

        Volt::route('/matieres', 'setup.matieres')
            ->name('subjects');

        Volt::route('/competences', 'setup.competences')
            ->name('competences');

        Volt::route('/eleves', 'setup.eleves')
            ->name('students');

        Volt::route('/seuils', 'setup.seuils')
            ->name('seuils');

        Volt::route('/enseignants', 'setup.enseignants')
            ->name('teachers');
    });
});

// ─── Authentification ─────────────────────────────────────────────────────────
require __DIR__ . '/auth.php';
