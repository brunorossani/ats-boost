<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TailoredResumeController;
use App\Livewire\Settings\Subscriptions;
use App\Livewire\Resume\ResumeAnalyzer;
use App\Livewire\Resume\ResumeTailor;
use Illuminate\Support\Facades\Route;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\Resume\CoverLetter;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return view('homepages.welcome');
})->name('home');

Route::view('panel', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Route::view('clientes', 'homepages.customers')
//     ->name('customers');

Route::view('caracteristicas', 'homepages.features')
    ->name('features');

Route::view('precios', 'homepages.pricing')
    ->name('pricing');

Route::view('privacidad', 'homepages.privacy')
    ->name('privacy');

Route::view('terminos', 'homepages.terms')
    ->name('terms');

// Vista previa/pitch del próximo producto (búsqueda + envío automático de CVs).
// Pública y sin autenticación a propósito: se comparte como link para mostrar
// la dirección del producto antes de construir la integración real.
Route::view('futuro', 'homepages.auto-apply-preview')
    ->name('auto-apply.preview');

Route::get('/checkout/start/{variant}', [CheckoutController::class, 'start'])
    ->name('checkout.start');

Route::middleware(['auth'])->group(function () {
    Route::delete('panel/cvs-tailored/{tailoredResume}', [TailoredResumeController::class, 'destroy'])
        ->name('resume.tailored.destroy');

    Route::redirect('ajustes', 'ajustes/perfil');

    Route::get('ajustes/perfil', Profile::class)->name('profile.edit');
    Route::get('ajustes/contraseña', Password::class)->name('user-password.edit');
    Route::get('ajustes/apariencia', Appearance::class)->name('appearance.edit');
    Route::get('ajustes/suscripciones', Subscriptions::class)->name('subscriptions.edit');

    Route::get('ajustes/autenticacion-doble', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

Route::middleware(['auth', 'subscribed'])->group(function () {
    Route::get('panel/adaptar-cv', ResumeTailor::class)->name('resume.resume-tailor');
    Route::get('panel/analizar-cv', ResumeAnalyzer::class)->name('resume.resume-analyzer');
    Route::get('panel/carta-presentacion', CoverLetter::class)->name('resume.cover-letter');
});
