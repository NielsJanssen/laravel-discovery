<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Exception;
use Illuminate\Foundation\Application;
use Rebing\GraphQL\Support\Field;
use Rebing\GraphQL\Support\Middleware as RebingMiddleware;

class DiscoveredAction
{
    public private(set) ?string $bindName = null;

    public function __construct(
        public Action $action,
        /** @var class-string */
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
        /** @var list<ActionArgProvider> */
        public array $argProviders = [],
        /** @var array<string, class-string> keyed by paramName; hydrated by an ArgumentHydrator */
        public array $argCompositions = [],
        /** @var list<DiscoveredModelBinding> */
        public array $modelBindings = [],
    ) {}

    public function createType(Application $app): Field
    {
        return match ($this->action::class) {
            Query::class => new QueryField($app, $this),
            Mutation::class => new MutationField($app, $this),
            default => throw new Exception('Unexpected action type'),
        };
    }

    public string $fieldType {
        get => match ($this->action::class) {
            Query::class => 'query',
            Mutation::class => 'mutation',
            default => throw new Exception('Unexpected action type'),
        };
    }

    public function withBindName(): static
    {
        return clone($this, [
            'bindName' => 'discovery.rebing_graphql.' . hash('sha256', serialize($this)),
        ]);
    }

    /**
     * Re-key request arguments by PHP parameter name, since #[Arg(name: 'id')] lets the two differ.
     * Arguments with no matching parameter (an ActionArgProvider's, say) are kept under their own
     * name, so a value object built from them still finds them.
     *
     * @param  array<string, mixed>  $args  keyed by GraphQL arg name
     * @return array<string, mixed>  keyed by parameter name
     */
    public function toParameters(array $args): array
    {
        $parameters = $args;

        foreach ($this->args as $arg) {
            if ($arg->name === $arg->paramName) {
                continue;
            }

            unset($parameters[$arg->name]);

            if (array_key_exists($arg->name, $args)) {
                $parameters[$arg->paramName] = $args[$arg->name];
            }
        }

        return $parameters;
    }

    /**
     * Translate a parameter path back to the GraphQL arg path a validator should report against,
     * keeping any trailing segments: `notify.0` becomes `recipients.0` when the arg was renamed.
     */
    public function toArgPath(string $paramPath): string
    {
        [$head, $rest] = array_pad(explode('.', $paramPath, 2), 2, null);

        foreach ($this->args as $arg) {
            if ($arg->paramName === $head) {
                return $rest === null ? $arg->name : "{$arg->name}.{$rest}";
            }
        }

        return $paramPath;
    }
}
