<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

interface Action
{
    /** A GraphQL type name, a scalar name or a class-string. */
    public ?string $type {get; set;}
    /** The item type of a list; a GraphQL type name, a scalar name or a class-string. */
    public ?string $of {get; set;}
    public ?string $name {get; set;}
    public ?string $schema {get; set;}
    public ?string $description {get; set;}
    public bool $list {get; set;}
    public bool $nullable {get; set;}
    public bool $nullableItems {get; set;}
}
