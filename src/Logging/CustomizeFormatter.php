<?php

namespace ScriptDevelop\MetaCatalogManager\Logging;

use Monolog\Formatter\LineFormatter;

class CustomizeFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $formatter = new LineFormatter(
                '[%datetime%] %channel%.%level_name%: %message% %context% %extra%' . "\n",
                'Y-m-d H:i:s.u',
                true,
                true
            );

            $handler->setFormatter($formatter);
        }
    }
}
