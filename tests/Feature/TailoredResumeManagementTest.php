<?php

use App\Models\TailoredResume;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

test('a user can delete their own tailored resume', function () {
    $user = User::factory()->create();
    $tailoredResume = TailoredResume::create([
        'user_id' => $user->id,
        'title' => 'CV adaptado',
        'job_description' => 'Descripción de prueba',
        'html' => '<h1>CV</h1>',
    ]);

    $response = $this->withoutMiddleware(VerifyCsrfToken::class)
        ->actingAs($user)
        ->delete(route('resume.tailored.destroy', $tailoredResume));

    $response->assertRedirect();
    expect(TailoredResume::find($tailoredResume->id))->toBeNull();
});

test('a user cannot delete another users tailored resume', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $tailoredResume = TailoredResume::create([
        'user_id' => $owner->id,
        'title' => 'CV adaptado',
        'job_description' => 'Descripción de prueba',
        'html' => '<h1>CV</h1>',
    ]);

    $response = $this->withoutMiddleware(VerifyCsrfToken::class)
        ->actingAs($user)
        ->delete(route('resume.tailored.destroy', $tailoredResume));

    $response->assertForbidden();
    expect(TailoredResume::find($tailoredResume->id))->not->toBeNull();
});
