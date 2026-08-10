<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use RuntimeException;

/**
 * The registered ArgumentHydrator implementations. Consulted at discovery time to decide which
 * parameters are hydrated, and at resolve time to build them.
 */
final class ArgumentHydrators
{
    /** @var array<class-string, ArgumentHydrator>|null */
    private ?array $resolved = null;

    public function __construct(
        /** @var iterable<ArgumentHydrator> lazy, since Container::tagged() returns a generator */
        private readonly iterable $hydrators = [],
    ) {}

    /**
     * @param  class-string  $class
     */
    public function hydrates(string $class): bool
    {
        return $this->hydratorFor($class) !== null;
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $args
     */
    public function hydrate(string $class, array $args): object
    {
        $hydrator = $this->hydratorFor($class);

        if ($hydrator === null) {
            throw new RuntimeException(sprintf(
                'No ArgumentHydrator handles %s. Tag one with ArgumentHydrator::TAG, or have the '
                . 'class implement ComposedFromArgs.',
                $class,
            ));
        }

        return $hydrator->hydrate($class, $args);
    }

    /**
     * First tagged hydrator wins, so a consumer's own registration can take precedence over the
     * built-in one for a class both would claim.
     *
     * @param  class-string  $class
     */
    private function hydratorFor(string $class): ?ArgumentHydrator
    {
        if (isset($this->resolved[$class])) {
            return $this->resolved[$class];
        }

        foreach ($this->hydrators as $hydrator) {
            if ($hydrator->hydrates($class)) {
                return $this->resolved[$class] = $hydrator;
            }
        }

        return null;
    }
}
