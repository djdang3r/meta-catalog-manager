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

            $newContent = $this->insertIntoChannelsArray($configContent);

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

    private function getChannelConfig(string $indent): string
    {
        $i1 = $indent;
        $i2 = $indent . '    ';

        return <<<EOD
{$i1}'meta-catalog' => [
{$i2}'driver' => 'daily',
{$i2}'path'   => storage_path('logs/meta-catalog.log'),
{$i2}'level'  => 'debug',
{$i2}'days'   => 7,
{$i2}'tap'    => [\ScriptDevelop\MetaCatalogManager\Logging\CustomizeFormatter::class],
{$i1}],
EOD;
    }

    private function insertIntoChannelsArray(string $content): ?string
    {
        $pattern = "/(['\"]channels['\"]\s*=>\s*\[)/";
        preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches)) {
            $this->error("❌ No se encontró la clave 'channels' en logging.php");
            return null;
        }

        $openPos = $matches[0][1] + strlen($matches[0][0]);
        $closePos = $this->findMatchingBracket($content, $openPos);

        if ($closePos === null) {
            $this->error("❌ No se pudo encontrar el cierre del array 'channels'");
            return null;
        }

        $indent = $this->detectIndent($content, $matches[0][1]);

        $channelConfig = $this->getChannelConfig($indent);

        $before = substr($content, 0, $closePos);
        $after  = substr($content, $closePos);

        return $before . $channelConfig . "\n" . $after;
    }

    private function detectIndent(string $content, int $pos): string
    {
        $start = $pos;
        while ($start > 0 && $content[$start - 1] !== "\n") {
            $start--;
        }
        $line = substr($content, $start, $pos - $start);

        return $line . '    ';
    }

    private function findMatchingBracket(string $content, int $startPos): ?int
    {
        $depth = 0;
        $len = strlen($content);

        for ($i = $startPos; $i < $len; $i++) {
            $char = $content[$i];

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                if ($depth === 0) {
                    return $i;
                }
                $depth--;
            }
        }

        return null;
    }
}
