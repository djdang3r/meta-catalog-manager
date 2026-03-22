<?php

namespace ScriptDevelop\MetaCatalogManager\MetaCatalogApi;

/**
 * Constantes de endpoints para la Meta Marketing API - Catalog.
 * Todos los placeholders usan la notación {param_name}.
 */
class Endpoints
{
    // -------------------------------------------------------------------------
    // Business Account Endpoints
    // -------------------------------------------------------------------------
    const GET_BUSINESS = '{business_id}';

    // -------------------------------------------------------------------------
    // Catalog Endpoints
    // -------------------------------------------------------------------------
    const GET_CATALOGS    = '{business_id}/owned_product_catalogs';
    const CREATE_CATALOG  = '{business_id}/owned_product_catalogs';
    const GET_CATALOG     = '{catalog_id}';
    const UPDATE_CATALOG  = '{catalog_id}';
    const DELETE_CATALOG  = '{catalog_id}';

    // -------------------------------------------------------------------------
    // Product Items (individual)
    // -------------------------------------------------------------------------
    const GET_PRODUCTS    = '{catalog_id}/products';
    const CREATE_PRODUCT  = '{catalog_id}/products';
    const GET_PRODUCT     = '{product_item_id}';
    const UPDATE_PRODUCT  = '{product_item_id}';
    const DELETE_PRODUCT  = '{product_item_id}';

    // -------------------------------------------------------------------------
    // Batch API
    // -------------------------------------------------------------------------
    const ITEMS_BATCH            = '{catalog_id}/items_batch';
    const LOCALIZED_ITEMS_BATCH  = '{catalog_id}/localized_items_batch';
    const CHECK_BATCH_STATUS     = '{catalog_id}/check_batch_request_status';

    // -------------------------------------------------------------------------
    // Product Feeds
    // -------------------------------------------------------------------------
    const GET_FEEDS         = '{catalog_id}/product_feeds';
    const CREATE_FEED       = '{catalog_id}/product_feeds';
    const GET_FEED          = '{feed_id}';
    const UPDATE_FEED       = '{feed_id}';
    const DELETE_FEED       = '{feed_id}';
    const GET_FEED_UPLOADS  = '{feed_id}/uploads';
    const UPLOAD_FEED       = '{feed_id}/uploads';

    // Upload session endpoints
    const GET_UPLOAD_SESSION          = '{upload_session_id}';
    const UPLOAD_SESSION_ERRORS       = '{upload_session_id}/errors';
    const UPLOAD_SESSION_ERROR_REPORT = '{upload_session_id}/error_report';

    // Data sources (primary feeds linked to catalog)
    const GET_DATA_SOURCES = '{catalog_id}/data_sources';

    // -------------------------------------------------------------------------
    // Product Sets
    // -------------------------------------------------------------------------
    const GET_PRODUCT_SETS          = '{catalog_id}/product_sets';
    const CREATE_PRODUCT_SET        = '{catalog_id}/product_sets';
    const GET_PRODUCT_SET           = '{product_set_id}';
    const UPDATE_PRODUCT_SET        = '{product_set_id}';
    const DELETE_PRODUCT_SET        = '{product_set_id}';
    const GET_PRODUCT_SET_PRODUCTS  = '{product_set_id}/products';

    // -------------------------------------------------------------------------
    // Diagnostics
    // -------------------------------------------------------------------------
    const GET_CATALOG_DIAGNOSTICS  = '{catalog_id}/diagnostics';
    const GET_CATALOG_ALL_ERRORS   = '{catalog_id}/all_errors';

    // -------------------------------------------------------------------------
    // Event Stats & Pixel/App checks
    // -------------------------------------------------------------------------
    const GET_EVENT_STATS    = '{catalog_id}/event_stats';
    const GET_PIXEL_DA_CHECKS = '{pixel_id}/da_checks';
    const GET_APP_DA_CHECKS   = '{app_id}/da_checks';

    // -------------------------------------------------------------------------
    // Event Sources
    // -------------------------------------------------------------------------
    const GET_EVENT_SOURCES = '{catalog_id}/event_sources';

    // -------------------------------------------------------------------------
    // Localized Catalog (Override Details API)
    // -------------------------------------------------------------------------
    const GET_ITEM_OVERRIDE_DETAILS = '{item_id}/override_details';

    // -------------------------------------------------------------------------
    // Generic Feed Files API
    // -------------------------------------------------------------------------
    const GENERIC_FILE_UPDATE = '{commerce_partner_integration_id}/file_update';

    // -------------------------------------------------------------------------
    // Commerce Merchant Settings
    // -------------------------------------------------------------------------
    const MERCHANT_SETTINGS = '{commerce_merchant_settings_id}';

    // -------------------------------------------------------------------------
    // Page-Owned Catalogs
    // -------------------------------------------------------------------------
    const CREATE_PAGE_CATALOG = '{page_id}/owned_product_catalogs';
    const GET_PAGE_CATALOGS   = '{page_id}/owned_product_catalogs';

    // -------------------------------------------------------------------------
    // Helper methods — retornan el array de params para buildUrl()
    // -------------------------------------------------------------------------

    /**
     * Parámetros para endpoints de catálogo.
     */
    public static function catalog(string $id): array
    {
        return ['catalog_id' => $id];
    }

    /**
     * Parámetros para endpoints de business.
     */
    public static function business(string $id): array
    {
        return ['business_id' => $id];
    }

    /**
     * Parámetros para endpoints de feed.
     */
    public static function feed(string $id): array
    {
        return ['feed_id' => $id];
    }

    /**
     * Parámetros para endpoints de product item.
     */
    public static function product(string $id): array
    {
        return ['product_item_id' => $id];
    }

    /**
     * Parámetros para endpoints de product set.
     */
    public static function productSet(string $id): array
    {
        return ['product_set_id' => $id];
    }

    /**
     * Parámetros para endpoints de upload session.
     */
    public static function uploadSession(string $id): array
    {
        return ['upload_session_id' => $id];
    }

    /**
     * Parámetros para endpoints de Generic Feed Files API.
     */
    public static function commercePartner(string $id): array
    {
        return ['commerce_partner_integration_id' => $id];
    }

    /**
     * Parámetros para endpoints de Commerce Merchant Settings.
     */
    public static function merchantSettings(string $id): array
    {
        return ['commerce_merchant_settings_id' => $id];
    }

    /**
     * Parámetros para endpoints de Facebook Page.
     */
    public static function page(string $id): array
    {
        return ['page_id' => $id];
    }

    /**
     * Parámetros para endpoints de product item (override details).
     */
    public static function item(string $id): array
    {
        return ['item_id' => $id];
    }
}
