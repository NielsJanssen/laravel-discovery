<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Method;
use NielsJanssen\Laravel\Discovery\Router\Route;

class MultiMethodController
{
    #[Route([Method::Get, Method::Post], '/multi')]
    public function index(): string
    {
        return 'ok';
    }
}
