# Batch API

## ¿Cuándo Usar Batch vs Feed?

| Criterio | Feed | Batch API |
|---|---|---|
| **Latencia** | Minutos a horas | Segundos |
| **Volumen** | Miles/millones de productos | Hasta 5000 ítems por request |
| **Ideal para** | Carga inicial, catálogo completo | Actualizaciones en tiempo real |
| **Eliminación** | Replace schedule elimina ausentes | DELETE explícito por ítem |
| **Costo API** | Bajo (1 request por schedule) | Más requests, pero asincrónicos |

**Regla general:**
- Usá **Feed** para la sincronización inicial y actualizaciones masivas periódicas
- Usá **Batch API** para actualizaciones de inventario en tiempo real post-venta

## BatchService — Métodos Disponibles

```php
MetaCatalog::batch()->{método}(...)
```

### `sendBatch(MetaCatalog $catalog, array $requests, string $itemType): MetaBatchRequest`

Envía un lote de operaciones mixtas (CREATE, UPDATE, DELETE) en una sola llamada a la API.

```php
$batch = MetaCatalog::batch()->sendBatch($catalog, [
    ['method' => 'CREATE', 'data' => ['id' => 'SKU-003', /* ... */ ]],
    ['method' => 'UPDATE', 'data' => ['id' => 'SKU-001', 'quantity_to_sell_on_facebook' => 5]],
    ['method' => 'DELETE', 'data' => ['id' => 'SKU-VIEJO']],
], 'PRODUCT_ITEM');
```

### `createItems(MetaCatalog $catalog, array $items, string $itemType): MetaBatchRequest`

Crea múltiples ítems en un solo batch.

```php
$batch = MetaCatalog::batch()->createItems($catalog, [
    [
        'id'            => 'SKU-001',
        'title'         => 'Remera Básica Azul',
        'price'         => '29.99 USD',
        'availability'  => 'in stock',
        'condition'     => 'new',
        'link'          => 'https://mitienda.com/remera-001',
        'image_url'     => 'https://mitienda.com/img/remera.jpg',
        'description'   => 'Remera 100% algodón',
        'brand'         => 'MiMarca',
    ],
    [
        'id'           => 'SKU-002',
        'title'        => 'Campera Invierno',
        'price'        => '89.99 USD',
        // ...
    ],
]);
```

### `updateItems(MetaCatalog $catalog, array $items, string $itemType): MetaBatchRequest`

Actualiza múltiples ítems. Solo necesitás enviar el `id` y los campos a modificar.

```php
// Actualización de inventario masiva
$batch = MetaCatalog::batch()->updateItems($catalog, [
    ['id' => 'SKU-001', 'quantity_to_sell_on_facebook' => 15, 'availability' => 'in stock'],
    ['id' => 'SKU-002', 'quantity_to_sell_on_facebook' => 0,  'availability' => 'out of stock'],
    ['id' => 'SKU-003', 'sale_price' => '19.99 USD'],  // también podés cambiar precios
]);
```

### `deleteItems(MetaCatalog $catalog, array $retailerIds, string $itemType): MetaBatchRequest`

Elimina múltiples ítems por su `retailer_id` (el `id` en el catálogo).

```php
// Eliminar productos descontinuados
$batch = MetaCatalog::batch()->deleteItems($catalog, [
    'SKU-VIEJO-001',
    'SKU-VIEJO-002',
    'SKU-VIEJO-003',
]);
```

> **Alternativa recomendada:** En lugar de eliminar, considerá cambiar `visibility` a `staging`. Eliminar un producto que está en una campaña activa puede interrumpirla.

### `checkStatus(MetaBatchRequest $batchRequest): MetaBatchRequest`

Consulta el estado de un batch en la API y actualiza el registro en DB. El procesamiento es asincrónico.

```php
$batch = MetaCatalog::batch()->createItems($catalog, $items);

// Verificar estado después de unos segundos
sleep(5);
$batch = MetaCatalog::batch()->checkStatus($batch);

echo "Estado: " . $batch->status->value . PHP_EOL;
echo "Ítems procesados: " . $batch->success_count . PHP_EOL;
echo "Errores: " . $batch->error_count . PHP_EOL;

if ($batch->errors) {
    foreach ($batch->errors as $error) {
        echo "Error: " . json_encode($error) . PHP_EOL;
    }
}
```

