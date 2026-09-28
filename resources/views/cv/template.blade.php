{{--
  ATS Boost — plantilla de CV (A4, una carilla). Versión DomPDF-safe.
  Recibe $cv: el JSON devuelto por Claude (ver resources/prompts/cv-tailor-system.md).

  DomPDF NO soporta flex ni grid: todo el layout de dos columnas va con <table>.
  DomPDF NO descarga Google Fonts: la fuente se registra con @font-face apuntando
  a archivos locales. Ver "Fuente" en las notas al pie de este archivo.
--}}
<!DOCTYPE html>
<html lang="{{ $cv['lang'] ?? 'es' }}">
<head>
<meta charset="utf-8">
<title>{{ $cv['name'] }} - CV</title>
<style>
  /* ---- Fuente ----
     Si existen los .ttf de Source Sans 3 en public/fonts/, se registran acá y
     se usan. Si no están (todavía no se subieron), la plantilla cae directo a
     DejaVu Sans para TODO el documento — a propósito no se deja "Source Sans 3"
     en la pila de font-family cuando falta el archivo: si el navegador/DomPDF
     resuelve el peso regular vía fallback pero el bold no encuentra el 600 de
     "Source Sans 3", el sustituto que elige para negritas/h1/h2 puede no ser el
     mismo que para el texto normal (se vio así en pruebas: nombre y títulos en
     serif, resto en DejaVu Sans). Con la pila unificada esto no pasa.

     Para activarla: subí SourceSans3-Regular.ttf y SourceSans3-SemiBold.ttf a
     public/fonts/ y borrá storage/fonts/* una vez para que DomPDF los recachee. */
  @php
    $sourceSansRegular = public_path('fonts/SourceSans3-Regular.ttf');
    $sourceSansSemiBold = public_path('fonts/SourceSans3-SemiBold.ttf');
    $hasCustomFont = file_exists($sourceSansRegular) && file_exists($sourceSansSemiBold);
  @endphp

  @if ($hasCustomFont)
    @font-face {
      font-family: 'Source Sans 3';
      font-weight: 400; font-style: normal;
      src: url('{{ $sourceSansRegular }}') format('truetype');
    }
    @font-face {
      font-family: 'Source Sans 3';
      font-weight: 600; font-style: normal;
      src: url('{{ $sourceSansSemiBold }}') format('truetype');
    }
  @endif

  @page { size: A4 portrait; margin: 0.6in; }
  body {
    font-family: @if ($hasCustomFont) 'Source Sans 3', @endif 'DejaVu Sans', sans-serif;
    color: #1f1f1f;
    margin: 0;
    font-size: 10.8pt;
    line-height: 1.24;
  }
  a { color: #1f1f1f; text-decoration: none; }

  /* DomPDF le da a <strong>/<b>/<h1>/<h2> una serif por defecto (hoja de estilos
     de usuario propia) — forzamos que hereden la misma familia que el body. */
  strong, b, h1, h2 {
    font-family: @if ($hasCustomFont) 'Source Sans 3', @endif 'DejaVu Sans', sans-serif;
  }

  /* ---- Escala tipográfica. No la toques: es lo que sostiene la carilla ----
     Nota: acá nunca se declara "font-weight" a mano. DomPDF, cuando falla la
     coincidencia de una variante bold/600 para la familia activa, sustituye por
     una serif genérica en vez de una sans — se vio en pruebas (nombre y títulos
     saliendo en serif mientras el resto quedaba en DejaVu Sans). Los tags
     semánticos (<h1>, <h2>, <strong>, <b>) SÍ resuelven bold correctamente sin
     tocar font-weight, así que el negrita sale de la etiqueta, no del CSS. */
  .name    { font-size: 22.5pt; text-align: center; margin: 0 0 3pt; }
  .title   { font-size: 10.4pt; text-align: center; color: #444; margin: 0 0 1pt; }
  .contact { font-size: 10.9pt; text-align: center; color: #444; margin: 0 0 3pt; }

  h2 {
    font-size: 12.3pt;
    border-bottom: 1px solid #c9c9c9;
    padding-bottom: 2pt;
    margin: 6pt 0 3pt;
  }

  /* Fila cargo + fecha: tabla de 2 columnas, la derecha se encoge al contenido */
  table.row { width: 100%; border-collapse: collapse; margin: 0 0 1pt; }
  table.row td { padding: 0; vertical-align: baseline; font-size: 11.7pt; }
  table.row td.date {
    text-align: right; white-space: nowrap;
    font-size: 10.4pt; color: #555; width: 1%;
  }

  .place { font-size: 10.4pt; color: #555; margin: 0 0 4pt; }
  p.body { margin: 0 0 4pt; }
  ul { margin: 0 0 3pt; padding-left: 15pt; }
  li { margin-bottom: 0.5pt; }

  /* Habilidades: etiqueta / valor */
  table.skills { width: 100%; border-collapse: collapse; margin: 0 0 4pt; }
  table.skills td { padding: 0 0 2pt; vertical-align: top; }
  table.skills td.label {
    white-space: nowrap;
    width: 105px; padding-right: 10pt;
  }
</style>
</head>
<body>

  <h1 class="name">{{ $cv['name'] }}</h1>
  <p class="title">{{ $cv['headline'] }}</p>
  <p class="contact">
    @foreach ($cv['contact'] as $i => $c)@if ($i) · @endif@if (!empty($c['url']))<a href="{{ $c['url'] }}">{{ $c['label'] }}</a>@else{{ $c['label'] }}@endif @endforeach
  </p>

  @foreach ($cv['sections'] as $section)
    <h2>{{ $section['heading'] }}</h2>

    @switch($section['type'])

      {{-- Experiencia / Educación --}}
      @case('entries')
        @foreach ($section['items'] as $item)
          <table class="row">
            <tr>
              <td><strong>{{ $item['title'] }}</strong>@if (!empty($item['org'])), {{ $item['org'] }}@endif</td>
              <td class="date">{{ $item['dates'] }}</td>
            </tr>
          </table>
          @if (!empty($item['place']))<p class="place">{{ $item['place'] }}</p>@endif
          @if (!empty($item['note']))<p class="body">{{ $item['note'] }}</p>@endif
          @if (!empty($item['bullets']))
            <ul>
              @foreach ($item['bullets'] as $b)<li>{{ $b }}</li>@endforeach
            </ul>
          @endif
        @endforeach
        @break

      {{-- Proyectos / Certificaciones --}}
      @case('list')
        <ul>
          @foreach ($section['items'] as $item)
            <li><strong>{{ $item['title'] }}</strong> - {{ $item['description'] }}@if (!empty($item['url'])) <a href="{{ $item['url'] }}" style="color:#555">{{ $item['url_label'] ?? $item['url'] }}</a>@endif</li>
          @endforeach
        </ul>
        @break

      {{-- Habilidades --}}
      @case('grid')
        <table class="skills">
          @foreach ($section['items'] as $item)
            <tr>
              <td class="label"><strong>{{ $item['label'] }}</strong></td>
              <td>{{ $item['value'] }}</td>
            </tr>
          @endforeach
        </table>
        @break

    @endswitch
  @endforeach

</body>
</html>

{{--
  ---- Notas de implementación ----

  Render:

    $pdf = Pdf::loadView('cv.template', ['cv' => $cv])
              ->setPaper('a4', 'portrait');
    return $pdf->download($cv['name'].'.pdf');

  Los márgenes los pone @page, no los pases por config.

  Fuente: bajá Source Sans 3 de Google Fonts, dejá los .ttf en public/fonts/
  y borrá storage/fonts/* la primera vez para que DomPDF los registre de nuevo.

  Verificación de una carilla: después de generar, chequeá el conteo de páginas
  antes de entregar el archivo —

    $pages = $pdf->getDomPDF()->getCanvas()->get_page_count();

  Si da > 1, reintentá el tailoring con los máximos del prompt bajados un escalón
  (una viñeta menos por empleo). Nunca achiques la tipografía: rompe la escala.
--}}
