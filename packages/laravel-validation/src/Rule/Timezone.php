<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Timezone extends StringRule
{
    /**
     * @param  string  $group  a DateTimeZone group name ('all', 'europe', 'per_country', ...)
     * @param  string|null  $country  an ISO 3166-1 alpha-2 code, for the 'per_country' group
     */
    public function __construct(
        public readonly string $group = 'all',
        public readonly ?string $country = null,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return [$this->group, $this->country];
    }
}
