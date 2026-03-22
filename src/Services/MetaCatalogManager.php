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
}
