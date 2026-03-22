# Catálogos

## ¿Qué es un Catálogo?

Un catálogo de Meta es un contenedor de productos (o cualquier otro tipo de ítem) que se usa para campañas de publicidad dinámica, Facebook Shops, Instagram Shopping, Marketplace y más.

Cada catálogo pertenece a un Business Manager y tiene una **vertical** que define el tipo de ítems que contiene.

## Verticales Disponibles

| Vertical | Descripción |
|---|---|
| `commerce` | Productos de e-commerce (default) |
| `vehicles` | Vehículos (autos, motos) |
| `hotels` | Hoteles y alojamientos |
| `flights` | Vuelos |
| `destinations` | Destinos turísticos |
| `home_listings` | Propiedades inmobiliarias |

## CatalogService — Métodos Disponibles

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

MetaCatalog::catalog()->{método}(...)
```

### `fetchFromApi(MetaBusinessAccount $account): array`

Obtiene todos los catálogos del Business Manager desde la Graph API. Retorna el array crudo de la API.

```php
$response = MetaCatalog::catalog()->fetchFromApi($account);
// $response['data'] contiene el array de catálogos
```

### `syncFromApi(MetaBusinessAccount $account): Collection`

Sincroniza los catálogos de la API con la base de datos local. Usa `updateOrCreate` por `meta_catalog_id`, por lo que es seguro correrlo múltiples veces.

```php
$catalogs = MetaCatalog::catalog()->syncFromApi($account);

echo "Catálogos sincronizados: " . $catalogs->count();
```

### `create(MetaBusinessAccount $account, array $data): MetaCatalog`

Crea un nuevo catálogo en la Graph API y lo guarda en la DB.

```php
$catalog = MetaCatalog::catalog()->create($account, [
    'name'     => 'Catálogo Principal',
    'vertical' => 'commerce',
]);
```

### `find(string $id): ?MetaCatalog`

Busca un catálogo por su ULID interno.

```php
$catalog = MetaCatalog::catalog()->find('01ABCDEF...');
```

### `findByMetaCatalogId(string $metaCatalogId): ?MetaCatalog`

Busca un catálogo por el Meta Catalog ID.

```php
$catalog = MetaCatalog::catalog()->findByMetaCatalogId('987654321');
```

### `forAccount(MetaBusinessAccount $account): Collection`

Retorna todos los catálogos locales de una cuenta.

```php
$catalogs = MetaCatalog::catalog()->forAccount($account);

foreach ($catalogs as $catalog) {
    echo $catalog->name . ' — vertical: ' . $catalog->vertical->value . PHP_EOL;
}
```

## Ejemplo: Flujo Completo

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

// 1. Obtener la cuenta
$account = MetaCatalog::account()->findByMetaBusinessId('123456789');

// 2. Sincronizar catálogos existentes desde Meta
$catalogs = MetaCatalog::catalog()->syncFromApi($account);

// 3. O crear un catálogo nuevo
$nuevoCatalog = MetaCatalog::catalog()->create($account, [
    'name'     => 'Productos Electrónica',
    'vertical' => 'commerce',
]);

echo "Catálogo creado con ID Meta: " . $nuevoCatalog->meta_catalog_id;
```
