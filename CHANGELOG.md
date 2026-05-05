# Changelog

## [1.0.25] - 2026-05-05

### Fixed
- `CatalogService::getDetail()`: campos `fields` se pasaban como body en vez de query parameter en GET request. La API de Meta ignoraba el body, retornando respuestas sin el campo `name`, lo que causaba que los catálogos se guardaran con nombres genéricos como "#123456" en vez del nombre real.

## [1.0.24] - 2026-05-04

### Changed
- `ProductService`: `currency` now stored in its own column instead of concatenated with `price`. Requires migration adding `currency` column to `meta_catalog_items`.

## [1.0.22] - 2026-05-03

### Added
- `AccountService::getWhatsAppAccounts(businessId, token)`: list WhatsApp Business Accounts under a Business Manager via `GET /{business_id}/client_whatsapp_business_accounts`
- `CatalogService::connectToWaba(account, wabaId, catalogMetaId)`: connect a catalog to a WABA via `POST /{waba_id}/product_catalogs`
- `CatalogService::validateWabaConnection(account, wabaId, catalogMetaId)`: verify catalog-WABA link via `GET /{waba_id}/product_catalogs`
- `Endpoints::whatsappBusinessAccount()`: helper for WABA endpoint URL construction
- New endpoint constants: `GET_CLIENT_WABAS`, `CONNECT_CATALOG_TO_WABA`, `GET_WABA_CATALOGS`

## [1.0.21] - 2026-05-03

### Fixed
- `ProductService`: added `cleanPrice()` helper to strip currency symbols and non-breaking spaces from Meta API price fields. Prevents `number_format()` TypeError when price comes formatted as `$ 100.000`.

## [1.0.20] - 2026-05-03

### Fixed
- `ProductService::syncFromApi()`: check `additional_image_urls` field first (catalog list endpoint), fallback to `additional_image_link` (single product endpoint). Additional images were not being stored because only `additional_image_link` was checked.

## [1.0.19] - 2026-05-03

### Fixed
- `ProductService::getFromApi()`: added `fields` parameter to catalog products list request to include `image_url` and `additional_image_urls`. Images were not being returned by Meta without explicit field request.
All notable changes to this project will be documented in this file.

## [1.0.18] - 2026-05-03

### Fixed
- `ProductService::getSingle()`: corrected field name `mpn` → `manufacturer_part_number` matching the canonical Graph API field name. Prevents 400 Bad Request "(#100) Tried accessing nonexisting field" errors
- `ProductService::mapApiDataToColumns()`: added `manufacturer_part_number` → `mpn` mapping for API-to-DB column translation
- `AccountService::create()` and `createFromEmbeddedSignup()`: API calls (sync business info, sync catalogs) are now best-effort — account creation no longer fails if Meta API is unavailable

## [1.0.10] - 2026-05-02

### Fixed
- Install wizard: `merge-logging` ahora es opt-in (default: false) para evitar modificar `logging.php` del proyecto host
- Documentado: el paquete usa `stack` como canal de log por defecto; el canal `meta-catalog` se configura manualmente

## [1.0.9] - 2026-05-02

### Fixed
- `MergeMetaCatalogLogging` — reemplazado regex `[^\]]*` por conteo de profundidad de brackets

## [1.0.8] - 2026-05-02

### Added
- Webhook support for Meta Catalog events (`product_feed`, `items_batch`)
- `Contracts\WebhookProcessorInterface` — pluggable webhook processing
- `Services\WebhookProcessors\DefaultWebhookProcessor` — verificación de firma, challenge y dispatch de eventos
- `Http\Controllers\MetaCatalogWebhookController` — endpoint `POST /meta-catalog-webhook`
- `routes/meta_catalog_webhook.php` — ruta publicable y personalizable
- `meta-catalog.webhook` config section (`processor`, `verify_token`)
- CSRF exclusion automática en `bootstrap/app.php` desde el install wizard
- `InstallMetaCatalogManager` — paso de CSRF + opción de publicar ruta de webhook

