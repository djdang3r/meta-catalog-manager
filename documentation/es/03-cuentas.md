# Cuentas de Negocio

## MetaBusinessAccount — Multi-cuenta

El paquete soporta múltiples cuentas de negocio de Meta de forma nativa. Cada cuenta tiene sus propias credenciales de API y puede tener múltiples catálogos asociados.

La estructura es:

```
MetaBusinessAccount
    └── MetaCatalog (N)
            └── MetaProductFeed (N)
            └── MetaCatalogItem (N)
            └── MetaProductSet (N)
            └── MetaBatchRequest (N)
```

## AccountService — Métodos Disponibles

Accedé al service via la Facade:

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

MetaCatalog::account()->{método}(...)
```

### `create(array $data): MetaBusinessAccount`

Crea una nueva cuenta en la base de datos y auto-fetchea el nombre del negocio desde Meta si no se proporciona.

```php
// Versión simplificada (recomendada): solo business_id + access_token
$account = MetaCatalog::account()->create([
    'meta_business_id' => '123456789',
    'access_token'     => 'EAAxxxxx...',
]);
// El sistema auto-completa 'name' desde Meta y sincroniza catálogos

// Versión completa: todos los datos manualmente
$account = MetaCatalog::account()->create([
    'meta_business_id' => '123456789',
    'name'             => 'Mi Empresa SA',
    'access_token'     => 'EAAxxxxx...',
]);
```

### `createFromEmbeddedSignup(array $data): MetaBusinessAccount`

Crear una cuenta via el flujo de registro embebido de WhatsApp (Embedded Signup v4). El frontend envía el `business_id` y el `code` (token intercambiable de 30s). El backend intercambia el `code` por un `access_token` via OAuth, fetchea los datos del negocio y sincroniza catálogos automáticamente.

```php
// Frontend → Backend: el objeto de sesión de Embedded Signup
$account = MetaCatalog::account()->createFromEmbeddedSignup([
    'business_id'  => '2729063490586005',        // viene de data.business_id
    'code'         => 'AQBhlXsctMxJYb...',       // viene de response.authResponse.code
]);
```

Configuración requerida en `.env`:
```env
META_CATALOG_APP_ID=236484624622562
META_CATALOG_APP_SECRET=614fc2afde15eee07a26b2fe3eaee9b9
```

### `update(string $id, array $data): MetaBusinessAccount`

Actualiza una cuenta existente por su ULID interno.

```php
$account = MetaCatalog::account()->update($account->id, [
    'access_token' => 'EAAyyyyy...',  // nuevo token
    'name'         => 'Mi Empresa SA (actualizado)',
]);
```

### `delete(string $id): bool`

Soft-delete de una cuenta. No la elimina físicamente de la DB.

```php
MetaCatalog::account()->delete($account->id);
```

### `find(string $id): ?MetaBusinessAccount`

Busca una cuenta por su ULID interno.

```php
$account = MetaCatalog::account()->find('01ABCDEF...');
```

### `findByMetaBusinessId(string $metaBusinessId): ?MetaBusinessAccount`

Busca una cuenta por el Meta Business Manager ID.

```php
$account = MetaCatalog::account()->findByMetaBusinessId('123456789');
```

### `all(): Collection`

Retorna todas las cuentas activas (sin soft-deleted).

```php
$accounts = MetaCatalog::account()->all();
```

### `markDisconnected(MetaBusinessAccount $account, ?string $reason = null): void`

Marca la cuenta como desconectada (por ejemplo, si el token expira o el usuario revoca el acceso).

```php
MetaCatalog::account()->markDisconnected($account, 'Token revocado por el usuario');
```

### `markActive(MetaBusinessAccount $account): void`

Marca la cuenta como activa nuevamente (reconexión).

```php
MetaCatalog::account()->markActive($account);
```

## Ejemplo: Obtener Cuentas Activas

```php
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Enums\AccountStatus;

// Via Eloquent directamente (con scope)
$cuentasActivas = MetaBusinessAccount::active()->get();

// O filtrando manualmente
$cuentasActivas = MetaBusinessAccount::where('status', AccountStatus::ACTIVE)->get();

foreach ($cuentasActivas as $account) {
    echo $account->name . ' — ' . $account->meta_business_id . PHP_EOL;
}
```

## Encriptación de Credenciales

Los campos `app_secret` y `access_token` se encriptan automáticamente al guardar y desencriptan al leer, usando la clave `APP_KEY` de Laravel. El campo `app_id` se almacena en texto plano (no contiene información sensible).

Esto es transparente para vos como desarrollador:

```php
// Al guardar: app_secret y access_token se encriptan automáticamente
$account = MetaCatalog::account()->create([
    'access_token' => 'EAAxxxxx...',
]);

// Al leer: se desencriptan automáticamente
echo $account->access_token; // Muestra 'EAAxxxxx...' en texto plano
echo $account->app_id;       // Muestra '236484624622562' en texto plano (sin encriptación)

// En la DB: app_secret y access_token están encriptados
// SELECT access_token, app_id FROM meta_business_accounts;
// → access_token: eyJpdiI6Ijh...  (ilegible sin la APP_KEY)
// → app_id: 236484624622562      (texto plano)
```

> **Importante:** Si cambiás `APP_KEY` en producción, todas las credenciales encriptadas quedarán ilegibles. Debés re-encriptarlas antes de rotar la clave.
