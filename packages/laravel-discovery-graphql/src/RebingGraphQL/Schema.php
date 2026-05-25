<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Schema implements ActionDecorator
{
    public function __construct(
        public ?string $name = null,
    ) {}

    public function decorate(Action $action): Action
    {
        if ($action->schema === null) {
            $action->schema = $this->name;
        }

        return $action;
    }
}