## [1.0.7] - 2026-05-02

### Added
- `AccountService::createFromEmbeddedSignup()` — flujo OAuth para registro embebido de WhatsApp (Embedded Signup v4)
- `AccountService::exchangeCodeForToken()` — intercambia code por business_token via OAuth
- `AccountService::fetchBusinessInfo()` — `GET /{business_id}?fields=name,id,vertical`
- `AccountService::syncBusinessInfo()` — actualiza cuenta local con datos de Meta
- `meta-catalog.oauth` config section — `app_id` y `app_secret` para flujo OAuth
- `Endpoints::OAUTH_ACCESS_TOKEN` — endpoint para intercambio de token

### Changed
- `AccountService::create()` — auto-fetchea `name` desde Meta si no se proporciona + auto-sincroniza catálogos
- `MetaBusinessAccount` — `app_id` ya no se encripta (no es información sensible)
- Migraciones completamente reescritas — 14 consolidadas (1 por tabla), compatibles MySQL 8 y PostgreSQL 18
- Tipos de columna: `enum` → `string` + `CHECK`, `unsigned*` → `bigInteger/smallInteger` para cross-DB compat
- Graph API default actualizado de `v22.0` a `v25.0` (Feb 2026) en todos los archivos

### Fixed
- Migraciones dispersas consolidadas — campos de `_add_*` y `_alter_*` incluidos en la migración base
- `MetaCatalogImage.id` ahora usa `char(26)` consistente con el resto de modelos
- Provider: `AccountService` ahora recibe `CatalogService` via setter injection (rompe circular dependency)

## [1.0.6] - 2026-05-02

### Changed
- Updated default `graph_version` from `v22.0` to `v25.0` (Meta Graph API, Feb 2026) across all config files, service providers, service classes, install wizard, ComposerInstaller, README, and Spanish documentation
- All hardcoded version fallbacks now consistently reference `config('meta-catalog.api.graph_version')`

## [1.0.5] - 2026-03-23

### Added
- `CatalogService::getDetail()` — GET `/{catalog_id}` — obtiene detalles de un catálogo específico y actualiza DB local
- `CatalogService::update()` — POST `/{catalog_id}` — actualiza nombre, vertical, country, currency o timezone en Meta y persiste en DB
- `CatalogService::delete()` — DELETE `/{catalog_id}` — elimina catálogo de Meta y soft-elimina el registro local
- `ProductService::getSingle()` — GET `/{product_item_id}` — obtiene detalles de un producto individual y actualiza DB local

### Changed
- `CatalogService` ahora tiene CRUD completo (Create, Read, Update, Delete + Sync)
- `ProductService` ahora tiene cobertura completa para lectura de un item individual

## [1.0.4] - 2026-03-22

### Added
- `MetaCatalogImage` model (`meta_catalog_images` table) — stores per-product image records with `type` (`product_main` / `product_additional`), `position`, `original_url`, `local_path`, `local_url`, `mime_type`, `file_size`, and `downloaded_at`
- `ImageService` — downloads Meta CDN images and persists them to Laravel Storage. Methods: `downloadForItem()`, `downloadForCatalog()`, `downloadForAccount()`, `syncImage()`, `download()`. Skips re-download if `original_url` has not changed; retries configurable via `meta-catalog.media.retries`
- Migration `2025_01_01_000016_create_meta_catalog_images_table.php`
- `MetaCatalogItem` — added `images()`, `mainImage()`, `additionalImages()` Eloquent relationships
- `MetaCatalogManager::image()` — accessor for `ImageService`
- `MetaCatalogManager::syncDeep()` — now downloads images when `META_CATALOG_AUTO_DOWNLOAD_IMAGES=true` (opt-in, default `false`). Summary includes `images` counter
- `meta-catalog.media` config section: `disk`, `auto_download`, `retries`, `paths` (per image type)
- `meta-catalog.models.meta_catalog_image` entry for model customization
- `InstallMetaCatalogManager` wizard — new step to run `storage:link` (opt-in, default `true`)

