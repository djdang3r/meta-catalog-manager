# Configuración

## Archivo de Configuración

Publicá el archivo de configuración si necesitás personalizarlo:

```bash
php artisan vendor:publish --tag=meta-catalog-config
```

Esto crea `config/meta-catalog.php` en tu proyecto.

## Opciones Disponibles

### `api` — Configuración de la Graph API

```php
'api' => [
    // URL base de la Graph API (no cambiar salvo entornos de prueba)
    'base_url' => env('META_CATALOG_API_URL', 'https://graph.facebook.com'),

    // Versión de la Graph API. Recomendado: mantener actualizado
    'graph_version' => env('META_CATALOG_GRAPH_VERSION', 'v22.0'),

    // Timeout en segundos para cada request
    'timeout' => env('META_CATALOG_API_TIMEOUT', 30),

    // Número de reintentos automáticos ante errores transitorios
    'retries' => env('META_CATALOG_API_RETRIES', 3),
],
```

### `models` — Override de Modelos

Podés reemplazar cualquier modelo del paquete con tu propia clase. Útil cuando necesitás agregar relaciones, métodos o comportamientos específicos de tu aplicación:

```php
'models' => [
    'meta_business_account'   => \ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount::class,
    'meta_catalog'            => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalog::class,
    'meta_product_feed'       => \ScriptDevelop\MetaCatalogManager\Models\MetaProductFeed::class,
    'meta_product_feed_upload' => \ScriptDevelop\MetaCatalogManager\Models\MetaProductFeedUpload::class,
    'meta_catalog_item'       => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem::class,
    'meta_product_set'        => \ScriptDevelop\MetaCatalogManager\Models\MetaProductSet::class,
    'meta_batch_request'      => \ScriptDevelop\MetaCatalogManager\Models\MetaBatchRequest::class,
    'meta_catalog_diagnostic' => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalogDiagnostic::class,
    'meta_event_source'       => \ScriptDevelop\MetaCatalogManager\Models\MetaEventSource::class,
    'meta_event_stat'         => \ScriptDevelop\MetaCatalogManager\Models\MetaEventStat::class,
],
```

### `migrations` — Control de Migraciones

```php
'migrations' => [
    // true  = el paquete carga las migraciones automáticamente
    // false = publicás las migraciones y las controlás vos
    'auto_load' => env('META_CATALOG_AUTO_MIGRATIONS', true),
],
```

### `logging` — Canal de Log

```php
'logging' => [
    // Canal de Laravel Logging configurado en config/logging.php
    'channel' => env('META_CATALOG_LOG_CHANNEL', 'meta-catalog'),
],
```

## Cómo Override Modelos

1. Creá tu modelo extendiendo el del paquete:

```php
namespace App\Models;

use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem as BaseItem;

class MiProducto extends BaseItem
{
    // Agregá tus relaciones o métodos adicionales

    public function pedidos()
    {
        return $this->hasMany(\App\Models\Pedido::class, 'retailer_id', 'retailer_id');
    }
}
```

2. Registrá el modelo en `config/meta-catalog.php`:

```php
'models' => [
    'meta_catalog_item' => \App\Models\MiProducto::class,
    // ... resto de modelos sin cambios
],
```

A partir de ese momento, todos los servicios del paquete usarán `MiProducto` en lugar del modelo por defecto.

## Cómo Cambiar el Canal de Log

1. Definí el canal en `config/logging.php`:

```php
'channels' => [
    'meta-catalog' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/meta-catalog.log'),
        'level'  => 'debug',
        'days'   => 14,
    ],
],
```

2. O simplemente apuntalo a un canal existente en `.env`:

```env
META_CATALOG_LOG_CHANNEL=stack
```
