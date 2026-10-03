<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldSource;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;

/** What the type and input collectors share when they read a class's members. */
final readonly class FieldMembers
{
    public function __construct(
        private Naming $names,
    ) {}

    /** The member as a field candidate, or null when it is never a field, as with #[Ignore] or a non-public member. */
    public function member(PropertyReflector|MethodReflector $reflector): ?FieldMember
    {
        $reflection = $reflector->getReflection();
        $isProperty = $reflector instanceof PropertyReflector;
        $field = $reflector->getAttribute(Field::class);
        $member = new FieldMember(
            $isProperty
                ? sprintf('Property %s::$%s', $reflection->getDeclaringClass()->getName(), $reflector->getName())
                : sprintf('Method %s::%s()', $reflection->getDeclaringClass()->getName(), $reflector->getName()),
            $field,
            $reflector,
        );

        if ($field?->isFactoryOnly()) {
            throw new LogicException("{$member->label} has #[Field(resolve:)] or #[Field(args:)], which only a field yielded by a TypeFactory can set. Remove it, or yield the field from a factory.");
        }

        if ($reflector->hasAttribute(Ignore::class)) {
            return $field === null ? null : throw new LogicException("{$member->label} has both #[Field] and #[Ignore]. Remove one.");
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            return $member->skipOrReject(sprintf(
                'is %s. Only public, non-static %s become fields.',
                $reflection->isStatic() ? 'static' : 'not public',
                $isProperty ? 'properties' : 'methods',
            ));
        }

        return $member;
    }

    /** The GraphQL name of a field: an explicit #[Field(name:)], or the strategy's name for the member. */
    public function name(FieldMember $member, NamingStrategy $naming): string
    {
        return $member->field->name ?? $this->names->name($naming, $member->reflector->getName(), $member->label);
    }

    /**
     * @param  string  $kind  what the class is, Type or Input
     * @param  list<DiscoveredTypeField>  $fields
     */
    public function assertUniqueNames(string $kind, string $class, array $fields): void
    {
        $seen = [];

        foreach ($fields as $field) {
            $previous = $seen[$field->name] ?? null;

            if ($previous !== null) {
                throw new LogicException(sprintf(
                    '%s %s has two fields named "%s" (%s and %s). Rename one with #[Field(name: ...)], or #[Ignore] one.',
                    $kind,
                    $class,
                    $field->name,
                    $this->describe($previous),
                    $this->describe($field),
                ));
            }

            $seen[$field->name] = $field;
        }
    }

    private function describe(DiscoveredTypeField $field): string
    {
        return $field->source === FieldSource::Method ? "{$field->phpName}()" : "\${$field->phpName}";
    }
}
