<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** A FieldDecorator that rejects, at discovery, a field it cannot apply to. */
interface FieldDiscoveryVerifier
{
    /**
     * @throws \LogicException
     */
    public function verify(string $member, DiscoveredTypeField $field): void;
}
