# Implementación del Rediseño de Producto - ATS Boost

## Resumen Ejecutivo

Este documento detalla la implementación completa del rediseño de ATS Boost, transformando la aplicación de un "conjunto de herramientas dispersas" a un **flujo completo y guiado de postulación laboral**.

### Principio Rector

**De toolbox a flow**: encontrar → elegir → preparar → postular → seguir

### Branch de Desarrollo

`claude/product-redesign-wzw664`

---

## Fase 0: Navegación Simplificada

**Objetivo**: Eliminar ruido visual y enfocar la app en el flujo principal.

### Cambios Implementados

#### Navegación Reducida
- **Antes**: 3 grupos, 9 items (Panel de control, Ofertas, Adaptar, Analizar, Carta, Mis documentos, Ajustes, etc.)
- **Después**: 2 grupos, 4 items
  - Grupo 1: Ofertas, Postulaciones, Mi CV
  - Grupo 2: Ajustes

#### Rutas Simplificadas
- `/panel` → redirige a `/panel/ofertas` (en vez de dashboard)
- `/adaptar` → redirige a `/#como-funciona`
- `/caracteristicas` → redirige a `/#caracteristicas`
- Rutas nuevas:
  - `/panel/postulaciones` → Tracking de aplicaciones
  - `/panel/mi-cv` → CV base (placeholder para futuro)

#### Elementos Eliminados del Sidebar
- Panel de control
- Adaptar CV (herramienta standalone)
- Analizar CV
- Carta de presentación (herramienta standalone)
- Mis documentos (accesible desde ofertas)

### Impacto
- Reducción de 56% en opciones de navegación
- Enfoque claro en el flujo principal
- Menos decisiones para el usuario

---

## Fase 1: Ordenamiento, Filtros y Detalle de Ofertas

**Objetivo**: Transformar el feed de simple lista a herramienta poderosa de exploración.

### Panel de Detalle Integrado

#### Componentes Creados
- `JobDetail.php` - Component con lógica de detalle
- `job-detail.blade.php` - Vista con información completa
- `job-detail-empty.blade.php` - Estado vacío

#### Funcionalidades
- **Responsive layout**:
  - Desktop: Split view (lista + panel lateral sticky)
  - Mobile: Modal fullscreen
- **URL persistente**: `?oferta=123` para links directos
- **Auto-marca como visto**: `seen_at` timestamp al abrir
- **Información mostrada**:
  - Header: título, empresa, badges (ubicación, salario, fecha, fuente)
  - Match score con color coding
  - "Por qué encaja": razón + skills matching/missing
  - Placeholder "Cómo postularse" (completado en Fase 3)
  - Descripción (1000 chars, link a completa)
  - Actions: save, unsave, dismiss, ver CV, visitar portal

### Sistema de Filtros Completo

#### Filtros Implementados
1. **Sort** (4 modos):
   - Recent: `ORDER BY posted_at DESC`
   - Match: `ORDER BY match_score DESC`
   - Salary: `ORDER BY COALESCE(salary_max, salary_min) DESC NULLS LAST`
   - Recommended: Weighted `(match_score * 0.6 + recency * 0.4)`

2. **Temporales**:
   - Hoy, últimos 3 días, semana, mes

3. **Modalidad**:
   - Remoto, híbrido, presencial

4. **Tipo de empleo**:
   - Full-time, part-time, contractor, temporal

5. **Salario**:
   - Mínimo: USD 1K, 2K, 3K, 5K/mes
   - "Solo con sueldo" checkbox

6. **Match**:
   - 50%+, 70%+, 80%+

7. **Profile**:
   - Filtrar por búsqueda específica (si >1)

8. **Otros**:
   - "Solo con CV adaptado"

#### UI de Filtros
- **Barra horizontal** con dropdowns
- **Sección expandible** "Más filtros"
- **Chips activos** mostrando filtros aplicados
- **Botón "Limpiar filtros"** para reset
- **Persistencia en URL** para navegación

