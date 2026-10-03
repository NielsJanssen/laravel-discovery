<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Illuminate\Contracts\Config\Repository;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;

/** Recognises members declared under the namespaces in `discovery.graphql.skip_namespaces`, which never become fields. */
final class SkippedMembers
{
    public const string CONFIG = 'discovery.graphql.skip_namespaces';

    /** @var list<string>|null namespace prefixes, each ending in a backslash */
    private ?array $prefixes = null;

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function skips(PropertyReflector|MethodReflector $member): bool
    {
        $declaringClass = $member->getReflection()->getDeclaringClass();

        if ($this->isSkipped($declaringClass->getName())) {
            return true;
        }

        return $member instanceof PropertyReflector && $this->declaredUnderSkipped($declaringClass, $member->getName());
    }

    /** Whether a property comes from a trait rather than the class body. */
    public function isImportedFromTrait(ReflectionProperty $property): bool
    {
        return $this->traitsDeclaring($property->getDeclaringClass(), $property->getName()) !== [];
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<string>
     */
    private function traitsDeclaring(ReflectionClass $class, string $property): array
    {
        return array_values(array_filter(trait_uses_recursive($class->getName()), static fn(string $trait): bool => property_exists($trait, $property)));
    }

    /**
     * Whether a parent class or trait under a skipped namespace declares the property as well.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function declaredUnderSkipped(ReflectionClass $class, string $property): bool
    {
        $owners = $this->traitsDeclaring($class, $property);

        for ($parent = $class->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            $owners[] = $parent->getName();
        }

        foreach ($owners as $owner) {
            if ($this->isSkipped($owner) && property_exists($owner, $property)) {
                return true;
            }
        }

        return false;
    }

    private function isSkipped(string $class): bool
    {
        return array_any($this->prefixes(), static fn(string $prefix): bool => str_starts_with($class, $prefix));
    }

    /**
     * @return list<string>
     */
    private function prefixes(): array
    {
        if ($this->prefixes !== null) {
            return $this->prefixes;
        }

        $configured = $this->config->get(self::CONFIG, ['Illuminate\\']);
        $prefixes = [];

        foreach (is_array($configured) ? $configured : [$configured] as $prefix) {
            if (! is_string($prefix) || trim($prefix, '\\ ') === '') {
                throw new LogicException(sprintf(
                    'Config %s lists namespace prefixes, as in [\'Illuminate\\\\\', \'Acme\\\\\'], got [%s].',
                    self::CONFIG,
                    is_string($prefix) ? "'$prefix'" : get_debug_type($prefix),
                ));
            }

            $prefixes[] = trim($prefix, '\\ ') . '\\';
        }

        return $this->prefixes = $prefixes;
    }
}
