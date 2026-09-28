<?php

use App\Actions\Jobs\SyncSearchProfile;
use App\Actions\Resume\ExtractResumeText;
use App\JobSearch\Regions;
use App\Models\JobSearchProfile;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('jobs:sync {--profile= : Sincronizar solo esta búsqueda}', function (SyncSearchProfile $sync) {
    $profiles = JobSearchProfile::query()
        ->where('is_active', true)
        ->whereNotNull('resume_text')
        ->when($this->option('profile'), fn ($q, $id) => $q->whereKey($id))
        ->get();

    if ($profiles->isEmpty()) {
        $this->warn('No hay búsquedas activas con CV base.');

        return;
    }

    foreach ($profiles as $profile) {
        try {
            $report = $sync->handle($profile);
        } catch (Throwable $e) {
            report($e);
            $this->error("Búsqueda #{$profile->id}: {$e->getMessage()}");

            continue;
        }

        $this->info("Búsqueda #{$profile->id} ({$profile->keywords} · {$profile->label()}): {$report['found']} ofertas, {$report['new_matches']} nuevas");

        foreach ($report['providers'] as $key => $p) {
            $this->line(sprintf('  %-12s %-15s %d%s', $key, $p['status'], $p['found'], isset($p['error']) ? " ({$p['error']})" : ''));
        }
    }
})->purpose('Busca ofertas nuevas para cada búsqueda y encola los CVs adaptados');

Artisan::command('jobs:demo
    {cv : Ruta al PDF o TXT del CV base de la demo}
    {--keywords= : Qué puesto buscar}
    {--countries=uy,us,es : Países de la demo, separados por coma}
    {--no-sync : Crear las búsquedas sin buscar todavía}', function (ExtractResumeText $extract, SyncSearchProfile $sync) {
    $path = $this->argument('cv');

    if (! is_file($path)) {
        $this->error("No existe el archivo {$path}");

        return 1;
    }

    $countries = collect(explode(',', (string) $this->option('countries')))
        ->map(fn ($c) => strtolower(trim($c)))
        ->filter();

    if ($invalid = $countries->reject(fn ($c) => Regions::exists($c))->implode(', ')) {
        $this->error("Países no soportados: {$invalid}");

        return 1;
    }

    $keywords = $this->option('keywords') ?: $this->ask('¿Qué puesto buscar en la demo? (ej. "Laravel developer")');
    $text = $extract->handle($path);

    $user = User::firstOrCreate(
        ['email' => config('jobsearch.demo.user_email')],
        ['name' => 'Demo ATS Boost', 'password' => Str::random(40)],
    );
    $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

    $user->jobSearchProfiles()->whereNotIn('country_code', $countries)->delete();

    foreach ($countries as $country) {
        $profile = $user->jobSearchProfiles()->updateOrCreate(['country_code' => $country], [
            'keywords' => $keywords,
            'include_remote' => true,
            'max_age_days' => 3,
            'is_active' => true,
            'resume_filename' => basename($path),
        ]);

        // Si cambió el CV hay que volver a parsearlo.
        if ($profile->resume_text !== $text) {
            $profile->update(['resume_text' => $text, 'resume_payload' => null]);
        }

        $this->info("Búsqueda demo: {$keywords} · ".Regions::name($country));

        if (! $this->option('no-sync')) {
            $report = $sync->handle($profile);
            $this->line("  {$report['found']} ofertas, {$report['new_matches']} en proceso");
        }
    }

    $this->newLine();
    $this->info('Listo. Los CVs adaptados se generan en la cola: corré `php artisan queue:work` si no hay un worker activo.');
})->purpose('Configura la demo pública de la home con un CV base');

Schedule::command('jobs:sync')
    ->cron(config('jobsearch.sync_cron'))
    ->withoutOverlapping()
    ->onOneServer();
