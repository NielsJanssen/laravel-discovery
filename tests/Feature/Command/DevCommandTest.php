<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\DevCommand as LaravelDevCommand;
use Illuminate\Foundation\DevCommandColor;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Artisan;
use NielsJanssen\Laravel\Discovery\Command\CommandDiscovery;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;
use NielsJanssen\Laravel\Discovery\Command\DiscoveredDevCommand;
use NielsJanssen\Laravel\Discovery\Command\DiscoveredDevCommands;
use NielsJanssen\Laravel\Discovery\Command\Exception\InvalidCommandRegistrationException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Command\DevAsCommand;
use Tests\Fixtures\Command\DevMethodCommands;
use Tests\Fixtures\Command\DevNamedCommand;
use Tests\Fixtures\Command\DevSignatureAttributeCommand;
use Tests\Fixtures\Command\DevSignatureCommand;
use Tests\Fixtures\Command\Invalid\DevUnnamedCommand;

/** Discover the given classes into a fresh item set and apply them. */
function discoverDevCommands(string|object ...$classes): CommandDiscovery
{
    $discovery = app(CommandDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Command',
        path: dirname(__DIR__, 2) . '/Fixtures/Command',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return $discovery;
}

/** @return array<string, array<string, mixed>> the registered dev commands keyed by name */
function devCommands(): array
{
    return array_column(DevCommands::commands(), null, 'name');
}

function devCommand(string $command, int $priority, string $name = 'acme', string $file = '/app/Acme.php'): LaravelDevCommand
{
    return new LaravelDevCommand($command, ['file' => $file, 'line' => 1], $name, $priority);
}

beforeEach(fn() => DiscoveredDevCommands::reset());
afterEach(fn() => DiscoveredDevCommands::reset());

describe('Laravel internals this relies on', function () {
    it('keeps the registry in the protected static DevCommands::$commands', function () {
        $property = new ReflectionProperty(DevCommands::class, 'commands');

        expect($property->isStatic())->toBeTrue('DevCommands::$commands is no longer static; DiscoveredDevCommands::add() writes to it.')
            ->and($property->isProtected())->toBeTrue('DevCommands::$commands is no longer protected; DiscoveredDevCommands::add() writes to it.')
            ->and((new ReflectionClass(DiscoveredDevCommands::class))->getProperty('commands')->getDeclaringClass()->getName())
            ->toBe(DevCommands::class);
    });

    it('constructs DevCommand from command, source, name and priority', function () {
        $parameters = array_map(
            static fn($parameter) => [$parameter->getName(), (string) $parameter->getType()],
            (new ReflectionMethod(LaravelDevCommand::class, '__construct'))->getParameters(),
        );

        expect($parameters)->toBe([
            ['command', 'string'],
            ['source', 'array'],
            ['name', '?string'],
            ['priority', 'int'],
        ], 'The constructor of ' . LaravelDevCommand::class . ' changed; DiscoveredDevCommand::toLaravel() builds it by hand.');
    });

    it('lists a command added through the subclass', function () {
        DiscoveredDevCommands::add(devCommand('php artisan acme:watch', LaravelDevCommand::PRIORITY_USERLAND));

        expect(devCommands())->toHaveKey('acme')
            ->and(devCommands()['acme']['command'])->toBe('php artisan acme:watch');
    });
});

describe('priority', function () {
    it('gives a file under the vendor path the vendor priority', function (string $file) {
        expect(DiscoveredDevCommands::priority($file, '/srv/app/vendor'))->toBe(LaravelDevCommand::PRIORITY_VENDOR);
    })->with([
        'a package' => '/srv/app/vendor/acme/tools/src/Commands.php',
        'a nested vendor directory' => '/srv/app/vendor/acme/tools/vendor/acme/inner/src/Commands.php',
        'a sibling starting with the vendor path, as Laravel matches it' => '/srv/app/vendors/Commands.php',
    ]);

    it('gives any other file the userland priority', function (string $file) {
        expect(DiscoveredDevCommands::priority($file, '/srv/app/vendor'))->toBe(LaravelDevCommand::PRIORITY_USERLAND);
    })->with([
        'the app' => '/srv/app/app/Console/Commands.php',
        'a path repository' => '/srv/packages/acme/src/Commands.php',
    ]);

    it('registers a discovered app command as userland', function () {
        discoverDevCommands(DevMethodCommands::class);

        expect(devCommands()['acme:watch']['priority'])->toBe(LaravelDevCommand::PRIORITY_USERLAND);
    });

    it('keeps a userland command when vendor commands are left out', function () {
        discoverDevCommands(DevMethodCommands::class);
        DiscoveredDevCommands::add((new DiscoveredDevCommand('acme:vendor', 'vendor', null, '/srv/app/vendor/acme/tools/src/Commands.php', 12, 'Acme\\Commands', 'run'))->toLaravel('/srv/app/vendor'));

        expect(devCommands())->toHaveKeys(['acme:watch', 'vendor']);

        DevCommands::withoutVendorCommands();

        expect(devCommands())->toHaveKey('acme:watch')
            ->not->toHaveKey('vendor');
    });
});

describe('registration', function () {
    it('registers only the commands that carry #[DevCommand]', function () {
        discoverDevCommands(DevMethodCommands::class);

        expect(array_keys(devCommands()))->toEqualCanonicalizing(['acme:watch', 'sync', 'acme:listen']);
    });

    it('names a command after its artisan command, without arguments', function () {
        discoverDevCommands(DevMethodCommands::class);

        expect(devCommands()['acme:watch']['command'])->toBe('php artisan acme:watch')
            ->and(devCommands()['acme:listen']['command'])->toBe('php artisan acme:listen --queue=default');
    });

    it('takes the name, arguments and color from the attribute', function () {
        discoverDevCommands(DevMethodCommands::class);

        expect(devCommands()['sync'])
            ->command->toBe('php artisan acme:sync --tries=1 --timeout=0')
            ->color->toBe(DevCommandColor::GREEN->value);
    });

    it('points the source at the discovered method', function () {
        discoverDevCommands(DevMethodCommands::class);

        expect(devCommands()['acme:watch']['source'])->toBe([
            'file' => (string) (new ReflectionMethod(DevMethodCommands::class, 'watch'))->getFileName(),
            'line' => (new ReflectionMethod(DevMethodCommands::class, 'watch'))->getStartLine(),
            'class' => DevMethodCommands::class,
            'function' => 'watch',
        ]);
    });

    it('reads the artisan command of a Laravel command class', function (string $class, string $name, string $command, string $function) {
        discoverDevCommands($class);

        expect(devCommands()[$name])
            ->command->toBe($command)
            ->source->toBe([
                'file' => (string) (new ReflectionClass($class))->getFileName(),
                'line' => (new ReflectionClass($class))->getStartLine(),
                'class' => $class,
                'function' => $function,
            ]);
    })->with([
        'a signature' => [DevSignatureCommand::class, 'acme:reverb', 'php artisan acme:reverb --fresh', 'handle'],
        'a name' => [DevNamedCommand::class, 'acme:named', 'php artisan acme:named', '__invoke'],
        'a hidden #[AsCommand] with an alias' => [DevAsCommand::class, 'acme:hidden', 'php artisan acme:hidden', 'handle'],
        'a #[Signature] over the property' => [DevSignatureAttributeCommand::class, 'acme:attribute', 'php artisan acme:attribute', 'handle'],
    ]);

    it('names the command the same way as DevCommands::artisan()', function () {
        discoverDevCommands(DevMethodCommands::class);
        $ours = devCommands()['acme:listen'];

        DiscoveredDevCommands::reset();
        DevCommands::artisan('acme:listen --queue=default');

        expect(devCommands()['acme:listen'])
            ->name->toBe($ours['name'])
            ->command->toBe($ours['command']);
    });

    it('still registers the commands with Artisan', function () {
        discoverDevCommands(DevMethodCommands::class);

        expect(Artisan::all())->toHaveKey('acme:watch');
    });

    it('registers nothing outside the console', function () {
        $app = app();
        $setConsoleFlag = function (?bool $value) use ($app): void {
            (fn() => $this->isRunningInConsole = $value)->call($app);
        };

        $setConsoleFlag(false);

        try {
            discoverDevCommands(DevMethodCommands::class);
        } finally {
            $setConsoleFlag(null);
        }

        expect(devCommands())->toBeEmpty();
    });

    it('registers the same commands from the discovery cache', function () {
        $items = unserialize(serialize(discoverDevCommands(DevMethodCommands::class, DevSignatureCommand::class)->getItems()));
        $registered = devCommands();
        DiscoveredDevCommands::reset();

        expect($items)->toBeInstanceOf(DiscoveryItems::class);

        $discovery = app(CommandDiscovery::class);
        $discovery->setItems($items);
        $discovery->apply();

        expect(devCommands())->toEqual($registered)
            ->toHaveKeys(['acme:watch', 'sync', 'acme:listen', 'acme:reverb']);
    });
});

describe('replacing a registered command', function () {
    it('replaces a framework default', function () {
        DiscoveredDevCommands::add(devCommand('php artisan serve', LaravelDevCommand::PRIORITY_DEFAULT, 'acme:watch'));

        discoverDevCommands(DevMethodCommands::class);

        expect(devCommands()['acme:watch']['command'])->toBe('php artisan acme:watch');
    });

    it('lets a vendor command not replace a userland one', function () {
        DiscoveredDevCommands::add(devCommand('php artisan acme:app', LaravelDevCommand::PRIORITY_USERLAND));
        DiscoveredDevCommands::add(devCommand('php artisan acme:vendor', LaravelDevCommand::PRIORITY_VENDOR));

        expect(devCommands()['acme']['command'])->toBe('php artisan acme:app');
    });

    it('lets an equal priority replace the earlier command', function () {
        DiscoveredDevCommands::add(devCommand('php artisan acme:first', LaravelDevCommand::PRIORITY_VENDOR));
        DiscoveredDevCommands::add(devCommand('php artisan acme:second', LaravelDevCommand::PRIORITY_VENDOR));

        expect(devCommands()['acme']['command'])->toBe('php artisan acme:second');
    });

    it('gives way to the app registering the same name afterwards', function () {
        discoverDevCommands(DevMethodCommands::class);

        DevCommands::artisan('acme:watch --verbose');

        expect(devCommands()['acme:watch'])
            ->command->toBe('php artisan acme:watch --verbose')
            ->priority->toBe(LaravelDevCommand::PRIORITY_USERLAND);
    });
});

describe('rejected shapes', function () {
    it('rejects them at discovery', function (string|object $shape, string $message) {
        $class = is_string($shape) ? $shape : $shape::class;

        expect(fn() => discoverDevCommands($shape))
            ->toThrow(InvalidCommandRegistrationException::class, sprintf($message, $class));
    })->with([
        'a method without #[ConsoleCommand]' => [
            fn() => new class {
                #[DevCommand]
                public function watch(): void {}
            },
            'Failed to register command "%s::watch": #[DevCommand] needs #[ConsoleCommand] on the same method.',
        ],
        'a class that is no Laravel command' => [
            fn() => new #[DevCommand] class {},
            'Failed to register command "%s": #[DevCommand] on a class needs a class extending',
        ],
        'a Laravel command without a name' => [
            DevUnnamedCommand::class,
            'Failed to register command "%s": #[DevCommand] needs the command to declare a name or signature.',
        ],
    ]);
});
