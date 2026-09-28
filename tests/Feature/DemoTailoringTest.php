<?php

use App\Livewire\Resume\Demo;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CvTailorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function demoValidDescription(): string
{
    return str_repeat('Necesitamos un desarrollador con experiencia en PHP y Laravel. ', 2);
}

function demoValidResume(): UploadedFile
{
    return UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
}

function demoFakeTailorResult(): array
{
    return [
        'cv' => [
            'lang' => 'es',
            'name' => 'Juan Pérez',
            'headline' => 'Backend Developer · PHP · Laravel',
            'contact' => [['label' => 'Montevideo, Uruguay']],
            'sections' => [],
        ],
        'html' => '<html><body><h1>Juan Pérez</h1></body></html>',
        'cvText' => 'texto del cv',
    ];
}

test('tailorResumeDemo blocks the 4th attempt even when called directly, bypassing startTailoring', function () {
    $this->mock(CvTailorService::class, function ($mock) {
        $mock->shouldReceive('tailorResume')
            ->times(3)
            ->andReturn(demoFakeTailorResult());
    });

    $component = Livewire::test(Demo::class)
        ->set('resume', demoValidResume())
        ->set('description', demoValidDescription());

    // Nunca se llama a startTailoring(): se invoca tailorResumeDemo directo,
    // tal como puede hacerlo cualquiera desde la consola del navegador.
    $component->call('tailorResumeDemo');
    $component->call('tailorResumeDemo');
    $component->call('tailorResumeDemo');
    $component->call('tailorResumeDemo'); // 4to intento: debe bloquearse

    $component->assertDispatched('modal-show', name: 'limit-modal');
});

test('tailorResumeDemo does not fatal when called without a file uploaded', function () {
    Livewire::test(Demo::class)
        ->set('description', demoValidDescription())
        ->call('tailorResumeDemo')
        ->assertHasErrors(['resume']);
});

test('the demo limit survives a fresh session (simulated incognito bypass)', function () {
    // Simula que el visitante ya agotó el cupo antes (misma IP), incluso si
    // ahora llega con una sesión completamente nueva.
    RateLimiter::hit('demo-tailor:127.0.0.1', 3600);
    RateLimiter::hit('demo-tailor:127.0.0.1', 3600);
    RateLimiter::hit('demo-tailor:127.0.0.1', 3600);

    $this->mock(CvTailorService::class, function ($mock) {
        $mock->shouldNotReceive('tailorResume');
    });

    Livewire::test(Demo::class)
        ->set('resume', demoValidResume())
        ->set('description', demoValidDescription())
        ->call('tailorResumeDemo')
        ->assertDispatched('modal-show', name: 'limit-modal');
});

test('an authenticated subscribed user has no demo limit', function () {
    $user = User::factory()->create();

    Subscriber::create([
        'user_id' => $user->id,
        'mp_subscription_id' => 'sub_'.$user->id,
        'mp_plan_id' => 'plan_test',
        'status' => 'authorized',
        'active' => true,
        'ends_at' => now()->addMonth(),
    ]);

    $this->mock(CvTailorService::class, function ($mock) {
        $mock->shouldReceive('tailorResume')
            ->times(5)
            ->andReturn(demoFakeTailorResult());
    });

    $component = Livewire::actingAs($user)
        ->test(Demo::class)
        ->set('resume', demoValidResume())
        ->set('description', demoValidDescription());

    for ($i = 0; $i < 5; $i++) {
        $component->call('tailorResumeDemo');
    }

    $component->assertNotDispatched('modal-show', name: 'limit-modal');
});

test('a failed AI call shows an error toast and does not count against the limit', function () {
    $this->mock(CvTailorService::class, function ($mock) {
        $mock->shouldReceive('tailorResume')
            ->once()
            ->andThrow(new RuntimeException('fallo de la API de IA'));
    });

    Livewire::test(Demo::class)
        ->set('resume', demoValidResume())
        ->set('description', demoValidDescription())
        ->call('tailorResumeDemo')
        ->assertDispatched('toast-show');

    expect(RateLimiter::attempts('demo-tailor:127.0.0.1'))->toBe(0);
});

test('downloadPdf sends the CV generated during tailoring, not raw HTML', function () {
    $result = demoFakeTailorResult();

    $this->mock(CvTailorService::class, function ($mock) use ($result) {
        $mock->shouldReceive('tailorResume')
            ->once()
            ->andReturn($result);

        $mock->shouldReceive('downloadPdf')
            ->once()
            ->with($result['cv'])
            ->andReturn(response('%PDF-1.4 contenido falso'));
    });

    Livewire::test(Demo::class)
        ->set('resume', demoValidResume())
        ->set('description', demoValidDescription())
        ->call('tailorResumeDemo')
        ->call('downloadPdf');
});

test('downloadPdf does nothing if no CV has been generated yet', function () {
    $this->mock(CvTailorService::class, function ($mock) {
        $mock->shouldNotReceive('downloadPdf');
    });

    Livewire::test(Demo::class)->call('downloadPdf');
});
