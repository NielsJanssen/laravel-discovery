<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

/**
 * A stand-in for an attribute that cannot be cached, which in practice means one holding a
 * closure: #[Rule(static fn () => ...)] or a conditional rule's condition. The discovery cache
 * is written with `serialize()`, and closures are not serializable, so the plan stores where the
 * attribute lives and re-reads it through reflection on first use — the same trick
 * DiscoveredSchedule uses for closure-based schedules.
 */
final class AttributeRef implements ValidationRule
{
    /**
     * Resolved attributes, memoised per process. A static never enters the discovery cache,
     * so this class stays serializable without excluding anything by hand.
     *
     * @var array<string, ValidationRule>
     */
    private static array $instances = [];

    /**
     * @param  class-string  $class
     */
    public function __construct(
        private readonly string $class,
        private readonly ?string $method,
        private readonly string $member,
        /** Index into the member's *unfiltered* native attribute list. */
        private readonly int $index,
    ) {}

    /** The attribute this reference stands for, re-read through reflection on first access. */
    public ValidationRule $attribute {
        get => self::$instances[$this->id] ??= $this->read();
    }

    private string $id {
        get => $this->description . '#' . $this->index;
    }

    private string $description {
        get => $this->method === null
            ? "{$this->class}::\${$this->member}"
            : "{$this->class}::{$this->method}(\${$this->member})";
    }

    /** The reflection member this reference points at. */
    private ReflectionProperty|ReflectionParameter $target {
        get {
            if ($this->method === null) {
                return new ReflectionProperty($this->class, $this->member);
            }

            foreach (new ReflectionMethod($this->class, $this->method)->getParameters() as $parameter) {
                if ($parameter->getName() === $this->member) {
                    return $parameter;
                }
            }

            throw new RuntimeException(sprintf('Parameter %s no longer exists.', $this->description));
        }
    }

    public function rules(ValidationContext $context): array
    {
        return $this->attribute->rules($context);
    }

    private function read(): ValidationRule
    {
        $attribute = $this->target->getAttributes()[$this->index] ?? null;

        if (! $attribute instanceof ReflectionAttribute) {
            throw new RuntimeException(sprintf(
                'Validation attribute #%d on %s is gone; clear the discovery cache.',
                $this->index,
                $this->description,
            ));
        }

        $instance = $attribute->newInstance();

        if (! $instance instanceof ValidationRule) {
            throw new RuntimeException(sprintf('%s is not a validation rule.', $this->description));
        }

        return $instance;
    }
}
