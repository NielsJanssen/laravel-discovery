<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Utils\SchemaPrinter;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredArg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelBinding;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredObjectType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ClassifiedParameters;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ParameterClassifier;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldSource;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\QueryField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\GraphQL as RebingGraphQL;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\Types\PamphletQuery;
use Tests\Fixtures\RebingGraphQL\Types\PamphletType;
use Tests\Fixtures\RebingGraphQL\Types\Shelf;
use Tests\Fixtures\RebingGraphQL\Types\ShelfQuery;
use Workbench\App\GraphQL\Types\AuthorType;
use Workbench\App\GraphQL\Types\BookType;
use Workbench\App\GraphQL\Types\UserType;
use Workbench\App\Models\User;

afterEach(function () {
    app()->forgetInstance('config_loaded_from_cache');
});

function shelfType(): DiscoveredType
{
    $class = new ClassReflector(Shelf::class);

    return new DiscoveredType(
        name: 'Shelf',
        class: Shelf::class,
        kind: TypeKind::Object,
        description: 'A shelf of books',
        fields: [
            new DiscoveredTypeField(
                phpName: 'label',
                name: 'label',
                type: TypeRef::scalar('string'),
                description: 'The label on the shelf',
                deprecationReason: 'Read the summary instead',
            ),
            new DiscoveredTypeField(
                phpName: 'summary',
                name: 'summary',
                type: TypeRef::scalar('string'),
                source: FieldSource::Method,
                parameters: app(ParameterClassifier::class)->classify($class, $class->getMethod('summary')),
            ),
        ],
    );
}

function singleFieldType(DiscoveredTypeField $field): DiscoveredType
{
    return new DiscoveredType('Shelf', Shelf::class, fields: [$field]);
}

describe('DiscoveredType', function () {
    it('survives serialization, fields included', function () {
        $type = shelfType()->withBindName();

        $restored = unserialize(serialize($type));

        expect($restored)->toEqual($type)
            ->and($restored->fields[1]->parameters->containerInjections)->toHaveKey('service')
            ->and($restored->fields[1]->parameters->injections)->toBe(['info' => 'info'])
            ->and($restored->fields[1]->parameters->args[0]->name)->toBe('times');
    });

    it('survives serialization as a standalone field', function () {
        $field = shelfType()->fields[1];

        expect(unserialize(serialize($field)))->toEqual($field);
    });

    it('derives a type-specific bind name from its contents', function () {
        $type = shelfType()->withBindName();
        $renamed = new DiscoveredType('Rack', Shelf::class)->withBindName();

        expect($type->bindName)->toStartWith('discovery.rebing_graphql.type.')
            ->and($type->bindName)->toBe(shelfType()->withBindName()->bindName)
            ->and($renamed->bindName)->not->toBe($type->bindName);
    });

    it('builds an object type adapter', function () {
        expect(shelfType()->createType(app()))->toBeInstanceOf(DiscoveredObjectType::class);
    });

    it('rejects kinds that have no adapter yet', function () {
        expect(fn() => new DiscoveredType('Shelf', Shelf::class, TypeKind::Enum)->createType(app()))
            ->toThrow(\LogicException::class, 'Cannot build GraphQL type [Shelf] for ' . Shelf::class . ': enum types are not supported yet.');
    });
});

