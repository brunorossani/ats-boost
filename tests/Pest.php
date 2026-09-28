<?php

use Anthropic\Client;
use App\Models\Subscriber;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers del dominio
|--------------------------------------------------------------------------
*/

/**
 * Un usuario con suscripción vigente.
 */
function subscribedUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    Subscriber::factory()->for($user)->create();

    return $user->refresh();
}

/**
 * Encola respuestas de Claude, en orden.
 *
 * Se usa el cliente real del SDK con un transporte HTTP falso: así el test
 * también ejercita cómo se arma el pedido. Cada payload viaja como el JSON
 * del bloque de texto, que es lo que devuelve la API con salida estructurada.
 * Sin payloads, el primer pedido falla como una caída real de la API.
 *
 * @param  array<string, mixed>  ...$payloads
 */
function fakeChatResponses(array ...$payloads): void
{
    $history = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler(array_map(
        fn (array $payload): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_fake',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-test',
            'content' => [['type' => 'text', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])),
        $payloads,
    )));
    $stack->push(Middleware::history($history));

    app()->instance('tests.chat-history', $history);
    app()->instance(Client::class, new Client(
        apiKey: 'test-key',
        requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
    ));
}

/**
 * Cuerpos JSON de los pedidos enviados a Claude desde el último fakeChatResponses().
 *
 * @return list<array<string, mixed>>
 */
function sentChatRequests(): array
{
    return array_map(
        fn (array $entry): array => json_decode((string) $entry['request']->getBody(), true),
        app('tests.chat-history')->getArrayCopy(),
    );
}

/**
 * Un CV de prueba con texto suficiente para pasar el umbral de extracción.
 */
function fakeResumeUpload(string $name = 'cv.txt'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, <<<'TXT'
    Bruno Rossani
    Desarrollador de Software
    Montevideo, Uruguay · brossani23@gmail.com · +598 91 845 585

    Experiencia
    Desarrollador de Software, Multiline Contact Center, May 2026 - Presente
    Desarrollo funcionalidades en PHP y Laravel para los sistemas internos.
    Mantengo mas de 12 aplicaciones criticas para 5 clientes corporativos.

    Educacion
    Tecnologo Informatico, Universidad Tecnologica del Uruguay, 2024 - 2027

    Habilidades
    PHP, Laravel, Livewire, Vue.js, MySQL, PostgreSQL, Docker, Git
    TXT);
}

/**
 * Una oferta lo bastante larga para pasar la validación de 80 caracteres.
 */
function fakeJobDescription(): string
{
    return 'Buscamos un desarrollador backend con experiencia en PHP y Laravel para sumarse a nuestro '
        .'equipo de producto. Vas a trabajar con Livewire, MySQL y Docker, participando del ciclo '
        .'completo de desarrollo, desde el analisis de requerimientos hasta el despliegue.';
}

/**
 * Lectura de oferta que devolvería AnalyzeJobPosting.
 *
 * @return array<string, mixed>
 */
function fakeJobPosting(): array
{
    return [
        'role' => 'Desarrollador Backend',
        'company' => 'Acme',
        'seniority' => 'semi senior',
        'keywords' => ['PHP', 'Laravel', 'Livewire', 'MySQL', 'Docker'],
        'requirements' => ['Experiencia con PHP y Laravel'],
        'responsibilities' => ['Desarrollar funcionalidades de producto'],
    ];
}
