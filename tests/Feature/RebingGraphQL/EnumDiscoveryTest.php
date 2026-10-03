<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredEnumType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredEnumValue;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Tempest\Discovery\DiscoveryItems;
use Tests\Fixtures\RebingGraphQL\Enums;
use Tests\Fixtures\RebingGraphQL\Types\Shelf;

/**
 * @return list<DiscoveredType>
 */
function discoveredEnums(string ...$classes): array
{
    $items = iterator_to_array(discoverGraphQL(...$classes)->getItems(), false);

    return array_values(array_filter($items, static fn(mixed $item): bool => $item instanceof DiscoveredType && $item->kind === TypeKind::Enum));
}

/** Discover the classes and pass the items through serialize(), as the discovery cache does. */
function cachedItems(string ...$classes): DiscoveryItems
{
    $items = unserialize(serialize(discoverGraphQL(...$classes)->getItems()));

    return $items instanceof DiscoveryItems ? $items : throw new \RuntimeException('Items did not survive serialization.');
}

function applyItems(DiscoveryItems $items): void
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems($items);
    $discovery->apply();
}

const HAND_REGISTRATION_HINT = 'or register the enum by hand with NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry::register() in a service provider that boots before NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider.';

const GENRE_SDL = <<<'GRAPHQL'
    "Shelf a book is filed under"
    enum Genre {
      Fiction

      "Biographies, essays, history"
      NonFiction
      Novel @deprecated(reason: "Use Fiction")
      Saga @deprecated(reason: "Use Fiction (since 2.0)")
    }
    GRAPHQL;

describe('the enum schema', function () {
    it('prints a backed enum with described and deprecated cases', function () {
        expect(schemaSdl(Enums\GenreQuery::class, Enums\Genre::class))
            ->toContain(GENRE_SDL)
            ->toContain(<<<'GRAPHQL'
                type Query {
                  genre: Genre!
                  genres: [Genre!]!
                  describeGenre(genre: Genre!): String!
                }
                GRAPHQL);

        buildAllSchemas();
    });

    it('registers a referenced enum without #[Enum], from a field, a field arg and an action arg', function () {
        expect(schemaSdl(Enums\DiaryQuery::class, Enums\Diary::class, Enums\MoodArgQuery::class))
            ->toContain(<<<'GRAPHQL'
                type Diary {
                  mood: Mood!
                  genre: Genre
                  feels(mood: Mood!): Boolean!
                  feelsLike(mood: Mood = Calm): String!
                }
                GRAPHQL)
            ->toContain(<<<'GRAPHQL'
                enum Mood {
                  Calm
                  Cheerful
                  Gloomy
                }
                GRAPHQL)
            ->toContain(GENRE_SDL)
            ->toContain('mood(mood: Mood!, fallback: Mood, preset: Mood): String!');

        buildAllSchemas();

        expect(app(TypeRegistry::class)->kindOf(Enums\Mood::class, Position::Output))->toBe(TypeKind::Enum);
    });

    it('registers an enum used only as an action arg, in both positions', function () {
        expect(schemaSdl(Enums\MoodArgQuery::class))->toContain('enum Mood {');

        buildAllSchemas();

        $registry = app(TypeRegistry::class);

        expect($registry->nameOf(Enums\Mood::class, Position::Input))->toBe('Mood')
            ->and($registry->nameOf(Enums\Mood::class, Position::Output))->toBe('Mood')
            ->and(config('graphql.types'))->toHaveKey('Mood');
    });

    it('registers an #[Enum] that nothing references', function () {
        expect(schemaSdl(Enums\GenreQuery::class, Enums\Orphan::class))->toContain(<<<'GRAPHQL'
            enum Orphan {
              Alone
            }
            GRAPHQL);

        buildAllSchemas();
    });

    it('names an enum with #[Enum(name:)] and describes it', function () {
        expect(schemaSdl(Enums\ColorQuery::class, Enums\Color::class))
            ->toContain(<<<'GRAPHQL'
                "Named explicitly"
                enum Colour {
                  Red
                  Green
                }
                GRAPHQL)
            ->toContain('mix(color: Colour!): Colour!')
            ->not->toContain('enum Color ');
    });

    it('registers an enum once, under its #[Enum] name, whatever the discovery order', function (array $order) {
        $sdl = schemaSdl(...$order);

        expect(sdlDefinitions($sdl))->toBe(sdlDefinitions(schemaSdl(Enums\ColorQuery::class, Enums\Color::class)))
            ->and(substr_count($sdl, 'enum Colour {'))->toBe(1)
            ->and(array_keys(config('graphql.types')))->toBe(['Colour']);

        isolateGraphQL();

        expect(array_map(static fn(DiscoveredType $enum): array => [$enum->name, $enum->implicit], discoveredEnums(...$order)))
            ->toBe($order[0] === Enums\ColorQuery::class ? [['Colour', true], ['Colour', false]] : [['Colour', false]]);
    })->with([
        'reference first' => [[Enums\ColorQuery::class, Enums\Color::class]],
        '#[Enum] first' => [[Enums\Color::class, Enums\ColorQuery::class]],
    ]);

    it('registers an enum once when both #[Enum] and references point at it', function (array $order) {
        $sdl = schemaSdl(...$order);

        expect(substr_count($sdl, 'enum Genre {'))->toBe(1)
            ->and(array_keys(config('graphql.types')))->toEqualCanonicalizing(['Genre', 'Diary', 'Mood']);

        buildAllSchemas();
    })->with([
        'references first' => [[Enums\GenreQuery::class, Enums\DiaryQuery::class, Enums\Diary::class, Enums\Genre::class]],
        '#[Enum] first' => [[Enums\Genre::class, Enums\Diary::class, Enums\DiaryQuery::class, Enums\GenreQuery::class]],
    ]);

    it('leaves an enum registered by hand to its hand-written type', function () {
        isolateGraphQL();
        app(TypeRegistry::class)->register(Enums\Mood::class, 'Mood', TypeKind::Enum);
        discoverGraphQL(Enums\HandWrittenMoodType::class, Enums\MoodArgQuery::class)->apply();

        expect(config('graphql.types.Mood'))->toBe(Enums\HandWrittenMoodType::class);

        buildAllSchemas();
    });

    it('leaves a hand-registered enum to its hand-written type when the items come from the cache', function () {
        isolateGraphQL();
        $items = cachedItems(Enums\HandWrittenMoodType::class, Enums\MoodArgQuery::class);

        isolateGraphQL();
        app(TypeRegistry::class)->register(Enums\Mood::class, 'Mood', TypeKind::Enum);
        applyItems($items);

        expect(config('graphql.types.Mood'))->toBe(Enums\HandWrittenMoodType::class);

        buildAllSchemas();
    });
});

