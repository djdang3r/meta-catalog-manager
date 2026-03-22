# Productos

## ¿Qué es un Product Item?

Un product item es un ítem individual dentro de un catálogo. Puede ser un producto de e-commerce, un vehículo, una propiedad, etc. dependiendo de la vertical del catálogo.

Los productos se pueden gestionar de tres formas:
1. **Via Feed** — archivo CSV/XML que Meta procesa periódicamente
2. **Via Batch API** — actualizaciones en tiempo real en lotes
3. **Via API individual** — un producto a la vez (no recomendado para volumen)

## Campos Principales

| Campo | Tipo | Descripción |
|---|---|---|
| `retailer_id` | string | Tu ID interno del producto (SKU). **Único dentro del catálogo** |
| `title` | string | Nombre del producto |
| `description` | text | Descripción completa |
| `brand` | string | Marca |
| `price` | string | Precio con moneda. Ej: `"29.99 USD"` |
| `sale_price` | string | Precio de oferta. Ej: `"19.99 USD"` |
| `availability` | enum | `in stock`, `out of stock`, `preorder`, `available for order`, `discontinued` |
| `condition` | enum | `new`, `refurbished`, `used` |
| `image_url` | string | URL de la imagen principal |
| `link` | string | URL del producto en tu tienda |

## Categorías

### Google Product Category (`category`)

Categoría estándar de Google. Se usa para clasificación automática y elegibilidad en Google Shopping. Formato: número entero ID o cadena con la ruta completa.

```php
// Formato ID
'category' => '2271',  // Apparel & Accessories > Clothing

// Formato texto
'category' => 'Apparel & Accessories > Clothing > Shirts & Tops',
```

### Facebook Product Category (`fb_product_category`)

Categoría específica de Meta. Puede ser el nombre o el ID de la taxonomía de Facebook. Afecta la clasificación en el catálogo y la elegibilidad para ciertas funciones de shopping.

```php
'fb_product_category' => 'Clothing & Accessories > Clothing > Tops & T-Shirts',
// o bien el ID numérico de la taxonomía de Meta:
'fb_product_category' => '424',
```

## Variantes y `item_group_id`

Para productos con variantes (talle, color, etc.), todos los ítems del mismo grupo deben compartir el mismo `item_group_id`:

```php
// Remera Azul Talle M
MetaCatalog::batch()->createItems($catalog, [[
    'id'            => 'REMERA-AZUL-M',    // retailer_id
    'item_group_id' => 'REMERA-001',       // agrupa todas las variantes
    'title'         => 'Remera Clásica - Azul, M',
    'color'         => 'Azul',
    'size'          => 'M',
    'gender'        => 'male',
    'age_group'     => 'adult',
    'price'         => '29.99 USD',
    'availability'  => 'in stock',
    'link'          => 'https://mitienda.com/remera-001?color=azul&talle=M',
    'image_url'     => 'https://mitienda.com/img/remera-azul-m.jpg',
]]);

// Remera Roja Talle L (misma familia de producto)
MetaCatalog::batch()->createItems($catalog, [[
    'id'            => 'REMERA-ROJA-L',
    'item_group_id' => 'REMERA-001',       // mismo group_id
    'title'         => 'Remera Clásica - Roja, L',
    'color'         => 'Rojo',
    'size'          => 'L',
    // ...
]]);
```

Meta agrupa automáticamente las variantes en los anuncios, mostrando la más relevante para cada usuario.

## App Links / Deep Links

El campo `app_links` (JSON) permite definir deep links para abrir el producto directamente en tu app móvil en lugar del navegador.

