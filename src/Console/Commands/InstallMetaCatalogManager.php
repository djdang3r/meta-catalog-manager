<?php

namespace ScriptDevelop\MetaCatalogManager\Console\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstallMetaCatalogManager extends Command
{
    protected $signature = 'meta-catalog:install {--force : Sobrescribir archivos existentes}';

    protected $description = 'Instalación guiada de Meta Catalog Manager';

    public function handle(): int
    {
        intro('META CATALOG MANAGER — Asistente de instalación');

        if (empty(config('app.key'))) {
            warning('No se encontró APP_KEY en tu configuración.');
            note('Ejecuta: php artisan key:generate');

            return self::FAILURE;
        }

        spin(function () {
            $this->callSilent('vendor:publish', [
                '--tag'   => 'meta-catalog-config',
                '--force' => $this->option('force'),
            ]);
        }, 'Publicando configuración...');

        $this->components->info('Configuración publicada en config/meta-catalog.php.');

        note('Las migraciones se cargan automáticamente (auto_load=true).');
        note('No necesitás publicarlas para que funcionen.');

        $publishMigrations = confirm(
            label: '¿Publicar migraciones en database/migrations/?',
            default: false,
            hint: 'Copiarlas a tu proyecto para revisarlas o modificarlas. No necesario si auto_load=true.'
        );

        if ($publishMigrations) {
            spin(function () {
                $this->callSilent('vendor:publish', [
                    '--tag'   => 'meta-catalog-migrations',
                    '--force' => $this->option('force'),
                ]);
            }, 'Publicando migraciones...');

            $this->components->info('Migraciones publicadas en database/migrations/');
        }

        $autoLoad = config('meta-catalog.migrations.auto_load', true);

        if (!$autoLoad && !$publishMigrations) {
            warning('⚠️  auto_load=false y no publicaste las migraciones.');
            warning('   Las migraciones no estarán disponibles. Habilitá una de las dos opciones.');
            note('   Ejecutá: php artisan vendor:publish --tag=meta-catalog-migrations');
            note('   O configurá: META_CATALOG_AUTO_MIGRATIONS=true en tu .env');
        }

        $runMigrations = confirm(
            label: '¿Ejecutar las migraciones ahora?',
            default: $autoLoad || $publishMigrations,
            hint: 'Aplica las tablas a la base de datos.'
        );

        if ($runMigrations) {
            spin(function () {
                $this->call('migrate');
            }, 'Ejecutando migraciones...');

            $this->components->info('Migraciones ejecutadas.');
        }

        note('El paquete usa el canal de log "stack" por defecto.');
        note('Para logs separados, ejecutá manualmente:');
        $this->line("  php artisan meta-catalog:merge-logging");
        note('⚠️  Este comando modifica config/logging.php. Asegurate de tener backup.');

        $runStorageLink = confirm(
            label: '¿Crear el enlace simbólico de storage (storage:link)?',
            default: true,
            hint: 'Necesario para servir imágenes descargadas desde el disco público.'
        );

        if ($runStorageLink) {
            spin(function () {
                $this->callSilent('storage:link');
            }, 'Creando enlace simbólico de storage...');

            $this->components->info('Enlace simbólico de storage creado.');
        }

        $excludeCsrf = confirm(
            label: '¿Excluir ruta de webhook meta-catalog-webhook de CSRF?',
            default: true,
            hint: 'Necesario para recibir webhooks de Meta (product_feed, items_batch). Modifica bootstrap/app.php.'
        );

        if ($excludeCsrf) {
            $this->addCsrfExclusion();
        }

        $publishRoutes = confirm(
            label: '¿Publicar ruta de webhook para personalizarla?',
            default: false,
            hint: 'Copia meta_catalog_webhook.php a routes/. Si no, se usa la ruta del paquete.'
        );

        if ($publishRoutes) {
            $this->publishWebhookRoute();
        }

        outro('INSTALACIÓN COMPLETADA');

        $this->newLine();
        $this->line('  Agrega las siguientes variables a tu .env:');
        $this->newLine();
        $this->line('  META_CATALOG_GRAPH_VERSION=v25.0');
        $this->line('  META_CATALOG_LOG_CHANNEL=meta-catalog');
        $this->line('  META_CATALOG_AUTO_MIGRATIONS=true');
        $this->newLine();
        $this->line('  Para webhooks (opcional):');
        $this->line('  META_CATALOG_WEBHOOK_VERIFY_TOKEN=<tu_token_secreto>');
        $this->line('  META_CATALOG_APP_ID=<tu_app_id>');
        $this->line('  META_CATALOG_APP_SECRET=<tu_app_secret>');
        $this->newLine();
        $this->line('  Para cada cuenta, guarda el access_token del System User via:');
        $this->line('  MetaCatalog::account()->create([..., "access_token" => "<token>"]);');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function addCsrfExclusion(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if (!file_exists($bootstrapPath)) {
            warning('No se encontró bootstrap/app.php. Agregá manualmente la exclusión CSRF en VerifyCsrfToken.');

            return;
        }

        $content = file_get_contents($bootstrapPath);
        $webhookPath = 'meta-catalog-webhook/*';

        if (str_contains($content, $webhookPath)) {
            $this->components->info('La ruta de webhook ya está excluida de CSRF.');

            return;
        }

        if (str_contains($content, 'validateCsrfTokens')) {
            $updated = preg_replace(
                '/(validateCsrfTokens\s*\(\s*except\s*:\s*\[)(\n?\s*|\s*)/',
                "$1\n            '{$webhookPath}', ",
                $content,
                1
            );

            if ($updated !== null && $updated !== $content) {
                if (file_put_contents($bootstrapPath, $updated) !== false) {
                    $this->components->info("Ruta '{$webhookPath}' agregada a la exclusión CSRF existente.");

                    return;
                }
            }

            warning('No se pudo modificar la exclusión CSRF existente. Agregá manualmente:');
            $this->line("  \$middleware->validateCsrfTokens(except: ['{$webhookPath}', ...]);");

            return;
        }

        $pattern = '/->withMiddleware\s*\(\s*function\s*\(\s*(?:Middleware\s+)?\$middleware\s*\)\s*\{/';
        $replacement = '$0' . PHP_EOL . '        $middleware->validateCsrfTokens(except: [' . PHP_EOL . "            '{$webhookPath}'," . PHP_EOL . '        ]);';

        $updated = preg_replace($pattern, $replacement, $content, 1, $count);

        if ($count && $updated !== null) {
            file_put_contents($bootstrapPath, $updated);
            $this->components->info("Ruta '{$webhookPath}' excluida de CSRF en bootstrap/app.php");
        } else {
            warning('No se pudo modificar bootstrap/app.php automáticamente. Agregá manualmente:');
            $this->line("  En App\\Http\\Middleware\\VerifyCsrfToken agregá:");
            $this->line("  protected \$except = ['{$webhookPath}'];");
        }
    }

    protected function publishWebhookRoute(): void
    {
        $publishedPath = base_path('routes/meta_catalog_webhook.php');

        if (file_exists($publishedPath) && !$this->option('force')) {
            $overwrite = confirm(
                label: 'Ya existe routes/meta_catalog_webhook.php. ¿Sobrescribir?',
                default: false,
                hint: 'Si elegís No, se usará el archivo existente (ya publicado).'
            );

            if (!$overwrite) {
                $this->components->info('Se conserva la ruta de webhook existente.');

                return;
            }
        }

        spin(function () {
            $this->callSilent('vendor:publish', [
                '--tag'   => 'meta-catalog-routes',
                '--force' => true,
            ]);
        }, 'Publicando ruta de webhook...');

        $this->components->info('Ruta de webhook publicada en routes/meta_catalog_webhook.php');
    }
}