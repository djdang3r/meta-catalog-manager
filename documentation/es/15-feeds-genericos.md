# Feeds Genéricos (Generic Feed Files API)

## ¿Qué es la Generic Feed Files API?

Es una API de Meta diseñada para feeds especializados que van más allá del catálogo de productos estándar. Permite enviar datos de promociones, perfiles de envío, menús de navegación y calificaciones directamente a Meta Commerce.

---

## Diferencia con la Feed API estándar

| Característica | Feed API Estándar | Generic Feed Files API |
|---|---|---|
| Endpoint base | `/{catalog_id}/product_feeds` | `/{commerce_partner_integration_id}/file_update` |
| Requiere | `catalog_id` | `commerce_partner_integration_id` |
| Usa | Meta Catalog ID | Commerce Partner Integration ID |
| Para qué | Productos del catálogo | Datos de tienda (envíos, menú, promociones) |
| Upload | `/{feed_id}/uploads` | Directo al endpoint `/file_update` |

---

## Cuándo usar cada API

**Feed API Estándar** (FeedService):
- Subir/actualizar productos del catálogo
- Feeds suplementarios con precio y disponibilidad
- Calificaciones y opiniones (también soportada acá)

**Generic Feed Files API** (GenericFeedService):
- Perfiles de envío (`SHIPPING_PROFILES`)
- Menú de navegación del shop (`NAVIGATION_MENU`)
- Promociones (`PROMOTIONS`)
- Calificaciones y opiniones (`PRODUCT_RATINGS_AND_REVIEWS`) — alternativa

---

## Obtener el `commerce_partner_integration_id`

Este ID se obtiene cuando conectás tu tienda con Meta Commerce Partner. No es el mismo que el Business ID ni el Catalog ID. Se puede encontrar en:
```
Meta Business Manager → Commerce Manager → [tu shop] → Configuración → Integraciones de socios
```

---

## GenericFeedService — métodos disponibles

### `createFeed()`

Crea un feed genérico en el catálogo (para feeds que pasan por `/{catalog_id}/product_feeds`):

```php
$feed = MetaCatalog::genericFeed()->createFeed(
    $catalog,
    \ScriptDevelop\MetaCatalogManager\Enums\GenericFeedType::PRODUCT_RATINGS_AND_REVIEWS,
    'Mi Feed de Reviews'
);
```

### `uploadFromUrl()`

```php
MetaCatalog::genericFeed()->uploadFromUrl($feed, 'https://tienda.com/feeds/reviews.csv');
```

### `uploadFromFile()`

```php
MetaCatalog::genericFeed()->uploadFromFile($feed, '/tmp/reviews.csv', 'text/csv');
```

### `uploadViaGenericApi()`

Usa el endpoint `/{commerce_partner_integration_id}/file_update`:

```php
MetaCatalog::genericFeed()->uploadViaGenericApi(
    $account,
    'COMMERCE-PARTNER-ID-123',
    \ScriptDevelop\MetaCatalogManager\Enums\GenericFeedType::SHIPPING_PROFILES,
    '/tmp/shipping-profiles.csv'
);
```

### `getUploadSessions()`

```php
$sessions = MetaCatalog::genericFeed()->getUploadSessions($feed);
// $sessions['data'] contiene el historial de uploads
```

### `getUploadErrors()`

```php
$errors = MetaCatalog::genericFeed()->getUploadErrors(
    $uploadSessionId,
    $account,
    'ERROR'  // null para todos, o: 'FATAL', 'ERROR', 'WARNING'
);
```

---

## Shortcuts por tipo

```php
// Perfiles de envío
MetaCatalog::genericFeed()->uploadShippingProfiles($account, $partnerId, '/tmp/shipping.csv');

// Calificaciones y opiniones
MetaCatalog::genericFeed()->uploadRatingsAndReviews($catalog, '/tmp/reviews.csv');

// Promociones
MetaCatalog::genericFeed()->uploadPromotions($account, $partnerId, '/tmp/promotions.csv');

// Menú de navegación
MetaCatalog::genericFeed()->uploadNavigationMenu($account, $partnerId, '/tmp/nav.json');
```

---

## Perfiles de Envío — Schema del CSV

Los perfiles de envío definen las opciones de envío disponibles en el shop:

```csv
shipping_profile_id*,name*,shipping_zone.country_code*,shipping_zone.region,shipping_zone.postal_code_range.lower,shipping_zone.postal_code_range.upper,shipping_rate.price.amount*,shipping_rate.price.currency*,shipping_rate.price_type,shipping_rate.free_shipping_min_order_value,shipping_option_type*,estimated_shipping_days_min,estimated_shipping_days_max,applies_to_all_products,applicable_products.retailer_id
"PROFILE-001","Envío Estándar","US","","","","5.99","USD","FLAT","0","STANDARD","3","7","true",""
"PROFILE-001","Envío Estándar","US","","","","0.00","USD","FREE","75.00","STANDARD","3","7","true",""
"PROFILE-002","Envío Express","US","","","","14.99","USD","FLAT","0","RUSH","1","2","true",""
```

