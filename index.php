<?php

require_once __DIR__ . '/lib/ResizeService.php';

Kirby::plugin('allsizes/batch-resize', [
    'areas' => [
        'clean-up' => require __DIR__ . '/config/area.php',
    ],
    'api' => [
        'routes' => require __DIR__ . '/config/api.php',
    ],
]);