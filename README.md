# Meta Catalog Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/scriptdevelop/meta-catalog-manager.svg?style=flat-square)](https://packagist.org/packages/scriptdevelop/meta-catalog-manager)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue?style=flat-square)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

**Laravel package for the Meta Marketing API** — complete multi-account catalog management: products, feeds, batch inventory updates, offers, diagnostics, localized catalogs and more.

---

## Features

- **Multi-account** — multiple Meta Business Manager accounts, each with multiple catalogs
- **Token encryption** — `app_id`, `app_secret`, `access_token` encrypted at rest using Laravel's `encrypt()`
- **Product Feeds** — primary, supplementary, and localized feeds (language / country / language+country)
- **Batch API** — real-time inventory updates, create/update/delete items, localized batch
- **Inventory history** — immutable audit log of every stock change (`meta_inventory_logs`)
- **Offers API** — `SALE`, `AUTOMATIC_AT_CHECKOUT`, `BUYER_APPLIED` (coupons, Buy X Get Y, free shipping)
- **Diagnostics** — catalog errors, event source issues, pixel/app DA checks
- **Event Stats** — pixel and app event metrics per catalog
- **Generic Feed Files API** — promotions, shipping profiles, navigation menu, ratings & reviews
- **Commerce Merchant Settings** — checkout config, merchant status, Korea FTC compliance
- **Page-Owned Catalogs** — create and list catalogs owned by a Facebook Page
- **Override Details API** — inspect localized overrides per product item
- **Best practices compliance** — `google_product_category`, `rich_text_description`, `product_type`, `internal_label`
- **17-file Spanish documentation** included

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^12.0` or `^13.0` |
| Guzzle | `^7.0` |

---

## Installation

```bash
composer require scriptdevelop/meta-catalog-manager
```

### Run the install wizard

```bash
php artisan meta-catalog:install
```

The wizard will:
1. Publish the configuration file to `config/meta-catalog.php`
2. Optionally merge the `meta-catalog` log channel into your `config/logging.php`
3. Optionally run the package migrations

### Publish manually

```bash
# Configuration
php artisan vendor:publish --tag=meta-catalog-config

# Migrations
php artisan vendor:publish --tag=meta-catalog-migrations

# Logging channel
php artisan vendor:publish --tag=meta-catalog-logging
```

### Run migrations

```bash
php artisan migrate
```

---

## Configuration

```php
// config/meta-catalog.php

'api' => [
    'base_url'      => env('META_CATALOG_API_URL', 'https://graph.facebook.com'),
    'graph_version' => env('META_CATALOG_GRAPH_VERSION', 'v22.0'),
    'timeout'       => env('META_CATALOG_API_TIMEOUT', 30),
    'retries'       => env('META_CATALOG_API_RETRIES', 3),
],
```

### Environment variables

```env
META_CATALOG_API_URL=https://graph.facebook.com
META_CATALOG_GRAPH_VERSION=v22.0
META_CATALOG_API_TIMEOUT=30
META_CATALOG_API_RETRIES=3
META_CATALOG_AUTO_MIGRATIONS=true
META_CATALOG_LOG_CHANNEL=meta-catalog
```

### Override models

You can swap any model for your own extended class:

```php
// config/meta-catalog.php
'models' => [
    'meta_business_account' => \App\Models\MyMetaBusinessAccount::class,
    'meta_catalog'          => \App\Models\MyMetaCatalog::class,
    // ...
],
```

---

## Quick Start

### Facade

```php
use ScriptDevelop\MetaCatalogManager\Facades\MetaCatalog;

// With context account
MetaCatalog::forAccount($account)->catalog()->syncFromApi();

// Direct access
MetaCatalog::account()->create([...]);
MetaCatalog::catalog()->syncFromApi($account);
```

---

## Services

### AccountService

```php
// Create an account
$account = MetaCatalog::account()->create([
    'name'               => 'My Business',
    'meta_business_id'   => '123456789',
    'app_id'             => 'APP_ID',        // stored encrypted
    'app_secret'         => 'APP_SECRET',    // stored encrypted
    'access_token'       => 'EAABwz...',     // stored encrypted
]);

