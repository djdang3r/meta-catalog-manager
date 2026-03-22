<?php

namespace ScriptDevelop\MetaCatalogManager;

use Composer\Script\Event;

class ComposerInstaller
{
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();

        // Verificar si es nuestro paquete
        $package = $event->getComposer()->getPackage();
        if ($package->getName() !== 'scriptdevelop/meta-catalog-manager') {
            return;
        }

        // Mensaje de éxito
        $io->write('  <bg=green;fg=white> SUCCESS </> <fg=green>Meta Catalog Manager instalado correctamente.</>');
        $io->write('');

        // Instrucciones
        $io->write('  <options=bold>Siguientes Pasos:</>');
        $io->write('  <fg=yellow>1. Ejecuta el asistente de instalación:</>');
        $io->write('     <fg=cyan>php artisan meta-catalog:install</>');
        $io->write('');
        $io->write('  <fg=yellow>2. O publica la configuración y migraciones manualmente:</>');
        $io->write('     <fg=cyan>php artisan vendor:publish --tag=meta-catalog-config</>');
        $io->write('     <fg=cyan>php artisan vendor:publish --tag=meta-catalog-migrations</>');
        $io->write('     <fg=cyan>php artisan migrate</>');
        $io->write('');
        $io->write('  <fg=yellow>3. Agrega las siguientes variables a tu .env:</>');
        $io->write('     <fg=cyan>META_CATALOG_GRAPH_VERSION=v22.0</>');
        $io->write('     <fg=cyan>META_CATALOG_LOG_CHANNEL=meta-catalog</>');
        $io->write('');
    }
}