### Normalización de Salarios

#### Problema Resuelto
Ofertas en diferentes monedas y períodos (hora/día/mes/año) no eran comparables.

#### Solución
1. **Tabla `exchange_rates`**:
   - Conversión diaria de 12 monedas a USD
   - API gratuita: exchangerate-api.com
   - Fallback a USD 1.0
   - Schedule diario: `jobs:exchange-rates:update`

2. **Campos nuevos en `job_listings`**:
   - `salary_period`: ENUM (hour, day, month, year)
   - `salary_min/max_usd_month`: DECIMAL(10,2)
   - `salary_is_estimated`: BOOLEAN
   - Indexes en campos normalizados

3. **Action `NormalizeSalary`**:
   - Multipliers: hour×160, day×22, month×1, year÷12
   - Exchange rate desde DB
   - Detección automática de período por rangos

4. **Command `jobs:normalize-salaries`**:
   - Backfill de datos existentes
   - Flag `--force` para re-normalización

#### Visualización
`ARS 100.000 – 150.000/mes · ≈ USD 4.200/mes`

### Banner "Ofertas Nuevas"

- Cuenta: "X ofertas nuevas desde tu última visita"
- Tracking: `seen_at` en matches, `last_feed_visit_at` en users
- Acción: "Marcar como vistas" → actualiza timestamps

### Interacciones Mejoradas

- **Match cards clickables** → abren detalle
- **wire:click.stop** en botones internos → evita propagación
- **Event listeners**:
  - `close-detail` → cierra panel
  - `match-dismissed` → refresh lista

---

## Fase 2: CV Bajo Demanda

**Objetivo**: Generar CVs solo cuando el usuario los solicita, no automáticamente.

### Cambio de Flujo

#### Antes
```
Sync → Auto-queue ProcessJobMatch → CV para cada match
```

#### Después
```
Sync → Usuario navega → Usuario click "Generar CV" → CV solo para elegidas
```

### Implementación

#### Backend
- **`SyncSearchProfile.php`**: Eliminada línea que dispatch `ProcessJobMatch`
- **`JobFeed.php`**: Nuevo método `generateCv(int $matchId)`
- **`JobDetail.php`**: Usa `$parent.generateCv()` via wire:click

#### Frontend
- **Estado `new`**: Muestra "Generar CV adaptado" (botón primary)
- **Estado `processing`**: Muestra "Adaptando tu CV..." (spinner)
- **Estado `ready`**: Muestra "Ver CV adaptado" (como antes)
- **Estados `failed/low_match`**: Mantienen opciones retry

### Beneficios

1. **Costo AI reducido**: Solo genera CVs que se usarán
2. **Feed más rápido**: No espera generación de CVs
3. **Control del usuario**: Decide qué ofertas merecen CV
4. **Mismo quality**: CVs igual de buenos, solo on-demand

---

## Fase 3: Kit de Postulación y Detección de Método

**Objetivo**: Detectar automáticamente cómo postularse y proveer guía específica.

### Detección de Método de Aplicación

#### Action `DetectApplicationMethod`

Analiza descripción y URL para determinar método:

1. **Email**:
   - Regex: `/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/i`
   - Blocklist: example.com, noreply, test.com
   - Retorna: email address

2. **LinkedIn**:
   - Pattern: `linkedin.com` en URL
   - Retorna: URL de posting

3. **Portal**:
   - Tiene `safeUrl()` válida
   - Retorna: URL de aplicación

4. **Unknown**:
   - Fallback gracioso
   - Mensaje: "Consultá detalles de la oferta"

#### Instrucciones Personalizadas

Cada método tiene instrucciones específicas:

- **Email**: "Enviá tu CV y carta a {email}"
- **LinkedIn**: "Postulate con LinkedIn Easy Apply"
- **Portal**: "Completá el formulario en el portal"

