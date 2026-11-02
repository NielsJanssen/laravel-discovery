<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

readonly class DiscoveredModelBinding
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $paramName,
        public string $argName,
        public string $modelClass,
        public bool $nullable,
        /** Explicit GraphQL type from #[Arg(type:)]; null defaults to the ID scalar. */
        public ?string $type = null,
        public bool $hasUserRules = false,
        /** @var list<Authorize> parameter-level #[Authorize('ability')], all must pass */
        public array $authorizations = [],
    ) {}

    /**
     * @return Builder<Model>
     */
    public function query(mixed $value): Builder
    {
        return self::lookup($this->modelClass, $value);
    }

    /**
     * Looks the model up by its route key, as Laravel's own route-model binding does.
     *
     * @param  class-string<Model>  $modelClass
     * @return Builder<Model>
     */
    public static function lookup(string $modelClass, mixed $value): Builder
    {
        return $modelClass::query()->where(new $modelClass()->getRouteKeyName(), $value);
    }

    /** The record this binding takes for a value, or null when there is no value or no record. */
    public function find(mixed $value): ?Model
    {
        return $value === null ? null : $this->query($value)->first();
    }

    /** The validation rule for a non-nullable binding: the record must exist under its route key. */
    public function existsRule(): Exists
    {
        $model = new $this->modelClass();

        return Rule::exists($model->getTable(), $model->getRouteKeyName());
    }

    /**
     * The first #[Authorize] the bound record fails; a nullable binding without a record has nothing to check.
     *
     * The record is kept in $records for the rest of the call, so validation and the resolver take it from
     * there instead of looking it up again.
     *
     * @param  array<string, mixed>  $args
     */
    public function denied(BoundRecords $records, Application $app, mixed $value, array $args, mixed $context, ?ResolveInfo $info): ?Authorize
    {
        if ($this->authorizations === []) {
            return null;
        }

        $model = $records->get($this->modelClass, $value) ?? $this->find($value);

        if ($model !== null) {
            $records->remember($this->modelClass, $value, $model);
        }

        if ($model === null && $this->nullable) {
            return null;
        }

        foreach ($this->authorizations as $authorize) {
            if ($model === null || ! $authorize->allows($app, $model, $args, $context, $info)) {
                return $authorize;
            }
        }

        return null;
    }
}
