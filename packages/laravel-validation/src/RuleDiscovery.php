<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Illuminate\Contracts\Foundation\Application;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

/**
 * Pre-builds the validation plan for every class and method that uses validation attributes,
 * so a cached application never reflects at validation time.
 */
final class RuleDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
        private readonly RuleFinder $ruleFinder,
    ) {}

    /**
     * @param  ClassReflector<object>  $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getProperties() as $property) {
            if ($property->getAttribute(ValidationRule::class) !== null) {
                $this->discoveryItems->add($location, new DiscoveredRules($this->ruleFinder->find($class)));

                break;
            }
        }

        foreach ($class->getPublicMethods() as $method) {
            if ($this->hasAnnotatedParameter($method)) {
                $this->discoveryItems->add($location, new DiscoveredRules($this->ruleFinder->find($method)));
            }
        }
    }

    public function apply(): void
    {
        $cache = [];

        foreach ($this->discoveryItems as $item) {
            assert($item instanceof DiscoveredRules);

            $cache[$item->rules->name] = $item->rules;
        }

        $this->app->extend(
            RuleFinder::class,
            static fn(RuleFinder $ruleFinder): RuleFinder => $ruleFinder->withCache($cache),
        );
    }

    private function hasAnnotatedParameter(MethodReflector $method): bool
    {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getAttribute(ValidationRule::class) !== null) {
                return true;
            }
        }

        return false;
    }
}
