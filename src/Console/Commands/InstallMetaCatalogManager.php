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

        // 3. Merge canal de logging en logging.php
        $mergeLogging = confirm(
            label: '¿Agregar el canal "meta-catalog" al logging.php del proyecto?',
            default: true,
            hint: 'Esto permite ver los logs en storage/logs/meta-catalog.log'
        );

        if ($mergeLogging) {
            $this->call('meta-catalog:merge-logging');
        }

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

        // 4. Instrucciones .env
        outro('INSTALACIÓN COMPLETADA');

        $this->newLine();
        $this->line('  Agrega las siguientes variables a tu .env:');
        $this->newLine();
        $this->line('  META_CATALOG_GRAPH_VERSION=v22.0');
        $this->line('  META_CATALOG_LOG_CHANNEL=meta-catalog');
        $this->line('  META_CATALOG_AUTO_MIGRATIONS=true');
        $this->newLine();
        $this->line('  Para cada cuenta, guarda el access_token del System User via:');
        $this->line('  MetaCatalog::account()->create([..., "access_token" => "<token>"]);');
        $this->newLine();

        return self::SUCCESS;
    }
}
