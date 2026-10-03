<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;

/** A BatchLoader that rejects, at discovery, a field whose options it cannot load. */
interface VerifiesLoadOptions
{
    /**
     * @param  string  $member  how errors name the member, e.g. "Method Book::author()"
     * @param  string  $attribute  the attribute that names the loader, e.g. "#[Relation]"
     * @param  array<string, mixed>  $options
     *
     * @throws \LogicException
     */
    public static function verifyOptions(string $member, string $attribute, DiscoveredTypeField $field, array $options): void;
}
