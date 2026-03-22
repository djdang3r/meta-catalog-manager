<?php

namespace ScriptDevelop\MetaCatalogManager\Facades;

use Illuminate\Support\Facades\Facade;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Services\AccountService;
use ScriptDevelop\MetaCatalogManager\Services\BatchService;
use ScriptDevelop\MetaCatalogManager\Services\CatalogService;
use ScriptDevelop\MetaCatalogManager\Services\DiagnosticsService;
use ScriptDevelop\MetaCatalogManager\Services\EventStatsService;
use ScriptDevelop\MetaCatalogManager\Services\FeedService;
use ScriptDevelop\MetaCatalogManager\Services\GenericFeedService;
use ScriptDevelop\MetaCatalogManager\Services\InventoryService;
use ScriptDevelop\MetaCatalogManager\Services\MerchantSettingsService;
use ScriptDevelop\MetaCatalogManager\Services\MetaCatalogManager;
use ScriptDevelop\MetaCatalogManager\Services\OfferService;
use ScriptDevelop\MetaCatalogManager\Services\ProductService;
use ScriptDevelop\MetaCatalogManager\Services\ProductSetService;

/**
 * @method static AccountService          account()
 * @method static CatalogService          catalog()
 * @method static ProductService          product()
 * @method static BatchService            batch()
 * @method static FeedService             feed()
 * @method static ProductSetService       productSet()
 * @method static DiagnosticsService      diagnostics()
 * @method static EventStatsService       eventStats()
 * @method static InventoryService        inventory()
 * @method static OfferService            offer()
 * @method static GenericFeedService      genericFeed()
 * @method static MerchantSettingsService merchantSettings()
 * @method static MetaCatalogManager      forAccount(MetaBusinessAccount|string $account)
 *
 * @see MetaCatalogManager
 */
class MetaCatalog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'meta-catalog';
    }
}
