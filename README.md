# ATS Boost

Adapt your CV to match any job posting in seconds.

## Tech Stack

[![Laravel](https://img.shields.io/badge/Laravel-11-F05340?logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3-4E56A6?logo=livewire)](https://livewire.laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)](https://www.php.net)
[![SQLite](https://img.shields.io/badge/SQLite-3-003B57?logo=sqlite)](https://www.sqlite.org)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3-38B2AC?logo=tailwindcss)](https://tailwindcss.com)

## Features

- Instant CV tailoring to specific job descriptions.
- AI‑powered keyword extraction and matching.
- Live preview of the adapted resume.
- Multiple export formats (PDF, DOCX, HTML).
- Subscription management with Mercado Pago integration.
- Secure authentication with email verification.

## Ofertas del día con CV adaptado

La home (`/`) es una demo en vivo: ofertas reales de los últimos días en varios países, cada una con un CV adaptado. Cada usuario suscripto configura sus búsquedas en `/panel/ofertas`.

**Flujo:** `jobs:sync` (por defecto 07:17 y 19:17) → para cada búsqueda consulta solo los portales que cubren ese país → descarta ofertas viejas o de zonas remotas que no aceptan al candidato → deduplica entre portales → `ScoreJobMatch` puntúa la compatibilidad → si supera `JOB_SEARCH_MIN_MATCH_SCORE`, `TailorResume` genera el CV con las convenciones del país (`app/JobSearch/Regions.php`) y se guarda como un `Document` más.

| Región | Fuentes |
|---|---|
| LatAm | Google Jobs (JSearch), Get on Board, Jooble, Adzuna (BR/MX), Remotive |
| Norteamérica | Google Jobs, Adzuna, Jooble, Remotive |
| Europa | Google Jobs, Adzuna, Arbeitnow, Jooble, Remotive |
| Todas | Páginas de empleo de empresas (Greenhouse, Lever, Ashby) en `config/jobsearch.php` |

**Configuración**

1. `.env`: `OPENAI_API_KEY`, `JSEARCH_API_KEY` (RapidAPI), `ADZUNA_APP_ID`/`ADZUNA_APP_KEY`, `JOOBLE_API_KEY`. Get on Board, Arbeitnow y Remotive no necesitan clave; un portal sin clave se omite.
2. `php artisan migrate`, más `php artisan queue:work` y el scheduler (`php artisan schedule:work` en local, cron `schedule:run` en el servidor).
3. Demo de la home: `php artisan jobs:demo ruta/al/cv.pdf --keywords="Laravel developer" --countries=uy,us,es`.
4. Ver qué devuelve cada portal: `php artisan jobs:sync`.

## Installation

1. Clone the repository: `git clone https://github.com/elkiki99/ats-boost.git`
2. Install dependencies: `composer install && npm install`
3. Copy the environment file: `cp .env.example .env`
4. Generate the application key: `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Start the development server: `php artisan serve`
