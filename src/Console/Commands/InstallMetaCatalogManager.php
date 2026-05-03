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
    /**
     * El nombre y firma del comando.
     */
    protected $signature = 'meta-catalog:install {--force : Sobrescribir archivos existentes}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Instalación guiada de Meta Catalog Manager';

    /**
     * Ejecutar el comando.
     */
    public function handle(): int
    {
        intro('META CATALOG MANAGER — Asistente de instalación');

        // 1. Verificar APP_KEY
        if (empty(config('app.key'))) {
            warning('No se encontró APP_KEY en tu configuración.');
            note('Ejecuta: php artisan key:generate');

            return self::FAILURE;
        }

        // 2. Publicar config, logging y migrations
        spin(function () {
            $this->callSilent('vendor:publish', [
                '--tag'   => 'meta-catalog-config',
                '--force' => $this->option('force'),
            ]);
            $this->callSilent('vendor:publish', [
                '--tag'   => 'meta-catalog-migrations',
                '--force' => $this->option('force'),
            ]);
        }, 'Publicando configuración y migraciones...');

        $this->components->info('Configuración y migraciones publicadas.');

        // 3. Merge canal de logging (manual, NO automático)
        note('El paquete usa el canal de log "stack" por defecto.');
        note('Para logs separados, ejecutá manualmente:');
        $this->line("  php artisan meta-catalog:merge-logging");
        note('⚠️  Este comando modifica config/logging.php. Asegurate de tener backup.');

        // 4. Preguntar si correr migraciones
        $runMigrations = confirm(
            label: '¿Deseas correr las migraciones ahora?',
            default: true,
            hint: 'Usa las flechas [↑/↓] para seleccionar y Enter para confirmar.'
        );

        if ($runMigrations) {
            spin(function () {
                $this->call('migrate');
            }, 'Ejecutando migraciones...');
        }

        // 5. Storage link (necesario para servir imágenes descargadas)
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
            hint: 'Copia meta_catalog_webhook.php a routes/. Si no, se usa la ruta por defecto del paquete.'
        );

        if ($publishRoutes) {
            spin(function () {
                $this->callSilent('vendor:publish', [
                    '--tag'   => 'meta-catalog-routes',
                    '--force' => $this->option('force'),
                ]);
            }, 'Publicando ruta de webhook...');

            $this->components->info('Ruta de webhook publicada en routes/meta_catalog_webhook.php');
        }

        // Instrucciones .env
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
                "/'meta-catalog-webhook\/\*',\s*/",
                '',
                $content
            );

            $updated = preg_replace(
                '/(validateCsrfTokens\s*\(\s*except\s*:\s*\[)(\n?\s*|\s*)/',
                "$1\n            '{$webhookPath}', ",
                $updated,
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
}
