<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use Closure;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Rebing\GraphQL\Support\ExecutionMiddleware\AbstractExecutionMiddleware;
use Rebing\GraphQL\Support\OperationParams;

/** Gives every GraphQL execution its own Loaders; registered as a singleton that holds the current one. */
final class LoadersExecutionMiddleware extends AbstractExecutionMiddleware
{
    private ?Loaders $current = null;

    public function __construct(private readonly Container $container) {}

    public function handle(string $schemaName, Schema $schema, OperationParams $params, $rootValue, $contextValue, Closure $next): ExecutionResult
    {
        $previous = $this->current;
        $this->current = new Loaders($this->container);

        try {
            $result = $next($schemaName, $schema, $params, $rootValue, $contextValue);

            return $result instanceof ExecutionResult ? $result : throw new LogicException('The GraphQL execution pipeline did not return an ExecutionResult.');
        } finally {
            $this->current = $previous;
        }
    }

    public function current(): Loaders
    {
        return $this->current ?? throw new LogicException(sprintf(
            'No GraphQL execution is running, so there are no Loaders to batch with. Batched fields resolve inside an execution that runs %s; add it to the execution_middleware of a schema that sets its own list.',
            self::class,
        ));
    }
}
