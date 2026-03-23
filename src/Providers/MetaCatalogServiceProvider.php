<?php

namespace ScriptDevelop\MetaCatalogManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\ApiClient;
use ScriptDevelop\MetaCatalogManager\Services\AccountService;
use ScriptDevelop\MetaCatalogManager\Services\BatchService;
use ScriptDevelop\MetaCatalogManager\Services\CatalogService;
use ScriptDevelop\MetaCatalogManager\Services\DiagnosticsService;
use ScriptDevelop\MetaCatalogManager\Services\EventStatsService;
use ScriptDevelop\MetaCatalogManager\Services\FeedService;
use ScriptDevelop\MetaCatalogManager\Services\GenericFeedService;
use ScriptDevelop\MetaCatalogManager\Services\ImageService;
use ScriptDevelop\MetaCatalogManager\Services\InventoryService;
use ScriptDevelop\MetaCatalogManager\Services\MerchantSettingsService;
use ScriptDevelop\MetaCatalogManager\Services\MetaCatalogManager;
use ScriptDevelop\MetaCatalogManager\Services\OfferService;
use ScriptDevelop\MetaCatalogManager\Services\ProductService;
use ScriptDevelop\MetaCatalogManager\Services\ProductSetService;
use ScriptDevelop\MetaCatalogManager\Support\MetaCatalogModelResolver;

class MetaCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config del paquete
        $this->mergeConfigFrom(__DIR__ . '/../Config/meta-catalog.php', 'meta-catalog');

        // ApiClient base (sin token — se crea uno por account en tiempo de uso)
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
                config('meta-catalog.api.graph_version', 'v22.0'),
                '',
                config('meta-catalog.api.timeout', 30)
            );
        });

        // Model resolver
        $this->app->singleton(MetaCatalogModelResolver::class);

        // Services
        $this->app->singleton(AccountService::class);

        $this->app->singleton(CatalogService::class, function ($app) {
            return new CatalogService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(ProductService::class, function ($app) {
            return new ProductService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(BatchService::class, function ($app) {
            return new BatchService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(FeedService::class, function ($app) {
            return new FeedService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(ProductSetService::class, function ($app) {
            return new ProductSetService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(DiagnosticsService::class, function ($app) {
            return new DiagnosticsService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(EventStatsService::class, function ($app) {
            return new EventStatsService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(InventoryService::class, function ($app) {
            return new InventoryService(
                $app->make(AccountService::class),
                $app->make(BatchService::class)
            );
        });

        $this->app->singleton(OfferService::class, function ($app) {
            return new OfferService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(GenericFeedService::class, function ($app) {
            return new GenericFeedService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(MerchantSettingsService::class, function ($app) {
            return new MerchantSettingsService(
                $app->make(AccountService::class)
            );
        });

        $this->app->singleton(ImageService::class);

        // MetaCatalogManager — orquestador principal
        $this->app->singleton(MetaCatalogManager::class, function ($app) {
            return new MetaCatalogManager(
                $app->make(AccountService::class),
                $app->make(CatalogService::class),
                $app->make(ProductService::class),
                $app->make(BatchService::class),
                $app->make(FeedService::class),
                $app->make(ProductSetService::class),
                $app->make(DiagnosticsService::class),
                $app->make(EventStatsService::class),
                $app->make(InventoryService::class),
                $app->make(OfferService::class),
                $app->make(GenericFeedService::class),
                $app->make(MerchantSettingsService::class),
                $app->make(ImageService::class)
            );
        });

        // Binding de la Facade
        $this->app->bind('meta-catalog', MetaCatalogManager::class);
    }

    public function boot(): void
    {
        // Publicar configuración principal
        $this->publishes([
            __DIR__ . '/../Config/meta-catalog.php' => config_path('meta-catalog.php'),
        ], 'meta-catalog-config');

        // Publicar configuración de logging
        $this->publishes([
            __DIR__ . '/../Config/logging.php' => config_path('meta-catalog-logging.php'),
        ], 'meta-catalog-logging');

        // Publicar migraciones
        $this->publishes([
            __DIR__ . '/../Database/Migrations' => database_path('migrations'),
        ], 'meta-catalog-migrations');

        // Cargar migraciones automáticamente si está habilitado
        if (config('meta-catalog.migrations.auto_load', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        }

        // Registrar comandos de consola
        if ($this->app->runningInConsole()) {
            $this->registerPackageCommands();
        }
    }

    /**
     * Registra automáticamente todos los comandos del paquete.
     */
    protected function registerPackageCommands(): void
    {
        $commandFiles = glob(__DIR__ . '/../Console/Commands/*.php') ?: [];
        $commandClasses = [];

        foreach ($commandFiles as $commandFile) {
            $className = pathinfo($commandFile, PATHINFO_FILENAME);
            $fqcn = "ScriptDevelop\\MetaCatalogManager\\Console\\Commands\\{$className}";

            if (class_exists($fqcn) && is_subclass_of($fqcn, \Illuminate\Console\Command::class)) {
                $commandClasses[] = $fqcn;
            }
        }

        if (!empty($commandClasses)) {
            $this->commands($commandClasses);
        }
    }
}
