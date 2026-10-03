<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Foundation\Application;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ClassifiedParameters;
use Rebing\GraphQL\Support\Field as RebingField;
use Rebing\GraphQL\Support\Middleware as RebingMiddleware;

class DiscoveredAction
{
    public private(set) ?string $bindName = null;

    public function __construct(
        public Query|Mutation $action,
        /** @var class-string */
        public string $class,
        public string $method,
        public ClassifiedParameters $parameters = new ClassifiedParameters(),
        /** @var list<class-string<RebingMiddleware>> outermost first */
        public array $middleware = [],
        public ?string $deprecationReason = null,
        /** @var list<Authorize> class-first then method-first, all must pass */
        public array $authorizations = [],
        public ?ActionTypeBuilder $typeBuilder = null,
        /** @var list<ActionArgProvider> */
        public array $argProviders = [],
        public ?TypeRef $returnType = null,
    ) {}

    public function createType(Application $app): RebingField
    {
        return match (true) {
            $this->action instanceof Query => new QueryField($app, $this),
            $this->action instanceof Mutation => new MutationField($app, $this),
        };
    }

    public string $fieldType {
        get => match (true) {
            $this->action instanceof Query => 'query',
            $this->action instanceof Mutation => 'mutation',
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

        foreach ($this->argNamesByParam() as $paramName => $argName) {
            if ($argName === $paramName) {
                continue;
            }

            unset($parameters[$argName]);

            if (array_key_exists($argName, $args)) {
                $parameters[$paramName] = $args[$argName];
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
        $segments = explode('.', $paramPath, 2);
        $head = $segments[0];
        $rest = $segments[1] ?? null;

        $argName = $this->argNamesByParam()[$head] ?? null;

        if ($argName === null) {
            return $paramPath;
        }

        return $rest === null ? $argName : "{$argName}.{$rest}";
    }

    /**
     * GraphQL arg name for every parameter that has one, model bindings included: those carry their
     * own `ID` arg on $modelBindings rather than an entry in $args.
     *
     * @return array<string, string> arg name keyed by parameter name
     */
    private function argNamesByParam(): array
    {
        $names = [];

        foreach ($this->parameters->args as $arg) {
            $names[$arg->paramName] = $arg->name;
        }

        foreach ($this->parameters->modelBindings as $binding) {
            $names[$binding->paramName] = $binding->argName;
        }

        return $names;
    }
}
