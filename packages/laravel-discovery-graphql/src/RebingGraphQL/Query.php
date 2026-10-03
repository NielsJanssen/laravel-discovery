<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Query extends ActionAttribute {}
