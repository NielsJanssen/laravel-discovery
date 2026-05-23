<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

interface Routable
{
    /** @var list<\NielsJanssen\Laravel\Discovery\Router\Method> */
    public array $methods { get; set; }

    public string|\BackedEnum|null $domain { get; set; }

    public string $uri { get; set; }

    public string|\BackedEnum|null $name { get; set; }

    /** @var class-string<class-string|string>[]  */
    public array $middleware { get; set; }

    /** @var class-string<class-string|string>[]  */
    public array $withoutMiddleware { get; set; }
}
