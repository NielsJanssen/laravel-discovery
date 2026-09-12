<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Support\Facades\Gate;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ComposedFromArgs;
use Rebing\GraphQL\Error\AuthorizationError;

final readonly class Authorization implements ComposedFromArgs
{
    /**
     * @param array{} $args
     */
    public static function fromArgs(array $args): static
    {
        return new static();
    }

    /**
     * @param string|iterable<string|\UnitEnum>|\UnitEnum $abilities
     * @param mixed $arguments
     * @param string|null $message
     *
     * @throws AuthorizationError
     * @return void
     */
    public function authorize(string|iterable|\UnitEnum $abilities, mixed $arguments = [], ?string $message = null): void
    {
        if (Gate::denies($abilities, $arguments)) {
            throw new AuthorizationError($message ?? DiscoveredModelAuthorization::DEFAULT_MESSAGE);
        }
    }
}
