<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Foundation\Application;
use Rebing\GraphQL\Support\Field;
use Rebing\GraphQL\Support\Middleware as RebingMiddleware;

class DiscoveredAction
{
    public function __construct(
        public Action $action,
        public string $class,
        public string $method,
        /** @var DiscoveredArg[] */
        public array $args = [],
        /** @var array<string, 'root'|'context'|'info'> keyed by paramName */
        public array $injections = [],
        /** @var list<class-string<RebingMiddleware>> outermost first */
        public array $middleware = [],
        public ?string $deprecationReason = null,
        /** @var list<Authorize> class-first then method-first, all must pass */
        public array $authorizations = [],
        public ?ActionTypeBuilder $typeBuilder = null,
        /** @var array<string, class-string> keyed by paramName */
        public array $containerInjections = [],
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
