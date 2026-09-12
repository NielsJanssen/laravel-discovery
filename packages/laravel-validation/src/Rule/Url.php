<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Url extends StringRule
{
    /**
     * @param  list<string>  $protocols  the schemes to accept; every scheme when empty
     */
    public function __construct(
        public readonly array $protocols = [],
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return $this->protocols;
    }
}
