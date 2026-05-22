<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Get;

class DomainController
{
    #[Get('/home', domain: 'api.example.com')]
    public function index(): void {}
}