## [1.0.3] - 2026-03-23

### Fixed
- `ProductService::syncFromApi()` now creates a `MetaInventoryLog` entry when `quantity_to_sell_on_facebook` changes during sync (source: `system`, notes: `sync_from_api`). New products with a non-null quantity also generate an initial log entry with `previous_quantity = null`

### Changed
- `MetaCatalogManager::syncDeep()` summary now includes `inventory_logs` counter reflecting how many inventory log entries were created during the product sync

## [1.0.2] - 2026-03-23

### Fixed
- `ProductService::createSingle()` now persists the created product to `meta_catalog_items` after the API call
- `ProductService::updateSingle()` now updates the local `meta_catalog_items` record after the API call
- `ProductService::deleteSingle()` now soft-deletes the local `meta_catalog_items` record after the API call
- `OfferService::fetchFromApi()` — removed nested array filter `['filter' => ['feed_type' => 'OFFER']]` that caused Meta API error `(#100) This field must be a string`; now fetches all feeds and filters locally
- `DiagnosticsService::syncFromApi()` — normalize Meta severity value `must_fix` to `error` to match DB enum (`warning`, `error`)
- `ApiClient` — log channel default changed from `meta-catalog` to `stack` to avoid `InvalidArgumentException` when the custom channel is not configured in the host application

### Added
- `ProductService::mapApiDataToColumns()` — private helper that maps Meta API field names (`name`, `url`, `price`+`currency`) to local DB columns (`title`, `link`, `price` as `"AMOUNT CURRENCY"`)
- `MetaCatalogManager::syncDeep(MetaBusinessAccount $account): array` — deep cascading sync that downloads and persists everything linked to an account: catalogs → products, feeds + uploads, product sets, offers, diagnostics, event stats. Returns a summary array with counters per entity
- `MetaCatalog` facade — added `@method static array syncDeep(MetaBusinessAccount $account)` for IDE autocompletion

## [1.0.1] - 2026-03-22

### Added
- Initial stable release
- Multi-account support with encrypted credentials (`app_id`, `app_secret`, `access_token`)
- 13 database migrations covering full catalog management schema
- 12 services: `AccountService`, `CatalogService`, `ProductService`, `BatchService`, `FeedService`, `ProductSetService`, `DiagnosticsService`, `EventStatsService`, `InventoryService`, `OfferService`, `GenericFeedService`, `MerchantSettingsService`
- 15 enums for all Meta API value types
- Facade `MetaCatalog` with full IDE autocompletion support
- **Feeds**: primary, supplementary, schedule-based, one-time upload (URL and file), error reports
- **Batch API**: create/update/delete items, localized batch, async status polling
- **Inventory**: `meta_inventory_logs` immutable audit table, low stock queries, catalog-level history
- **Offers API**: `SALE`, `AUTOMATIC_AT_CHECKOUT`, `BUYER_APPLIED`, Buy X Get Y, coupon codes
- **Generic Feed Files API**: promotions, shipping profiles, navigation menu, ratings & reviews
- **Commerce Merchant Settings API**: get/update/enable/disable, checkout configuration
- **Page-Owned Catalogs**: create and list catalogs owned by a Facebook Page
- **Localized Catalogs**: language, country, and language+country override feeds; `FeedOverrideType` enum; Override Details API
- **DiagnosticsService** extended with `EVENT_SOURCE_ISSUES` methods
- Best practices compliance: `google_product_category`, `rich_text_description`, `product_type`, `internal_label` added to `meta_catalog_items`
- `ProductService::syncFromApi()` — full field mapping from Meta API response
- 17-file Spanish documentation
- Install wizard `meta-catalog:install` with interactive prompts
