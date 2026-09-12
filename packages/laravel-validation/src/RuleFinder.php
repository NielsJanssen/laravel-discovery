<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use LogicException;
use NielsJanssen\Laravel\Validation\Infer\RuleInferrer;
use ReflectionAttribute;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\ParameterReflector;
use Tempest\Reflection\PropertyReflector;
use Tempest\Reflection\TypeReflector;
use Throwable;
use Traversable;

/**
 * Turns a class or method into a cacheable RuleSet. RuleDiscovery pre-builds these and hands
 * them back through withCache(); anything not discovered is reflected on demand, so the cache
 * is only ever an optimisation.
 */
final class RuleFinder
{
    /** @var array<string, RuleSet> */
    private array $cache = [];

    public function __construct(
        /** @var iterable<RuleInferrer> */
        private readonly iterable $ruleInferrers = [],
    ) {}

    /**
     * @param  array<string, RuleSet>  $cache
     */
    public function withCache(array $cache): self
    {
        return clone($this, [
            'cache' => $cache,
        ]);
    }

    /**
     * A string source is a class name, or `Class::method` for a method's parameters — the same
     * form a RuleSet is named by, so a cached plan is served without any reflection.
     *
     * @param  ClassReflector<object>|MethodReflector|class-string|string  $source
     */
    public function find(ClassReflector|MethodReflector|string $source): RuleSet
    {
        $name = $this->getName($source);

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        if (is_string($source)) {
            [$class, $method] = array_pad(explode('::', $source, 2), 2, null);

            /** @var class-string $class */
            $source = $method === null
                ? new ClassReflector($class)
                : new ClassReflector($class)->getMethod($method);
        }

        $members = [];

        if ($source instanceof ClassReflector) {
            $class = $source->getName();

            foreach ($source->getProperties() as $property) {
                if ($property->getReflection()->isStatic()) {
                    continue;
                }

                if ($member = $this->discoverMember($property, $class, null)) {
                    $members[$member->name] = $member;
                }
            }
        } else {
            $class = $source->getDeclaringClass()->getName();

            foreach ($source->getParameters() as $parameter) {
                if ($parameter->isVariadic()) {
                    continue;
                }

                if ($member = $this->discoverMember($parameter, $class, $source->getName())) {
                    $members[$member->name] = $member;
                }
            }
        }

        return $this->cache[$name] = new RuleSet($name, $members);
    }

    /**
     * @param  ClassReflector<object>|MethodReflector|class-string|string  $source
     */
    private function getName(ClassReflector|MethodReflector|string $source): string
    {
        if (is_string($source)) {
            return $source;
        }

        return $source instanceof ClassReflector
            ? $source->getName()
            : $source->getDeclaringClass()->getName() . '::' . $source->getName();
    }

    /**
     * @param  class-string  $class
     */
    private function discoverMember(
        PropertyReflector|ParameterReflector $member,
        string $class,
        ?string $method,
    ): ?MemberRules {
        /** @var list<array{int, ValidationRule}> $attributes */
        $attributes = [];
        /** @var list<NestedValidationRule> $nests */
        $nests = [];
        $messages = [];

        // Enumerated natively (not through Tempest) so the index we cache addresses the same
        // list AttributeRef will read back.
        foreach ($member->getReflection()->getAttributes() as $index => $attribute) {
            if (! is_a($attribute->getName(), ValidationRule::class, allow_string: true)) {
                continue;
            }

            $instance = $this->instantiate($attribute);

            // Both behaviours are found by interface, so a user's own attribute gets them too.
            if ($instance instanceof MessageValidationRule && $instance->message !== null) {
                $key = $instance->messageKey ?? '';

                // Two messages on one key would silently overwrite each other.
                if (isset($messages[$key])) {
                    throw new LogicException(sprintf(
                        '%s carries two messages for the "%s" rule; keep one.',
                        $this->describe($member, $class, $method),
                        $key === '' ? 'field' : $key,
                    ));
                }

                $messages[$key] = $instance->message;
            }

            if ($instance instanceof NestedValidationRule) {
                $nests[] = $instance;
            }

            $attributes[] = [$index, $instance];
        }

        if ($attributes === []) {
            return null;
        }

        $rules = new RuleCollection();

        foreach ($this->ruleInferrers as $infer) {
            $infer($rules, $member);
        }

        $declared = array_map(static fn(array $attribute): ValidationRule => $attribute[1], $attributes);

        $inferred = $rules
            ->reject(fn(ValidationRule $rule): bool => $this->isSuperseded($rule, $declared))
            ->values()
            ->all();

        foreach ($attributes as [$index, $instance]) {
            $inferred[] = $this->isCacheable($instance)
                ? $instance
                : new AttributeRef($class, $method, $member->getName(), $index);
        }

        $nesting = Nesting::None;
        $allowed = [];
        $elementRules = [];

        foreach ($nests as $nest) {
            // #[ListOf] and #[Each] compose because both describe elements; #[Valid] describes the
            // one object a member holds, so it cannot join them.
            if ($nesting !== Nesting::None && $nesting !== $nest->nesting) {
                throw new LogicException(sprintf(
                    '%s carries nesting attributes that disagree; a member holds either one object '
                    . 'or many elements.',
                    $this->describe($member, $class, $method),
                ));
            }

            $nesting = $nest->nesting;
            $allowed = [...$allowed, ...$nest->allowedClasses];
            $elementRules = [...$elementRules, ...$nest->elementRules];
        }

        $type = $member->getType();

        if ($nesting === Nesting::Each) {
            if ($this->cannotIterate($type)) {
                throw new LogicException(sprintf(
                    '%s is not iterable, so it has no elements to validate. Use #[Valid] for a '
                    . 'single object.',
                    $this->describe($member, $class, $method),
                ));
            }

            $this->assertCacheable($elementRules, $member, $class, $method);
        }

        if ($nesting === Nesting::Value) {
            if ($this->iterates($type)) {
                throw new LogicException(sprintf(
                    '#[Valid] on %s, which holds many elements. Use #[ListOf(Thing::class)] instead.',
                    $this->describe($member, $class, $method),
                ));
            }

            // The declared type is the constraint unless the attribute narrowed it.
            $allowed = $allowed === [] ? $this->declaredClass($type) : $allowed;
            $nesting = $this->isObjectType($type) ? $nesting : Nesting::None;
        }

        return new MemberRules(
            name: $member->getName(),
            rules: array_values($inferred),
            nesting: $nesting,
            allowed: $allowed,
            elementRules: $elementRules,
            messages: $messages,
        );
    }

