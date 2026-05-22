<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Router;

interface Route
{
    public Method $method { get; set; }

    public ?string $domain { get; set; }

    public string $uri { get; set; }

    /** @var class-string<class-string|string>[]  */
    public array $middleware { get; set; }

    /** @var class-string<class-string|string>[]  */
    public array $withoutMiddleware { get; set; }
}
