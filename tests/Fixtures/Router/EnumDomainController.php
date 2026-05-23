<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Domain;
use NielsJanssen\Laravel\Discovery\Router\Get;

#[Domain(RouteDomain::Api)]
class EnumDomainController
{
    #[Get('/enum-domain')]
    public function index(): void {}
}
