<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use Illuminate\Http\Request;
use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Router\Metadata;

#[Metadata([
    'head' => ['title' => 'Acme', 'robots' => 'index'],
    'nav' => ['section' => 'acme'],
    'tags' => ['class'],
    'layer' => 'class',
])]
class MetadataController
{
    #[Metadata(['head' => ['title' => 'Users'], 'tags' => ['method'], 'layer' => 'method'])]
    #[Get('/acme/users', metadata: ['head' => ['description' => 'All users'], 'layer' => 'route'])]
    public function index(Request $request): mixed
    {
        return $request->route()?->getMetadata('head.title');
    }

    #[Get('/acme/inherited')]
    public function inherited(): void {}

    #[Metadata(['nav' => ['section' => 'first', 'active' => true], 'tags' => ['first']])]
    #[Metadata(['nav' => ['section' => 'second'], 'tags' => ['second']])]
    #[Get('/acme/repeated')]
    public function repeated(): void {}
}
