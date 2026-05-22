<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        RouteLog::$calls[] = self::class;

        return $next($request);
    }
}
