<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Utils\SchemaPrinter;
use Illuminate\Support\Facades\DB;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use Rebing\GraphQL\Support\Facades\GraphQL;
use RuntimeException;
use stdClass;
use Tempest\Discovery\DiscoveryItems;
use Tests\Fixtures\RebingGraphQL\Naming as Fixtures;
use Workbench\App\Models\User;

const NAMING_SOURCES = [
    Fixtures\VolumeQueries::class,
    Fixtures\Volume::class,
    Fixtures\ShelveVolume::class,
    Fixtures\ShelfSpot::class,
];

const PRESERVED_SDL = <<<'GRAPHQL'
    type Volume {
      title: String!
      pageCount: Int!
      ISBN: String!
      shelfPlacement: Placement!
      coverUrl(maxWidth: Int = 100, fileFormat: String = "png"): String!
    }

    enum Placement {
      TopRow
      bottom_row
    }

    input ShelveVolumeInput {
      shelfLabel: String!
      rowNumber: Int
      binNo: String
      exactSpot: ShelfSpotInput
      vaultCode: String
    }

    input ShelfSpotInput {
      slotIndex: Int!
    }

    type Query {
      latestVolume: Volume!
      volumesByAuthor(authorName: String!, maxCount: Int): [Volume!]!
      volumeCount(onShelf: String): Int!
      ownerName(volumeOwner: ID!, coOwner: ID): String!
    }

    type Mutation {
      shelveVolume(shelveRequest: ShelveVolumeInput!): String!
    }

    GRAPHQL;

const SNAKE_SDL = <<<'GRAPHQL'
    type Volume {
      title: String!
      page_count: Int!
      ISBN: String!
      shelf_placement: Placement!
      cover_url(max_width: Int = 100, fileFormat: String = "png"): String!
    }

    enum Placement {
      TopRow
      bottom_row
    }

    input ShelveVolumeInput {
      shelf_label: String!
      row_number: Int
      binNo: String
      exact_spot: ShelfSpotInput
      vault_code: String
    }

    input ShelfSpotInput {
      slot_index: Int!
    }

    type Query {
      latest_volume: Volume!
      volumes_by_author(author_name: String!, max_count: Int): [Volume!]!
      volumeCount(onShelf: String): Int!
      owner_name(volume_owner: ID!, coOwner: ID): String!
    }

    type Mutation {
      shelve_volume(shelve_request: ShelveVolumeInput!): String!
    }

    GRAPHQL;

/**
 * @param  FieldCase|class-string<NamingStrategy>|string  $fields
 * @param  FieldCase|class-string<NamingStrategy>|string  $arguments
 * @param  FieldCase|class-string<NamingStrategy>|string  $operations
 */
function useNaming(FieldCase|string $fields = FieldCase::Preserve, FieldCase|string $arguments = FieldCase::Preserve, FieldCase|string $operations = FieldCase::Preserve): void
{
    namingConfig(['fields' => $fields, 'arguments' => $arguments, 'operations' => $operations]);
}

/** Sets the naming config and drops the Naming singleton that read the previous one. */
function namingConfig(mixed $naming): void
{
    config()->set(Naming::CONFIG, $naming);
    app()->forgetInstance(Naming::class);
}

/**
 * @return array<string, mixed>
 */
function namingRequest(string $query): array
{
    return test()->postJson('/graphql', ['query' => $query])->assertOk()->json();
}

beforeEach(function () {
    Fixtures\VolumeQueries::$received = null;
});

