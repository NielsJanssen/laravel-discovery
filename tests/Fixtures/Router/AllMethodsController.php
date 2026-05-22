<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Delete;
use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Router\Head;
use NielsJanssen\Laravel\Discovery\Router\Options;
use NielsJanssen\Laravel\Discovery\Router\Patch;
use NielsJanssen\Laravel\Discovery\Router\Post;
use NielsJanssen\Laravel\Discovery\Router\Put;

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
