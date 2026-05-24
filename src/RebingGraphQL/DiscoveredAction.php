<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Foundation\Application;
use Rebing\GraphQL\Support\Field;

class DiscoveredAction
{
    public function __construct(
        public Action $action,
        public string $class,
        public string $method,
        /** @var DiscoveredArg[] */
        public array $args = [],
    ) {}

    public function createType(Application $app): Field
    {
        return match ($this->action::class) {
            Query::class => new QueryField($app, $this),
            Mutation::class => new MutationField($app, $this),
            default => throw new \Exception('Unexpected action type'),
        };
    }

    public string $fieldType {
        get => match ($this->action::class) {
            Query::class => 'query',
            Mutation::class => 'mutation',
            default => throw new \Exception('Unexpected action type'),
        };
    }
}