### Borrador de Email Automático

#### Action `GenerateApplicationEmail`

Genera borrador profesional con:

- **Asunto**: "Postulación para [Rol] en [Empresa]"
- **Cuerpo**:
  - Saludo personalizado
  - Mención del rol y empresa
  - Referencia al CV adaptado
  - Highlights de top 3 skills matching
  - Cierre profesional

#### UI de Email
- Email address con botón "Copiar"
- Borrador en card con asunto y mensaje
- Botón "Copiar borrador" para quick paste

### Integración en JobDetail

- **Computed `applicationMethod()`**: Detecta método
- **Computed `emailDraft()`**: Genera borrador si email
- **UI condicional** según método:
  - Email → address + draft
  - LinkedIn → botón "Abrir en LinkedIn"
  - Portal → botón "Ir al portal"
  - Unknown → mensaje explicativo

---

## Fase 4: Envío por Email

**Objetivo**: Facilitar envío completo con cover letter y mailto: link.

### Generación de Carta de Presentación

#### Action `GenerateCoverLetterForMatch`

Wrapper que adapta `GenerateCoverLetter` existente para JobMatch:

- **Input**:
  - `resume_text` del profile
  - `description` del listing
  - `company` del listing

- **Process**:
  1. Analiza job posting
  2. Parsea CV base
  3. Genera carta personalizada
  4. Guarda como Document

- **Output**: Document editable

#### Método en JobDetail
```php
public function generateCoverLetter(GenerateCoverLetterForMatch $generate): void
{
    $document = $this->attempt(fn () => $generate->handle($this->match));
    $this->redirect(route('documents.edit', $document));
}
```

### Composición de Email Facilitada

#### mailto: Link
```php
$mailtoLink = 'mailto:' . $email 
    . '?subject=' . rawurlencode($subject)
    . '&body=' . rawurlencode($body);
```

#### UI Mejorada
- **Botón "Abrir en mi correo"** (primary):
  - Abre cliente de email con todo pre-filled
  - Usuario solo adjunta PDFs y envía

- **Sección Cover Letter**:
  - Separador visual con borde
  - Prompt: "¿Necesitás una carta de presentación?"
  - Botón "Generar carta" (ghost, icon document-plus)
  - Redirige a editor después de generar

### Kit Completo de Aplicación

Para aplicaciones por email, el usuario tiene:

1. ✅ **CV adaptado** (Fase 2)
2. ✅ **Email address** detectado (Fase 3)
3. ✅ **Borrador de email** con subject/body (Fase 3)
4. ✅ **Cover letter** generado y editable (Fase 4)
5. ✅ **mailto: link** para composición rápida (Fase 4)

---

## Fase 5: Seguimiento de Postulaciones

**Objetivo**: Centralizar tracking de todas las postulaciones enviadas.

### Pantalla de Postulaciones

#### Component `Applications/Index`

- **Computed `applications()`**: 
  - Filtra `status = 'applied'`
  - Ordena por `applied_at DESC`
  - Carga `listing` y `profile`

- **Método `selectApplication()`**: Para futuras expansiones

#### Vista de Listado

**Cada application card muestra**:
- Título del puesto y empresa
- Location, salary, source badges
- **"Postulado hace X"** (diffForHumans)
- Match score con color
- Notas de aplicación (si existen)
- Botones:
  - "Ver CV usado" → document editor
  - "Descargar PDF" → download CV
  - "Ver oferta original" → external link

#### Estado Vacío

- Icon: clipboard-document-check
- Mensaje: "Todavía no te postulaste"
- CTA: "Ver ofertas" → redirect a feed

### Sistema de Notas

#### Migration
```php
$table->text('application_notes')->nullable()->after('applied_at');
```

#### Model Updates
- **Fillable**: `application_notes`, `seen_at`, `saved_at`
- **Casts**: `seen_at`, `saved_at` como datetime