```php
'app_links' => [
    // Android
    'android_app_name' => 'Mi Tienda',
    'android_package'  => 'com.mitienda.app',
    'android_url'      => 'mitienda://product/REMERA-001',

    // iOS universal
    'ios_app_name'     => 'Mi Tienda',
    'ios_app_store_id' => '1234567890',
    'ios_url'          => 'mitienda://product/REMERA-001',

    // iPhone específico (opcional, override de ios_*)
    'iphone_app_name'     => 'Mi Tienda',
    'iphone_app_store_id' => '1234567890',
    'iphone_url'          => 'mitienda://product/REMERA-001',

    // iPad específico (opcional)
    'ipad_app_name'     => 'Mi Tienda iPad',
    'ipad_app_store_id' => '1234567890',
    'ipad_url'          => 'mitienda://product/REMERA-001',

    // Windows Phone (legacy, raramente necesario)
    'windows_phone_app_name' => 'Mi Tienda',
    'windows_phone_app_id'   => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    'windows_phone_url'      => 'mitienda://product/REMERA-001',
],
```

## GTIN y MPN

Identificadores estándar de producto. Mejoran la calidad del catálogo y la coincidencia con el inventario de Google.

- **GTIN** (`gtin`): Global Trade Identification Number. Incluye UPC (North America), EAN (Europa), JAN (Japón), ISBN (libros). Ej: `"0614141999999"`
- **MPN** (`mpn`): Manufacturer Part Number. ID asignado por el fabricante. Ej: `"GO12345OOGLE"`

```php
'gtin' => '0614141999999',  // código de barras EAN/UPC
'mpn'  => 'GO12345OOGLE',   // número de parte del fabricante
```

## Visibility: `published` vs `staging`

El campo `visibility` controla si el producto es visible en anuncios:

- **`published`** (default): El producto aparece en anuncios y en el catálogo público
- **`staging`**: El producto existe en el catálogo pero NO aparece en anuncios

### ¿Para qué sirve `staging`?

En lugar de eliminar productos discontinuados (lo que puede romper campañas activas), Meta recomienda cambiarlos a `staging`. Así:
- No aparecen en nuevos anuncios
- Las campañas existentes que ya los usaban no se interrumpen abruptamente
- Podés volver a publicarlos fácilmente si regresan al stock

```php
// "Discontinuar" un producto sin eliminarlo
MetaCatalog::batch()->updateItems($catalog, [[
    'id'         => 'REMERA-001-AZUL-M',
    'visibility' => 'staging',
]]);

// Reactivarlo cuando vuelve al stock
MetaCatalog::batch()->updateItems($catalog, [[
    'id'         => 'REMERA-001-AZUL-M',
    'visibility' => 'published',
    'availability' => 'in stock',
    'quantity_to_sell_on_facebook' => 50,
]]);
```

## ProductService — Métodos Disponibles

```php
MetaCatalog::product()->{método}(...)
```

### `getFromApi(MetaCatalog $catalog, int $limit, ?string $after): array`

Obtiene productos con paginación desde la Graph API.

```php
$response = MetaCatalog::product()->getFromApi($catalog, 50);
$productos = $response['data'];
$nextCursor = $response['paging']['cursors']['after'] ?? null;
```

### `createSingle(MetaCatalog $catalog, array $data): array`

Crea un producto individual via API. Para volumen, usá Batch API.

```php
$response = MetaCatalog::product()->createSingle($catalog, [
    'retailer_id'  => 'SKU-001',
    'title'        => 'Remera Básica',
    'price'        => '25.00 USD',
    'availability' => 'in stock',
    'link'         => 'https://mitienda.com/remera-basica',
    'image_url'    => 'https://mitienda.com/img/remera.jpg',
]);
```

### `updateSingle(string $productItemId, MetaBusinessAccount $account, array $data): array`

Actualiza un producto por su Meta Product Item ID.

### `deleteSingle(string $productItemId, MetaBusinessAccount $account): array`

Elimina un producto de la API. Preferí usar `visibility: staging` en lugar de eliminar.

### `syncFromApi(MetaCatalog $catalog): int`

Sincroniza todos los productos de la API hacia la DB local con paginación automática.

```php
$count = MetaCatalog::product()->syncFromApi($catalog);
echo "Productos sincronizados: {$count}";
```

### `findLocal(string $retailerId, MetaCatalog $catalog): ?MetaCatalogItem`

Busca un producto en la DB local por `retailer_id`.

```php
$item = MetaCatalog::product()->findLocal('SKU-001', $catalog);
if ($item) {
    echo $item->title . ' — ' . $item->availability->value;
}
```
