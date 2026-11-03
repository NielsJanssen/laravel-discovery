<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use LogicException;
use NielsJanssen\Laravel\Discovery\Command\CommandArgumentsDefinition;
use NielsJanssen\Laravel\Discovery\Command\CommandDiscovery;
use NielsJanssen\Laravel\Discovery\Command\ConsoleArgument;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\ConsoleOption;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputDefinition;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Command\Priority;
use Tests\Fixtures\Command\Status;
use Tests\Fixtures\Command\Suit;
use Tests\Fixtures\Command\TypedInputCommand;

/**
 * @param array<string, mixed> $parameters
 * @return array<string, mixed>
 */
function runTypedCommand(string $command, array $parameters = []): array
{
    $discovery = app(CommandDiscovery::class);
    $discovery->setItems(new DiscoveryItems());
    $discovery->discover(
        new DiscoveryLocation('Tests\\Fixtures\\Command', dirname(__DIR__, 2) . '/Fixtures/Command'),
        new ClassReflector(TypedInputCommand::class),
    );
    $discovery->apply();

    TypedInputCommand::$captured = [];

    Artisan::call($command, $parameters);

    return TypedInputCommand::$captured;
}

function typedDefinition(string $method): InputDefinition
{
    $definition = new InputDefinition();

    CommandArgumentsDefinition::from(new ClassReflector(TypedInputCommand::class)->getMethod($method))
        ->define($definition);

    return $definition;
}

describe('enums', function () {
    it('converts arguments and options to their backed enum case', function () {
        expect(runTypedCommand('fixture:typed-enums', [
            'status' => 'published',
            'priority' => '2',
            '--state' => 'published',
            '--level' => '1',
        ]))->toBe([
            'status' => Status::Published,
            'priority' => Priority::High,
            'state' => Status::Published,
            'level' => Priority::Low,
        ]);
    });

    it('falls back to the parameter default or null when no value is given', function () {
        expect(runTypedCommand('fixture:typed-enums', ['status' => 'draft']))->toBe([
            'status' => Status::Draft,
            'priority' => null,
            'state' => Status::Draft,
            'level' => null,
        ]);
    });

    it('rejects a value that is not a case, listing the cases', function (array $parameters, string $exception, string $message) {
        expect(fn() => runTypedCommand('fixture:typed-enums', ['status' => 'draft', ...$parameters]))
            ->toThrow($exception, $message);
    })->with([
        'string-backed argument' => [['status' => 'archived'], InvalidArgumentException::class, 'The "status" argument does not accept "archived"; expected one of: draft, published.'],
        'int-backed argument' => [['priority' => '9'], InvalidArgumentException::class, 'The "priority" argument does not accept "9"; expected one of: 1, 2.'],
        'non-numeric for an int-backed enum' => [['priority' => 'high'], InvalidArgumentException::class, 'The "priority" argument does not accept "high"; expected one of: 1, 2.'],
        'option' => [['--state' => 'archived'], InvalidOptionException::class, 'The "--state" option does not accept "archived"; expected one of: draft, published.'],
        'nullable option' => [['--level' => 'max'], InvalidOptionException::class, 'The "--level" option does not accept "max"; expected one of: 1, 2.'],
    ]);

    it('requires a value for a non-nullable option without a default', function () {
        expect(fn() => runTypedCommand('fixture:typed-required-option'))
            ->toThrow(InvalidOptionException::class, 'The "--status" option requires a value.');
    });

    it('suggests the cases and shows the default case value', function () {
        $definition = typedDefinition('enums');

        $suggestions = new CompletionSuggestions();
        $definition->getArgument('priority')->complete(new CompletionInput(), $suggestions);

        expect(array_map(fn($suggestion) => $suggestion->getValue(), $suggestions->getValueSuggestions()))->toBe(['1', '2'])
            ->and($definition->getArgument('status')->hasCompletion())->toBeTrue()
            ->and($definition->getOption('state')->getDefault())->toBe('draft')
            ->and($definition->getOption('state')->isValueOptional())->toBeTrue()
            ->and($definition->getOption('level')->getDefault())->toBeNull();
    });

    it('rejects an enum without backing values', function () {
        $shape = new class {
            #[ConsoleCommand('fixture:unit-enum')]
            public function run(Suit $suit): void {}
        };

        expect(fn() => CommandArgumentsDefinition::from(new ClassReflector($shape)->getMethod('run')))
            ->toThrow(LogicException::class, 'is typed as the enum ' . Suit::class . ', which has no backing values to read from the console.');
    });
});

