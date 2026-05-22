<?php

declare(strict_types=1);

return [
    'autoload' => base_path(),

    'skip_classes' => [],

    'skip_paths' => [
        app_path('Commands'),
        app_path('Listeners'),
        app_path('Subscribers'),
    ],
];
