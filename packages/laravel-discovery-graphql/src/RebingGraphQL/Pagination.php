<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class Pagination implements ComposedFromArgs
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
    ) {}

    public static function fromArgs(array $args): static
    {
        return new self(
            page: $args['page'] ?? 1,
            limit: $args['limit'] ?? 20,
        );
    }

    public function __invoke(Builder $query): LengthAwarePaginator
    {
        return $query->paginate($this->limit, ['*'], 'page', $this->page);
    }
}
