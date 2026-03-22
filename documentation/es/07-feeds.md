# Feeds

## Tipos de Feed

### PRIMARY_FEED

El feed principal del catálogo. Contiene la lista completa de productos. Puede tener un `replace_schedule` (que sobrescribe todo el catálogo) y/o un `update_schedule` (incremental).

### SUPPLEMENTARY_FEED

Un feed suplementario que se "superpone" a uno o más feeds primarios. Permite:
- Sobreescribir campos específicos sin modificar el feed principal (útil para promociones, precios regionales, etc.)
- Solo puede **crear y actualizar** ítems, nunca eliminarlos (siempre trabaja con `update_only = true`)
- Requiere referenciar al menos un `PRIMARY_FEED` via `primary_feed_ids`

**Caso de uso típico:** Tenés un feed principal con el catálogo completo y un feed suplementario que actualiza los precios de oferta para una campaña.

## Replace Schedule vs Update Schedule

| | Replace Schedule | Update Schedule |
|---|---|---|
| **Operación** | Sobrescribe TODO el catálogo | Solo crea/actualiza ítems existentes |
| **Elimina ítems ausentes** | Sí | No |
| **Frecuencia típica** | DAILY | HOURLY |
| **Uso** | Catálogo completo 1x/día | Delta de cambios frecuentes |

### Estrategia Recomendada: DAILY replace + HOURLY update

```
replace_schedule (DAILY a las 02:00 AM):
    → Descarga tu catálogo completo
    → Elimina productos que ya no existen
    → Crea/actualiza todo el resto

update_schedule (HOURLY):
    → Solo los productos que cambiaron de precio o stock
    → No elimina nada
    → Mantiene el catálogo fresco sin sobrecargar la API
```

## FeedService — Métodos Completos

```php
MetaCatalog::feed()->{método}(...)
```

### `fetchFromApi(MetaCatalog $catalog): array`

Obtiene los feeds del catálogo desde la Graph API. Retorna array crudo.

### `syncFromApi(MetaCatalog $catalog): Collection`

Sincroniza los feeds de la API hacia la DB local.

```php
$feeds = MetaCatalog::feed()->syncFromApi($catalog);
```

### `create(MetaCatalog $catalog, array $data): MetaProductFeed`

Crea un PRIMARY_FEED con schedule en la API y lo guarda en DB.

```php
$feed = MetaCatalog::feed()->create($catalog, [
    'name'   => 'Catálogo Principal',
    'format' => 'csv',

    // Replace schedule: catálogo completo todos los días a las 2AM
    'schedule' => [
        'interval'    => 'DAILY',
        'url'         => 'https://mitienda.com/feeds/catalogo-completo.csv',
        'hour'        => 2,
        'minute'      => 0,
        'day_of_week' => null,  // null para DAILY
    ],

    // Encoding y formato
    'encoding'          => 'UTF-8',
    'delimiter'         => ',',
    'quoted_fields_mode' => 'AUTO',

    // Credenciales si el feed requiere autenticación básica
    'credentials' => [
        'user'     => 'feeduser',
        'password' => 'feedpassword',  // se encripta en DB
    ],
]);
```

### `createSupplementaryFeed(MetaCatalog $catalog, array $data): MetaProductFeed`

Crea un SUPPLEMENTARY_FEED que se superpone a feeds primarios existentes.

```php
$supplementaryFeed = MetaCatalog::feed()->createSupplementaryFeed($catalog, [
    'name'             => 'Precios de Oferta - Black Friday',
    'primary_feed_ids' => ['123456789', '987654321'],  // IDs Meta de los feeds primarios
    'update_only'      => true,  // siempre true para supplementary

    // Schedule para actualizar los precios de oferta cada hora
    'schedule' => [
        'interval' => 'HOURLY',
        'url'      => 'https://mitienda.com/feeds/precios-oferta.csv',
        'hour'     => null,
        'minute'   => 0,
    ],
    'format'   => 'csv',
    'encoding' => 'UTF-8',
]);
```

### `update(MetaProductFeed $feed, array $data): MetaProductFeed`

Actualiza la configuración de un feed en la API y en DB.

```php
$feed = MetaCatalog::feed()->update($feed, [
    'replace_schedule_url'  => 'https://mitienda.com/feeds/nuevo-url.csv',
    'replace_schedule_hour' => 3,  // cambiar a las 3AM
]);
```

### `delete(MetaProductFeed $feed): bool`

Elimina el feed de la API y lo soft-delete en DB.

```php
MetaCatalog::feed()->delete($feed);
```

### `getUploads(MetaProductFeed $feed): array`

Obtiene el historial de uploads de un feed desde la API.

### `syncUploads(MetaProductFeed $feed): Collection`

Sincroniza el historial de uploads hacia la DB local.

