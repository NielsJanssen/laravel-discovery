<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\ThirdParty\Acme;

/** Stands in for a package trait whose members an application type inherits. */
trait HasAudit
{
    public string $auditedBy = 'system';
}
