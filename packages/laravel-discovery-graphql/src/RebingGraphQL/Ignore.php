<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Keeps a public property or method of a #[Type] class out of the GraphQL type. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Ignore {}