// Find / list
$account = MetaCatalog::account()->find($ulid);
$account = MetaCatalog::account()->findByMetaBusinessId('123456789');
$accounts = MetaCatalog::account()->all();
```

### CatalogService

```php
// Sync catalogs from Meta API to local DB
$catalogs = MetaCatalog::catalog()->syncFromApi($account);

// Create a catalog
$catalog = MetaCatalog::catalog()->create($account, [
    'name'     => 'My Store Catalog',
    'vertical' => 'commerce',
]);

// Create a Page-Owned catalog
$catalog = MetaCatalog::catalog()->createForPage($account, $pageId, [
    'name' => 'Page Catalog',
]);
```

### ProductService

```php
// Sync all products (auto-pagination)
$count = MetaCatalog::product()->syncFromApi($catalog);

// Single product operations
MetaCatalog::product()->createSingle($catalog, [
    'retailer_id' => 'SKU-001',
    'title'       => 'Blue T-Shirt',
    'price'       => '19.99 USD',
    'link'        => 'https://mystore.com/t-shirt',
    'image_link'  => 'https://mystore.com/t-shirt.jpg',
    'availability'=> 'in stock',
    'condition'   => 'new',
    'brand'       => 'MyBrand',
    'google_product_category' => 'Apparel & Accessories > Clothing > Shirts & Tops',
]);

// Get override details (localized data)
$overrides = MetaCatalog::product()->getOverrideDetails(
    $productItemId,
    $account,
    keys: ['US', 'es_XX'],
    type: FeedOverrideType::COUNTRY
);
```

### FeedService

```php
// Create a primary feed with daily schedule
$feed = MetaCatalog::feed()->create($catalog, [
    'name'     => 'Main Product Feed',
    'schedule' => [
        'interval' => 'DAILY',
        'url'      => 'https://mystore.com/feed.csv',
        'hour'     => 2,
    ],
]);

// Upload from URL (one-time)
MetaCatalog::feed()->uploadFromUrl($feed, 'https://mystore.com/feed.csv');

// Upload from local file
MetaCatalog::feed()->uploadFromFile($feed, '/path/to/feed.csv');

// Supplementary feed (updates without deleting items)
MetaCatalog::feed()->createSupplementaryFeed($catalog, [
    'name'            => 'Price Updates',
    'primary_feed_ids' => ['1234567890'],
    'schedule'        => ['interval' => 'HOURLY', 'url' => 'https://...'],
]);

// Localized feeds
MetaCatalog::feed()->createLanguageFeed($catalog, 'Language Feed ES/FR');
MetaCatalog::feed()->createCountryFeed($catalog, 'Country Feed US/UK');
MetaCatalog::feed()->createLanguageAndCountryFeed($catalog, 'Locale Feed fr|CA');

// Error reporting
$errors  = MetaCatalog::feed()->getUploadErrors($upload);
MetaCatalog::feed()->requestErrorReport($upload);
$report  = MetaCatalog::feed()->getErrorReport($upload);
```

### BatchService

```php
// Real-time inventory update (async)
$batch = MetaCatalog::batch()->updateItems($catalog, [
    ['retailer_id' => 'SKU-001', 'quantity_to_sell_on_facebook' => 50],
    ['retailer_id' => 'SKU-002', 'quantity_to_sell_on_facebook' => 0],
]);

// Check status
$status = MetaCatalog::batch()->checkStatus($catalog, $batch->handle);

// Create items
MetaCatalog::batch()->createItems($catalog, $items);

// Delete items
MetaCatalog::batch()->deleteItems($catalog, ['SKU-001', 'SKU-002']);

// Localized batch
MetaCatalog::batch()->sendLocalizedBatch($catalog, $localizedItems);
```

### InventoryService

```php
// Update a single item's stock
MetaCatalog::inventory()->updateSingle(
    $catalogItem,
    25,
    InventoryChangeSource::MANUAL,
    'Manual stock adjustment'
);

// Batch update via Meta Batch API
MetaCatalog::inventory()->updateBatch($catalog, [
    ['retailer_id' => 'SKU-001', 'quantity' => 10],
    ['retailer_id' => 'SKU-002', 'quantity' => 0],
]);

