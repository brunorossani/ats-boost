<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DocumentController;
use App\Livewire\Documents;
use App\Livewire\Jobs;
use App\Livewire\Resume;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/

// Home = demo en vivo: ofertas reales del día por país, cada una con su CV adaptado.
Route::view('/', 'homepages.home')->name('home');

// Redirecciones de páginas consolidadas en home
Route::redirect('futuro', '/', 301);
Route::redirect('adaptar', '/#como-funciona', 301);
Route::redirect('caracteristicas', '/#caracteristicas', 301);

Route::get('demo/cv/{document}/{disposition?}', [DocumentController::class, 'demo'])
    ->whereIn('disposition', ['inline', 'attachment'])
    ->middleware('throttle:30,1')
    ->name('demo.document');

Route::view('precios', 'homepages.pricing')->name('pricing');
Route::view('privacidad', 'homepages.privacy')->name('privacy');
Route::view('terminos', 'homepages.terms')->name('terms');

Route::get('checkout/{variant}', [CheckoutController::class, 'start'])->name('checkout.start');

/*
|--------------------------------------------------------------------------
| Cuenta
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::redirect('ajustes', 'ajustes/perfil');

    Route::get('ajustes/perfil', Settings\Profile::class)->name('profile.edit');
    Route::get('ajustes/contrasena', Settings\Password::class)->name('user-password.edit');
    Route::get('ajustes/apariencia', Settings\Appearance::class)->name('appearance.edit');
    Route::get('ajustes/suscripciones', Settings\Subscriptions::class)->name('subscriptions.edit');

    Route::get('ajustes/autenticacion-doble', Settings\TwoFactor::class)
        ->middleware(when(
            Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
            ['password.confirm'],
            [],
        ))
        ->name('two-factor.show');
});

/*
|--------------------------------------------------------------------------
| Panel — requiere suscripción vigente
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Panel redirige a Ofertas (el flujo arranca ahí)
    Route::redirect('panel', 'panel/ofertas')->name('dashboard');

    Route::middleware('subscribed')->group(function (): void {
        // Navegación principal
        Route::get('panel/ofertas', Jobs\JobFeed::class)->name('jobs.feed');
        Route::get('panel/postulaciones', \App\Livewire\Applications\Index::class)->name('applications.index');
        Route::get('panel/mi-cv', Resume\BaseResume::class)->name('resume.base');

        // Herramientas accesibles desde el flujo (mantener rutas para no romper links)
        Route::get('panel/adaptar-cv', Resume\Tailor::class)->name('resume.tailor');
        Route::get('panel/analizar-cv', Resume\Analyzer::class)->name('resume.analyzer');
        Route::get('panel/carta-presentacion', Resume\CoverLetter::class)->name('resume.cover-letter');

        Route::get('panel/documentos', Documents\Index::class)->name('documents.index');
        Route::get('panel/documentos/{document}', Documents\Edit::class)->name('documents.edit');

        // La descarga y la vista previa son rutas HTTP propias, no acciones de
        // Livewire: así el PDF tiene URL estable, pasa por DocumentPolicy y se
        // puede volver a abrir desde el historial sin regenerar nada.
        Route::get('panel/documentos/{document}/descargar', [DocumentController::class, 'download'])
            ->name('documents.download');

        Route::get('panel/documentos/{document}/vista-previa', [DocumentController::class, 'preview'])
            ->name('documents.preview');
    });
});
