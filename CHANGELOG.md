# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