describe('the discovery cache', function () {
    it('writes the bind names of the cached items, so the config and the container agree', function () {
        isolateGraphQL();
        $items = cachedItems(Enums\GenreQuery::class, Enums\DiaryQuery::class, Enums\Diary::class, Enums\Genre::class, Enums\MoodArgQuery::class);

        isolateGraphQL();
        applyItems($items);

        $bindNames = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType) {
                $bindNames[] = $item->bindName;
            }
        }

        $written = array_values(config('graphql.types'));

        expect($written)->toHaveCount(3)
            ->and(array_diff($written, $bindNames))->toBe([]);

        foreach ($written as $bindName) {
            expect(app()->bound($bindName))->toBeTrue();
        }

        $this->postJson('/graphql', ['query' => '{ genre diary { mood } mood(mood: Gloomy) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.diary.mood', 'Cheerful');
    });

    it('round-trips an action with an enum default', function () {
        $action = discoveredActions(Enums\MoodArgQuery::class)['mood'];

        expect(unserialize(serialize($action)))->toEqual($action);
    });
});

describe('validation rules on enum args', function () {
    it('passes the case to the rules, so Rule::enum() validates it', function () {
        schemaSdl(Enums\MoodRuleQuery::class);

        $this->postJson('/graphql', ['query' => '{ calmOnly(mood: Cheerful) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.calmOnly', 'Cheerful');

        $this->postJson('/graphql', ['query' => '{ calmOnly(mood: Gloomy) }'])
            ->assertJsonPath('errors.0.message', 'validation')
            ->assertJsonPath('data.calmOnly', null);
    });
});

describe('enum resolution', function () {
    it('resolves enum properties and returns to their case names, for backed and pure enums', function () {
        schemaSdl(Enums\GenreQuery::class, Enums\DiaryQuery::class, Enums\Diary::class);

        $this->postJson('/graphql', ['query' => '{ genre genres diary { mood genre feels(mood: Cheerful) other: feels(mood: Gloomy) feelsLike gloomy: feelsLike(mood: Gloomy) } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => [
                'genre' => 'NonFiction',
                'genres' => ['Fiction', 'NonFiction'],
                'diary' => ['mood' => 'Cheerful', 'genre' => 'Fiction', 'feels' => true, 'other' => false, 'feelsLike' => 'Calm', 'gloomy' => 'Gloomy'],
            ]]);
    });

    it('passes a backed enum arg to the resolver as its case', function () {
        schemaSdl(Enums\GenreQuery::class);

        $this->postJson('/graphql', ['query' => '{ describeGenre(genre: NonFiction) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['describeGenre' => Enums\Genre::class . '::NonFiction']]);
    });

    it('passes a pure enum arg to the resolver as its case, from a literal or a variable', function () {
        schemaSdl(Enums\MoodArgQuery::class);

        $this->postJson('/graphql', [
            'query' => 'query ($fallback: Mood) { mood(mood: Gloomy, fallback: $fallback) }',
            'variables' => ['fallback' => 'Cheerful'],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['mood' => Enums\Mood::class . '::Gloomy,Cheerful,Calm']]);
    });

    it('rejects a value that is not a case name', function () {
        schemaSdl(Enums\GenreQuery::class);

        $this->postJson('/graphql', ['query' => '{ describeGenre(genre: fiction) }'])
            ->assertJsonPath('errors.0.message', 'Value "fiction" does not exist in "Genre" enum. Did you mean the enum value "Fiction" or "NonFiction"?');
    });
});

describe('discovery', function () {
    it('classifies an enum parameter as an arg, not a container injection', function () {
        $action = discoveredActions(Enums\MoodArgQuery::class)['mood'];

        expect(array_map(static fn($arg) => [$arg->name, $arg->type, $arg->nullable], $action->args))->toBe([
            ['mood', Enums\Mood::class, false],
            ['fallback', Enums\Mood::class, true],
            ['preset', Enums\Mood::class, true],
        ])
            ->and($action->args[2]->defaultValue)->toBe(Enums\Mood::Calm)
            ->and($action->containerInjections)->toBe([]);
    });

    it('collects an enum that survives serialization, keeping case order', function () {
        [$enum] = discoveredEnums(Enums\Genre::class);

        expect(unserialize(serialize($enum)))->toEqual($enum)
            ->and($enum->bindName)->toStartWith('discovery.rebing_graphql.type.')
            ->and($enum->name)->toBe('Genre')
            ->and($enum->class)->toBe(Enums\Genre::class)
            ->and($enum->description)->toBe('Shelf a book is filed under')
            ->and($enum->values)->toEqual([
                new DiscoveredEnumValue('Fiction'),
                new DiscoveredEnumValue('NonFiction', description: 'Biographies, essays, history'),
                new DiscoveredEnumValue('Novel', deprecationReason: 'Use Fiction'),
                new DiscoveredEnumValue('Saga', deprecationReason: 'Use Fiction (since 2.0)'),
            ])
            ->and($enum->createType(app()))->toBeInstanceOf(DiscoveredEnumType::class);
    });

    it('uses the case instances as internal values', function () {
        [$enum] = discoveredEnums(Enums\Genre::class);

        $values = $enum->createType(app())->toArray()['values'];

        expect(array_keys($values))->toBe(['Fiction', 'NonFiction', 'Novel', 'Saga'])
            ->and(array_column($values, 'value'))->toBe(Enums\Genre::cases());
    });

    it('rejects #[Enum] on a class that is not an enum', function () {
        expect(fn() => discoverGraphQL(Enums\EnumOnClass::class))->toThrow(\LogicException::class, sprintf(
            '#[Enum] on %s, which is not a PHP enum: only an enum becomes a GraphQL enum. Declare EnumOnClass as an enum, or use #[Type] for an object type.',
            Enums\EnumOnClass::class,
        ));
    });

    it('rejects two enums with the same GraphQL name, pointing at #[Enum(name:)]', function () {
        expect(fn() => discoverGraphQL(Enums\Color::class, Enums\DuplicateColour::class))->toThrow(\LogicException::class, sprintf(
            'GraphQL type name [Colour] is used by both %s and %s. Rename one with #[Enum(name: ...)], %s',
            Enums\Color::class,
            Enums\DuplicateColour::class,
            HAND_REGISTRATION_HINT,
        ));
    });

    it('rejects two referenced enums that share a short name, pointing at #[Enum(name:)]', function () {
        expect(fn() => discoverGraphQL(Enums\ClashingMoodsQuery::class))->toThrow(\LogicException::class, sprintf(
            'GraphQL type name [Mood] is used by both %s and %s. Rename one with #[Enum(name: ...)], %s',
            Enums\Mood::class,
            Enums\Clash\Mood::class,
            HAND_REGISTRATION_HINT,
        ));
    });

    it('refuses to build an enum type for a class that is not an enum', function () {
        expect(fn() => new DiscoveredType('Shelf', Shelf::class, TypeKind::Enum)->createType(app())->toArray())
            ->toThrow(\RuntimeException::class, 'Cannot build GraphQL enum [Shelf]: ' . Shelf::class . ' is not an enum.');
    });

    it('refuses to build an enum type for a case the enum no longer has', function () {
        $type = new DiscoveredType('Mood', Enums\Mood::class, TypeKind::Enum, values: [new DiscoveredEnumValue('Calm'), new DiscoveredEnumValue('Furious')]);

        expect(fn() => $type->createType(app())->toArray())
            ->toThrow(\RuntimeException::class, 'Cannot build GraphQL enum [Mood]: ' . Enums\Mood::class . ' has no case Furious. Clear the discovery cache.');
    });

    it('rejects a referenced enum named like a hand-written Rebing type', function () {
        isolateGraphQL();

        expect(fn() => discoverGraphQL(Enums\HandWrittenMoodType::class, Enums\MoodArgQuery::class)->apply())
            ->toThrow(\LogicException::class, sprintf(
                'GraphQL type name [Mood] is used by both %s (#[Enum]) and the Rebing type %s. Rename one with #[Enum(name: ...)], %s',
                Enums\Mood::class,
                Enums\HandWrittenMoodType::class,
                HAND_REGISTRATION_HINT,
            ));
    });
});
