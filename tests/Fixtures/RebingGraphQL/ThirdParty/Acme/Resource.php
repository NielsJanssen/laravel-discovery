<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\ThirdParty\Acme;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;

/** Stands in for a package base class whose members an application type inherits. */
abstract class Resource
{
    public string $vendorId = 'base';

    public string $vendorLabel {
        get => 'label';
    }

    #[Field]
    public function vendorStatus(): string
    {
        return 'draft';
    }
}
