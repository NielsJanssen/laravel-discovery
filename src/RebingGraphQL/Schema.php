<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

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
