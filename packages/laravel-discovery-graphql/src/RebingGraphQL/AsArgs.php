<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Flattens an #[Input] parameter's fields into the action's own top-level args. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class AsArgs {}