```php
$uploads = MetaCatalog::feed()->syncUploads($feed);
$ultimo = $uploads->sortByDesc('started_at')->first();
echo "Último upload: " . $ultimo->status . " — " . $ultimo->num_persisted_items . " ítems";
```

### `triggerUpload(MetaProductFeed $feed, array $data): MetaProductFeedUpload`

Dispara un upload manual del feed en la API.

## Uploads One-Time

### `uploadFromUrl(MetaProductFeed $feed, string $url, bool $updateOnly): MetaProductFeedUpload`

Sube el catálogo desde una URL, sin necesidad de un schedule configurado.

```php
$upload = MetaCatalog::feed()->uploadFromUrl(
    $feed,
    'https://mitienda.com/exports/catalogo-20260322.csv',
    false  // false = replace (elimina ítems ausentes), true = update only
);

echo "Upload iniciado. Session ID: " . $upload->meta_upload_session_id;
```

### `uploadFromFile(MetaProductFeed $feed, string $filePath, string $mimeType, bool $updateOnly): MetaProductFeedUpload`

Sube el catálogo desde un archivo local via multipart upload.

```php
$upload = MetaCatalog::feed()->uploadFromFile(
    $feed,
    storage_path('exports/catalogo-hoy.csv'),
    'text/csv',
    false  // false = replace completo
);
```

Para TSV o XML:

```php
// TSV
$upload = MetaCatalog::feed()->uploadFromFile($feed, $path, 'text/tab-separated-values');

// XML/RSS
$upload = MetaCatalog::feed()->uploadFromFile($feed, $path, 'application/rss+xml');
```

## Manejo de Errores de Upload

Después de un upload, Meta procesa el archivo y puede generar errores o warnings. El flujo para consultarlos:

### `getUploadErrors(MetaProductFeedUpload $upload): array`

Obtiene una muestra de los errores del upload (errores fatales y warnings).

```php
$errors = MetaCatalog::feed()->getUploadErrors($upload);

foreach ($errors['data'] ?? [] as $error) {
    echo "[{$error['severity']}] {$error['summary']}" . PHP_EOL;
    echo "Descripción: {$error['description']}" . PHP_EOL;

    // Muestras de ítems afectados
    foreach ($error['samples']['data'] ?? [] as $sample) {
        echo "  → Ítem: {$sample['retailer_id']} — {$sample['message']}" . PHP_EOL;
    }
}
```

La respuesta típica se ve así:

```json
{
    "data": [
        {
            "id": "error_123",
            "summary": "Invalid price format",
            "description": "The price field must be in format '10.99 USD'",
            "severity": "FATAL",
            "samples": {
                "data": [
                    {
                        "retailer_id": "SKU-001",
                        "message": "Price '10.99' is missing currency code"
                    }
                ]
            }
        }
    ]
}
```

### `requestErrorReport(MetaProductFeedUpload $upload): array`

Solicita la generación del reporte completo de errores (proceso asincrónico en Meta). Actualiza `error_report_status` a `PENDING` en DB.

```php
MetaCatalog::feed()->requestErrorReport($upload);
// Meta procesa el reporte en segundo plano
```

### `getErrorReport(MetaProductFeedUpload $upload): array`

Obtiene el status del reporte y la URL para descargarlo. Actualiza `error_report_status` en DB automáticamente.

```php
$report = MetaCatalog::feed()->getErrorReport($upload);

$status   = $report['error_report']['report_status'] ?? null;
$fileUrl  = $report['error_report']['file_handle'] ?? null;

if ($status === 'WRITE_FINISHED' && $fileUrl) {
    // Descargar el CSV completo de errores
    $contenido = file_get_contents($fileUrl);
    file_put_contents(storage_path('logs/feed-errors.csv'), $contenido);
}
```

Los estados posibles de `error_report_status`:
- `PENDING` — reporte solicitado, en cola
- `WRITE_FINISHED` — reporte listo para descargar
- `WRITE_FAILED` — Meta no pudo generar el reporte

## Autenticación HTTP/FTP Básica para Feeds Privados

Si tu URL de feed requiere autenticación básica, los campos `feed_username` y `feed_password` se encriptan automáticamente en la DB:

```php
// Al crear el feed con credenciales
$feed = MetaCatalog::feed()->create($catalog, [
    'name' => 'Catálogo Privado',
    'schedule' => [
        'interval' => 'DAILY',
        'url'      => 'https://mitienda.com/feeds/privado.csv',
        'hour'     => 2,
    ],
    'credentials' => [
        'user'     => 'mi_usuario',
        'password' => 'mi_password',  // encriptado en DB automáticamente
    ],
]);

// Al leer: se desencriptan automáticamente
echo $feed->feed_username;  // 'mi_usuario'
echo $feed->feed_password;  // 'mi_password'
```

> Las credenciales se envían a Meta en el momento de creación del feed. Meta las usa internamente para autenticar las peticiones al schedule. El paquete las guarda encriptadas solo como referencia local.
