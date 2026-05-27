<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Contracts\Database\Eloquent\Builder;

readonly class Sort implements ComposedFromArgs
{
    public function __construct(
        public ?string $field = null,
        public string  $direction = 'asc',
    ) {}

    public static function fromArgs(array $args): static
    {
        if (isset($args['order']) && is_string($args['order']) && str_contains($args['order'], ':')) {
            [$field, $direction] = explode(':', $args['order'], 2);

            return new self($field, $direction);
        }

        return new self(
            field: $args['sortBy'] ?? null,
            direction: $args['sortDirection'] ?? 'asc',
        );
    }

    public function __invoke(Builder $query): Builder
    {
        return $this->field !== null ? $query->orderBy($this->field, $this->direction) : $query;
    }
}
