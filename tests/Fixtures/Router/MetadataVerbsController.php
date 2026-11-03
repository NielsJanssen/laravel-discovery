<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Delete;
use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Router\Head;
use NielsJanssen\Laravel\Discovery\Router\Method;
use NielsJanssen\Laravel\Discovery\Router\Options;
use NielsJanssen\Laravel\Discovery\Router\Patch;
use NielsJanssen\Laravel\Discovery\Router\Post;
use NielsJanssen\Laravel\Discovery\Router\Put;
use NielsJanssen\Laravel\Discovery\Router\Query;
use NielsJanssen\Laravel\Discovery\Router\Route;

class MetadataVerbsController
{
    #[Get('/verbs/get', metadata: ['verb' => 'get'])]
    public function get(): void {}

    #[Post('/verbs/post', metadata: ['verb' => 'post'])]
    public function post(): void {}

    #[Put('/verbs/put', metadata: ['verb' => 'put'])]
    public function put(): void {}

    #[Patch('/verbs/patch', metadata: ['verb' => 'patch'])]
    public function patch(): void {}

    #[Delete('/verbs/delete', metadata: ['verb' => 'delete'])]
    public function delete(): void {}

    #[Head('/verbs/head', metadata: ['verb' => 'head'])]
    public function head(): void {}

    #[Options('/verbs/options', metadata: ['verb' => 'options'])]
    public function options(): void {}

    #[Query('/verbs/query', metadata: ['verb' => 'query'])]
    public function query(): void {}

    #[Route([Method::Get, Method::Post], '/verbs/route', metadata: ['verb' => 'route'])]
    public function route(): void {}
}
