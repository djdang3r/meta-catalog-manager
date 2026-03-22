<?php

namespace ScriptDevelop\MetaCatalogManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MergeMetaCatalogLogging extends Command
{
    protected $signature   = 'meta-catalog:merge-logging';
    protected $description = 'Agrega el canal de logs meta-catalog al archivo logging.php del proyecto';

    public function handle(): int
    {
        $projectConfigPath = config_path('logging.php');

        try {
            if (!File::exists($projectConfigPath)) {
                $this->error("❌ Archivo logging.php no encontrado en " . $projectConfigPath);
                return 1;
            }

            $configContent = File::get($projectConfigPath);

            if (strpos($configContent, "'meta-catalog'") !== false) {
                $this->info("ℹ️  El canal 'meta-catalog' ya existe en logging.php");
                return 0;
            }

            $newContent = preg_replace(
                "/(['\"]channels['\"]\s*=>\s*\[)([^\]]*)/",
                "$1$2\n" . $this->getChannelConfig(),
                $configContent
            );

            if ($newContent === null) {
                $this->error("❌ Error al modificar el archivo de configuración");
                return 2;
            }

            File::put($projectConfigPath, $newContent);
            $this->info("✅ Canal 'meta-catalog' agregado exitosamente a logging.php");

            return 0;

        } catch (\Exception $e) {
            $this->error("🔥 Error crítico: " . $e->getMessage());
            return 3;
        }
    }

    private function getChannelConfig(): string
    {
        return <<<'EOD'

    'meta-catalog' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/meta-catalog.log'),
        'level'  => 'debug',
        'days'   => 7,
        'tap'    => [\ScriptDevelop\MetaCatalogManager\Logging\CustomizeFormatter::class],
    ],
EOD;
    }
}
