<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Database\Eloquent\Model;

/**
 * The records the #[Authorize] checks of one field call looked up, so validation and the resolver reuse them
 * instead of querying again. A record is only ever reused within the call that found it: the field empties
 * the store when its call begins and again when it is denied, fails validation or has finished resolving.
 */
final class BoundRecords
{
    /** @var array<string, Model> keyed by model class and the route-key value the record was found by */
    private array $records = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function remember(string $modelClass, mixed $value, Model $model): void
    {
        $key = self::key($modelClass, $value);

        if ($key !== null) {
            $this->records[$key] = $model;
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function get(string $modelClass, mixed $value): ?Model
    {
        $key = self::key($modelClass, $value);

        return $key === null ? null : $this->records[$key] ?? null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function has(string $modelClass, mixed $value): bool
    {
        return $this->get($modelClass, $value) !== null;
    }

    public function forget(): void
    {
        $this->records = [];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function key(string $modelClass, mixed $value): ?string
    {
        return is_scalar($value) ? $modelClass . '|' . var_export($value, true) : null;
    }
}
