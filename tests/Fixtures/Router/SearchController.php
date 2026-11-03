<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use Illuminate\Http\Request;
use NielsJanssen\Laravel\Discovery\Router\Query;

class SearchController
{
    #[Query('/search')]
    public function search(Request $request): array
    {
        return ['term' => $request->json('term')];
    }

    #[Query('/web/search', middleware: ['web'])]
    public function webSearch(): string
    {
        return 'ok';
    }
}
