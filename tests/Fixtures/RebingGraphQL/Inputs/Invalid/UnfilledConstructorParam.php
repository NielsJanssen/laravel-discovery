<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class UnfilledConstructorParam
{
    public string $token;

    public function __construct(public string $title, string $secret)
    {
        $this->token = hash('sha256', $secret);
    }
}