### Campos del CSV de envío

| Campo | Requerido | Descripción |
|---|---|---|
| `shipping_profile_id` | SI | ID único del perfil (tu sistema) |
| `name` | SI | Nombre descriptivo |
| `shipping_zone.country_code` | SI | Código ISO del país (ej: `US`) |
| `shipping_zone.region` | NO | Estado/provincia |
| `shipping_zone.postal_code_range.lower` | NO | CP mínimo |
| `shipping_zone.postal_code_range.upper` | NO | CP máximo |
| `shipping_rate.price.amount` | SI | Precio del envío (0 para gratis) |
| `shipping_rate.price.currency` | SI | Moneda (ej: `USD`) |
| `shipping_rate.price_type` | SI | `FLAT` o `FREE` |
| `shipping_rate.free_shipping_min_order_value` | NO | Monto mínimo para envío gratis |
| `shipping_option_type` | SI | `STANDARD`, `RUSH`, `EXPEDITED` |
| `estimated_shipping_days_min` | NO | Días mínimos de entrega |
| `estimated_shipping_days_max` | NO | Días máximos de entrega |
| `applies_to_all_products` | SI | `true` o `false` |
| `applicable_products.retailer_id` | NO | Si no aplica a todos, IDs específicos |

---

## Menú de Navegación — Estructura JSON

```json
{
    "id": "nav-root",
    "resourceType": "navigation",
    "items": [
        {
            "id": "cat-mujer",
            "label": "Mujer",
            "resourceType": "collection",
            "resourceId": "PRODUCT-SET-MUJER-123",
            "items": [
                {
                    "id": "cat-mujer-zapatillas",
                    "label": "Zapatillas",
                    "resourceType": "collection",
                    "resourceId": "PRODUCT-SET-ZAPATILLAS-MUJER",
                    "items": []
                },
                {
                    "id": "prod-nuevo-mujer",
                    "label": "Lo nuevo",
                    "resourceType": "product",
                    "resourceId": "SKU-NUEVO-MUJER-001",
                    "items": []
                }
            ]
        },
        {
            "id": "cat-hombre",
            "label": "Hombre",
            "resourceType": "collection",
            "resourceId": "PRODUCT-SET-HOMBRE-456",
            "items": []
        },
        {
            "id": "promo-especial",
            "label": "Ofertas especiales",
            "resourceType": "other",
            "resourceId": null,
            "url": "https://tienda.com/ofertas",
            "items": []
        }
    ]
}
```

### Tipos de `resourceType`

| Valor | Descripción | Requiere |
|---|---|---|
| `navigation` | Nodo raíz del árbol | — |
| `collection` | Categoría / Product Set | `resourceId` = product set ID |
| `product` | Producto individual | `resourceId` = retailer_id |
| `other` | Link arbitrario | `url` |

---

## Manejo de errores de upload

El flujo correcto para verificar errores:

```php
use ScriptDevelop\MetaCatalogManager\Models\MetaGenericFeed;

// 1. Hacer el upload
$response = MetaCatalog::genericFeed()->uploadShippingProfiles(
    $account,
    $partnerId,
    '/tmp/shipping.csv'
);

// 2. Guardar el upload_session_id
$sessionId = $response['id'];

// 3. Más tarde, verificar sesiones del feed
$sessions = MetaCatalog::genericFeed()->getUploadSessions($feed);

foreach ($sessions['data'] as $session) {
    if (($session['status'] ?? '') === 'finished_with_errors') {
        // 4. Obtener errores detallados
        $errors = MetaCatalog::genericFeed()->getUploadErrors(
            $session['id'],
            $account,
            'ERROR'  // solo errores críticos
        );

        foreach ($errors['data'] ?? [] as $error) {
            logger()->error('Error en feed genérico', [
                'summary'     => $error['summary'] ?? '',
                'description' => $error['description'] ?? '',
                'severity'    => $error['severity'] ?? '',
                'line'        => $error['row_number'] ?? null,
            ]);
        }
    }
}
```

### Valores de `error_priority`

| Valor | Descripción |
|---|---|
| `FATAL` | El upload falló completamente |
| `ERROR` | Filas con errores (descartadas) |
| `WARNING` | Advertencias (procesadas pero con avisos) |
| `null` | Todos los niveles |
