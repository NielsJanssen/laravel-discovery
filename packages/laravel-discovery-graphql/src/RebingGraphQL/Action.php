<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

interface Action
{
    public ?string $type {get; set;}
    public ?string $name {get; set;}
    public ?string $schema {get; set;}
    public bool $list {get; set;}
    public bool $nullable {get; set;}
}
