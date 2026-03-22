# Catálogos Localizados

## Información general

Los catálogos localizados permiten configurar tu catálogo de productos para mostrar artículos en diferentes países e idiomas. Con esta funcionalidad podés:

- **Localizar precios y moneda** por país (ej: USD para EE.UU., EUR para Italia)
- **Traducir títulos y descripciones** por idioma (ej: español, francés, inglés)
- **Proporcionar URLs localizadas** por país o idioma (ej: `https://mitienda.com/ar/producto`)
- **Controlar disponibilidad** por país (`in stock` en UK, `out of stock` en otros)

Meta implementa esto mediante "feeds de reemplazo" (`override_type`) que se vinculan al catálogo principal y **sobreescriben campos específicos** según el país o idioma del espectador.

---

## Tipos de feeds localizados

| Tipo | `override_type` | Descripción |
|------|----------------|-------------|
| **Lista de idiomas** | `language` | Traducciones: `title`, `description`, `brand`. No incluir precio. |
| **Lista de países** | `country` | Precios, disponibilidad y URLs por país. Sí incluir `price`, `availability`. |
| **Lista de idioma+país** | `language_and_country` | Casos avanzados: URLs que dependen de **ambos** (idioma y país). |

> **Regla crítica**: `price`, `sale_price`, `unit_price`, `base_price`, `status` y `availability` **solo pueden ir en la lista de países**, no en la de idiomas. Esto garantiza que los clientes vean los datos de precio correctos.

---

## Campos localizables

### Productos
- `title`, `description`, `brand`
- `availability`, `link`
- `price`, `sale_price`, `sale_price_effective_date`
- `color`, `size`, `material`, `pattern`
- `custom_label_[0-4]`, `short_description`, `additional_variant_attribute`
- `image[0].url`, `image[0].tag[0]` *(usar campos anidados, no `image_link`)*
- `applink.ios_url`, `applink.android_url`, etc. *(deben proporcionarse todos los campos applink juntos)*

### Hoteles
`name`, `description`, `base_price`, `sale_price`, `brand`, `url`, `neighborhood`, `longitude`, `latitude`, `image[0].url`, applinks

### Vuelos
`description`, `url`, `origin_city`, `destination_city`, `price`, `one_way_price`, `image[0].url`, `custom_label_[0-4]`, `custom_number_[0-4]`, applinks

### Vehículos
`title`, `description`, `price`, `sale_price`, `url`, `image[0].url`

---

## Formato del archivo CSV

### Lista de idiomas
```csv
id; override; description; title; delete
FB_product_1234; es_XX; Camiseta unisex de algodón orgánico.; Camiseta Unisex; false
FB_product_1234; fr_XX; Le t-shirt préféré de tous.; T-shirt Unisexe; false
```

### Lista de países
```csv
id; override; price; link; delete
FB_product_1234; GB; 9.00 GBP; https://mitienda.com/en_GB/producto; false
FB_product_1234; IT; 10.49 EUR; https://mitienda.com/it_IT/producto; false
```

### Lista de idioma+país
```csv
id; override; link; delete
FB_product_1234; fr_XX|CA; https://ca.mitienda.com/fr/producto; false
FB_product_1234; fr_XX|US; https://us.mitienda.com/fr/producto; false
```

Para `language_and_country` el valor del campo `override` es `{idioma_ISO}|{país_ISO}`, por ejemplo `fr_XX|CA` (francés en Canadá).

---

## Enum `FeedOverrideType`

```php
use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;

FeedOverrideType::LANGUAGE;              // 'language'
FeedOverrideType::COUNTRY;               // 'country'
FeedOverrideType::LANGUAGE_AND_COUNTRY;  // 'language_and_country'
```

---

## Crear feeds localizados con `FeedService`

### Feed de idiomas
```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

$catalog = MetaCatalog::catalog()->find('01HV...');

// Sin schedule (se sube manualmente después)
$languageFeed = MetaCatalog::feed()->createLanguageFeed(
    $catalog,
    'Language Feed ES/FR/EN'
);

// Con schedule diario
$languageFeed = MetaCatalog::feed()->createLanguageFeed(
    $catalog,
    'Language Feed ES/FR',
    [
        'interval' => 'DAILY',
        'url'      => 'https://mitienda.com/feeds/idiomas.csv',
        'hour'     => 22,
    ]
);
```

### Feed de países
```php
$countryFeed = MetaCatalog::feed()->createCountryFeed(
    $catalog,
    'Country Feed US/UK/IT',
    [
        'interval' => 'DAILY',
        'url'      => 'https://mitienda.com/feeds/paises.csv',
        'hour'     => 3,
    ]
);
```

### Feed de idioma+país
```php
$localeFeed = MetaCatalog::feed()->createLanguageAndCountryFeed(
    $catalog,
    'Language+Country Feed fr_XX|CA/US',
    [
        'interval' => 'DAILY',
        'url'      => 'https://mitienda.com/feeds/locale.tsv',
        'hour'     => 4,
    ]
);
```