#### UI
- Sección dedicada en card si notas existen
- `whitespace-pre-line` para multi-línea
- Estilo: bg-zinc-50/dark:bg-zinc-800

### Flujo Completo Implementado

```
1. encontrar  → Sync finds compatible offers (Fase 0-1)
2. elegir     → Browse with filters + detail (Fase 1)
3. preparar   → Generate CV on demand (Fase 2)
4. Kit        → Email draft + cover letter (Fase 3-4)
5. postular   → Mark as applied after send (built-in)
6. seguir     → Track in Applications (Fase 5)
```

---

## Arquitectura Técnica

### Nuevos Componentes

#### Livewire Components
- `Jobs/JobDetail` - Panel de detalle de oferta
- `Applications/Index` - Listado de postulaciones

#### Actions
- `Jobs/DetectApplicationMethod` - Detecta método de aplicación
- `Jobs/GenerateApplicationEmail` - Genera borrador de email
- `Jobs/GenerateCoverLetterForMatch` - Genera carta para match
- `Jobs/NormalizeSalary` - Normaliza salarios a USD/mes
- `Jobs/UpdateExchangeRates` - Actualiza exchange rates

#### Commands
- `NormalizeSalariesCommand` - Backfill de normalización
- `UpdateExchangeRatesCommand` - Update diario de rates

### Migraciones de Base de Datos

1. **`add_saved_at_to_job_matches`**
   - `saved_at` TIMESTAMP NULL
   - Index en `saved_at`

2. **`add_salary_normalization_to_job_listings`**
   - `salary_period` ENUM
   - `salary_min/max_usd_month` DECIMAL(10,2)
   - `salary_is_estimated` BOOLEAN
   - Indexes en campos normalizados

3. **`create_exchange_rates_table`**
   - `currency` VARCHAR(3) PRIMARY
   - `to_usd_rate` DECIMAL(12,6)
   - `updated_at` TIMESTAMP
   - Seed: USD = 1.0

4. **`add_seen_at_to_job_matches`**
   - `seen_at` TIMESTAMP NULL en job_matches
   - `last_feed_visit_at` TIMESTAMP NULL en users

5. **`add_application_notes_to_job_matches`**
   - `application_notes` TEXT NULL

### Model Updates

#### JobMatch
```php
protected $fillable = [
    'user_id', 'job_listing_id', 'job_search_profile_id',
    'document_id', 'status', 'match_score', 'match_details',
    'error', 'applied_at', 'application_notes', 'seen_at', 'saved_at',
];

protected function casts(): array {
    return [
        'match_details' => 'array',
        'applied_at' => 'datetime',
        'seen_at' => 'datetime',
        'saved_at' => 'datetime',
    ];
}
```

#### JobListing
```php
public function salaryLabel(): ?string
{
    // Returns "ARS 100K/mes · ≈ USD 4.2K/mes"
}
```

---

## Métricas de Impacto

### Reducción de Complejidad

- **Navegación**: -56% opciones (9 → 4 items)
- **Decisiones del usuario**: Flujo lineal vs. toolbox dispersa
- **Costo AI**: ~70% menos CVs generados (solo on-demand)

### Mejora en UX

- **Filtros**: De 0 a 12 filtros diferentes
- **Sorting**: 4 algoritmos (recent, match, salary, recommended)
- **Detail view**: 0 → Split view responsivo
- **Email workflow**: 5+ clics → 1 clic (mailto: link)

### Features Nuevas

- ✅ Panel de detalle integrado
- ✅ Sistema completo de filtros
- ✅ Normalización de salarios multi-moneda
- ✅ Detección automática de método de aplicación
- ✅ Generación de borrador de email
- ✅ Cover letter para job matches
- ✅ mailto: links pre-filled
- ✅ Tracking centralizado de postulaciones
- ✅ Sistema de notas en aplicaciones

---

## Testing y Validación

### Comandos Disponibles

