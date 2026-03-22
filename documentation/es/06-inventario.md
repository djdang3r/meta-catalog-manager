# Inventario

## Ciclo de Vida del Inventario

Meta distingue dos estados de inventario para cada producto:

- **`provided`**: Lo que vos le decís a Meta que tenés disponible
- **`available`**: Lo que Meta realmente muestra en anuncios (puede diferir por reglas internas)

El campo clave es `quantity_to_sell_on_facebook` (o su equivalente en feeds: `quantity_to_sell_on_facebook`). Este campo reemplaza al antiguo `inventory` y es el que Meta usa para:
- Dejar de mostrar un producto cuando llega a 0
- Cambiar automáticamente a otra variante disponible del mismo `item_group_id`

## Estrategias de Actualización

### Pre-allocated (Para catálogos pequeños o actualizaciones poco frecuentes)

Cargás el inventario inicial en el feed y lo actualizás periódicamente con un replace schedule. Es la estrategia más simple pero con mayor latencia.

```
Feed inicial → replace_schedule DAILY → inventario actualizado 1 vez por día
```

**Cuándo usarla:** Tiendas con menos de 1000 productos, sin rotación rápida de stock.

### Slow-selling (Feed con schedule dual)

Usás un `replace_schedule` DAILY para el catálogo completo, más un `update_schedule` HOURLY para actualizaciones incrementales. El update schedule solo crea/actualiza, nunca elimina.

```
replace_schedule DAILY  → catálogo completo una vez al día
update_schedule  HOURLY → delta de cambios cada hora
```

**Cuándo usarla:** Catálogos medianos con cambios moderados de inventario.

### Fast-selling (Batch API en tiempo real)

Para productos con alta rotación (electrónicos, tickets, moda), usás la Batch API para actualizar inventario en tiempo real directamente desde tu sistema de gestión de stock.

```
Tu sistema ERP/WMS
       ↓
Batch API (actualización inmediata)
       ↓
Meta Catalog (inventario actualizado en segundos)
```

**Cuándo usarla:** Ecommerce de alto volumen, productos con riesgo de sobreventa.

## `quantity_to_sell_on_facebook`

Este campo define cuántas unidades están disponibles para venta via Meta. Es independiente de tu stock real.

```php
// Actualizar inventario via Batch (recomendado para tiempo real)
MetaCatalog::batch()->updateItems($catalog, [
    [
        'id'                           => 'SKU-001',
        'quantity_to_sell_on_facebook' => 15,
        'availability'                 => 'in stock',
    ],
    [
        'id'                           => 'SKU-002',
        'quantity_to_sell_on_facebook' => 0,
        'availability'                 => 'out of stock',
    ],
]);
```

> **Nota:** Cuando `quantity_to_sell_on_facebook` llega a 0, Meta puede seguir mostrando el producto un tiempo hasta que actualice su caché interna. Por eso es importante también setear `availability: out of stock`.

## Productos Agotados

Cuando un producto se agota, Meta tiene el siguiente comportamiento:

1. Si el producto tiene variantes (`item_group_id`), Meta intenta mostrar automáticamente una variante disponible del mismo grupo
2. Si no hay variantes disponibles, el producto deja de aparecer en anuncios
3. El anuncio no se pausa automáticamente — sigue activo pero sin ese producto

**Comportamiento recomendado al agotar stock:**

```php
// Opción A: Marcar como out of stock (Meta lo excluye de anuncios pero permanece en catálogo)
MetaCatalog::batch()->updateItems($catalog, [[
    'id'                           => 'SKU-001',
    'availability'                 => 'out of stock',
    'quantity_to_sell_on_facebook' => 0,
]]);

// Opción B: Cambiar a staging (más agresivo — excluye de TODO)
MetaCatalog::batch()->updateItems($catalog, [[
    'id'         => 'SKU-001',
    'visibility' => 'staging',
]]);
```

## Sobreventa

Meta **no provee transacciones atómicas** para el inventario. Esto significa que si dos personas compran el último ítem casi simultáneamente, ambas órdenes pueden procesarse antes de que Meta actualice el inventario.

**Estrategias para minimizar sobreventa:**

1. **Actualización inmediata post-compra:** Tan pronto se confirma un pago, actualizá el inventario via Batch API
2. **Buffer de seguridad:** Reportar a Meta `(stock_real - buffer)` unidades. Ej: si tenés 10, reportá 8
3. **Polling frecuente:** Aumentar la frecuencia del update schedule (HOURLY en lugar de DAILY)
4. **Inventario propio como fuente de verdad:** El stock en Meta es solo una "vista" — tu sistema siempre tiene la última palabra

```php
// Ejemplo: actualizar inventario post-compra con buffer
$stockReal = 10;
$buffer    = 2;
$stockMeta = max(0, $stockReal - $buffer);

MetaCatalog::batch()->updateItems($catalog, [[
    'id'                           => 'SKU-001',
    'quantity_to_sell_on_facebook' => $stockMeta,
    'availability'                 => $stockMeta > 0 ? 'in stock' : 'out of stock',
]]);
```

## Cómo Actualizar Inventario en Tiempo Real con BatchService

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

// Tu listener de evento de venta
class ProductoVendidoListener
{
    public function handle(ProductoVendido $event): void
    {
        $catalog = MetaCatalog::catalog()->findByMetaCatalogId(
            config('meta.catalog_id')
        );

        // Actualizar solo el producto vendido
        $batch = MetaCatalog::batch()->updateItems($catalog, [
            [
                'id'                           => $event->sku,
                'quantity_to_sell_on_facebook' => $event->nuevoStock,
                'availability'                 => $event->nuevoStock > 0
                    ? 'in stock'
                    : 'out of stock',
            ]
        ]);

        // Opcionalmente verificar el estado del batch
        // $result = MetaCatalog::batch()->checkStatus($batch);
    }
}
```
