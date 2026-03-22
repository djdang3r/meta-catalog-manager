# Ofertas (Offers API)

La Offers API de Meta permite crear descuentos y promociones directamente en el catálogo de productos, visibles en Facebook e Instagram Shopping.

## Tipos de oferta (`application_type`)

### SALE

Descuento aplicado directamente al precio del producto. El precio tachado se muestra automáticamente.

```php
MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'         => 'PROMO-VERANO-2025',
    'title'            => '30% off en toda la colección verano',
    'application_type' => 'SALE',
    'value_type'       => 'PERCENTAGE',
    'percent_off'      => 30,
    'start_date_time'  => '2025-12-01 00:00:00',
    'end_date_time'    => '2025-12-31 23:59:59',
]);
```

### AUTOMATIC_AT_CHECKOUT

El descuento se aplica automáticamente cuando el cliente agrega el producto al carrito, sin necesidad de código.

```php
MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'         => 'AUTO-10USD',
    'title'            => '$10 de descuento automático',
    'application_type' => 'AUTOMATIC_AT_CHECKOUT',
    'value_type'       => 'FIXED_AMOUNT',
    'fixed_amount_off' => '10.00 USD',
    'start_date_time'  => '2025-12-01 00:00:00',
]);
```

> **Limitación**: Meta permite un máximo de **25 ofertas AUTOMATIC_AT_CHECKOUT activas** simultáneamente por catálogo.

### BUYER_APPLIED

El comprador ingresa un código de cupón para aplicar el descuento.

```php
MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'           => 'CUPON-CYBER25',
    'title'              => 'Cyber Monday 25% off',
    'application_type'   => 'BUYER_APPLIED',
    'value_type'         => 'PERCENTAGE',
    'percent_off'        => 25,
    'public_coupon_code' => 'CYBER25',
    'start_date_time'    => '2025-11-25 00:00:00',
    'end_date_time'      => '2025-11-25 23:59:59',
]);
```

> **Limitación**: Máximo **10 cupones públicos activos** (`public_coupon_code`) simultáneamente por catálogo.

---

## Tipos de descuento (`value_type`)

### FIXED_AMOUNT

Monto fijo en la moneda del catálogo:

```php
'value_type'       => 'FIXED_AMOUNT',
'fixed_amount_off' => '15.00 USD',  // formato: "monto MONEDA"
```

### PERCENTAGE

Porcentaje de descuento (0-100):

```php
'value_type'  => 'PERCENTAGE',
'percent_off' => 20,   // entero, no decimal
```

---

## Shipping Offers (envío gratuito)

Para ofrecer envío gratuito, usar `target_type = SHIPPING`:

```php
MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'             => 'FREE-SHIPPING-DIC',
    'title'                => 'Envío gratis en diciembre',
    'application_type'     => 'AUTOMATIC_AT_CHECKOUT',
    'value_type'           => 'PERCENTAGE',
    'percent_off'          => 100,
    'target_type'          => 'SHIPPING',
    'target_granularity'   => 'ORDER_LEVEL',
    'target_shipping_option_types' => ['STANDARD'],
    'start_date_time'      => '2025-12-01 00:00:00',
    'end_date_time'        => '2025-12-31 23:59:59',
]);
```

Tipos de envío disponibles en `target_shipping_option_types`:
- `STANDARD`
- `RUSH`
- `EXPEDITED`

---

## Buy X Get Y

Ofertas del tipo "compra X cantidad, recibe descuento en Y":

```php
MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'                   => 'BUY2GET1',
    'title'                      => 'Comprá 2, llevate el 3ro con 50% off',
    'application_type'           => 'AUTOMATIC_AT_CHECKOUT',
    'value_type'                 => 'PERCENTAGE',
    'percent_off'                => 50,
    'target_type'                => 'LINE_ITEM',
    'target_granularity'         => 'ITEM_LEVEL',
    'target_selection'           => 'SPECIFIC_PRODUCTS',
    // Prerrequisito: comprar mínimo 2 unidades
    'min_quantity'               => 2,
    'prerequisite_product_retailer_ids' => ['SKU-001', 'SKU-002'],
    // Target: a qué se aplica el descuento
    'target_quantity'            => 1,
    'target_product_retailer_ids' => ['SKU-001', 'SKU-002'],
    'redemption_limit_per_order' => 1,
    'start_date_time'            => '2025-12-01 00:00:00',
]);
```

Campos de Buy X Get Y:
| Campo | Descripción |
|---|---|
| `min_quantity` | Cantidad mínima a comprar (X) |
| `min_subtotal` | Monto mínimo (alternativa a min_quantity): `"30.99 USD"` |
| `target_quantity` | Cantidad de ítems que reciben el descuento (Y) |
| `redemption_limit_per_order` | Cuántas veces se puede aplicar por orden |
| `prerequisite_*` | Qué productos debe comprar el cliente |
| `target_*` | A qué productos aplica el descuento |

