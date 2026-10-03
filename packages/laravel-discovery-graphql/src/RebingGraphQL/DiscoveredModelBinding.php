<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;

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
     * Looks the model up by its route key, as Laravel's own route-model binding does.
     *
     * @return Builder<Model>
     */
    public function query(mixed $value): Builder
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->where(new $modelClass()->getRouteKeyName(), $value);
    }

    /**
     * The first #[Authorize] the bound record fails; a nullable binding without a record has nothing to check.
     *
     * @param  array<string, mixed>  $args
     */
    public function deniedBy(Application $app, mixed $value, array $args, mixed $context, ?ResolveInfo $info): ?Authorize
    {
        if ($this->authorizations === []) {
            return null;
        }

        $model = $value === null ? null : $this->query($value)->first();

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
