# Diagnósticos

## DiagnosticsService

Los diagnósticos permiten detectar problemas en el catálogo: campos faltantes, URLs rotas, precios inválidos, ítems rechazados, etc.

```php
MetaCatalog::diagnostics()->{método}(...)
```

### `fetchFromApi(MetaCatalog $catalog): array`

Obtiene los diagnósticos del catálogo desde la Graph API. Retorna array crudo.

```php
$response = MetaCatalog::diagnostics()->fetchFromApi($catalog);
foreach ($response['data'] as $diag) {
    echo "[{$diag['severity']}] {$diag['type']}: {$diag['description']} ({$diag['count']} ítems)" . PHP_EOL;
}
```

### `syncFromApi(MetaCatalog $catalog): Collection`

Sincroniza los diagnósticos de la API hacia la DB local. Reemplaza los diagnósticos anteriores (siempre trabaja con datos frescos).

```php
$diagnostics = MetaCatalog::diagnostics()->syncFromApi($catalog);

$errores   = $diagnostics->where('severity', 'error');
$warnings  = $diagnostics->where('severity', 'warning');

echo "Errores: {$errores->count()}, Warnings: {$warnings->count()}";
```

### `getAllErrors(MetaCatalog $catalog): array`

Obtiene todos los errores de ítems individuales del catálogo. Más granular que `fetchFromApi`.

```php
$errors = MetaCatalog::diagnostics()->getAllErrors($catalog);

foreach ($errors['data'] ?? [] as $error) {
    echo "Ítem {$error['retailer_id']}: {$error['message']}" . PHP_EOL;
}
```

### `hasErrors(MetaCatalog $catalog): bool`

Verifica si el catálogo tiene errores en la DB local (sincronizados previamente).

```php
if (MetaCatalog::diagnostics()->hasErrors($catalog)) {
    // Notificar al equipo
    Notification::send($admin, new CatalogConErrores($catalog));
}
```

### `hasWarnings(MetaCatalog $catalog): bool`

Verifica si el catálogo tiene warnings en la DB local.

```php
if (MetaCatalog::diagnostics()->hasWarnings($catalog)) {
    logger()->warning('Catálogo con warnings', ['catalog' => $catalog->meta_catalog_id]);
}
```

## EventStatsService

Estadísticas de eventos de píxel y app asociados al catálogo. Útil para medir qué tan bien están "hablando" tus fuentes de eventos con el catálogo.

```php
MetaCatalog::eventStats()->{método}(...)
```

### `fetchFromApi(MetaCatalog $catalog, array $params): array`

Obtiene estadísticas de eventos del catálogo.

```php
// Estadísticas básicas
$stats = MetaCatalog::eventStats()->fetchFromApi($catalog);

// Con parámetros adicionales
$stats = MetaCatalog::eventStats()->fetchFromApi($catalog, [
    'time_range' => json_encode([
        'since' => now()->subDays(7)->timestamp,
        'until' => now()->timestamp,
    ]),
]);
```

### `fetchWithBreakdown(MetaCatalog $catalog, string $breakdown): array`

Estadísticas con un breakdown específico.

```php
// Por tipo de dispositivo
$statsPorDispositivo = MetaCatalog::eventStats()->fetchWithBreakdown($catalog, 'device_type');

// Por nombre de evento (ViewContent, AddToCart, Purchase, etc.)
$statsPorEvento = MetaCatalog::eventStats()->fetchWithBreakdown($catalog, 'event_name');
```

### `syncFromApi(MetaCatalog $catalog): Collection`

Sincroniza estadísticas de eventos hacia la DB local.

### `checkPixel(MetaEventSource $eventSource): array`

Ejecuta las verificaciones de Dynamic Ads (da_checks) para un Pixel. Verifica que el píxel esté bien configurado para usar con el catálogo.

```php
$eventSource = $catalog->eventSources()->where('source_type', 'pixel')->first();
$checks = MetaCatalog::eventStats()->checkPixel($eventSource);

foreach ($checks['data'] ?? [] as $check) {
    $status = $check['status'] === 'pass' ? '✓' : '✗';
    echo "{$status} {$check['description']}" . PHP_EOL;
}
```

### `checkApp(MetaEventSource $eventSource): array`

Ejecuta las verificaciones de Dynamic Ads para una App.

```php
$appSource = $catalog->eventSources()->where('source_type', 'app')->first();
$checks = MetaCatalog::eventStats()->checkApp($appSource);
```

### `syncEventSource(MetaCatalog $catalog, string $sourceId, string $sourceType): MetaEventSource`

Crea o actualiza un Event Source en la DB local.

```php
$eventSource = MetaCatalog::eventStats()->syncEventSource(
    $catalog,
    '123456789',  // Pixel ID o App ID
    'pixel'       // 'pixel' o 'app'
);
```

## DA Checks — Interpretación de Resultados

Los DA Checks (Dynamic Ads Checks) verifican la calidad de la integración entre tu fuente de eventos y el catálogo. Los checks clave son:

| Check | Descripción |
|---|---|
| `pixel_fire` | El píxel está disparando eventos |
| `content_id_match` | Los `content_ids` del píxel coinciden con `retailer_id` del catálogo |
| `micro_data` | El píxel incluye el microdata correcto |
| `event_match_quality` | Calidad general del match entre eventos y catálogo |

Un resultado `FAIL` en `content_id_match` es el problema más común: significa que los IDs de productos que envía tu píxel no coinciden con los `retailer_id` en el catálogo. Revisá que ambos usen el mismo identificador.

## Ejemplo: Dashboard de Salud del Catálogo

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogDiagnostic;

function healthCheck(string $catalogId): array
{
    $catalog = MetaCatalog::catalog()->findByMetaCatalogId($catalogId);

    // Sincronizar diagnósticos frescos
    MetaCatalog::diagnostics()->syncFromApi($catalog);

    $diagnostics = MetaCatalogDiagnostic::where('meta_catalog_id', $catalog->id)->get();

    return [
        'catalog'        => $catalog->name,
        'has_errors'     => MetaCatalog::diagnostics()->hasErrors($catalog),
        'has_warnings'   => MetaCatalog::diagnostics()->hasWarnings($catalog),
        'total_errors'   => $diagnostics->where('severity', 'error')->sum('count'),
        'total_warnings' => $diagnostics->where('severity', 'warning')->sum('count'),
        'issues'         => $diagnostics->map(fn($d) => [
            'type'        => $d->error_type,
            'severity'    => $d->severity,
            'count'       => $d->count,
            'description' => $d->description,
        ])->toArray(),
    ];
}
```