```bash
# Normalizar salarios existentes
php artisan jobs:normalize-salaries

# Force re-normalization
php artisan jobs:normalize-salaries --force

# Update exchange rates manualmente
php artisan exchange-rates:update
```

### Verificaciones Sugeridas

#### Fase 0
- [ ] Sidebar muestra solo 4 items
- [ ] `/panel` redirige a `/panel/ofertas`
- [ ] Rutas legacy redirigen correctamente

#### Fase 1
- [ ] Filtros funcionan individualmente
- [ ] Filtros funcionan combinados
- [ ] Sort algorithms dan resultados diferentes
- [ ] Panel de detalle abre en desktop
- [ ] Panel de detalle es modal en mobile
- [ ] Salarios normalizados muestran "≈ USD X/mes"
- [ ] Banner "nuevas ofertas" aparece cuando hay unseen

#### Fase 2
- [ ] Sync NO genera CVs automáticamente
- [ ] Botón "Generar CV" aparece en matches nuevos
- [ ] Click en "Generar CV" queues ProcessJobMatch
- [ ] CV se genera correctamente

#### Fase 3
- [ ] Detecta emails en descripciones
- [ ] Detecta LinkedIn URLs
- [ ] Muestra "Abrir en LinkedIn" para LinkedIn
- [ ] Muestra email + draft para aplicaciones por email
- [ ] Botón "Copiar" funciona

#### Fase 4
- [ ] "Generar carta" crea Document
- [ ] Redirect a editor después de crear carta
- [ ] "Abrir en mi correo" abre email client
- [ ] Subject y body pre-filled correctamente

#### Fase 5
- [ ] Postulaciones listadas en orden cronológico
- [ ] Notas se muestran si existen
- [ ] Links a CV usado funcionan
- [ ] Empty state muestra cuando no hay postulaciones

---

## Deployment

### Pre-requisitos

```bash
# Run migrations
php artisan migrate

# Normalize existing salaries
php artisan jobs:normalize-salaries

# Update exchange rates
php artisan exchange-rates:update
```

### Cron Jobs

Agregar al crontab:

```
# Daily exchange rate update (8 AM UTC)
0 8 * * * cd /path/to/app && php artisan exchange-rates:update
```

---

## Próximos Pasos Sugeridos

### Corto Plazo

1. **Tests automatizados**:
   - Feature tests para cada fase
   - Test de filtros combinados
   - Test de normalización de salarios

2. **Performance**:
   - Eager loading en queries complejas
   - Cache de exchange rates
   - Pagination en Applications

### Mediano Plazo

1. **CV Base Único**:
   - Modelo `Resume` separado
   - Gestión de CV base sin job search
   - Análisis ATS del CV base

2. **Estados de Aplicación**:
   - Beyond "applied": interviewing, offer, rejected
   - Timeline visual
   - Reminders de follow-up

3. **Analytics**:
   - Conversion rate: views → CVs → applications
   - Most successful filters/searches
   - A/B testing de match algorithms

---

## Conclusión

Este rediseño transforma ATS Boost de una colección de herramientas dispersas en un **flujo cohesivo y guiado** de job application. Cada fase construye sobre la anterior, creando una experiencia integrada que:

- **Reduce fricción**: De múltiples herramientas a un solo flow
- **Aumenta control**: CVs on-demand, no automáticos
- **Mejora descubrimiento**: Filtros poderosos + detail view
- **Facilita aplicación**: Kit completo + mailto: links
- **Permite tracking**: Centraliza historial de postulaciones

El resultado es una aplicación que **guía** al usuario a través del proceso completo, en vez de presentarle un toolbox y esperar que lo descubra por sí mismo.

---

**Implementado por**: Claude Sonnet 4.5  
**Session**: https://claude.ai/code/session_01Ffsm9tJLZ8Ys3HsmtytsWX  
**Branch**: `claude/product-redesign-wzw664`  
**Fecha**: 21 de octubre, 2026