Estados posibles (`BatchRequestStatus`): `PENDING`, `PROCESSING`, `COMPLETE`, `FAILED`

### `sendLocalizedBatch(MetaCatalog $catalog, array $requests, string $itemType): MetaBatchRequest`

Envía un lote de ítems localizados (multi-idioma/región). Usa el endpoint `localized_items_batch`.

```php
$batch = MetaCatalog::batch()->sendLocalizedBatch($catalog, [
    [
        'method' => 'CREATE',
        'data' => [
            'id'     => 'SKU-001',
            'title'  => 'Remera Básica',
            'price'  => '29.99 USD',
            // ... campos base
        ],
        'localizations' => [
            'es_AR' => [
                'title' => 'Remera Básica',
                'price' => '2999 ARS',
            ],
            'pt_BR' => [
                'title' => 'Camiseta Básica',
                'price' => '79.99 BRL',
            ],
        ],
    ],
]);
```

## Tipos de Ítem (`item_type`)

El parámetro `$itemType` en todos los métodos define el tipo de ítem del catálogo:

| Valor | Vertical |
|---|---|
| `PRODUCT_ITEM` | E-commerce (default) |
| `VEHICLE` | Vehículos |
| `HOTEL` | Hoteles |
| `HOTEL_ROOM` | Habitaciones de hotel |
| `FLIGHT` | Vuelos |
| `DESTINATION` | Destinos turísticos |
| `HOME_LISTING` | Propiedades inmobiliarias |
| `VEHICLE_OFFER` | Ofertas de vehículos |

## Tracking de Estado con MetaBatchRequest

El modelo `MetaBatchRequest` guarda el estado de cada batch en DB:

```php
use ScriptDevelop\MetaCatalogManager\Models\MetaBatchRequest;
use ScriptDevelop\MetaCatalogManager\Enums\BatchRequestStatus;

// Consultar batches pendientes
$pendientes = MetaBatchRequest::where('status', BatchRequestStatus::PENDING)->get();

foreach ($pendientes as $batch) {
    $actualizado = MetaCatalog::batch()->checkStatus($batch);

    if ($actualizado->status === BatchRequestStatus::FAILED) {
        // Loguear errores
        logger()->error('Batch fallido', [
            'handle'      => $actualizado->handle,
            'error_count' => $actualizado->error_count,
            'errors'      => $actualizado->errors,
        ]);
    }
}
```

## Ejemplo: Actualización de Inventario en Tiempo Real

```php
// Job de Laravel para actualizar inventario post-compra
class ActualizarInventarioMeta implements ShouldQueue
{
    public function __construct(
        private array $cambios  // [['sku' => 'SKU-001', 'stock' => 5], ...]
    ) {}

    public function handle(FeedService $feedService): void
    {
        $catalog = MetaCatalog::catalog()->findByMetaCatalogId(
            config('services.meta.catalog_id')
        );

        $items = array_map(fn($cambio) => [
            'id'                           => $cambio['sku'],
            'quantity_to_sell_on_facebook' => $cambio['stock'],
            'availability'                 => $cambio['stock'] > 0
                ? 'in stock'
                : 'out of stock',
        ], $this->cambios);

        MetaCatalog::batch()->updateItems($catalog, $items);
    }
}

// Dispatchar desde tu controlador de checkout
ActualizarInventarioMeta::dispatch([
    ['sku' => 'SKU-001', 'stock' => $nuevoStock],
])->onQueue('meta-sync');
```

## Ejemplo: Batch Mixto (Crear + Actualizar + Eliminar)

```php
$batch = MetaCatalog::batch()->sendBatch($catalog, [
    // Nuevos productos
    [
        'method' => 'CREATE',
        'data' => [
            'id'           => 'SKU-NUEVO',
            'title'        => 'Producto Nuevo',
            'price'        => '49.99 USD',
            'availability' => 'in stock',
            'link'         => 'https://mitienda.com/nuevo',
            'image_url'    => 'https://mitienda.com/img/nuevo.jpg',
        ],
    ],

    // Actualización de precio
    [
        'method' => 'UPDATE',
        'data' => [
            'id'    => 'SKU-001',
            'price' => '24.99 USD',  // rebaja de precio
        ],
    ],

    // Eliminar un producto descontinuado
    [
        'method' => 'DELETE',
        'data' => ['id' => 'SKU-VIEJO'],
    ],
]);

echo "Batch enviado. Handle: " . $batch->handle;
```