describe('the schema', function () {
    it('keeps every name by default', function () {
        expect(config(Naming::CONFIG))->toBe(['fields' => FieldCase::Preserve, 'arguments' => FieldCase::Preserve, 'operations' => FieldCase::Preserve])
            ->and(schemaSdl(...NAMING_SOURCES))->toBe(PRESERVED_SDL)
            ->and(discoveredActions(...NAMING_SOURCES)['latestVolume']->action->name)->toBeNull();

        buildAllSchemas();
    });

    it('names fields, args and operations in snake_case, leaving explicit names, type names and enum values alone', function () {
        useNaming(FieldCase::Snake, FieldCase::Snake, FieldCase::Snake);

        expect(schemaSdl(...NAMING_SOURCES))->toBe(SNAKE_SDL);

        buildAllSchemas();
    });

    it('accepts the value of a FieldCase', function () {
        useNaming('snake', 'snake', 'snake');

        expect(schemaSdl(...NAMING_SOURCES))->toBe(SNAKE_SDL);
    });

    it('names snake_case PHP members in camelCase', function () {
        useNaming(FieldCase::Camel, FieldCase::Camel, FieldCase::Camel);

        expect(schemaSdl(Fixtures\IssueQueries::class, Fixtures\Issue::class))->toBe(<<<'GRAPHQL'
            type Issue {
              issueNumber: Int!
              pageRange(firstPage: Int = 1): String!
            }

            type Query {
              latestIssue(pageSize: Int): Issue!
            }

            GRAPHQL);
    });

    it('applies each setting to its own names only', function (array $naming, array $expected, array $unexpected) {
        useNaming(...$naming);

        $sdl = schemaSdl(...NAMING_SOURCES);

        foreach ($expected as $line) {
            expect($sdl)->toContain($line);
        }

        foreach ($unexpected as $line) {
            expect($sdl)->not->toContain($line);
        }
    })->with([
        'fields' => [
            ['fields' => FieldCase::Snake],
            ['  page_count: Int!', '  shelf_label: String!', '  cover_url(maxWidth: Int = 100', '  latestVolume: Volume!', 'volumesByAuthor(authorName: String!'],
            ['max_width', 'latest_volume', 'author_name'],
        ],
        'arguments' => [
            ['arguments' => FieldCase::Snake],
            ['  coverUrl(max_width: Int = 100', 'volumesByAuthor(author_name: String!, max_count: Int)', 'ownerName(volume_owner: ID!, coOwner: ID)', 'shelveVolume(shelve_request: ShelveVolumeInput!)', '  pageCount: Int!', '  shelfLabel: String!'],
            ['page_count', 'shelf_label', 'latest_volume'],
        ],
        'operations' => [
            ['operations' => FieldCase::Snake],
            ['  latest_volume: Volume!', '  volumes_by_author(authorName: String!', '  shelve_volume(shelveRequest: ShelveVolumeInput!)', '  volumeCount(onShelf: String)', '  pageCount: Int!'],
            ['page_count', 'author_name', 'max_width'],
        ],
    ]);

    it('fills the settings a partial config leaves out with Preserve', function () {
        namingConfig(['operations' => FieldCase::Snake]);

        expect(schemaSdl(...NAMING_SOURCES))
            ->toContain('  latest_volume: Volume!')
            ->toContain('  pageCount: Int!')
            ->toContain('volumes_by_author(authorName: String!');
    });

    it('lets #[Type(naming:)] and #[Input(naming:)] override the configured strategies for one type', function () {
        useNaming(FieldCase::Snake, FieldCase::Snake, FieldCase::Snake);

        expect(sdlDefinitions(schemaSdl(Fixtures\OverrideQueries::class, Fixtures\PlainVolume::class, Fixtures\SnakeNote::class)))->toBe(sdlDefinitions(<<<'GRAPHQL'
            type PlainVolume {
              pageCount: String!
              coverUrl(maxWidth: Int = 100): String!
            }

            type SnakeNote {
              noteText_: String!
            }

            input SnakeNoteInput {
              note_text: String = ""
            }

            type Query {
              plain_volume: PlainVolume!
              find_plain_volume(search_term: String!): PlainVolume!
            }

            type Mutation {
              keep_note(note_input: SnakeNoteInput!): SnakeNote!
            }
            GRAPHQL));
    });

    it('names a #[Query] on a #[Type(naming:)] class by the configured settings, and its #[Field] args by the override', function () {
        useNaming(arguments: FieldCase::Snake);

        $actions = discoveredActions(Fixtures\PlainVolume::class);
        $type = array_values(array_filter(
            iterator_to_array(discoverGraphQL(Fixtures\PlainVolume::class)->getItems(), false),
            static fn(mixed $item): bool => $item instanceof DiscoveredType,
        ))[0];

        expect(array_column($actions['findPlainVolume']->args, 'name'))->toBe(['search_term'])
            ->and(array_column($type->fields[1]->parameters->args, 'name'))->toBe(['maxWidth']);
    });

    it('stores the decided names in the discovered items', function () {
        useNaming(FieldCase::Snake, FieldCase::Snake, FieldCase::Snake);

        $actions = discoveredActions(...NAMING_SOURCES);

        expect($actions['latestVolume']->action->name)->toBe('latest_volume')
            ->and($actions['countVolumes']->action->name)->toBe('volumeCount')
            ->and(array_column($actions['volumesByAuthor']->args, 'name', 'paramName'))->toBe(['authorName' => 'author_name', 'maxCount' => 'max_count'])
            ->and(array_column($actions['ownerName']->modelBindings, 'argName', 'paramName'))->toBe(['volumeOwner' => 'volume_owner', 'secondOwner' => 'coOwner'])
            ->and($actions['volumesByAuthor']->toArgPath('authorName'))->toBe('author_name')
            ->and($actions['ownerName']->toParameters(['volume_owner' => 1, 'coOwner' => 2]))->toBe(['volumeOwner' => 1, 'secondOwner' => 2]);
    });
});

