<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\ContainerInjectionQuery;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\NonScalarQuery;

function discoverInjectionFixture(string ...$classes): GraphQLDiscovery
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\GraphQL',
        path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    return $discovery;
}

describe('container injection of resolve parameters', function () {
    it('records class-typed unattributed parameters as container injections during discovery', function () {
        $items = iterator_to_array(discoverInjectionFixture(ContainerInjectionQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        expect($item->args)->toHaveCount(1)
            ->and($item->args[0]->paramName)->toBe('name')
            ->and($item->injections)->toBe([
                'root' => 'root',
                'context' => 'context',
                'info' => 'info',
            ])
            ->and($item->containerInjections)->toBe([
                'service' => ContainerService::class,
            ]);
    });

    it('resolves the container-injected parameter at resolve time alongside args, root, context and ResolveInfo', function () {
        $items = iterator_to_array(discoverInjectionFixture(ContainerInjectionQuery::class)->getItems());
        $field = $items[0]->createType(app());

        expect($field->resolve('root-value', ['name' => 'Niels'], 'context-value', null))
            ->toBe('Niels!');
    });

    it('still throws for unattributed non-class, non-scalar parameter types', function () {
        // NonScalarQuery declares an unattributed array $missingArg, which is neither a
        // scalar nor a resolvable class — the original "use #[Arg(type:)]" guard must fire.
        expect(fn() => discoverInjectionFixture(NonScalarQuery::class))
            ->toThrow(\RuntimeException::class, '#[Arg(type:');
    });
});
