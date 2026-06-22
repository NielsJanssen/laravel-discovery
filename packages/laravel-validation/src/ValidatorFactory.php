<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Illuminate\Validation\Factory as BaseFactory;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use RuntimeException;

/**
 * Drop-in replacement for Laravel's validator factory, adding entry points that build the
 * rules from an object's (or a method's) validation attributes.
 */
final class ValidatorFactory extends BaseFactory
{
    /**
     * Build a validator from an object's attributes. Extra $rules and $messages are merged
     * over the ones derived from the object, so a caller key takes precedence.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    public function makeFromObject(object $object, array $rules = [], array $messages = []): Validator
    {
        return $this->fromCompiled($this->compiler->forObject($object), $rules, $messages);
    }

    /**
     * Build a validator from a method's parameter attributes, against the given arguments.
     *
     * @param  object|class-string  $target
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    public function makeFromMethod(
        object|string $target,
        string $method,
        array $arguments,
        array $rules = [],
        array $messages = [],
    ): Validator {
        return $this->fromCompiled($this->compiler->forMethod($target, $method, $arguments), $rules, $messages);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<array-key, mixed>
     *
     * @throws ValidationException
     */
    public function validateObject(object $object, array $rules = [], array $messages = []): array
    {
        return $this->makeFromObject($object, $rules, $messages)->validate();
    }

    /**
     * @param  object|class-string  $target
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<array-key, mixed>
     *
     * @throws ValidationException
     */
    public function validateMethod(
        object|string $target,
        string $method,
        array $arguments,
        array $rules = [],
        array $messages = [],
    ): array {
        return $this->makeFromMethod($target, $method, $arguments, $rules, $messages)->validate();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    private function fromCompiled(CompiledRules $compiled, array $rules, array $messages): Validator
    {
        return $this->make(
            $compiled->data,
            array_merge($compiled->rules, $rules),
            array_merge($compiled->messages, $messages),
        );
    }

    private RuleCompiler $compiler {
        get {
            if ($this->container === null) {
                throw new RuntimeException('Building rules from attributes needs a container; none was given to the validator factory.');
            }

            return $this->container->make(RuleCompiler::class);
        }
    }
}
