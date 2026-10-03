<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Config\Repository;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\SkippedMembers;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use Tempest\Reflection\PropertyReflector;
use Tests\Fixtures\RebingGraphQL\Inputs\QueuedReport;
use Tests\Fixtures\RebingGraphQL\Skipped;

/** Set the skipped namespaces and rebuild the discoverer that reads them. */
function skipNamespaces(mixed $namespaces): void
{
    config()->set(SkippedMembers::CONFIG, $namespaces);
    app()->forgetInstance(GraphQLDiscovery::class);
}

/**
 * @return list<string> the field names of the one type of that kind the class yields
 */
function fieldNamesOf(string $class, TypeKind $kind): array
{
    [$type] = discoveredTypesOf($kind, $class);

    return array_map(static fn($field) => $field->name, $type->fields);
}

const THIRD_PARTY_NAMESPACE = 'Tests\\Fixtures\\RebingGraphQL\\ThirdParty\\Acme';

it('skips only Laravel by default', function () {
    expect(config(SkippedMembers::CONFIG))->toBe(['Illuminate\\'])
        ->and(fieldNamesOf(QueuedReport::class, TypeKind::Input))->toBe(['title'])
        ->and(fieldNamesOf(Skipped\Article::class, TypeKind::Object))->toEqualCanonicalizing(['vendorId', 'title', 'vendorLabel', 'auditedBy', 'vendorStatus']);
});

it('leaves out the public, hooked and #[Field] members of a configured namespace, and a property redeclared from it', function () {
    skipNamespaces(['Illuminate\\', THIRD_PARTY_NAMESPACE]);

    expect(fieldNamesOf(Skipped\Article::class, TypeKind::Object))->toBe(['title'])
        ->and(fieldNamesOf(Skipped\ArticleDraft::class, TypeKind::Input))->toBe(['title'])
        ->and(fieldNamesOf(QueuedReport::class, TypeKind::Input))->toBe(['title']);
});

it('reads a namespace prefix with or without a leading or trailing backslash', function (string $namespace) {
    $config = new Repository([SkippedMembers::CONFIG => [$namespace]]);

    $skipped = new SkippedMembers($config);

    expect($skipped->skips(PropertyReflector::fromParts(Skipped\Article::class, 'vendorId')))->toBeTrue()
        ->and($skipped->skips(PropertyReflector::fromParts(Skipped\Article::class, 'title')))->toBeFalse();
})->with([
    'without a trailing backslash' => [THIRD_PARTY_NAMESPACE],
    'with a trailing backslash' => [THIRD_PARTY_NAMESPACE . '\\'],
    'with a leading backslash' => ['\\' . THIRD_PARTY_NAMESPACE],
]);

it('stops skipping Laravel when the list leaves it out', function () {
    skipNamespaces([THIRD_PARTY_NAMESPACE]);

    expect(fn() => fieldNamesOf(QueuedReport::class, TypeKind::Input))
        ->toThrow(LogicException::class, 'declares no type');
});

it('rejects an entry that is not a namespace prefix', function (mixed $entry, string $shown) {
    skipNamespaces(['Illuminate\\', $entry]);

    expect(fn() => discoverGraphQL(Skipped\Article::class))->toThrow(
        LogicException::class,
        "Config discovery.graphql.skip_namespaces lists namespace prefixes, as in ['Illuminate\\\\', 'Acme\\\\'], got [$shown].",
    );
})->with([
    'an empty string' => ['', "''"],
    'a lone backslash' => ['\\', "'\\'"],
    'a number' => [42, 'int'],
]);
