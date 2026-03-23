<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Services\GenericFeedService;
use ScriptDevelop\MetaCatalogManager\Services\InventoryService;
use ScriptDevelop\MetaCatalogManager\Services\MerchantSettingsService;
use ScriptDevelop\MetaCatalogManager\Services\OfferService;

/**
 * Orquestador principal del paquete.
 * Provee acceso fluido a todos los services del paquete.
 *
 * Uso:
 *   MetaCatalog::catalog()->syncFromApi($account);
 *   MetaCatalog::forAccount($account)->catalog()->syncFromApi(...);
 */
class MetaCatalogManager
{
    protected ?MetaBusinessAccount $contextAccount = null;

    public function __construct(
        protected AccountService         $accountService,
        protected CatalogService         $catalogService,
        protected ProductService         $productService,
        protected BatchService           $batchService,
        protected FeedService            $feedService,
        protected ProductSetService      $productSetService,
        protected DiagnosticsService     $diagnosticsService,
        protected EventStatsService      $eventStatsService,
        protected InventoryService       $inventoryService,
        protected OfferService           $offerService,
        protected GenericFeedService     $genericFeedService,
        protected MerchantSettingsService $merchantSettingsService
    ) {}

    // -------------------------------------------------------------------------
    // Service accessors
    // -------------------------------------------------------------------------

    public function account(): AccountService
    {
        return $this->accountService;
    }

    public function catalog(): CatalogService
    {
        return $this->catalogService;
    }

    public function product(): ProductService
    {
        return $this->productService;
    }

    public function batch(): BatchService
    {
        return $this->batchService;
    }

    public function feed(): FeedService
    {
        return $this->feedService;
    }

    public function productSet(): ProductSetService
    {
        return $this->productSetService;
    }

    public function diagnostics(): DiagnosticsService
    {
        return $this->diagnosticsService;
    }

    public function eventStats(): EventStatsService
    {
        return $this->eventStatsService;
    }

    public function inventory(): InventoryService
    {
        return $this->inventoryService;
    }

    public function offer(): OfferService
    {
        return $this->offerService;
    }

    public function genericFeed(): GenericFeedService
    {
        return $this->genericFeedService;
    }

    public function merchantSettings(): MerchantSettingsService
    {
        return $this->merchantSettingsService;
    }

    // -------------------------------------------------------------------------
    // Context helpers
    // -------------------------------------------------------------------------

    /**
     * Establece el account de contexto y retorna $this para encadenamiento.
     *
     * @param MetaBusinessAccount|string $account Instancia o ULID del account
     */
    public function forAccount(MetaBusinessAccount|string $account): self
    {
        if (is_string($account)) {
            $resolved = $this->accountService->find($account);

            if ($resolved === null) {
                throw new \RuntimeException("MetaBusinessAccount [{$account}] not found.");
            }

            $this->contextAccount = $resolved;
        } else {
            $this->contextAccount = $account;
        }

        return $this;
    }

    /**
     * Retorna el account de contexto actual (si fue seteado).
     */
    public function getContextAccount(): ?MetaBusinessAccount
    {
        return $this->contextAccount;
    }

    // -------------------------------------------------------------------------
    // Deep Sync
    // -------------------------------------------------------------------------

    /**
     * Sincronización profunda en cascada desde Meta hacia la base de datos local.
     *
     * Descarga y persiste todo lo asociado a la cuenta:
     *   account → catalogs → (products, feeds + uploads, product sets, offers, diagnostics, event stats)
     *
     * @return array Contadores de lo sincronizado por entidad
     *
     * Ejemplo de uso:
     *   $result = MetaCatalog::syncDeep($account);
     *   // ['catalogs' => 2, 'products' => 150, 'feeds' => 4, 'feed_uploads' => 12, ...]
     */
    public function syncDeep(MetaBusinessAccount $account): array
    {
        $summary = [
            'catalogs'       => 0,
            'products'       => 0,
            'inventory_logs' => 0,
            'feeds'          => 0,
            'feed_uploads'   => 0,
            'product_sets'   => 0,
            'offers'         => 0,
            'diagnostics'    => 0,
            'event_stats'    => 0,
        ];

        // 1. Sincronizar catálogos de la cuenta
        $catalogs = $this->catalogService->syncFromApi($account);
        $summary['catalogs'] = $catalogs->count();

        // 2. Por cada catálogo, sincronizar todo lo que cuelga de él
        foreach ($catalogs as $catalog) {
            // Productos + historial de inventario
            $logsBefore = \ScriptDevelop\MetaCatalogManager\Models\MetaInventoryLog::where('meta_catalog_id', $catalog->id)->count();
            $summary['products'] += $this->productService->syncFromApi($catalog);
            $summary['inventory_logs'] += \ScriptDevelop\MetaCatalogManager\Models\MetaInventoryLog::where('meta_catalog_id', $catalog->id)->count() - $logsBefore;

            // Feeds + uploads de cada feed
            $feeds = $this->feedService->syncFromApi($catalog);
            $summary['feeds'] += $feeds->count();

            foreach ($feeds as $feed) {
                $uploads = $this->feedService->syncUploads($feed);
                $summary['feed_uploads'] += $uploads->count();
            }

            // Product Sets
            $productSets = $this->productSetService->syncFromApi($catalog);
            $summary['product_sets'] += $productSets->count();

            // Ofertas
            $offers = $this->offerService->syncFromApi($catalog);
            $summary['offers'] += $offers->count();

            // Diagnósticos
            $diagnostics = $this->diagnosticsService->syncFromApi($catalog);
            $summary['diagnostics'] += $diagnostics->count();

            // Event Stats
            $eventStats = $this->eventStatsService->syncFromApi($catalog);
            $summary['event_stats'] += $eventStats->count();
        }

        return $summary;
    }
}