    /**
     * The declared type as the single allowed class for #[Valid], which PHP already enforces.
     *
     * @return list<class-string>
     */
    private function declaredClass(TypeReflector $type): array
    {
        if (! $this->isObjectType($type)) {
            return [];
        }

        /** @var class-string $declared */
        $declared = ltrim($type->getName(), '?');

        return [$declared];
    }

    /** A union or intersection names no single thing, so none of the questions below apply to it. */
    private function isComposite(TypeReflector $type): bool
    {
        return $type->isUnion() || $type->isIntersection();
    }

    /**
     * A single object type. `isClass()` alone is `class_exists()`, which is false for an
     * interface — and an interface-typed property is exactly what #[Valid] narrowing is for.
     */
    private function isObjectType(TypeReflector $type): bool
    {
        return ! $this->isComposite($type) && ($type->isClass() || $type->isInterface());
    }

    /** A Traversable is a class, but what it holds is what #[ListOf] and #[Each] are about. */
    private function iterates(TypeReflector $type): bool
    {
        if ($this->isComposite($type)) {
            return false;
        }

        return $type->isIterable() || $type->matches(Traversable::class);
    }

    /** Definitely not iterable, as opposed to `mixed` or a union where the value decides. */
    private function cannotIterate(TypeReflector $type): bool
    {
        if ($this->isComposite($type)) {
            return false;
        }

        return $type->isScalar() || ($this->isObjectType($type) && ! $type->matches(Traversable::class));
    }

    /**
     * Element rules live on MemberRules rather than being re-read through reflection, so unlike a
     * property's own rules they have to survive the cache on their own.
     *
     * @param  list<ValidationRule>  $elementRules
     * @param  class-string  $class
     */
    private function assertCacheable(array $elementRules, PropertyReflector|ParameterReflector $member, string $class, ?string $method): void
    {
        foreach ($elementRules as $rule) {
            if (! $this->isCacheable($rule)) {
                throw new LogicException(sprintf(
                    '#[Each] on %s holds a closure, which cannot be cached. Put the rule on the '
                    . "element's own property, or pass a rule object.",
                    $this->describe($member, $class, $method),
                ));
            }
        }
    }

    /**
     * @param  class-string  $class
     */
    private function describe(PropertyReflector|ParameterReflector $member, string $class, ?string $method): string
    {
        return $method === null
            ? "{$class}::\${$member->getName()}"
            : "{$class}::{$method}(\${$member->getName()})";
    }

    /**
     * Whether an explicitly written attribute makes an inferred rule redundant: the same
     * attribute class, or any other type rule, since a member has exactly one type. This is what
     * lets #[Nullable] on a `?string` not yield `nullable` twice, and #[Numeric] on an `int`
     * give `numeric` instead of `integer`.
     *
     * @param  list<ValidationRule>  $declared
     */
    private function isSuperseded(ValidationRule $inferred, array $declared): bool
    {
        foreach ($declared as $explicit) {
            if ($explicit::class === $inferred::class) {
                return true;
            }

            if ($inferred instanceof TypeRule && $explicit instanceof TypeRule) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  ReflectionAttribute<object>  $attribute
     */
    private function instantiate(ReflectionAttribute $attribute): ValidationRule
    {
        $instance = $attribute->newInstance();

        assert($instance instanceof ValidationRule);

        return $instance;
    }

    /**
     * Whether the attribute survives the discovery cache. Only closures fail, and they fail by
     * throwing from serialize(); asking is cheaper than trying to detect them structurally.
     *
     * serialize() is the probe. If a rule ever needs a different cache backend with
     * different rules about what it accepts, this is the single place to change.
     */
    private function isCacheable(ValidationRule $attribute): bool
    {
        try {
            serialize($attribute);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
