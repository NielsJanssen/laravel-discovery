<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\TypeRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ArrayType extends StringRule implements TypeRule
{
    /**
     * @param  list<string>|null  $keys  the only keys the array may hold; any key when null
     */
    public function __construct(
        public readonly ?array $keys = null,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    public string $name {
        get => 'array';
    }

    protected function parameters(): array
    {
        return $this->keys ?? [];
    }
}
