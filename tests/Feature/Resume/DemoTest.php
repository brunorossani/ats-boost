<?php

use App\Livewire\Resume\Demo;
use App\Models\Document;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    RateLimiter::clear('resume-demo:127.0.0.1');
});

it('adapta un CV sin necesidad de cuenta y no lo guarda', function (): void {
    fakeChatResponses(fakeJobPosting(), DocumentFactory::resumePayload(), DocumentFactory::resumePayload());

    Livewire::test(Demo::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee('Bruno Rossani');

    // La prueba pública no persiste nada.
    expect(Document::count())->toBe(0);
});

it('descuenta el cupo contra el limitador y no contra la sesión', function (): void {
    $max = (int) config('resume.limits.demo_generations');

    for ($i = 0; $i < $max; $i++) {
        fakeChatResponses(fakeJobPosting(), DocumentFactory::resumePayload(), DocumentFactory::resumePayload());

        Livewire::test(Demo::class)
            ->set('form.resume', fakeResumeUpload())
            ->set('form.description', fakeJobDescription())
            ->call('generate');
    }

    // Un componente nuevo simula un navegador recién abierto: la sesión ya no
    // guarda el contador, así que borrar cookies no devuelve pruebas gratis.
    Livewire::test(Demo::class)
        ->assertSet('remaining', 0)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate')
        ->assertDispatched('demo-limit-reached')
        ->assertSet('result', null);
});

it('no consume cupo cuando falla la llamada al modelo', function (): void {
    fakeChatResponses();

    $before = RateLimiter::remaining('resume-demo:127.0.0.1', (int) config('resume.limits.demo_generations'));

    Livewire::test(Demo::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate');

    expect(RateLimiter::remaining('resume-demo:127.0.0.1', (int) config('resume.limits.demo_generations')))
        ->toBe($before);
});

it('permite volver a empezar', function (): void {
    fakeChatResponses(fakeJobPosting(), DocumentFactory::resumePayload(), DocumentFactory::resumePayload());

    Livewire::test(Demo::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate')
        ->assertSet('result', fn (?array $result): bool => $result !== null)
        ->call('startOver')
        ->assertSet('result', null)
        ->assertSet('form.description', '');
});
