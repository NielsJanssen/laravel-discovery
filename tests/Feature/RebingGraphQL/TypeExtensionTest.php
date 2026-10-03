<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredExtension;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\Enums\Mood;
use Tests\Fixtures\RebingGraphQL\Enums\Orphan;
use Tests\Fixtures\RebingGraphQL\Extensions\AcmeBilledUserPerks;
use Tests\Fixtures\RebingGraphQL\Extensions\AcmeSnakeNoteByName;
use Tests\Fixtures\RebingGraphQL\Extensions\AcmeSnakeNoteExtras;
use Tests\Fixtures\RebingGraphQL\Extensions\AcmeUserExtras;
use Tests\Fixtures\RebingGraphQL\Extensions\AcmeWriterExtras;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeAbstractExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeDuplicateFactory;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeInputArgExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeOrphanExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmePamphletExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeRebingExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeRivalExtras;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeUnknownExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeUnregisteredReturn;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeUnusedInputExtension;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeUserRelation;
use Tests\Fixtures\RebingGraphQL\Extensions\Invalid\AcmeUserRename;
use Tests\Fixtures\RebingGraphQL\Inputs\Unused;
use Tests\Fixtures\RebingGraphQL\Loaders\LoaderQueries;
use Tests\Fixtures\RebingGraphQL\Loaders\Novel;
use Tests\Fixtures\RebingGraphQL\Loaders\Review;
use Tests\Fixtures\RebingGraphQL\Loaders\Writer;
use Tests\Fixtures\RebingGraphQL\Naming\OverrideQueries;
use Tests\Fixtures\RebingGraphQL\Naming\PlainVolume;
use Tests\Fixtures\RebingGraphQL\Naming\SnakeNote;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserQuery;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserWithBilling;
use Tests\Fixtures\RebingGraphQL\Types\PamphletType;
use Workbench\App\Models\User;

const ACME_USERS = [AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class];

const SNAKE_NOTES = [OverrideQueries::class, PlainVolume::class, SnakeNote::class, AcmeSnakeNoteExtras::class, AcmeSnakeNoteByName::class];

/** The text of the type definition with the name. */
function typeDefinition(string $sdl, string $name): string
{
    return array_values(array_filter(sdlDefinitions($sdl), static fn(string $definition): bool => str_contains($definition, "type $name {")))[0] ?? '';
}

/**
 * Post the query and return the whole response.
 *
 * @return array<string, mixed>
 */
function postGraphQL(string $query): array
{
    $json = test()->postJson('/graphql', ['query' => $query])->assertOk()->json();

    return is_array($json) ? $json : [];
}

beforeEach(function () {
    AcmeUserExtras::$resolved = [];
    AcmeUserExtras::$contexts = [];
});

describe('a contributor', function () {
    it('adds its #[Field] methods and factory fields to the target type', function () {
        expect(typeDefinition(schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class]), 'AcmeUser'))->toBe(<<<'GRAPHQL'
            "An Acme user"
            type AcmeUser {
              billingReference: String
              name: String!
              invoices(limit: Int = 10): [String!]!
              secret: String
              vault: String
              mood: Mood!
              rank: Int!
            }
            GRAPHQL);
    });

    it('resolves through the contributor, with the parent as #[Root] and its dependencies injected', function () {
        schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class]);

        expect(postGraphQL('{ user { name invoices(limit: 2) mood rank } }'))->toBe(['data' => ['user' => [
            'name' => 'Ada',
            'invoices' => ['Ada-1!', 'Ada-2!'],
            'mood' => 'Cheerful',
            'rank' => 3,
        ]]]);
    });

    it('uses the default of an arg left out', function () {
        schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class]);

        expect(postGraphQL('{ user { invoices } }'))->toBe(['data' => ['user' => ['invoices' => array_map(static fn(int $number): string => "Ada-$number!", range(1, 10))]]]);
    });

    it('merges contributors by class name, whatever the discovery order', function () {
        $first = typeDefinition(schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class, AcmeBilledUserPerks::class]), 'AcmeUser');
        $second = typeDefinition(schemaSdl(AcmeUserExtras::class, AcmeBilledUserPerks::class, ...ACME_USERS), 'AcmeUser');

        expect($first)->toBe($second)
            ->and($first)->toContain("name: String!\n  perks: String!\n  invoices(limit: Int = 10): [String!]!");
    });

    it('stores what it adds as a discovery item of its own', function () {
        $items = iterator_to_array(discoverGraphQL(AcmeBilledUserPerks::class)->getItems(), false);

        expect($items)->toHaveCount(1)
            ->and($items[0])->toBeInstanceOf(DiscoveredExtension::class)
            ->and([$items[0]->target, $items[0]->targetIsClass, $items[0]->fields[0]->host, $items[0]->fields[0]->typeClass])
            ->toBe([AcmeUserWithBilling::class, true, AcmeBilledUserPerks::class, AcmeUserWithBilling::class]);
    });
});

