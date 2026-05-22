<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Delete;
use NielsJanssen\Laravel\Discovery\Feature\Router\Get;
use NielsJanssen\Laravel\Discovery\Feature\Router\Head;
use NielsJanssen\Laravel\Discovery\Feature\Router\Options;
use NielsJanssen\Laravel\Discovery\Feature\Router\Patch;
use NielsJanssen\Laravel\Discovery\Feature\Router\Post;
use NielsJanssen\Laravel\Discovery\Feature\Router\Put;

class AllMethodsController
{
    #[Get('/users')]
    public function getUsers(): void {}

    #[Post('/users')]
    public function createUser(): void {}

    #[Put('/users/{id}')]
    public function replaceUser(): void {}

    #[Patch('/users/{id}')]
    public function updateUser(): void {}

    #[Delete('/users/{id}')]
    public function deleteUser(): void {}

    #[Head('/users')]
    public function headUsers(): void {}

    #[Options('/users')]
    public function optionsUsers(): void {}
}
