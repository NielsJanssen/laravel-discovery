<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

interface ActionDecorator
{
    public function decorate(Action $action): Action;
}
