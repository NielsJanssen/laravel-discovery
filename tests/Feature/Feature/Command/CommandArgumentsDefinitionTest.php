<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Feature\Command\CommandArgumentsDefinition;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Command\ArgumentCommand;
use Tests\Fixtures\Command\ArrayArgumentCommand;
use Tests\Fixtures\Command\NamedArgumentCommand;
use Tests\Fixtures\Command\NamedOptionCommand;
use Tests\Fixtures\Command\OptionCommand;

it('creates a required InputArgument for a typed parameter with no default', function () {
    $method = new ClassReflector(ArgumentCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasArgument('name'))->toBeTrue();
    expect($definition->getArgument('name')->isRequired())->toBeTrue();
    expect($definition->getArgument('name'))->toBeInstanceOf(InputArgument::class);
});

it('creates an optional InputArgument for a parameter with a default value', function () {
    $method = new ClassReflector(ArgumentCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasArgument('count'))->toBeTrue();
    expect($definition->getArgument('count')->isRequired())->toBeFalse();
    expect($definition->getArgument('count')->getDefault())->toBe(1);
});

it('creates an IS_ARRAY InputArgument for an array-typed parameter', function () {
    $method = new ClassReflector(ArrayArgumentCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasArgument('items'))->toBeTrue();

    $argument = $definition->getArgument('items');
    expect($argument->isArray())->toBeTrue();
});

it('creates a VALUE_NONE InputOption for a bool parameter with #[ConsoleOption]', function () {
    $method = new ClassReflector(OptionCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasOption('verbose'))->toBeTrue();

    $option = $definition->getOption('verbose');
    expect($option)->toBeInstanceOf(InputOption::class);
    expect($option->acceptValue())->toBeFalse(); // VALUE_NONE means acceptValue() is false
});

it('creates an InputOption with a shortcut from ConsoleOption::shortcut', function () {
    $method = new ClassReflector(OptionCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasOption('user'))->toBeTrue();
    expect($definition->getOption('user')->getShortcut())->toBe('u');
});

it('uses the name from #[ConsoleArgument] over the parameter name', function () {
    $method = new ClassReflector(NamedArgumentCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasArgument('custom-name'))->toBeTrue();
    expect($definition->hasArgument('original-name'))->toBeFalse();
});

it('uses the name from #[ConsoleOption] over the parameter name', function () {
    $method = new ClassReflector(NamedOptionCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $definition = new InputDefinition();
    $argsDef->define($definition);

    expect($definition->hasOption('custom-opt'))->toBeTrue();
    expect($definition->hasOption('original-name'))->toBeFalse();
});

it('resolves input values to an array keyed by the argument name', function () {
    $method = new ClassReflector(ArgumentCommand::class)->getMethod('run');
    $argsDef = CommandArgumentsDefinition::from($method);

    $inputDefinition = new InputDefinition();
    $argsDef->define($inputDefinition);

    $input = new ArrayInput(['name' => 'Alice', 'count' => 5], $inputDefinition);

    $resolved = $argsDef->resolveInput($input);

    expect($resolved)->toEqualCanonicalizing([
        'count' => 5,
        'name' => 'Alice',
    ]);
});
