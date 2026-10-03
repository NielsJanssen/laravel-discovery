<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;

/** The type a set of yielded fields belongs to, and how its errors name the type and where the fields came from. */
final readonly class FactoryOwner
{
    /**
     * @param  string  $origin  names the yielder, such as `type factory Acme\Fields`
     * @param  string  $subject  names the type, such as `type [Company] (Acme\Company)`
     * @param  FieldCase|class-string<NamingStrategy>|null  $naming  the override of the type's naming strategy
     */
    public function __construct(
        public string $name,
        public string $origin,
        public string $subject,
        public string $namingLabel,
        public FieldCase|string|null $naming = null,
    ) {}
}
