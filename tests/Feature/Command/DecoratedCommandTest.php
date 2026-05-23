<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Command as LaravelCommand;
use NielsJanssen\Laravel\Discovery\Command\Command;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\DiscoveredCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Command\ArgumentCommand;
use Tests\Fixtures\Command\CapturingCommand;
use Tests\Fixtures\Command\InvokableCommand;
use Tests\Fixtures\Command\MiddlewareCommand;
use Tests\Fixtures\Command\MiddlewareLog;
use Tests\Fixtures\Command\MultiMiddlewareCommand;
use Tests\Fixtures\Command\OptionCommand;
use Tests\Fixtures\Command\OptionMiddlewareCommand;
use Tests\Fixtures\Command\RecordingMiddleware;
use Tests\Fixtures\Command\ShortCircuitCommand;

function decorateMethod(string $class, string $method): LaravelCommand
{
    $reflector  = new ClassReflector($class)->getMethod($method);
    $definition = $reflector->getAttribute(ConsoleCommand::class);
    $commandDef = new DiscoveredCommand($reflector, $definition);

    return new Command(app(), $commandDef);
}

/**
 * Run a decorated command via Symfony's run() method, so that $this->input and
 * $this->output are initialised before __invoke() is called.
 */
function runDecoratedCommand(LaravelCommand $command, array $input = []): int
{
    $command->setLaravel(app());

    return $command->run(new ArrayInput($input), new NullOutput());
}

it('sets the command name from the ConsoleCommand attribute', function () {
    $command = decorateMethod(InvokableCommand::class, '__invoke');

    expect($command->getName())->toBe('fixture:invokable');
});

it('sets the command description', function () {
    $command = decorateMethod(InvokableCommand::class, '__invoke');

    expect($command->getDescription())->toBe('Desc');
});

it('sets command aliases', function () {
    $command = decorateMethod(InvokableCommand::class, '__invoke');

    expect($command->getAliases())->toContain('fixture:inv');
});

it('adds method parameters as input arguments', function () {
    $command = decorateMethod(ArgumentCommand::class, 'run');
    $definition = $command->getDefinition();

    expect($definition->hasArgument('name'))->toBeTrue();
    expect($definition->getArgument('name')->isRequired())->toBeTrue();

    expect($definition->hasArgument('count'))->toBeTrue();
    expect($definition->getArgument('count')->isRequired())->toBeFalse();
});

it('adds ConsoleOption parameters as input options', function () {
    $command = decorateMethod(OptionCommand::class, 'run');
    $definition = $command->getDefinition();

    expect($definition->hasOption('verbose'))->toBeTrue();

    $verboseOpt = $definition->getOption('verbose');
    expect($verboseOpt->acceptValue())->toBeFalse(); // VALUE_NONE

    expect($definition->hasOption('user'))->toBeTrue();
    expect($definition->getOption('user')->getShortcut())->toBe('u');
});

it('adds middleware-provided options to the input definition', function () {
    $command = decorateMethod(OptionMiddlewareCommand::class, 'run');
    $definition = $command->getDefinition();

    expect($definition->hasOption('tag'))->toBeTrue();
});

it('passes the DecoratedCommand instance (not the inner class) to middleware', function () {
    RecordingMiddleware::$calls = [];

    $command = decorateMethod(MiddlewareCommand::class, 'run');
    runDecoratedCommand($command);

    expect(RecordingMiddleware::$calls)->toHaveCount(1);
    expect(RecordingMiddleware::$calls[0]['command'])->toBeInstanceOf(LaravelCommand::class);
});

it('calls middleware in the correct order (outermost first)', function () {
    MiddlewareLog::$entries = [];

    $command = decorateMethod(MultiMiddlewareCommand::class, 'run');
    runDecoratedCommand($command);

    expect(MiddlewareLog::$entries)->toBe(['outer:before', 'inner:before', 'inner:after', 'outer:after']);
});

it('short-circuits when middleware does not call $next', function () {
    $command = decorateMethod(ShortCircuitCommand::class, 'run');

    $command->setLaravel(app());

    // The return value from __invoke is used as the exit code by LaravelCommand::execute().
    // ShortCircuitMiddleware returns 42, which becomes the command exit code.
    $exitCode = $command->run(new ArrayInput([]), new NullOutput());

    expect($exitCode)->toBe(42);
});

it('injects argument values into the command method', function () {
    CapturingCommand::$capturedName = '';

    $command = decorateMethod(CapturingCommand::class, 'run');
    runDecoratedCommand($command, ['name' => 'Alice']);

    expect(CapturingCommand::$capturedName)->toBe('Alice');
});

it('auto-discovers and registers workbench commands via the service provider', function () {
    $this->artisan('app:invokable')->assertSuccessful();
});
