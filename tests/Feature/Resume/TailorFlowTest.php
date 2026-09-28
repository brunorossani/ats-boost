<?php

use App\Enums\DocumentType;
use App\Livewire\Resume\Tailor;
use App\Models\Document;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = subscribedUser();
});

it('genera un currículum adaptado y lo guarda como documento estructurado', function (): void {
    fakeChatResponses(
        fakeJobPosting(),                     // lectura de la oferta
        DocumentFactory::resumePayload(),     // parseo del CV
        DocumentFactory::resumePayload(),     // adaptación
    );

    Livewire::actingAs($this->user)
        ->test(Tailor::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate')
        ->assertHasNoErrors()
        ->assertRedirect();

    $document = Document::sole();

    expect($document->type)->toBe(DocumentType::TailoredResume)
        ->and($document->user_id)->toBe($this->user->id)
        ->and($document->role)->toBe('Desarrollador Backend')
        ->and($document->company)->toBe('Acme')
        ->and($document->source_filename)->toBe('cv.txt')
        // El payload es el documento estructurado, no HTML del modelo.
        ->and($document->payload['full_name'])->toBe('Bruno Rossani')
        ->and($document->payload['experience'][0]['company'])->toBe('Multiline Contact Center')
        ->and($document->resume()->skills)->toHaveCount(3);
});

it('recorta las viñetas que exceden el máximo por puesto', function (): void {
    $payload = DocumentFactory::resumePayload();
    $payload['experience'][0]['bullets'] = array_map(
        fn (int $i): string => "Logro numero {$i} del puesto",
        range(1, 9),
    );

    fakeChatResponses(fakeJobPosting(), $payload, $payload);

    Livewire::actingAs($this->user)
        ->test(Tailor::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate');

    expect(Document::sole()->resume()->experience[0]->bullets)
        ->toHaveCount(config('resume.limits.bullets_per_entry'));
});

it('exige currículum y descripción', function (): void {
    Livewire::actingAs($this->user)
        ->test(Tailor::class)
        ->call('generate')
        ->assertHasErrors(['form.resume' => 'required', 'form.description' => 'required']);

    expect(Document::count())->toBe(0);
});

it('rechaza una descripción demasiado corta para adaptar nada', function (): void {
    Livewire::actingAs($this->user)
        ->test(Tailor::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', 'Se busca dev.')
        ->call('generate')
        ->assertHasErrors(['form.description' => 'min']);
});

it('rechaza archivos que no son PDF ni TXT', function (): void {
    Livewire::actingAs($this->user)
        ->test(Tailor::class)
        ->set('form.resume', Illuminate\Http\UploadedFile::fake()->image('cv.png'))
        ->set('form.description', fakeJobDescription())
        ->call('generate')
        ->assertHasErrors('form.resume');
});

it('avisa al usuario y no guarda nada cuando el modelo falla', function (): void {
    // Sin respuestas encoladas, el cliente falso lanza excepción en la
    // primera llamada, igual que una caída real de la API.
    fakeChatResponses();

    Livewire::actingAs($this->user)
        ->test(Tailor::class)
        ->set('form.resume', fakeResumeUpload())
        ->set('form.description', fakeJobDescription())
        ->call('generate')
        ->assertNoRedirect();

    expect(Document::count())->toBe(0);
});

it('bloquea el acceso a quien no tiene suscripción vigente', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('resume.tailor'))
        ->assertRedirect(route('subscriptions.edit'));
});

it('deja entrar a quien sí la tiene', function (): void {
    $this->actingAs($this->user)
        ->get(route('resume.tailor'))
        ->assertOk();
});