// History and reporting
$history  = MetaCatalog::inventory()->getHistory($catalogItem);
$last     = MetaCatalog::inventory()->getLastLog($catalogItem);
$lowStock = MetaCatalog::inventory()->getLowStock($catalog, threshold: 5);
$outOfStock = MetaCatalog::inventory()->getOutOfStock($catalog);
```

### ProductSetService

```php
$set = MetaCatalog::productSet()->create($catalog, [
    'name'   => 'Summer Sale',
    'filter' => ['availability' => ['eq' => 'in stock']],
]);

MetaCatalog::productSet()->syncFromApi($catalog);
```

### DiagnosticsService

```php
// Catalog-level diagnostics
$diagnostics = MetaCatalog::diagnostics()->fetchFromApi($catalog);
MetaCatalog::diagnostics()->syncFromApi($catalog);

// Event source issues
$issues = MetaCatalog::diagnostics()->getEventSourceIssues($catalog, $account);
$hasCritical = MetaCatalog::diagnostics()->hasCriticalEventSourceIssues($catalog, $account);
```

### EventStatsService

```php
$stats = MetaCatalog::eventStats()->fetchFromApi($catalog);
MetaCatalog::eventStats()->syncFromApi($catalog);
```

### OfferService

```php
$offer = MetaCatalog::offer()->createOffer($catalog, [
    'offer_id'         => 'SUMMER20',
    'title'            => 'Summer Sale 20% Off',
    'application_type' => OfferApplicationType::SALE->value,
    'value_type'       => 'PERCENTAGE',
    'percent_off'      => 20,
    'start_date_time'  => '2025-06-01T00:00:00',
    'end_date_time'    => '2025-08-31T23:59:59',
]);

$activeSales   = MetaCatalog::offer()->getActiveSales($catalog);
$activeCoupons = MetaCatalog::offer()->getActiveCoupons($catalog);
```

### GenericFeedService

```php
// Upload shipping profiles
MetaCatalog::genericFeed()->uploadShippingProfiles(
    $account,
    $commercePartnerIntegrationId,
    '/path/to/shipping.csv'
);

// Upload promotions
MetaCatalog::genericFeed()->uploadPromotions(
    $account,
    $commercePartnerIntegrationId,
    '/path/to/promotions.csv'
);

// Upload ratings & reviews
MetaCatalog::genericFeed()->uploadRatingsAndReviews($catalog, '/path/to/reviews.csv');
```

### MerchantSettingsService

```php
$settings = MetaCatalog::merchantSettings()->get($account, $commerceMerchantSettingsId);

MetaCatalog::merchantSettings()->enable($account, $commerceMerchantSettingsId);

MetaCatalog::merchantSettings()->setCheckoutConfig(
    $account,
    $commerceMerchantSettingsId,
    checkoutUrl: 'https://mystore.com/checkout',
    countryCode: 'US'
);
```

---

## Localized Catalogs

Support for language, country, and language+country override feeds:

```php
use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;

// Language feed (translations: title, description)
MetaCatalog::feed()->createLanguageFeed($catalog, 'Language Feed ES/FR', [
    'interval' => 'DAILY',
    'url'      => 'https://mystore.com/feeds/languages.csv',
    'hour'     => 22,
]);

// Country feed (prices, availability, links by country)
MetaCatalog::feed()->createCountryFeed($catalog, 'Country Feed US/UK/AR', [
    'interval' => 'DAILY',
    'url'      => 'https://mystore.com/feeds/countries.csv',
    'hour'     => 3,
]);

// Language+country feed (advanced: fr_XX|CA, fr_XX|US)
MetaCatalog::feed()->createLanguageAndCountryFeed($catalog, 'Locale Feed', [
    'interval' => 'DAILY',
    'url'      => 'https://mystore.com/feeds/locales.tsv',
    'hour'     => 4,
]);

