# Instalación

## Requisitos

- **PHP** 8.2 o superior
- **Laravel** 12.0 o superior
- **Extensiones PHP**: `openssl` (para encriptación de credenciales), `json`
- Acceso a la **Meta Business Manager API** (app_id, app_secret, access_token)

## Instalación via Composer

```bash
composer require scriptdevelop/meta-catalog-manager
```

El paquete se auto-registra via Laravel Package Discovery. No es necesario registrar el Service Provider manualmente.

## Wizard de Instalación

Después de instalar el paquete, ejecutá el wizard interactivo:

```bash
php artisan meta-catalog:install
```

El wizard realiza las siguientes acciones:

1. **Publica el archivo de configuración** en `config/meta-catalog.php`
2. **Publica las migraciones** en `database/migrations/` (si elegís control manual)
3. **Ejecuta las migraciones** automáticamente (si tenés `META_CATALOG_AUTO_MIGRATIONS=true`)
4. Muestra un resumen de los modelos y servicios disponibles

## Variables de Entorno

Agregá estas variables a tu archivo `.env`:

```env
# Versión de la Graph API de Meta (default: v22.0)
META_CATALOG_GRAPH_VERSION=v22.0

# Canal de logging para el paquete (default: meta-catalog)
META_CATALOG_LOG_CHANNEL=meta-catalog

# Control de migraciones automáticas (default: true)
# Si lo ponés en false, tenés que publicar y correr las migraciones manualmente
META_CATALOG_AUTO_MIGRATIONS=true

# Timeout de requests a la API en segundos (default: 30)
META_CATALOG_API_TIMEOUT=30
```

## Crear la Primera Cuenta

Una vez instalado, creá tu primera cuenta de negocio de Meta:

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

$account = MetaCatalog::account()->create([
    'meta_business_id' => '123456789',        // ID de tu Business Manager
    'name'             => 'Mi Tienda',
    'app_id'           => 'tu_app_id',         // Se encripta automáticamente en DB
    'app_secret'       => 'tu_app_secret',     // Se encripta automáticamente en DB
    'access_token'     => 'EAAxxxxx...',       // Se encripta automáticamente en DB
]);

// Sincronizar los catálogos existentes desde Meta
$catalogs = MetaCatalog::catalog()->syncFromApi($account);

echo "Catálogos sincronizados: " . $catalogs->count();
```

> **Importante:** `app_id`, `app_secret` y `access_token` se encriptan automáticamente usando la clave de encriptación de Laravel (`APP_KEY`). Nunca se almacenan en texto plano en la base de datos.
