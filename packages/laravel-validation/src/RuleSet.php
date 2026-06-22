<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * The cached validation plan for one class or one method. Everything it holds is
 * serializable, so a RuleSet can go straight into the discovery cache; values and any
 * closure-backed rule are resolved per call by the RuleCompiler.
 */
final readonly class RuleSet
{
    public function __construct(
        /** Fully qualified class name, or "Class::method" for a method's parameters. */
        public string $name,

        /** @var array<string, MemberRules> keyed by property/parameter name */
        public array $members,
    ) {}
}
