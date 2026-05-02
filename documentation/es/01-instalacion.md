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
# Versión de la Graph API de Meta (default: v25.0)
META_CATALOG_GRAPH_VERSION=v25.0

# Canal de logging para el paquete (default: meta-catalog)
META_CATALOG_LOG_CHANNEL=meta-catalog

# Control de migraciones automáticas (default: true)
META_CATALOG_AUTO_MIGRATIONS=true

# Timeout de requests a la API en segundos (default: 30)
META_CATALOG_API_TIMEOUT=30

# Credenciales OAuth para el registro embebido (Embedded Signup)
META_CATALOG_APP_ID=
META_CATALOG_APP_SECRET=
```

## Crear la Primera Cuenta

### Flujo Manual

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

// Solo necesitás business_id y access_token
$account = MetaCatalog::account()->create([
    'meta_business_id' => '123456789',
    'access_token'     => 'EAAxxxxx...',
]);
// El nombre del negocio se obtiene automáticamente desde Meta
// Los catálogos se sincronizan automáticamente

echo $account->name; // "Mi Tienda" (fetcheado de Meta)
echo $account->catalogs->count(); // Catálogos ya sincronizados
```

### Flujo Embedded Signup (WhatsApp)

Para usar el registro embebido, configurá las credenciales OAuth en `.env`:
