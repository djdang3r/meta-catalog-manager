<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de la Graph API de Meta
    |--------------------------------------------------------------------------
    |
    | Configuración principal para interactuar con la Meta Marketing API.
    |
    */
    'api' => [
        // URL base de la Graph API
        'base_url' => env('META_CATALOG_API_URL', 'https://graph.facebook.com'),

        // Versión de la Graph API
        'graph_version' => env('META_CATALOG_GRAPH_VERSION', 'v22.0'),

        // Tiempo de espera para las solicitudes (en segundos)
        'timeout' => env('META_CATALOG_API_TIMEOUT', 30),

        // Número de reintentos en caso de error
        'retries' => env('META_CATALOG_API_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modelos Personalizados
    |--------------------------------------------------------------------------
    |
    | Puedes sobrescribir los modelos del paquete especificando tus propias
    | clases aquí. Útil cuando necesitas extender o modificar comportamiento.
    |
    */
    'models' => [
        'meta_business_account'  => \ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount::class,
        'meta_catalog'           => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalog::class,
        'meta_product_feed'      => \ScriptDevelop\MetaCatalogManager\Models\MetaProductFeed::class,
        'meta_product_feed_upload' => \ScriptDevelop\MetaCatalogManager\Models\MetaProductFeedUpload::class,
        'meta_catalog_item'      => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem::class,
        'meta_product_set'       => \ScriptDevelop\MetaCatalogManager\Models\MetaProductSet::class,
        'meta_batch_request'     => \ScriptDevelop\MetaCatalogManager\Models\MetaBatchRequest::class,
        'meta_catalog_diagnostic' => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalogDiagnostic::class,
        'meta_event_source'      => \ScriptDevelop\MetaCatalogManager\Models\MetaEventSource::class,
        'meta_event_stat'        => \ScriptDevelop\MetaCatalogManager\Models\MetaEventStat::class,
        'meta_inventory_log'     => \ScriptDevelop\MetaCatalogManager\Models\MetaInventoryLog::class,
        'meta_catalog_offer'     => \ScriptDevelop\MetaCatalogManager\Models\MetaCatalogOffer::class,
        'meta_generic_feed'      => \ScriptDevelop\MetaCatalogManager\Models\MetaGenericFeed::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migraciones Automáticas
    |--------------------------------------------------------------------------
    |
    | Controla si las migraciones del paquete deben cargarse automáticamente.
    | Si prefieres publicarlas y controlarlas tú mismo, establece en false.
    |
    */
    'migrations' => [
        'auto_load' => env('META_CATALOG_AUTO_MIGRATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Canal de log utilizado por el paquete. Por defecto usa el canal
    | 'meta-catalog'. Puedes cambiarlo a cualquier canal configurado en
    | config/logging.php de tu proyecto.
    |
    */
    'logging' => [
        'channel' => env('META_CATALOG_LOG_CHANNEL', 'meta-catalog'),
    ],
];
