<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * The plan for a single opted-in property or parameter.
 */
final readonly class MemberRules
{
    public function __construct(
        public string $name,

        /** @var list<ValidationRule> inferred rules first, then the member's attributes */
        public array $rules,

        /** Set by #[Valid]. */
        public Nesting $nesting = Nesting::None,

        /**
         * Classes a nested member may hold, from #[Valid]/#[Each]'s arguments or the declared
         * type. Never inferred from the value.
         *
         * @var list<class-string>
         */
        public array $allowed = [],

        /**
         * Rules #[Each] applies to every element, for iterables of scalars.
         *
         * @var list<ValidationRule>
         */
        public array $elementRules = [],

        /** @var array<string, string> custom messages keyed by rule suffix ('' = the path itself) */
        public array $messages = [],
    ) {}
}
