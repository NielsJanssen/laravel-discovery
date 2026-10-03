<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Decorators;

use Attribute;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldBlueprint;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDiscoveryVerifier;

/** Uppercases a string field, and makes it nullable so an empty value becomes null. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Uppercase implements FieldDiscoveryVerifier, FieldDecorator
{
    public function verify(string $member, DiscoveredTypeField $field): void
    {
        if ($field->type->scalar !== 'string') {
            throw new LogicException("$member has #[Uppercase] but is not a string.");
        }
    }

    public function decorate(FieldBlueprint $field): void
    {
        $field->nullable();
        $field->wrapResolver(static function (mixed $root, array $args, mixed $context, ?ResolveInfo $info, Closure $next): ?string {
            $value = $next($root, $args, $context, $info);

            return is_string($value) && $value !== '' ? strtoupper($value) : null;
        });
    }
}