describe('dates', function () {
    it('parses a date into the type each parameter asks for', function () {
        $captured = runTypedCommand('fixture:typed-dates', [
            'at' => '2026-03-01 10:00',
            '--carbon' => '2026-03-02',
            '--immutable' => '2026-03-03',
            '--interface' => '2026-03-04',
            '--mutable' => '2026-03-05',
            '--native' => '2026-03-06',
            '--day' => '07-03-2026',
        ]);

        expect($captured['at'])->toBeInstanceOf(Carbon::class)
            ->and($captured['at']->format('Y-m-d H:i'))->toBe('2026-03-01 10:00')
            ->and($captured['carbon'])->toBeInstanceOf(Carbon::class)
            ->and($captured['immutable'])->toBeInstanceOf(CarbonImmutable::class)
            ->and($captured['immutable']->toDateString())->toBe('2026-03-03')
            ->and($captured['interface'])->toBeInstanceOf(Carbon::class)
            ->and($captured['mutable'])->toBeInstanceOf(DateTime::class)
            ->and($captured['native'])->toBeInstanceOf(DateTimeImmutable::class)
            ->and($captured['native']->format('Y-m-d'))->toBe('2026-03-06')
            ->and($captured['day'])->toBeInstanceOf(CarbonImmutable::class)
            ->and($captured['day']->toDateString())->toBe('2026-03-07');
    });

    it('falls back to the parameter default or null when no value is given', function () {
        $captured = runTypedCommand('fixture:typed-dates', ['at' => '2026-03-01']);

        expect($captured['carbon'])->toBeNull()
            ->and($captured['day'])->toBeNull()
            ->and($captured['since'])->toBeInstanceOf(CarbonImmutable::class)
            ->and($captured['since']->toDateString())->toBe('2020-01-01');
    });

    it('converts to a mutable date when the Date factory hands out immutable ones', function () {
        Date::use(CarbonImmutable::class);

        try {
            $captured = runTypedCommand('fixture:typed-dates', ['at' => '2026-03-01', '--carbon' => '2026-03-02']);
        } finally {
            Date::useDefault();
        }

        expect($captured['at'])->toBeInstanceOf(CarbonImmutable::class)
            ->and($captured['carbon'])->toBeInstanceOf(Carbon::class)
            ->and($captured['carbon']->toDateString())->toBe('2026-03-02');
    });

    it('reads an argument in the format it names', function () {
        $captured = runTypedCommand('fixture:typed-formatted', ['on' => '2026-03-08']);

        expect($captured['on'])->toBeInstanceOf(DateTimeImmutable::class)
            ->and($captured['on']->format('Y-m-d'))->toBe('2026-03-08');
    });

    it('rejects a value that is not a date', function (string $command, array $parameters, string $exception, string $message) {
        expect(fn() => runTypedCommand($command, $parameters))->toThrow($exception, $message);
    })->with([
        'unparsable argument' => ['fixture:typed-dates', ['at' => 'someday'], InvalidArgumentException::class, 'The "at" argument does not accept "someday"; expected a date.'],
        'option in the wrong format' => ['fixture:typed-dates', ['at' => '2026-03-01', '--day' => '2026-03-07'], InvalidOptionException::class, 'The "--day" option does not accept "2026-03-07"; expected a date in the format "d-m-Y".'],
        'argument in the wrong format' => ['fixture:typed-formatted', ['on' => '08-03-2026'], InvalidArgumentException::class, 'The "on" argument does not accept "08-03-2026"; expected a date in the format "Y-m-d".'],
    ]);

    it('rejects a format on a parameter that is not a date', function (object $shape) {
        expect(fn() => CommandArgumentsDefinition::from(new ClassReflector($shape)->getMethod('run')))
            ->toThrow(LogicException::class, 'only applies to a date.');
    })->with([
        'argument' => [fn() => new class {
            #[ConsoleCommand('fixture:formatted-string')]
            public function run(#[ConsoleArgument(format: 'Y-m-d')] string $on): void {}
        }],
        'option' => [fn() => new class {
            #[ConsoleCommand('fixture:formatted-enum')]
            public function run(#[ConsoleOption(format: 'Y-m-d')] ?Status $status = null): void {}
        }],
    ]);
});

it('still injects a class that is not a value type from the container', function () {
    $captured = runTypedCommand('fixture:typed-injected', ['status' => 'draft']);

    expect($captured['status'])->toBe(Status::Draft)
        ->and($captured['collection'])->toBeInstanceOf(Collection::class)
        ->and(typedDefinition('injected')->hasArgument('collection'))->toBeFalse();
});
