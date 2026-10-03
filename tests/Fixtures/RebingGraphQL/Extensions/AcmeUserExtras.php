<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;
use Tests\Fixtures\RebingGraphQL\AlwaysDenyGate;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\Enums\Mood;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;

#[TypeExtension(AcmeUser::class)]
final class AcmeUserExtras implements TypeFactory
{
    /** @var list<string> the members whose method ran */
    public static array $resolved = [];

    /** @var list<TypeContext> the context of every fields() call */
    public static array $contexts = [];

    public string $label = 'not a field';

    public function __construct(private readonly ContainerService $service) {}

    /**
     * @return list<string>
     */
    #[Field(of: 'String')]
    public function invoices(#[Root] AcmeUser $user, int $limit = 10): array
    {
        return array_map(fn(int $number): string => "{$user->name}-$number{$this->service->suffix()}", range(1, $limit));
    }

    #[Field, Authorize('viewSecrets')]
    public function secret(#[Root] AcmeUser $user): string
    {
        self::$resolved[] = 'secret';

        return "{$user->name} keeps a secret";
    }

    #[Field, Authorize(gate: AlwaysDenyGate::class, onDenied: Denied::Error)]
    public function vault(): string
    {
        self::$resolved[] = 'vault';

        return 'gold';
    }

    #[Field]
    public function mood(): Mood
    {
        return Mood::Cheerful;
    }

    public function fields(TypeContext $context): iterable
    {
        self::$contexts[] = $context;

        yield new Field(name: 'rank', type: 'int', resolve: static fn(AcmeUser $user): int => strlen($user->name));
    }
}
