<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature;

use Illuminate\Contracts\Foundation\Application;
use Tempest\Discovery\DiscoveryConfig;

interface Feature
{
    public static function register(Application $app, DiscoveryConfig $config): void;
}
