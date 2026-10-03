<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;

#[TypeExtension('AcmeWarehouse')]
final class AcmeWarehouseExtras implements TypeFactory
{
    /** @var list<TypeContext> the context of every fields() call */
    public static array $contexts = [];

    /**
     * @param  array{name: string}  $warehouse
     */
    #[Field]
    public function manager(#[Root] array $warehouse, string $title = 'Ms'): string
    {
        return "$title Manager of {$warehouse['name']}";
    }

    public function fields(TypeContext $context): iterable
    {
        self::$contexts[] = $context;

        yield new Field(name: 'zone', type: 'string', resolve: static fn(array $warehouse): string => strtoupper($warehouse['name']));
    }
}