### Método genérico `createLocalizedFeed()`
```php
use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;

$feed = MetaCatalog::feed()->createLocalizedFeed(
    $catalog,
    'Mi Feed Localizado',
    FeedOverrideType::COUNTRY,
    ['interval' => 'WEEKLY', 'url' => 'https://...', 'hour' => 6]
);

echo $feed->override_type->value; // 'country'
echo $feed->meta_feed_id;         // ID en Meta
```

### Obtener feeds localizados de un catálogo
```php
$localizedFeeds = MetaCatalog::feed()->getLocalizedFeeds($catalog);

foreach ($localizedFeeds as $feed) {
    echo "{$feed->name} — {$feed->override_type->value}\n";
}
```

---

## Subir datos al feed localizado

Una vez creado el feed, usás los mismos métodos de `FeedService` para subir el archivo:

```php
// Desde URL
MetaCatalog::feed()->uploadFromUrl($languageFeed, 'https://mitienda.com/idiomas.csv');

// Desde archivo local
MetaCatalog::feed()->uploadFromFile($languageFeed, '/path/to/paises.tsv', 'text/tab-separated-values');
```

---

## Override Details API — `getOverrideDetails()`

La **Override Details API** te permite consultar qué datos localizados tiene configurado un artículo específico en Meta.

### Endpoint
```
GET /{item_id}/override_details
```

### Uso básico
```php
$account = MetaCatalog::account()->find('01HV...');
$productItemId = '12345678901234'; // meta_product_item_id de Meta

// Obtener todos los overrides del artículo
$overrides = MetaCatalog::product()->getOverrideDetails(
    $productItemId,
    $account
);
```

### Filtrar por claves específicas
```php
// Solo ver los overrides de EE.UU., español y Alemania
$overrides = MetaCatalog::product()->getOverrideDetails(
    $productItemId,
    $account,
    keys: ['US', 'es_XX', 'DE']
);
```

### Filtrar por tipo de override
```php
use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;

// Solo overrides de países
$countryOverrides = MetaCatalog::product()->getOverrideDetails(
    $productItemId,
    $account,
    type: FeedOverrideType::COUNTRY
);

// Solo overrides de idioma
$languageOverrides = MetaCatalog::product()->getOverrideDetails(
    $productItemId,
    $account,
    type: FeedOverrideType::LANGUAGE
);
```

### Respuesta de ejemplo
```json
{
  "override_details": [
    {
      "type": "country",
      "key": "US",
      "values": {
        "price": "9.99 USD",
        "availability": "in stock"
      }
    },
    {
      "type": "country",
      "key": "IT",
      "values": {
        "price": "10.49 EUR",
        "availability": "in stock",
        "link": "https://mitienda.com/it/producto"
      }
    },
    {
      "type": "language",
      "key": "es_XX",
      "values": {
        "title": "Camiseta Unisex",
        "description": "Camiseta de algodón 100% orgánico."
      }
    }
  ]
}
```

---

## Orden de prioridad de datos localizados

Cuando Meta decide qué información mostrarle a un usuario, aplica este orden de prioridad:

1. **Lista de idioma+país** — si existe un override para el idioma y país específico del usuario
2. **Lista de idiomas** — si existe un override para el idioma del usuario
3. **Lista de países** — si existe un override para el país del usuario
4. **Datos del catálogo principal** — si no hay ningún override aplicable

---

## Scopes en el modelo `MetaProductFeed`

```php
use ScriptDevelop\MetaCatalogManager\Models\MetaProductFeed;

// Todos los feeds localizados (con override_type)
MetaProductFeed::localized()->get();

// Solo feeds de idioma
MetaProductFeed::languageOverride()->get();

// Solo feeds de país
MetaProductFeed::countryOverride()->get();

// Solo feeds de idioma+país
MetaProductFeed::languageAndCountryOverride()->get();
```

---

## Eliminar localización de un artículo

Para eliminar la información localizada de un artículo específico, incluí una columna `delete` con valor `true` en tu feed:

```csv
id; override; delete
FB_product_1234; IT; true
```

> Cuando eliminás un artículo del catálogo **principal**, también se eliminan automáticamente todos sus datos de reemplazo.

---

## Mejores prácticas

| Escenario | Recomendación |
|-----------|--------------|
| Catálogo grande (+100k artículos) | Crear una lista separada por cada idioma o país |
| Misma moneda en todos los países | Usar solo el catálogo principal, sin feed de países |
| Traducciones independientes de precio | Separar lista de idiomas y lista de países |
| URL única por locale (idioma+país) | Usar `language_and_country` solo para el campo `link` |
| `title` y `description` | Siempre en la lista de **idiomas** |
| `price`, `availability`, `link` | Siempre en la lista de **países** |

---

## Notas importantes

- Podés definir hasta **350 pares de idioma+país** por artículo de catálogo.
- La columna `id` de los feeds de reemplazo debe coincidir con el `id` del catálogo principal (o el `content_id` del píxel para anuncios dinámicos).
- Siempre incluí el campo `override` en tu feed de reemplazo con el código ISO correspondiente.
- Los datos de los feeds localizados **nunca** se almacenan localmente en este paquete — se mantienen en Meta. Usá `getOverrideDetails()` para consultarlos.
