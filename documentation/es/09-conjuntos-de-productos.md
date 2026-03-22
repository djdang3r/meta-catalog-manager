# Conjuntos de Productos (Product Sets)

## ¿Qué son los Product Sets?

Un Product Set es un subconjunto de productos de un catálogo, definido por filtros. Se usan principalmente para:

- **Targeting de anuncios**: Mostrar solo productos de una categoría específica en una campaña
- **Catálogos dinámicos**: Crear conjuntos por precio, disponibilidad, categoría, marca, etc.
- **Organización**: Agrupar productos para distintas audiencias o regiones

Los filtros del Product Set usan el mismo formato de reglas que las campañas de Dynamic Ads de Meta.

## ProductSetService — Métodos Disponibles

```php
MetaCatalog::productSet()->{método}(...)
```

### `fetchFromApi(MetaCatalog $catalog): array`

Obtiene todos los product sets del catálogo desde la Graph API.

```php
$response = MetaCatalog::productSet()->fetchFromApi($catalog);
```

### `syncFromApi(MetaCatalog $catalog): Collection`

Sincroniza los product sets de la API hacia la DB local.

```php
$sets = MetaCatalog::productSet()->syncFromApi($catalog);
echo "Product Sets sincronizados: " . $sets->count();
```

### `create(MetaCatalog $catalog, array $data): MetaProductSet`

Crea un nuevo product set con filtros en la API y lo guarda en DB.

```php
// Product Set de electrónica en oferta
$set = MetaCatalog::productSet()->create($catalog, [
    'name'   => 'Electrónica en Oferta',
    'filter' => [
        'retailer_product_group_id' => [
            ['i_contains' => 'ELECTRONICA'],
        ],
        'sale_price' => [
            ['gt' => 0],
        ],
    ],
]);
```

### `update(MetaProductSet $productSet, array $data): MetaProductSet`

Actualiza el nombre o filtros de un product set.

```php
$set = MetaCatalog::productSet()->update($set, [
    'name' => 'Electrónica en Oferta (verano)',
]);
```

### `delete(MetaProductSet $productSet): bool`

Elimina el product set de la API y lo soft-delete en DB.

```php
MetaCatalog::productSet()->delete($set);
```

### `getProducts(MetaProductSet $productSet): array`

Obtiene los productos que actualmente caen dentro del filtro del product set.

```php
$response = MetaCatalog::productSet()->getProducts($set);
$productos = $response['data'];

echo "Productos en el set: " . count($productos);
foreach ($productos as $p) {
    echo $p['name'] . ' — ' . $p['price'] . PHP_EOL;
}
```

## Ejemplos de Filtros

Los filtros son arrays de condiciones. Podés combinar múltiples condiciones con AND implícito.

### Por Disponibilidad

```php
// Solo productos en stock
'filter' => [
    'availability' => [
        ['eq' => 'in stock'],
    ],
],
```

### Por Categoría

```php
// Productos de una categoría específica (contains, case-insensitive)
'filter' => [
    'google_product_category' => [
        ['i_contains' => 'Clothing'],
    ],
],
```

### Por Rango de Precio

```php
// Productos entre $10 y $50 USD
'filter' => [
    'price' => [
        ['gte' => 1000],  // precio en centavos: 1000 = $10.00
        ['lte' => 5000],  // 5000 = $50.00
    ],
],
```

### Por Marca

```php
'filter' => [
    'brand' => [
        ['eq' => 'Nike'],
    ],
],
```

### Por Condición

```php
// Solo productos nuevos
'filter' => [
    'condition' => [
        ['eq' => 'new'],
    ],
],
```

### Filtro Compuesto: Productos Nuevos en Stock de una Marca

```php
$set = MetaCatalog::productSet()->create($catalog, [
    'name' => 'Nike - Nuevos en Stock',
    'filter' => [
        'brand' => [
            ['eq' => 'Nike'],
        ],
        'condition' => [
            ['eq' => 'new'],
        ],
        'availability' => [
            ['eq' => 'in stock'],
        ],
    ],
]);
```

### Operadores Disponibles

| Operador | Descripción |
|---|---|
| `eq` | Igual a |
| `neq` | Distinto de |
| `lt` | Menor que |
| `lte` | Menor o igual |
| `gt` | Mayor que |
| `gte` | Mayor o igual |
| `contains` | Contiene (case-sensitive) |
| `i_contains` | Contiene (case-insensitive) |
| `not_contains` | No contiene |
| `starts_with` | Empieza con |
| `ends_with` | Termina con |

## Uso en Campañas

Una vez creado el Product Set, podés usarlo en tus campañas de Facebook Ads referenciándolo por su `meta_product_set_id`. Cuando el catálogo se actualiza, el Product Set se actualiza automáticamente — Meta reevalúa los filtros en tiempo real.
