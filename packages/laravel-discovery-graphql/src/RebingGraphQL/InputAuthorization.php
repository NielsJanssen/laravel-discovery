<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Foundation\Application;

/** Runs the model-bound #[Authorize] checks of the discovered input objects in a field's args. */
final readonly class InputAuthorization
{
    public function __construct(
        private Application $app,
        private InputObjects $inputs,
    ) {}

    /**
     * The first #[Authorize] a bound record fails.
     *
     * @param  array<string, mixed>  $definitions  the field's args(), keyed by arg name
     * @param  array<string, mixed>  $args
     */
    public function deniedBy(array $definitions, array $args, mixed $context, ?ResolveInfo $info): ?Authorize
    {
        foreach ($this->inputs->inArgs($definitions, $args) as [$type, $values]) {
            foreach ($type->fields as $field) {
                $denied = $field->binding?->deniedBy($this->app, $values[$field->name] ?? null, $args, $context, $info);

                if ($denied !== null) {
                    return $denied;
                }
            }
        }

        return null;
    }
}