describe('naming', function () {
    it('names a class target\'s fields with its #[Type(naming:)], and a name target\'s with the configured strategy', function () {
        expect(typeDefinition(schemaSdl(...SNAKE_NOTES), 'SnakeNote'))->toBe(<<<'GRAPHQL'
            type SnakeNote {
              noteText_: String!
              wordLimit: Int!
              readingTime_(wordsPerMinute_: Int = 1): Int!
            }
            GRAPHQL);
    });

    it('maps the args of a class target by its #[Type(naming:)]', function () {
        schemaSdl(...SNAKE_NOTES);

        expect(postGraphQL('mutation { keepNote(noteInput: {note_text: "one two three four"}) { readingTime_(wordsPerMinute_: 2) wordLimit } }'))
            ->toBe(['data' => ['keepNote' => ['readingTime_' => 2, 'wordLimit' => 500]]]);
    });
});

describe('replaced types', function () {
    it('adds the fields for the parent and for the replacer to the one type both share', function () {
        $sdl = schemaSdl(...ACME_USERS, ...[AcmeBilledUserPerks::class]);

        expect(typeDefinition($sdl, 'AcmeUser'))->toContain('perks: String!')
            ->and($sdl)->not->toContain('AcmeUserWithBilling');
    });

    it('resolves a contributed field for the parent and for the replacer', function () {
        schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class, AcmeBilledUserPerks::class]);

        expect(postGraphQL('{ user { perks rank } billedUser { perks rank } }'))->toBe(['data' => [
            'user' => ['perks' => 'no perks', 'rank' => 3],
            'billedUser' => ['perks' => 'perks for B-1', 'rank' => 3],
        ]]);
    });
});

describe('field authorization on a contributed field', function () {
    beforeEach(function () {
        Gate::define('viewSecrets', static fn(?User $user, AcmeUser $parent): bool => $user !== null && $parent->name === 'Ada');
        schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class]);
    });

    it('checks the ability against the parent object', function () {
        $this->actingAs(new User());

        expect(postGraphQL('{ user { name secret } }'))->toBe(['data' => ['user' => ['name' => 'Ada', 'secret' => 'Ada keeps a secret']]])
            ->and(AcmeUserExtras::$resolved)->toBe(['secret']);
    });

    it('resolves a denied field to null without calling the contributor', function () {
        expect(postGraphQL('{ user { name secret } }'))->toBe(['data' => ['user' => ['name' => 'Ada', 'secret' => null]]])
            ->and(AcmeUserExtras::$resolved)->toBe([]);
    });

    it('reports Denied::Error as a field error without calling the contributor', function () {
        expect(postGraphQL('{ user { name vault } }'))->toBe([
            'errors' => [[
                'message' => 'Forbidden',
                'locations' => [['line' => 1, 'column' => 15]],
                'path' => ['user', 'vault'],
                'extensions' => ['category' => 'authorization'],
            ]],
            'data' => ['user' => ['name' => 'Ada', 'vault' => null]],
        ])->and(AcmeUserExtras::$resolved)->toBe([]);
    });
});

