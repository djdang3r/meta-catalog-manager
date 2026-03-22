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

Crea una nueva cuenta en la base de datos local. No hace llamadas a la API.

```php
$account = MetaCatalog::account()->create([
    'meta_business_id' => '123456789',
    'name'             => 'Mi Empresa SA',
    'app_id'           => 'tu_facebook_app_id',
    'app_secret'       => 'tu_facebook_app_secret',
    'access_token'     => 'EAAxxxxx...',
]);
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

Los campos `app_id`, `app_secret` y `access_token` se encriptan automáticamente al guardar y desencriptan al leer, usando la clave `APP_KEY` de Laravel.

Esto es transparente para vos como desarrollador:

```php
// Al guardar: se encripta automáticamente
$account = MetaCatalog::account()->create([
    'access_token' => 'EAAxxxxx...',
]);

// Al leer: se desencripta automáticamente
echo $account->access_token; // Muestra 'EAAxxxxx...' en texto plano

// En la DB: está almacenado encriptado
// SELECT access_token FROM meta_business_accounts;
// → eyJpdiI6Ijh...  (ilegible sin la APP_KEY)
```

> **Importante:** Si cambiás `APP_KEY` en producción, todas las credenciales encriptadas quedarán ilegibles. Debés re-encriptarlas antes de rotar la clave.