describe('resolving under snake_case', function () {
    beforeEach(function () {
        useNaming(FieldCase::Snake, FieldCase::Snake, FieldCase::Snake);
        schemaSdl(...NAMING_SOURCES);
    });

    it('reads fields and passes field and action args by their PHP names', function () {
        expect(namingRequest('{ latest_volume { page_count ISBN shelf_placement cover_url(max_width: 200, fileFormat: "jpg") } volumes_by_author(author_name: "Ann", max_count: 2) { title } volumeCount(onShelf: "abc") }'))
            ->toBe(['data' => [
                'latest_volume' => ['page_count' => 412, 'ISBN' => '978-0441013593', 'shelf_placement' => 'TopRow', 'cover_url' => 'cover-200.jpg'],
                'volumes_by_author' => [['title' => 'Ann'], ['title' => 'Ann']],
                'volumeCount' => 3,
            ]]);
    });

    it('binds models through their renamed args', function () {
        $this->loadLaravelMigrations();
        $ann = User::factory()->create(['name' => 'Ann']);
        $bob = User::factory()->create(['name' => 'Bob']);

        expect(namingRequest("{ owner_name(volume_owner: {$ann->id}, coOwner: {$bob->id}) }"))->toBe(['data' => ['owner_name' => 'Ann & Bob']])
            ->and(namingRequest('{ owner_name(volume_owner: 999999) }')['errors'][0]['extensions']['validation'] ?? null)->toHaveKey('volume_owner');
    });

    it('aliases renamed input fields, so the input is hydrated by property name', function () {
        expect(namingRequest('mutation { shelve_volume(shelve_request: { shelf_label: "Top", row_number: 2, binNo: "B1", exact_spot: { slot_index: 3 } }) }'))
            ->toBe(['data' => ['shelve_volume' => 'Top']])
            ->and(Fixtures\VolumeQueries::$received)->toEqual(new Fixtures\ShelveVolume('Top', 2, 'B1', new Fixtures\ShelfSpot(3)));
    });

    it('reports validation errors and their messages under the GraphQL paths', function () {
        $response = namingRequest('mutation { shelve_volume(shelve_request: { shelf_label: "ab", row_number: 0, exact_spot: { slot_index: 12 } }) }');

        expect(Fixtures\VolumeQueries::$received)->toBeNull()
            ->and($response['errors'][0]['extensions']['validation'])->toBe([
                'shelve_request.shelf_label' => ['A shelf label needs three letters.'],
                'shelve_request.row_number' => ['The shelve request.row number field must be at least 1.'],
                'shelve_request.exact_spot.slot_index' => ['A shelf has nine slots.'],
            ]);
    });

    it('hands a #[Field(rules:)] closure the input values under their PHP property names', function () {
        expect(namingRequest('mutation { shelve_volume(shelve_request: { shelf_label: "Vault" }) }')['errors'][0]['extensions']['validation'])
            ->toBe(['shelve_request.vault_code' => ['The shelve request.vault code field is required.']])
            ->and(namingRequest('mutation { shelve_volume(shelve_request: { shelf_label: "Vault", vault_code: "V1" }) }'))->toBe(['data' => ['shelve_volume' => 'Vault']])
            ->and(Fixtures\VolumeQueries::$received?->vaultCode)->toBe('V1');
    });

    it('reports a validation attribute on an action parameter under the arg name', function () {
        expect(namingRequest('{ volumes_by_author(author_name: "A") { title } }')['errors'][0]['extensions']['validation'])
            ->toBe(['author_name' => ['Name at least three letters.']]);
    });
});

