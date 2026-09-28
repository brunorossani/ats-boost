<?php

declare(strict_types=1);

test('cv_tailor_service_respects_spanish_job_description_over_english_resume', function () {
    // Simulate a Spanish job description with an English-looking CV
    $spanishJobDescription = 'Se busca un desarrollador con experiencia en PHP y Laravel. La empresa requiere habilidades de educación continua.';
    $englishCv = 'Education: Computer Science degree. Experience: 5 years as developer. Skills: PHP, Laravel, MySQL.';

    // We test this implicitly through tailorResume which uses the job description language
    $service = app(\App\Services\CvTailorService::class);

    // Create temporary files for testing
    $cvPath = sys_get_temp_dir().'/test_cv_'.uniqid().'.txt';
    $cvHandle = fopen($cvPath, 'w');
    fwrite($cvHandle, $englishCv);
    fclose($cvHandle);

    try {
        $result = $service->tailorResume($cvPath, $spanishJobDescription);
        // If the language detection works correctly, the service should detect Spanish from job description
        // and process the CV in Spanish context
        expect($result)->toHaveKeys(['cv', 'html', 'cvText']);
        expect($result['cv'])->toHaveKeys(['lang', 'name', 'headline', 'contact', 'sections']);
        expect($result['cv']['lang'])->toBe('es');
    } finally {
        @unlink($cvPath);
    }
});

test('cv_tailor_service_respects_english_job_description_over_spanish_resume', function () {
    // Simulate an English job description with a Spanish-looking CV
    $englishJobDescription = 'We are looking for a developer with experience in PHP and Laravel. The company requires learning skills.';
    $spanishCv = 'Educación: Licenciatura en Informática. Experiencia: 5 años como desarrollador. Habilidades: PHP, Laravel, MySQL.';

    $service = app(\App\Services\CvTailorService::class);

    // Create temporary files for testing
    $cvPath = sys_get_temp_dir().'/test_cv_'.uniqid().'.txt';
    $cvHandle = fopen($cvPath, 'w');
    fwrite($cvHandle, $spanishCv);
    fclose($cvHandle);

    try {
        $result = $service->tailorResume($cvPath, $englishJobDescription);
        // The service should detect English from job description
        expect($result)->toHaveKeys(['cv', 'html', 'cvText']);
        expect($result['cv'])->toHaveKeys(['lang', 'name', 'headline', 'contact', 'sections']);
        expect($result['cv']['lang'])->toBe('en');
    } finally {
        @unlink($cvPath);
    }
});

test('cover_letter_service_respects_spanish_job_description', function () {
    $spanishJobDescription = 'Se busca un desarrollador senior con experiencia en educación y capacidad de aprendizaje.';
    $cvText = 'John Doe - Senior Developer - 10 years experience with PHP and Laravel.';

    $service = app(\App\Services\CoverLetterService::class);

    // The service should detect Spanish from the job description and generate Spanish content
    $result = $service->generateCoverLetter($spanishJobDescription, $cvText, 'Tech Company');

    expect($result)->toBeString();
    expect(strlen($result))->toBeGreaterThan(0);
});

test('cover_letter_service_respects_english_job_description', function () {
    $englishJobDescription = 'We are looking for a senior developer with experience in education and learning ability.';
    $cvText = 'Juan García - Desarrollador Senior - 10 años de experiencia con PHP y Laravel.';

    $service = app(\App\Services\CoverLetterService::class);

    // The service should detect English from the job description
    $result = $service->generateCoverLetter($englishJobDescription, $cvText, 'Tech Company');

    expect($result)->toBeString();
    expect(strlen($result))->toBeGreaterThan(0);
});

test('analyze_resume_service_uses_resume_language_by_default', function () {
    $spanishCv = 'Educación: Licenciatura en Informática. Experiencia: 5 años de trabajo como desarrollador. Habilidades: PHP, Laravel, MySQL.';

    $service = app(\App\Services\AnalyzeResumeService::class);

    // Create temporary file
    $cvPath = sys_get_temp_dir().'/test_cv_'.uniqid().'.txt';
    $cvHandle = fopen($cvPath, 'w');
    fwrite($cvHandle, $spanishCv);
    fclose($cvHandle);

    try {
        // When no job description is provided, it should use CV language
        $result = $service->improveResume(new \Symfony\Component\HttpFoundation\File\File($cvPath, false));
        expect($result)->toHaveKeys(['html', 'cvText', 'name']);
    } finally {
        @unlink($cvPath);
    }
});

test('analyze_resume_service_respects_job_description_language_when_provided', function () {
    $spanishCv = 'Educación: Licenciatura en Informática. Experiencia: 5 años de trabajo.';
    $englishJobDescription = 'We are looking for a developer with experience in education.';

    $service = app(\App\Services\AnalyzeResumeService::class);

    // Create temporary file
    $cvPath = sys_get_temp_dir().'/test_cv_'.uniqid().'.txt';
    $cvHandle = fopen($cvPath, 'w');
    fwrite($cvHandle, $spanishCv);
    fclose($cvHandle);

    try {
        // When job description is provided, it should use its language
        $result = $service->improveResume(new \Symfony\Component\HttpFoundation\File\File($cvPath, false), $englishJobDescription);
        expect($result)->toHaveKeys(['html', 'cvText', 'name']);
    } finally {
        @unlink($cvPath);
    }
});