---

## Especificar productos elegibles

### Todos los productos del catálogo (default)

```php
'target_selection' => 'ALL_CATALOG_PRODUCTS',
```

### Productos específicos por retailer ID

```php
'target_selection'            => 'SPECIFIC_PRODUCTS',
'target_product_retailer_ids' => ['SKU-001', 'SKU-002', 'SKU-003'],
```

### Por grupos de variantes

```php
'target_product_group_retailer_ids' => ['GROUP-ZAPATOS', 'GROUP-BOTAS'],
```

### Por Product Sets

```php
'target_product_set_retailer_ids' => ['set-id-123', 'set-id-456'],
```

### Con filtros dinámicos

```php
'target_filter' => [
    'retailer_id' => ['is_any' => ['SKU-001', 'SKU-002']],
    'availability' => ['is_any' => ['in stock']],
],
```

---

## Coupon codes vs public_coupon_code vs automatic

| Campo | Tipo | Descripción |
|---|---|---|
| `coupon_codes` | array de strings | Códigos de uso único (max 100 por oferta). Cada código solo puede ser usado por un comprador. |
| `public_coupon_code` | string (max 20 chars) | Un código visible públicamente que todos pueden usar. |
| *(sin código)* | — | `AUTOMATIC_AT_CHECKOUT`: no se requiere código. |

Ejemplo con códigos de uso único:

```php
MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'         => 'INFLUENCER-CODES',
    'application_type' => 'BUYER_APPLIED',
    'value_type'       => 'PERCENTAGE',
    'percent_off'      => 15,
    'coupon_codes'     => ['INFLUENCER01', 'INFLUENCER02', 'INFLUENCER03'],
    'start_date_time'  => '2025-12-01 00:00:00',
]);
```

---

## Combinación de ofertas (stacking)

Meta aplica las ofertas en este orden de prioridad:
1. Ofertas SALE (precio ya descontado en el item)
2. Ofertas AUTOMATIC_AT_CHECKOUT
3. Ofertas BUYER_APPLIED (cupones)

Un producto puede tener precio de venta (`sale_price` del feed) **y** además una oferta activa. La oferta se calcula sobre el precio de venta.

Para excluir productos ya en oferta:
```php
'exclude_sale_priced_products' => true,
```

---

## Términos y condiciones

```php
'offer_terms' => 'Válido solo para compras superiores a $50. No acumulable con otras promociones. Hasta agotar stock.',
// Máximo 2500 caracteres
```

---

## OfferService — métodos disponibles

```php
// Obtener feeds de tipo OFFER desde la API
MetaCatalog::offer()->fetchFromApi(MetaCatalog $catalog): array

// Sincronizar offers desde API a DB
MetaCatalog::offer()->syncFromApi(MetaCatalog $catalog): Collection

// Crear oferta
MetaCatalog::offer()->createOffer(MetaCatalog $catalog, array $data): MetaCatalogOffer

// Actualizar oferta (solo DB local)
MetaCatalog::offer()->updateOffer(MetaCatalogOffer $offer, array $data): MetaCatalogOffer

// Eliminar oferta (soft delete)
MetaCatalog::offer()->deleteOffer(MetaCatalogOffer $offer): bool

// Buscar por ULID interno
MetaCatalog::offer()->find(string $id): ?MetaCatalogOffer

// Todas las ofertas de un catálogo
MetaCatalog::offer()->forCatalog(MetaCatalog $catalog): Collection

// Ofertas SALE activas
MetaCatalog::offer()->getActiveSales(MetaCatalog $catalog): Collection

// Cupones (BUYER_APPLIED) activos
MetaCatalog::offer()->getActiveCoupons(MetaCatalog $catalog): Collection
```

---

## Scopes del modelo MetaCatalogOffer

```php
// Ofertas actualmente vigentes (status=active + fechas válidas)
MetaCatalogOffer::active()->get()

// Por estado
MetaCatalogOffer::inactive()->get()
MetaCatalogOffer::expired()->get()

// Por tipo
MetaCatalogOffer::saleType()->get()
MetaCatalogOffer::automaticType()->get()
MetaCatalogOffer::buyerApplied()->get()

// Combinados
MetaCatalogOffer::where('meta_catalog_id', $catalog->id)->active()->saleType()->get()
```

---

## Helper methods del modelo

```php
$offer->isActive(): bool        // true si vigente en este momento
$offer->isSale(): bool          // true si es tipo SALE
$offer->hasCouponCode(): bool   // true si tiene códigos de cupón
$offer->isShippingOffer(): bool // true si es descuento de envío
```