// Inspect overrides for a specific product
$overrides = MetaCatalog::product()->getOverrideDetails($productItemId, $account);
```

**Priority order**: `language_and_country` → `language` → `country` → main catalog.

---

## Database Schema

| Table | Description |
|---|---|
| `meta_business_accounts` | Accounts with encrypted tokens |
| `meta_catalogs` | Product catalogs |
| `meta_product_feeds` | Primary, supplementary, and localized feeds |
| `meta_product_feed_uploads` | Upload sessions and error tracking |
| `meta_catalog_items` | Product items with full field set |
| `meta_product_sets` | Product sets with filter JSON |
| `meta_batch_requests` | Batch API requests and status |
| `meta_catalog_diagnostics` | Catalog error cache |
| `meta_event_sources` | Pixels and apps linked to catalogs |
| `meta_event_stats` | Event metric cache |
| `meta_inventory_logs` | Immutable inventory change audit log |
| `meta_catalog_offers` | Offers (sale, coupon, Buy X Get Y) |
| `meta_generic_feeds` | Generic feed files (shipping, promotions, etc.) |

---

## Available Enums

| Enum | Values |
|---|---|
| `AccountStatus` | `ACTIVE`, `DISCONNECTED`, `REMOVED` |
| `CatalogVertical` | `commerce`, `hotels`, `flights`, `destinations`, `vehicles`, `home_listings` |
| `CatalogItemType` | `PRODUCT_ITEM`, `VEHICLE`, `HOTEL`, `FLIGHT`, `DESTINATION`, `HOME_LISTING`, `VEHICLE_OFFER` |
| `ItemAvailability` | `in stock`, `out of stock`, `preorder`, `available for order`, `discontinued` |
| `ItemCondition` | `new`, `refurbished`, `used` |
| `FeedIngestionSourceType` | `PRIMARY_FEED`, `SUPPLEMENTARY_FEED` |
| `FeedOverrideType` | `language`, `country`, `language_and_country` |
| `FeedFormat` | `csv`, `tsv`, `rss_xml`, `atom_xml`, `google_sheets` |
| `FeedScheduleType` | `HOURLY`, `DAILY`, `WEEKLY` |
| `BatchRequestStatus` | `IN_PROGRESS`, `FINISHED`, `ERROR` |
| `InventoryChangeSource` | `feed_upload`, `batch_api`, `manual`, `system` |
| `OfferApplicationType` | `SALE`, `AUTOMATIC_AT_CHECKOUT`, `BUYER_APPLIED` |
| `OfferValueType` | `FIXED_AMOUNT`, `PERCENTAGE` |
| `GenericFeedType` | `PROMOTIONS`, `SHIPPING_PROFILES`, `NAVIGATION_MENU`, `PRODUCT_RATINGS_AND_REVIEWS` |
| `EventSourceIssueType` | `PIXEL_MISSING_SIGNAL`, `PIXEL_NOT_MAPPED`, `LOW_MATCH_RATE`, ... |

---

## Documentation

Full Spanish documentation is available in [`documentation/es/`](./documentation/es/):

1. [Instalación](./documentation/es/01-instalacion.md)
2. [Configuración](./documentation/es/02-configuracion.md)
3. [Cuentas de Negocio](./documentation/es/03-cuentas.md)
4. [Catálogos](./documentation/es/04-catalogos.md)
5. [Productos](./documentation/es/05-productos.md)
6. [Inventario](./documentation/es/06-inventario.md)
7. [Feeds](./documentation/es/07-feeds.md)
8. [Batch API](./documentation/es/08-batch-api.md)
9. [Conjuntos de Productos](./documentation/es/09-conjuntos-de-productos.md)
10. [Diagnósticos](./documentation/es/10-diagnosticos.md)
11. [Ofertas](./documentation/es/11-ofertas.md)
12. [Diagnósticos Avanzados](./documentation/es/12-diagnostico-avanzado.md)
13. [Microdatos](./documentation/es/13-microdatos.md)
14. [Calificaciones y Opiniones](./documentation/es/14-calificaciones-y-opiniones.md)
15. [Feeds Genéricos](./documentation/es/15-feeds-genericos.md)
16. [Merchant Settings](./documentation/es/16-merchant-settings.md)
17. [Catálogos Localizados](./documentation/es/17-catalogos-localizados.md)

---

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for release history.

---

## License

MIT © [Wilfredo Perilla](https://github.com/djdang3r)