describe('batch loading', function () {
    beforeEach(function () {
        loadGraphQLMigrations();
        $ann = Writer::create(['name' => 'Ann']);
        $bob = Writer::create(['name' => 'Bob']);
        Novel::create(['writer_id' => $ann->id, 'title' => 'Ann 1']);
        Novel::create(['writer_id' => $bob->id, 'title' => 'Bob 1']);
        Novel::create(['writer_id' => $ann->id, 'title' => 'Ann 2']);
    });

    it('loads a relation of the target model once for every parent', function () {
        schemaSdl(LoaderQueries::class, Writer::class, Novel::class, Review::class, AcmeWriterExtras::class);
        DB::enableQueryLog();

        expect(postGraphQL('{ writers { name books { title } } }'))->toBe(['data' => ['writers' => [
            ['name' => 'Ann', 'books' => [['title' => 'Ann 1'], ['title' => 'Ann 2']]],
            ['name' => 'Bob', 'books' => [['title' => 'Bob 1']]],
        ]]])->and(DB::getQueryLog())->toHaveCount(2);
    });
});

describe('a contributor that is a TypeFactory', function () {
    it('is told the type and every name the type has before its fields', function () {
        schemaSdl(...ACME_USERS, ...[AcmeUserExtras::class]);
        $context = AcmeUserExtras::$contexts[0] ?? null;

        expect(AcmeUserExtras::$contexts)->toHaveCount(1)
            ->and([$context?->name, $context?->class, $context?->kind, $context?->declaredFields])
            ->toBe(['AcmeUser', AcmeUserWithBilling::class, Position::Output, ['billingReference', 'name', 'invoices', 'secret', 'vault', 'mood']])
            ->and($context?->naming)->toBeInstanceOf(NamingStrategy::class);
    });

    it('rejects a field whose name the type already has at type build', function () {
        expect(fn() => schemaSdl(...ACME_USERS, ...[AcmeDuplicateFactory::class]))
            ->toThrow(LogicException::class, sprintf('The type extension %s yields a field "name" that type [AcmeUser] (%s) already has.', AcmeDuplicateFactory::class, AcmeUserWithBilling::class));
    });
});

describe('the discovery cache', function () {
    it('serves the contributed fields after a round trip', function () {
        applyCachedGraphQL([...ACME_USERS, AcmeUserExtras::class, AcmeBilledUserPerks::class]);

        expect(postGraphQL('{ billedUser { name invoices(limit: 1) mood rank perks } }'))->toBe(['data' => ['billedUser' => [
            'name' => 'Ada',
            'invoices' => ['Ada-1!'],
            'mood' => 'Cheerful',
            'rank' => 3,
            'perks' => 'perks for B-1',
        ]]]);
    });

    it('keeps the bind name of the target, so a cached config still points at the merged type', function () {
        $registry = assertBoundWhenConfigCached([...ACME_USERS, AcmeBilledUserPerks::class], static fn(DiscoveredType $type): bool => $type->class !== AcmeUser::class);

        expect(array_column($registry->typeNamed('AcmeUser')->fields ?? [], 'name'))->toBe(['billingReference', 'name', 'perks']);
    });
});

