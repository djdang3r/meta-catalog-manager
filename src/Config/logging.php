<?php

return [

    'channels' => [
        'meta-catalog' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/meta-catalog.log'),
            'level'  => 'debug',
            'days'   => 7,
            'tap'    => [\ScriptDevelop\MetaCatalogManager\Logging\CustomizeFormatter::class],
        ],
    ],

];