describe('the object type adapter', function () {
    it('prints a hand-built type in the schema', function () {
        expect(schemaSdl(ShelfQuery::class, shelfType()))->toContain(<<<'GRAPHQL'
            "A shelf of books"
            type Shelf {
              "The label on the shelf"
              label: String! @deprecated(reason: "Read the summary instead")

              summary(
                "How often to repeat the label"
                times: Int!
              ): String!
            }
            GRAPHQL);

        buildAllSchemas();
    });

    it('resolves property and method fields end to end', function () {
        schemaSdl(ShelfQuery::class, shelfType());

        $this->postJson('/graphql', ['query' => '{ shelf { label summary(times: 2) } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.shelf.label', 'Fiction')
            ->assertJsonPath('data.shelf.summary', 'summary: FictionFiction!');
    });

    it('reads property fields from the property, not through ArrayAccess', function () {
        schemaSdl(ShelfQuery::class, shelfType());

        $label = GraphQL::type('Shelf')->getField('label');

        expect(new Shelf('Poetry')['label'])->toBe('read through offsetGet')
            ->and(($label->resolveFn)(new Shelf('Poetry'), [], null, null))->toBe('Poetry');
    });

    it('rejects a root that is not an object', function () {
        schemaSdl(ShelfQuery::class, shelfType());

        $label = GraphQL::type('Shelf')->getField('label');

        expect(fn() => ($label->resolveFn)(['label' => 'Poetry'], [], null, null))
            ->toThrow(\RuntimeException::class, 'Cannot resolve Shelf.label: expected an object, got array.');
    });

    it('rejects a method field on a root that is not an object', function () {
        schemaSdl(ShelfQuery::class, shelfType());

        $summary = GraphQL::type('Shelf')->getField('summary');

        expect(fn() => ($summary->resolveFn)(null, ['times' => 1], null, null))
            ->toThrow(\RuntimeException::class, 'Cannot resolve Shelf.summary: expected an object, got null.');
    });

    it('names the missing method when the root lacks it', function () {
        schemaSdl(ShelfQuery::class, singleFieldType(new DiscoveredTypeField('shout', 'shout', TypeRef::scalar('string'), FieldSource::Method)));

        $shout = GraphQL::type('Shelf')->getField('shout');

        expect(fn() => ($shout->resolveFn)(new Shelf(), [], null, null))
            ->toThrow(\RuntimeException::class, 'Cannot resolve Shelf.shout: ' . Shelf::class . ' has no public method shout().');
    });

    it('rejects a factory field until type factories exist', function () {
        $type = singleFieldType(new DiscoveredTypeField('label', 'label', TypeRef::scalar('string'), FieldSource::Factory));

        expect(fn() => schemaSdl(ShelfQuery::class, $type))
            ->toThrow(\LogicException::class, 'Field Shelf.label comes from a type factory');
    });

    it('rejects a model-bound or value-object parameter on a method field', function (ClassifiedParameters $parameters) {
        $type = singleFieldType(new DiscoveredTypeField('summary', 'summary', TypeRef::scalar('string'), FieldSource::Method, $parameters));

        expect(fn() => schemaSdl(ShelfQuery::class, $type))
            ->toThrow(\LogicException::class, 'Field Shelf.summary binds a model or value-object parameter');
    })->with([
        'model binding' => fn() => new ClassifiedParameters(modelBindings: [new DiscoveredModelBinding('user', 'user', User::class, false)]),
        'value object' => fn() => new ClassifiedParameters(argCompositions: ['page' => Pagination::class]),
    ]);

    it('rejects #[Arg(rules:)] on a method field arg instead of dropping the rules', function () {
        $parameters = new ClassifiedParameters(args: [new DiscoveredArg('times', 'times', 'int', false, hasRules: true)]);
        $type = singleFieldType(new DiscoveredTypeField('summary', 'summary', TypeRef::scalar('string'), FieldSource::Method, $parameters));

        expect(fn() => schemaSdl(ShelfQuery::class, $type))
            ->toThrow(\LogicException::class, 'Field Shelf.summary has #[Arg(rules:)] on $times, which type fields do not validate yet.');
    });
});

describe('apply()', function () {
    it('registers types in graphql.types under their GraphQL name', function () {
        isolateGraphQL();

        $type = shelfType()->withBindName();
        discoverGraphQL(ShelfQuery::class, $type)->apply();

        expect(config('graphql.types'))->toBe(['Shelf' => $type->bindName])
            ->and(app($type->bindName))->toBeInstanceOf(DiscoveredObjectType::class)
            ->and(app(TypeRegistry::class)->nameOf(Shelf::class, Position::Output))->toBe('Shelf');
    });

    it('still binds types and fills the registry when the configuration is cached', function () {
        isolateGraphQL();
        app()->instance('config_loaded_from_cache', true);

        $type = shelfType()->withBindName();
        $discovery = discoverGraphQL(ShelfQuery::class, $type);
        $discovery->apply();

        $action = iterator_to_array($discovery->getItems())[0];

        expect(app()->bound($type->bindName))->toBeTrue()
            ->and(app($action->bindName))->toBeInstanceOf(QueryField::class)
            ->and(app($type->bindName))->toBeInstanceOf(DiscoveredObjectType::class)
            ->and(app(TypeRegistry::class)->kindOf(Shelf::class, Position::Output))->toBe(TypeKind::Object)
            ->and(config('graphql.types'))->toBe([])
            ->and(config('graphql.schemas'))->toBe([]);
    });

    it('writes hand-written Rebing types to graphql.types rather than a schema', function () {
        isolateGraphQL();

        discoverGraphQL(PamphletType::class, PamphletQuery::class)->apply();

        expect(config('graphql.types'))->toBe(['Pamphlet' => PamphletType::class])
            ->and(config('graphql.schemas'))->not->toHaveKey('default');
    });

    it('lets a non-default schema built first resolve a hand-written type', function () {
        isolateGraphQL();

        discoverGraphQL(PamphletType::class, PamphletQuery::class)->apply();

        $schema = GraphQL::schema('pamphlets');
        $schema->assertValid();

        expect((string) $schema->getQueryType()?->getField('pamphlet')->getType())->toBe('Pamphlet!')
            ->and(array_keys(buildAllSchemas()))->toBe(['pamphlets']);
    });
});

describe('the workbench schema', function () {
    it('registers its hand-written types globally', function () {
        expect(config('graphql.types'))->toMatchArray([
            'Book' => BookType::class,
            'Author' => AuthorType::class,
            'User' => UserType::class,
        ])->and(config('graphql.schemas.default'))->not->toHaveKey('types');
    });

    it('prints the same schema as with the types listed per schema', function () {
        $sdl = SchemaPrinter::doPrint(GraphQL::schema());

        config()->set('graphql.schemas.default.types', config('graphql.types'));
        config()->set('graphql.types', []);
        app()->forgetInstance(RebingGraphQL::class);
        GraphQL::clearResolvedInstance(RebingGraphQL::class);

        expect(SchemaPrinter::doPrint(GraphQL::schema()))->toBe($sdl);
    });
});