describe('rejections', function () {
    it('rejects at discovery', function (object|string $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'a contributor without fields' => [
            fn() => new #[TypeExtension(AcmeUser::class)] class {},
            '#[TypeExtension(Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser)] on %1$s adds no fields. Add a #[Field] method, or implement NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory.',
        ],
        'a contributor that is also a #[Type]' => [
            fn() => new #[TypeExtension(AcmeUser::class), Type] class {
                public string $badge = 'gold';
            },
            '#[TypeExtension] on %1$s, which is also an #[Type]: a contributor only adds fields to the type it extends.',
        ],
        'a contributor that is also an #[Input]' => [
            fn() => new #[TypeExtension(AcmeUser::class), Input] class {
                public string $badge = 'gold';
            },
            '#[TypeExtension] on %1$s, which is also an #[Input]',
        ],
        'a #[Field] property' => [
            fn() => new #[TypeExtension(AcmeUser::class)] class {
                #[Field]
                public string $badge = 'gold';
            },
            'Property %1$s::$badge has #[Field], but a contributor adds #[Field] methods only, which take the parent object as #[Root].',
        ],
        'a class-level field decorator' => [
            fn() => new #[TypeExtension(AcmeUser::class), Authorize] class {
                #[Field]
                public function badge(): string
                {
                    return 'gold';
                }
            },
            '#[Authorize] on the contributor %1$s has nothing to apply to',
        ],
        'two fields with one name' => [
            fn() => new #[TypeExtension(AcmeUser::class)] class {
                #[Field(name: 'badge')]
                public function first(): string
                {
                    return 'gold';
                }

                #[Field(name: 'badge')]
                public function second(): string
                {
                    return 'silver';
                }
            },
            'TypeExtension %1$s has two fields named "badge" (first() and second()).',
        ],
        'an input class' => [
            fn() => new #[TypeExtension(Unused::class)] class {
                #[Field]
                public function badge(): string
                {
                    return 'gold';
                }
            },
            '#[TypeExtension(Tests\Fixtures\RebingGraphQL\Inputs\Unused)] on %1$s targets the input type Tests\Fixtures\RebingGraphQL\Inputs\Unused, and inputs cannot be extended. Extend an #[Input] with a subclass marked #[Input(replace: true)], or add the field where the input is defined.',
        ],
        'an enum class' => [
            fn() => new #[TypeExtension(Mood::class)] class {
                #[Field]
                public function badge(): string
                {
                    return 'gold';
                }
            },
            '#[TypeExtension(Tests\Fixtures\RebingGraphQL\Enums\Mood)] on %1$s targets the enum Tests\Fixtures\RebingGraphQL\Enums\Mood, which has no fields to extend.',
        ],
        'an abstract contributor' => [
            AcmeAbstractExtension::class,
            '#[TypeExtension] on %1$s, which cannot be instantiated: a contributor is resolved from the container. Make it a concrete class.',
        ],
        'a Rebing type' => [
            AcmeRebingExtension::class,
            '#[TypeExtension] on %1$s, which extends Rebing\GraphQL\Support\Type: a contributor is a plain class.',
        ],
    ]);

    it('rejects at apply()', function (array $sources, string $message) {
        isolateGraphQL();

        expect(fn() => discoverGraphQL(...$sources)->apply())->toThrow(LogicException::class, $message);
    })->with([
        'an unknown target' => [
            [...ACME_USERS, AcmeUnknownExtension::class],
            sprintf('#[TypeExtension(AcmeNowhere)] on %s names no discovered #[Type]. Add #[Type] to the class, or name an existing type by its class or GraphQL name.', AcmeUnknownExtension::class),
        ],
        'an input by name' => [
            [...ACME_USERS, Unused::class, AcmeUnusedInputExtension::class],
            sprintf('#[TypeExtension(UnusedInput)] on %s targets the input type [UnusedInput], and inputs cannot be extended.', AcmeUnusedInputExtension::class),
        ],
        'an enum by name' => [
            [...ACME_USERS, Orphan::class, AcmeOrphanExtension::class],
            sprintf('#[TypeExtension(Orphan)] on %s targets the enum [Orphan], which has no fields to extend.', AcmeOrphanExtension::class),
        ],
        'a hand-written Rebing type' => [
            [...ACME_USERS, PamphletType::class, AcmePamphletExtension::class],
            sprintf('#[TypeExtension(Pamphlet)] on %s names the hand-written Rebing type %s, which cannot be extended. Add the field to that class.', AcmePamphletExtension::class, PamphletType::class),
        ],
        'a field the target has' => [
            [...ACME_USERS, AcmeUserRename::class],
            sprintf('Type [AcmeUser] gets the field "name" from both %s::$name and %s::name(). Rename one with #[Field(name: ...)].', AcmeUserWithBilling::class, AcmeUserRename::class),
        ],
        'a field another contributor adds' => [
            [...ACME_USERS, AcmeUserExtras::class, AcmeRivalExtras::class],
            sprintf('Type [AcmeUser] gets the field "invoices" from both %s::invoices() and %s::invoices().', AcmeUserExtras::class, AcmeRivalExtras::class),
        ],
        '#[Relation] on a name target that is no model' => [
            [...ACME_USERS, AcmeUserRelation::class],
            sprintf('Method %s::posts() has #[Relation], but %s is not an Eloquent model', AcmeUserRelation::class, AcmeUserWithBilling::class),
        ],
        'an input type as an arg' => [
            [...ACME_USERS, Unused::class, AcmeInputArgExtension::class],
            'Argument filter of field AcmeUser.peers takes the input type [UnusedInput]',
        ],
        'a contributed field returning an unregistered class' => [
            [...ACME_USERS, AcmeUnregisteredReturn::class],
            sprintf('Method %s::service() references %s, which is not a registered GraphQL output type.', AcmeUnregisteredReturn::class, ContainerService::class),
        ],
    ]);
});
