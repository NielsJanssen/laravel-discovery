<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Contracts\Database\Eloquent\Builder;

final readonly class Sort implements ComposedFromArgs
{
    public function __construct(
        public ?string $field = null,
        public string  $direction = 'asc',
    ) {}

    /**
     * @param array{order?: string, sortBy?: string, sortDirection?: string} $args
     */
    public static function fromArgs(array $args): static
    {
        if (isset($args['order']) && str_contains($args['order'], ':')) {
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
        if ($this->field === null) {
            return $query;
        }

        return $query->orderBy($this->field, $this->direction === 'desc' ? 'desc' : 'asc');
    }
}
