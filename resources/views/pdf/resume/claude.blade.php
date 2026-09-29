{{--
Plantilla «Claude Design».

Replica el diseño creado en Claude Design: encabezado centrado,
secciones con bordes inferiores, fechas alineadas a la derecha,
y grid de dos columnas para skills.

@var \App\Data\ResumeData $resume
@var array $metrics
@var string $font
@var string $locale
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">

<head>
    <meta charset="utf-8">
    <title>{{ $resume->displayName() }}</title>

    <style>
        @page {
            margin: {
                    {
                    $metrics['margin']
                }
            }

            ;
        }

        body {
            font-family: {
                    {
                    $font
                }
            }

            ,
            sans-serif;

            font-size: {
                    {
                    $metrics['base']
                }
            }

            pt;
            line-height: 1.24;
            color: #1f1f1f;
            margin: 0;
        }

        /* ---------- Encabezado ---------- */

        .name {
            font-size: 22.5pt;
            font-weight: 600;
            text-align: center;
            margin: 0 0 3pt;
            color: #1f1f1f;
        }

        .headline {
            font-size: 10.4pt;
            text-align: center;
            color: #444;
            margin: 0 0 3pt;
        }

        .contact {
            font-size: 10.9pt;
            text-align: center;
            color: #444;
            margin: 0 0 6pt;
        }

        .contact a {
            color: #444;
            text-decoration: none;
        }

        /* ---------- Secciones ---------- */

        .section {
            margin-top: 5.5pt;
        }

        .section-title {
            font-size: 12.3pt;
            font-weight: 600;
            color: #1f1f1f;
            margin: 0 0 2.5pt;
            padding-bottom: 2pt;
            border-bottom: 1pt solid #c9c9c9;
        }

        /* ---------- Entradas ---------- */

        .entry {
            margin-bottom: 3pt;
            page-break-inside: avoid;
        }

        .entry:last-child {
            margin-bottom: 0;
        }

        .entry-header {
            font-size: 11.7pt;
            margin: 0 0 1pt;
        }

        .entry-dates {
            float: right;
            font-size: 10.4pt;
            color: #555;
            padding-left: 8pt;
            white-space: nowrap;
        }

        .entry-title {
            font-weight: 600;
        }

        .entry-company {
            font-weight: normal;
        }

        .entry-location {
            font-size: 10.4pt;
            color: #555;
            margin: 0 0 4pt;
        }

        /* ---------- Listas ---------- */

        ul {
            font-size: 10.8pt;
            line-height: 1.24;
            margin: 0 0 3pt;
            padding-left: 15pt;
        }

        li {
            margin-bottom: 0.5pt;
        }

        .inline-name {
            font-weight: 600;
        }

        .muted {
            color: #555;
        }

        /* ---------- Skills Grid ---------- */

        .skills-grid {
            font-size: 10.8pt;
            line-height: 1.24;
            margin: 0 0 4pt;
        }

        .skill-row {
            margin-bottom: 2pt;
        }

        .skill-label {
            font-weight: 600;
            display: inline-block;
            min-width: 105pt;
            vertical-align: top;
        }

        .skill-value {
            display: inline;
        }
    </style>
</head>

<body>
    @php
    $section = fn (string $key): string => __("resume.sections.{$key}", [], $locale);
    @endphp

    <h1 class="name">{{ $resume->displayName() }}</h1>

    @if ($resume->headline)
    <p class="headline">{{ $resume->headline }}</p>
    @endif

    <p class="contact">{{ $resume->displayContactLine() }}</p>

    @if ($resume->summary)
    <div class="section">
        <h2 class="section-title">{{ $section('summary') }}</h2>
        <p style="margin: 0; font-size: 10.8pt; line-height: 1.24;">{{ $resume->summary }}</p>
    </div>
    @endif

    @if ($resume->experience)
    <div class="section">
        <h2 class="section-title">{{ $section('experience') }}</h2>

        @foreach ($resume->experience as $entry)
        <div class="entry">
            @if ($entry->dates)
            <span class="entry-dates">{{ $entry->dates }}</span>
            @endif

            <p class="entry-header">
                <span class="entry-title">{{ $entry->role }}</span>@if ($entry->company), <span class="entry-company">{{
                    $entry->company }}</span>@endif
            </p>

            @if ($entry->location)
            <p class="entry-location">{{ $entry->location }}</p>
            @endif

            @if ($entry->bullets)
            <ul>
                @foreach ($entry->bullets as $bullet)
                <li>{{ $bullet }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    @if ($resume->education)
    <div class="section">
        <h2 class="section-title">{{ $section('education') }}</h2>

        @foreach ($resume->education as $entry)
        <div class="entry">
            @if ($entry->dates)
            <span class="entry-dates">{{ $entry->dates }}</span>
            @endif

            <p class="entry-header">
                <span class="entry-title">{{ $entry->degree }}</span>@if ($entry->institution), <span
                    class="entry-company">{{ $entry->institution }}</span>@endif
            </p>

            @if ($entry->location)
            <p class="entry-location">{{ $entry->location }}</p>
            @endif

            @if ($entry->description)
            <p style="font-size: 10.8pt; line-height: 1.24; margin: 0 0 4pt;">{{ $entry->description }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    @if ($resume->projects)
    <div class="section">
        <h2 class="section-title">{{ $section('projects') }}</h2>

        <ul>
            @foreach ($resume->projects as $project)
            <li>
                <span class="inline-name">{{ $project->name }}</span>@if ($project->description) — {{
                $project->description }}@endif
                @if ($project->meta) <span class="muted">{{ $project->meta }}</span>@endif
                @if ($project->link) <span class="muted">{{ $project->link }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($resume->skills)
    <div class="section">
        <h2 class="section-title">{{ $section('skills') }}</h2>

        <div class="skills-grid">
            @foreach ($resume->skills as $skill)
            <div class="skill-row">
                <span class="skill-label">{{ $skill->label }}</span>
                <span class="skill-value">{{ $skill->value }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if ($resume->certifications)
    <div class="section">
        <h2 class="section-title">{{ $section('certifications') }}</h2>

        <ul style="margin: 0; padding-left: 15pt;">
            @foreach ($resume->certifications as $cert)
            <li>
                {{ $cert->name }}@if ($cert->issuer) — {{ $cert->issuer }}@endif
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</body>

</html>