describe('#[Relation] under snake_case', function () {
    beforeEach(function () {
        $this->loadMigrationsFrom(dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL/Loaders/migrations');
        useNaming(FieldCase::Snake, FieldCase::Snake, FieldCase::Snake);
    });

    it('loads the relation the PHP method names, whatever the field is called', function () {
        $field = array_values(array_filter(
            iterator_to_array(discoverGraphQL(Fixtures\AuthorQueries::class, Fixtures\Author::class, Fixtures\Manuscript::class)->getItems(), false),
            static fn(mixed $item): bool => $item instanceof DiscoveredType && $item->class === Fixtures\Author::class,
        ))[0]->fields[1];

        expect($field->name)->toBe('written_novels')
            ->and($field->phpName)->toBe('writtenNovels')
            ->and(new Relation()->options($field->phpName))->toBe(['relation' => 'writtenNovels']);

        $ann = Fixtures\Author::create(['name' => 'Ann']);
        Fixtures\Author::create(['name' => 'Bob']);
        Fixtures\Manuscript::create(['writer_id' => $ann->id, 'title' => 'Ann 1']);
        Fixtures\Manuscript::create(['writer_id' => $ann->id, 'title' => 'Ann 2']);

        schemaSdl(Fixtures\AuthorQueries::class, Fixtures\Author::class, Fixtures\Manuscript::class);
        DB::enableQueryLog();

        expect(namingRequest('{ all_authors { pen_name written_novels { working_title } } }'))->toBe(['data' => ['all_authors' => [
            ['pen_name' => 'Ann', 'written_novels' => [['working_title' => 'Ann 1'], ['working_title' => 'Ann 2']]],
            ['pen_name' => 'Bob', 'written_novels' => []],
        ]]])
            ->and(DB::getQueryLog())->toHaveCount(2);
    });
});

describe('rejected configuration', function () {
    it('rejects an invalid naming config at discovery', function (mixed $naming, string $message) {
        namingConfig($naming);

        expect(fn() => discoverGraphQL(...NAMING_SOURCES))->toThrow(LogicException::class, $message);
    })->with([
        'not an array' => [
            'snake',
            "Config discovery.graphql.naming maps fields, arguments and operations to a naming strategy, as in ['fields' => FieldCase::Snake], got string.",
        ],
        'an unknown key' => [
            ['field' => FieldCase::Snake],
            'Config discovery.graphql.naming has the unknown key [field]. Use fields, arguments or operations.',
        ],
        'an unknown case' => [
            ['fields' => 'shouting'],
            "Config discovery.graphql.naming.fields takes a FieldCase case (Preserve, Camel, Snake), its value ('preserve', 'camel', 'snake') or a class-string of a " . NamingStrategy::class . ", got 'shouting'.",
        ],
        'a number' => [
            ['arguments' => 42],
            'Config discovery.graphql.naming.arguments takes a FieldCase case',
        ],
        'a class that is no strategy' => [
            ['operations' => stdClass::class],
            "Config discovery.graphql.naming.operations takes a FieldCase case (Preserve, Camel, Snake), its value ('preserve', 'camel', 'snake') or a class-string of a " . NamingStrategy::class . ", got 'stdClass'.",
        ],
    ]);

    it('rejects an invalid per-type override', function (string $class, string $message) {
        expect(fn() => discoverGraphQL($class))->toThrow(LogicException::class, $message);
    })->with([
        '#[Type(naming:)]' => [Fixtures\Invalid\LoudType::class, "#[Type(naming:)] on " . Fixtures\Invalid\LoudType::class . " takes a FieldCase case (Preserve, Camel, Snake), its value ('preserve', 'camel', 'snake') or a class-string of a " . NamingStrategy::class . ", got 'loud'."],
        '#[Input(naming:)]' => [Fixtures\Invalid\LoudInput::class, '#[Input(naming:)] on ' . Fixtures\Invalid\LoudInput::class . ' takes a FieldCase case'],
    ]);

    it('rejects a strategy class the container resolves to something else', function () {
        app()->bind(Fixtures\TrailingUnderscore::class, static fn(): stdClass => new stdClass());
        useNaming(fields: Fixtures\TrailingUnderscore::class);

        expect(fn() => discoverGraphQL(Fixtures\Volume::class))->toThrow(
            LogicException::class,
            'Config discovery.graphql.naming.fields names ' . Fixtures\TrailingUnderscore::class . ', but the container resolves it to stdClass, which is no ' . NamingStrategy::class . '.',
        );
    });

    it('creates a strategy class once', function () {
        useNaming(fields: Fixtures\TrailingUnderscore::class, arguments: Fixtures\TrailingUnderscore::class);
        $made = 0;
        app()->resolving(Fixtures\TrailingUnderscore::class, function () use (&$made): void {
            $made++;
        });

        discoverGraphQL(...NAMING_SOURCES);
        discoverGraphQL(Fixtures\Volume::class);

        expect($made)->toBe(1);
    });

    it('rejects a strategy that returns an invalid GraphQL name', function () {
        useNaming(fields: Fixtures\KebabNames::class);

        expect(fn() => discoverGraphQL(Fixtures\Volume::class))->toThrow(
            LogicException::class,
            'The naming strategy ' . Fixtures\KebabNames::class . ' turns property ' . Fixtures\Volume::class . '::$pageCount into "page-count", which is not a valid GraphQL name. Return letters, digits and underscores only, not starting with a digit, or give the member an explicit name:.',
        );
    });

});

describe('arg name collisions', function () {
    it('rejects two owners of one GraphQL arg name, naming both', function (string $class, string $message) {
        useNaming(arguments: FieldCase::Snake);

        expect(fn() => discoverGraphQL($class))->toThrow(LogicException::class, $message);

        useNaming();

        expect(discoveredActions($class))->toHaveCount(1);
    })->with([
        'a parameter and its snake_case twin' => [
            Fixtures\CollidingNames::class,
            'The parameter $user_id in ' . Fixtures\CollidingNames::class . '::lookup takes the arg "user_id", which collides with the arg of the parameter $userId. The argument naming strategy made one of these names: give one an explicit name with #[Arg(name: ...)] or #[Field(name: ...)], or rename a parameter.',
        ],
        'a model binding and a parameter' => [
            Fixtures\Invalid\BindingCollision::class,
            'The model-bound parameter $volumeOwner in ' . Fixtures\Invalid\BindingCollision::class . '::ownerName takes the arg "volume_owner", which collides with the arg of the parameter $volume_owner. The argument naming strategy made one of these names',
        ],
        'two parameters that differ in case' => [
            Fixtures\Invalid\CaseCollision::class,
            'The parameter $FooBar in ' . Fixtures\Invalid\CaseCollision::class . '::pair takes the arg "foo_bar", which collides with the arg of the parameter $fooBar. The argument naming strategy made one of these names',
        ],
        'a flattened field and a parameter' => [
            Fixtures\Invalid\FlattenedCollision::class,
            '#[AsArgs] on the parameter $parcel in ' . Fixtures\Invalid\FlattenedCollision::class . '::track flattens ' . Fixtures\TrackParcel::class . '::$trackingCode into the arg "tracking_code", which collides with the arg of the parameter $tracking_code. The argument naming strategy made one of these names',
        ],
    ]);

    it('rejects a renamed arg that takes another parameter\'s PHP name', function (string $class, string $message) {
        useNaming(arguments: FieldCase::Snake);

        expect(fn() => discoverGraphQL($class))->toThrow(LogicException::class, $message);
    })->with([
        'by #[Arg(name:)]' => [
            Fixtures\Invalid\ExplicitOntoParameter::class,
            "Argument #[Arg(name: 'name')] on " . Fixtures\Invalid\ExplicitOntoParameter::class . '::label($title) collides with the parameter $name. Rename the arg or the parameter.',
        ],
        'by the strategy' => [
            Fixtures\Invalid\StrategyOntoParameter::class,
            'The argument naming strategy names the parameter $userId in ' . Fixtures\Invalid\StrategyOntoParameter::class . '::lookup "user_id", which collides with the parameter $user_id. Name one with #[Arg(name: ...)], or rename a parameter.',
        ],
    ]);
});

describe('#[AsArgs] under an argument strategy', function () {
    beforeEach(function () {
        Fixtures\ParcelMutations::$flattened = null;
        Fixtures\ParcelMutations::$nested = null;
        useNaming(fields: FieldCase::Preserve, arguments: FieldCase::Snake);
        schemaSdl(Fixtures\ParcelMutations::class, Fixtures\TrackParcel::class, Fixtures\OverrideQueries::class, Fixtures\PlainVolume::class, Fixtures\SnakeNote::class);
    });

    it('names flattened fields by the argument strategy, not the field strategy or #[Input(naming:)]', function () {
        $sdl = SchemaPrinter::doPrint(GraphQL::schema());

        expect($sdl)
            ->toContain('trackParcel(tracking_code: String!, carrierName: String, delivery_note: String): String!')
            ->toContain('trackNested(parcel_input: TrackParcelInput!): String!')
            ->toContain("input TrackParcelInput {\n  trackingCode: String!\n  carrierName: String\n  deliveryNote: String\n}");
    });

    it('hydrates the flattened input by property name', function () {
        expect(namingRequest('mutation { trackParcel(tracking_code: "AB123", carrierName: "Post", delivery_note: "Door") }'))
            ->toBe(['data' => ['trackParcel' => 'AB123']])
            ->and(Fixtures\ParcelMutations::$flattened)->toEqual(new Fixtures\TrackParcel('AB123', 'Post', 'Door'));
    });

    it('reports validation errors under the flattened arg names, and hands closures property names', function () {
        expect(namingRequest('mutation { trackParcel(tracking_code: "AB") }')['errors'][0]['extensions']['validation'])
            ->toBe(['tracking_code' => ['A tracking code has five characters.']])
            ->and(namingRequest('mutation { trackParcel(tracking_code: "PRIORITY") }')['errors'][0]['extensions']['validation'])
            ->toBe(['delivery_note' => ['The delivery note field is required.']])
            ->and(namingRequest('mutation { trackNested(parcel_input: { trackingCode: "PRIORITY" }) }')['errors'][0]['extensions']['validation'])
            ->toBe(['parcel_input.deliveryNote' => ['The parcel input.delivery note field is required.']])
            ->and(Fixtures\ParcelMutations::$flattened)->toBeNull()
            ->and(Fixtures\ParcelMutations::$nested)->toBeNull();
    });
});

describe('duplicate operations', function () {
    it('rejects two operations a strategy gives one name', function (bool $cached) {
        useNaming(operations: FieldCase::Camel);
        isolateGraphQL();

        if ($cached) {
            app()->instance('config_loaded_from_cache', true);
        }

        expect(fn() => discoverGraphQL(Fixtures\Invalid\DuplicateOperations::class)->apply())->toThrow(
            LogicException::class,
            'The query [latestIssue] in schema [default] is declared by both ' . Fixtures\Invalid\DuplicateOperations::class . '::latest_issue and ' . Fixtures\Invalid\DuplicateOperations::class . '::latestIssue. Rename one with #[Query(name: ...)].',
        );

        app()->forgetInstance('config_loaded_from_cache');
    })->with(['config written' => false, 'config cached' => true]);

    it('rejects one name declared by two classes in one schema, and allows it across kinds and schemas', function (bool $cached) {
        isolateGraphQL();

        if ($cached) {
            app()->instance('config_loaded_from_cache', true);
        }

        discoverGraphQL(Fixtures\Invalid\FirstShelfQueries::class)->apply();

        expect(fn() => discoverGraphQL(Fixtures\Invalid\FirstShelfQueries::class, Fixtures\Invalid\SecondShelfQueries::class)->apply())->toThrow(
            LogicException::class,
            'The query [shelfCount] in schema [default] is declared by both ' . Fixtures\Invalid\FirstShelfQueries::class . '::shelfCount and ' . Fixtures\Invalid\SecondShelfQueries::class . '::shelfCount. Rename one with #[Query(name: ...)].',
        );

        app()->forgetInstance('config_loaded_from_cache');
    })->with(['config written' => false, 'config cached' => true]);
});

describe('the discovery cache', function () {
    it('keeps the names decided at discovery, also once the strategy changes', function () {
        useNaming(FieldCase::Snake, FieldCase::Snake, FieldCase::Snake);
        isolateGraphQL();

        $items = discoverGraphQL(...NAMING_SOURCES)->getItems();
        $cached = unserialize(serialize($items));

        if (! $cached instanceof DiscoveryItems) {
            throw new RuntimeException('Items did not survive serialization.');
        }

        foreach (iterator_to_array($items, false) as $index => $item) {
            expect(iterator_to_array($cached, false)[$index])->toEqual($item);
        }

        useNaming();
        isolateGraphQL();
        $discovery = app(GraphQLDiscovery::class);
        $discovery->setItems($cached);
        $discovery->apply();

        expect(sdlDefinitions(SchemaPrinter::doPrint(GraphQL::schema())))->toBe(sdlDefinitions(SNAKE_SDL))
            ->and(namingRequest('mutation { shelve_volume(shelve_request: { shelf_label: "Top" }) }'))->toBe(['data' => ['shelve_volume' => 'Top']]);
    });

    it('names the operation from the item when the configuration is cached', function () {
        useNaming(operations: FieldCase::Snake);
        isolateGraphQL();
        app()->instance('config_loaded_from_cache', true);

        $discovery = discoverGraphQL(...NAMING_SOURCES);
        $discovery->apply();

        $names = [];

        foreach ($discovery->getItems() as $item) {
            if ($item instanceof DiscoveredAction) {
                $names[] = app((string) $item->bindName)->attributes()['name'];
            } elseif ($item instanceof DiscoveredType && $item->kind === TypeKind::Input) {
                expect(app()->bound((string) $item->bindName))->toBeTrue();
            }
        }

        expect($names)->toEqualCanonicalizing(['latest_volume', 'volumes_by_author', 'volumeCount', 'owner_name', 'shelve_volume'])
            ->and(config('graphql.schemas'))->toBe([]);

        app()->forgetInstance('config_loaded_from_cache');
    });

    it('exports the naming strategies the way config:cache writes them', function () {
        useNaming(FieldCase::Snake, Fixtures\TrailingUnderscore::class, 'camel');

        $path = tempnam(sys_get_temp_dir(), 'naming');
        file_put_contents($path, '<?php return ' . var_export(config(Naming::CONFIG), true) . ';');
        $exported = require $path;
        unlink($path);

        expect($exported)->toBe(['fields' => FieldCase::Snake, 'arguments' => Fixtures\TrailingUnderscore::class, 'operations' => 'camel']);
    });
});
