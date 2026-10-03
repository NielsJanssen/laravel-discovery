<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping;

use Illuminate\Contracts\Config\Repository;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** Maps classes to the GraphQL scalar or type names in `discovery.graphql.scalars`, subclasses and implementations included. */
final class ScalarMap implements TypeMapper
{
    public const string CONFIG = 'discovery.graphql.scalars';

    /** @var array<class-string, string>|null */
    private ?array $scalars = null;

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        $class = $type->getName();

        if ($type->isUnion() || $type->isIntersection() || ! (class_exists($class) || interface_exists($class))) {
            return null;
        }

        foreach ($this->scalars() as $parent => $name) {
            if (is_a($class, $parent, true)) {
                return in_array($name, TypeRef::SCALARS, true) ? TypeRef::scalar($name) : TypeRef::named($name);
            }
        }

        return null;
    }

    /**
     * @return array<class-string, string>
     */
    private function scalars(): array
    {
        if ($this->scalars !== null) {
            return $this->scalars;
        }

        $scalars = $this->config->get(self::CONFIG, []);
        $valid = [];

        foreach (is_array($scalars) ? $scalars : [] as $class => $name) {
            if (! is_string($class) || ! is_string($name) || $name === '') {
                throw new LogicException(sprintf(
                    'Config %s maps class-strings to GraphQL type names, as in [CarbonInterface::class => \'DateTime\'], got [%s => %s].',
                    self::CONFIG,
                    var_export($class, true),
                    get_debug_type($name),
                ));
            }

            if (! class_exists($class) && ! interface_exists($class)) {
                throw new LogicException(sprintf(
                    'Config %s maps [%s], which is not a class or interface. Use a class-string such as CarbonInterface::class.',
                    self::CONFIG,
                    $class,
                ));
            }

            $valid[$class] = $name;
        }

        return $this->scalars = $valid;
    }
}
