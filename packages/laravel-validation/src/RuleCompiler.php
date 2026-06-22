<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use ReflectionProperty;
use Tempest\Reflection\ClassReflector;

/**
 * Marries a cached RuleSet to actual values, producing the data/rules/messages arrays Laravel's
 * validator takes. This is the only place values are read, so the plan itself stays cacheable.
 */
final class RuleCompiler
{
    /** @var array<class-string, array<string, ReflectionProperty>> */
    private array $properties = [];

    public function __construct(
        private readonly RuleFinder $finder,
    ) {}

    public function forObject(object $object): CompiledRules
    {
        return $this->compile($object, $this->finder->find($object::class), null, '');
    }

    /**
     * @param  object|class-string  $target
     * @param  array<string, mixed>  $arguments
     */
    public function forMethod(object|string $target, string $method, array $arguments): CompiledRules
    {
        $class = is_object($target) ? $target::class : $target;
        $set = $this->finder->find(new ClassReflector($class)->getMethod($method));

        return $this->compile(is_object($target) ? $target : null, $set, $arguments, '');
    }

    /**
     * @param  array<string, mixed>|null  $arguments  null means read values off $root
     */
    private function compile(mixed $root, RuleSet $set, ?array $arguments, string $prefix): CompiledRules
    {
        $data = [];
        $rules = [];
        $messages = [];

        foreach ($set->members as $member) {
            $path = $prefix === '' ? $member->name : "{$prefix}.{$member->name}";

            [$hasValue, $value] = $this->valueOf($member, $root, $arguments);

            if ($hasValue) {
                $data[$member->name] = $value;
            }

            $memberRules = $this->flatten($member->rules, $root, $path, $value);

            if ($memberRules !== []) {
                $rules[$path] = $memberRules;
            }

            foreach ($member->messages as $suffix => $message) {
                $messages[$suffix === '' ? $path : "{$path}.{$suffix}"] = $message;
            }

            if ($member->nesting === Nesting::None) {
                continue;
            }

            $allowed = new AllowedTypes($member->allowed);

            // #[Valid]: recurse, taking each nested plan straight from the cache.
            if ($member->nesting === Nesting::Value && is_object($value)) {
                if (! $allowed->accepts($value)) {
                    $rules[$path] = [...$rules[$path] ?? [], $allowed];

                    continue;
                }

                $child = $this->descend($value, $path);

                $data[$member->name] = $child->data;
                $rules += $child->rules;
                $messages += $child->messages;
            }

            if ($member->nesting === Nesting::Each && is_iterable($value)) {
                $child = $this->compileEach($member, $allowed, $value, $root, $path);

                $data[$member->name] = $child->data;
                $rules += $child->rules;
                $messages += $child->messages;
            }
        }

        return new CompiledRules($data, $rules, $messages);
    }

    /**
     * #[ListOf] / #[Each]: every element validated at its own path.
     *
     * ponytail: one rule set per element, so error keys read `lines.2.sku` and each element's
     * closures see their own object. Cost is elements × rules; if lists ever get big enough to
     * matter, swap to a single `lines.*` wildcard and give up the per-element context.
     *
     * @param  iterable<mixed, mixed>  $value
     */
    private function compileEach(
        MemberRules $member,
        AllowedTypes $allowed,
        iterable $value,
        mixed $root,
        string $path,
    ): CompiledRules {
        $items = [];
        $rules = [];
        $messages = [];

        foreach ($value as $key => $item) {
            // Only a hand-rolled Iterator can yield a key no validation path could name.
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $itemPath = "{$path}.{$key}";

            // #[Each]: rules on the element itself, whether or not it is also a #[ListOf] object.
            $elementRules = $member->elementRules === []
                ? []
                : $this->flatten($member->elementRules, $root, $itemPath, $item);

            // #[Each] alone: no classes named, so nothing to descend into.
            if ($member->allowed === []) {
                $items[$key] = $item;

                if ($elementRules !== []) {
                    $rules[$itemPath] = $elementRules;
                }

                continue;
            }

            // Anything #[ListOf] does not name fails at its own path; it is never validated
            // against whatever class it happens to be.
            if (! is_object($item) || ! $allowed->accepts($item)) {
                $items[$key] = $item;
                $rules[$itemPath] = [...$elementRules, $allowed];

                continue;
            }

            if ($elementRules !== []) {
                $rules[$itemPath] = $elementRules;
            }

            $child = $this->descend($item, $itemPath);

            $items[$key] = $child->data;
            $rules += $child->rules;
            $messages += $child->messages;
        }

        return new CompiledRules($items, $rules, $messages);
    }

    /**
     * @param  list<ValidationRule>  $rules
     * @return array<int, mixed>
     */
    private function flatten(array $rules, mixed $root, string $path, mixed $value): array
    {
        $context = new ValidationContext($root, $path, $value);
        $flat = [];

        foreach ($rules as $rule) {
            $flat = [...$flat, ...$rule->rules($context)];
        }

        return $flat;
    }

    private function descend(object $value, string $path): CompiledRules
    {
        return $this->compile($value, $this->finder->find($value::class), null, $path);
    }

    /**
     * @param  array<string, mixed>|null  $arguments
     * @return array{bool, mixed}
     */
    private function valueOf(MemberRules $member, mixed $root, ?array $arguments): array
    {
        if ($arguments !== null) {
            return array_key_exists($member->name, $arguments)
                ? [true, $arguments[$member->name]]
                : [false, null];
        }

        if (! is_object($root)) {
            return [false, null];
        }

        // Memoised because a #[ListOf] of 100 elements reads every member 100 times per call, and
        // the (class, property) pair repeats constantly. Lives on the compiler, never on the
        // cached plan, which has to stay serialize()-clean.
        $property = $this->properties[$root::class][$member->name] ??= new ReflectionProperty($root, $member->name);

        return $property->isInitialized($root)
            ? [true, $property->getValue($root)]
            : [false, null];
    }
}
