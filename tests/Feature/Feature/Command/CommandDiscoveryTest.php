<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Command\CommandDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tests\Fixtures\Command\AbstractCommand;
use Tests\Fixtures\Command\LaravelStyleCommand;
use Tests\Fixtures\Command\MethodCommand;

/**
 * Reset the discovery items queue AND the accumulated $commands array so that
 * workbench-discovered commands (registered during the service-provider boot)
 * don't bleed into fixture-scoped assertions.
 *
 * NOTE: $commands is declared `public private(set)`, so we must use reflection
 * to reset it from outside the class.
 */
function discoverCommands(string ...$classes): CommandDiscovery
{
    $discovery = app(CommandDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Command',
        path: dirname(__DIR__, 3) . '/Fixtures/Command',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return $discovery;
}

it('discovers a method annotated with #[ConsoleCommand]', function () {
    $discovery = discoverCommands(MethodCommand::class);
    $commands = iterator_to_array($discovery->getItems());

    expect($commands)->toHaveCount(1);

    expect($commands[0]->reflector)->toBeInstanceOf(MethodReflector::class);
});

it('discovers a class extending LaravelCommand', function () {
    $discovery = discoverCommands(LaravelStyleCommand::class);
    $commands = iterator_to_array($discovery->getItems());

    expect($commands)->toHaveCount(1);
    expect($commands[0]->definition)->toBeNull();
});

it('skips abstract classes', function () {
    $discovery = discoverCommands(AbstractCommand::class);
    $commands = iterator_to_array($discovery->getItems());

    $commandClasses = array_map(
        fn($cmd) => $cmd->reflector->getName(),
        $commands,
    );

    expect($commandClasses)->not->toContain(AbstractCommand::class);
    expect($commands)->toBeEmpty();
});
