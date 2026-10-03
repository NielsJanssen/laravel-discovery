<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;
use Rebing\GraphQL\Support\Facades\GraphQL;
use RuntimeException;
use stdClass;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeCompany;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeCompanyFields;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeFactoryQueries;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeLookup;

const FACTORY_SOURCES = [AcmeFactoryQueries::class, AcmeCompany::class, AcmeLookup::class];

/**
 * @param  list<Field>  $fields
 */
function yieldFactoryFields(array $fields): string
{
    app()->instance(AcmeCompanyFields::class, new AcmeCompanyFields($fields));

    return schemaSdl(...FACTORY_SOURCES);
}

describe('discovery', function () {
    it('keeps the factory and the naming override on the discovered type', function () {
        $type = discoveredTypes(AcmeCompany::class)[0];

        expect($type->factory)->toBe(AcmeCompanyFields::class)
            ->and($type->naming)->toBeNull()
            ->and(array_column($type->fields, 'name'))->toBe(['name'])
            ->and(unserialize(serialize($type)))->toEqual($type);
    });

    it('keeps the naming override of #[Type(naming:)]', function () {
        $type = discoveredTypes(new #[Type(naming: FieldCase::Snake, factory: AcmeCompanyFields::class)] class {
            public string $legalName = 'Acme';
        })[0];

        expect($type->naming)->toBe(FieldCase::Snake);
    });

    it('rejects a factory it cannot use', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'a class that is no TypeFactory' => [
            fn() => new #[Type(factory: stdClass::class)] class {
                public string $name = 'Acme';
            },
            '#[Type(factory:)] on %1$s names stdClass, which is no class implementing ' . TypeFactory::class . '.',
        ],
        'a class that does not exist' => [
            fn() => new #[Type(factory: 'Acme\Missing\Fields')] class {
                public string $name = 'Acme';
            },
            '#[Type(factory:)] on %1$s names Acme\Missing\Fields, which is no class implementing ' . TypeFactory::class . '.',
        ],
        'a factory on an #[Input]' => [
            fn() => new #[Input(factory: AcmeCompanyFields::class)] class {
                public function __construct(public string $name = 'Acme') {}
            },
            '#[Input(factory:)] on %1$s is not supported yet: a type factory only contributes fields to a #[Type]. Remove factory:.',
        ],
    ]);
});

describe('building', function () {
    it('merges the factory fields after the declared ones', function () {
        expect(sdlDefinitions(schemaSdl(...FACTORY_SOURCES)))->toContain(<<<'GRAPHQL'
            type AcmeCompany {
              name: String!

              "Sales region"
              region: String!

              tags: [String!]
              kind: String! @deprecated(reason: "Use region")
            }
            GRAPHQL);
    });

    it('resolves a factory field from the property of the root', function () {
        schemaSdl(...FACTORY_SOURCES);

        expect(GraphQL::query('{ company { name region tags } }'))
            ->toBe(['data' => ['company' => ['name' => 'Acme', 'region' => 'EU', 'tags' => ['anvils']]]]);
    });

    it('resolves a factory field the root does not have to null', function () {
        yieldFactoryFields([new Field(name: 'motto', type: 'string', nullable: true)]);

        expect(GraphQL::query('{ company { name motto } }'))
            ->toBe(['data' => ['company' => ['name' => 'Acme', 'motto' => null]]]);
    });

    it('rejects a factory field named like a declared field', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'name', type: 'string')]))
            ->toThrow(LogicException::class, sprintf(
                'The type factory %s yields a field "name" that type [AcmeCompany] (%s) already has.',
                AcmeCompanyFields::class,
                AcmeCompany::class,
            ));
    });

    it('rejects two factory fields with one name', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'region', type: 'string'), new Field(name: 'region', type: 'int')]))
            ->toThrow(LogicException::class, 'yields a field "region" that type [AcmeCompany]');
    });

    it('rejects a factory field without a name', function () {
        expect(fn() => yieldFactoryFields([new Field(type: 'string')]))
            ->toThrow(LogicException::class, sprintf('yielded a field without a name for type [AcmeCompany] (%s). Set name: on the Field.', AcmeCompany::class));
    });

    it('rejects a factory field without a type', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'region')]))
            ->toThrow(LogicException::class, sprintf('Field "region" from the type factory %s for type [AcmeCompany] (%s) has no type.', AcmeCompanyFields::class, AcmeCompany::class));
    });

    it('rejects a factory field with both type and of', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'region', type: 'string', of: 'string')]))
            ->toThrow(LogicException::class, 'sets both type: and of:');
    });

    it('rejects a container binding that is no TypeFactory', function () {
        app()->instance(AcmeCompanyFields::class, new stdClass());

        expect(fn() => schemaSdl(...FACTORY_SOURCES))
            ->toThrow(LogicException::class, 'resolves to stdClass, which is no ' . TypeFactory::class);
    });

    it('does not run the factory during discovery', function () {
        app()->instance(AcmeCompanyFields::class, new class implements TypeFactory {
            public function fields(TypeContext $context): iterable
            {
                throw new RuntimeException('ran');
            }
        });

        expect(discoveredTypes(AcmeCompany::class))->toHaveCount(1);
    });
});

describe('resolvers and args', function () {
    it('prints the args of a factory field, named by the argument strategy', function () {
        expect(sdlDefinitions(schemaSdl(...FACTORY_SOURCES)))->toContain(<<<'GRAPHQL'
            type AcmeLookup {
              name: String!

              "Looks a region up"
              regionLabel(
                "The code"
                region_code: String!

                upper: Boolean
              ): String!
            }
            GRAPHQL);
    });

    it('runs the resolve closure with the args keyed as declared, and injects the factory dependencies', function () {
        schemaSdl(...FACTORY_SOURCES);

        expect(GraphQL::query('{ lookup { regionLabel(region_code: "eu") } }'))->toBe(['data' => ['lookup' => ['regionLabel' => 'Region eu!']]])
            ->and(GraphQL::query('{ lookup { regionLabel(region_code: "eu", upper: true) } }'))->toBe(['data' => ['lookup' => ['regionLabel' => 'REGION EU!']]]);
    });

    it('passes the root and the resolve info to the closure', function () {
        $seen = [];
        $field = new Field(name: 'seen', type: 'string', resolve: function (mixed $root, array $args, mixed $context, ResolveInfo $info) use (&$seen): string {
            $seen = [$root::class, $args, $info->fieldName];

            return 'ok';
        });

        yieldFactoryFields([$field]);

        expect(GraphQL::query('{ company { seen } }'))->toBe(['data' => ['company' => ['seen' => 'ok']]])
            ->and($seen)->toBe([AcmeCompany::class, [], 'seen']);
    });

    it('keeps arg names as declared without a naming strategy', function () {
        $sdl = yieldFactoryFields([new Field(name: 'echo', type: 'string', args: ['someText' => new Field(of: 'string')], resolve: fn($root, array $args): string => implode(',', $args['someText']))]);

        expect($sdl)->toContain('echo(someText: [String!]!): String!')
            ->and(GraphQL::query('{ company { echo(someText: ["a", "b"]) } }'))->toBe(['data' => ['company' => ['echo' => 'a,b']]]);
    });

    it('serves a factory type from the discovery cache', function () {
        applyCachedGraphQL(FACTORY_SOURCES);

        expect(GraphQL::query('{ lookup { name regionLabel(region_code: "nl") } }'))->toBe(['data' => ['lookup' => ['name' => 'Acme', 'regionLabel' => 'Region nl!']]]);
    });

    it('rejects what a field arg cannot do', function (Field $arg, string $message) {
        expect(fn() => yieldFactoryFields([new Field(name: 'echo', type: 'string', args: ['text' => $arg])]))
            ->toThrow(LogicException::class, $message);
    })->with([
        'rules' => [new Field(type: 'string', rules: ['min:2']), 'sets rules:, which are not applied to the args of a type field'],
        'no type' => [new Field(), 'Argument "echo(text)" from the type factory'],
        'type and of' => [new Field(type: 'string', of: 'string'), 'sets both type: and of:'],
    ]);

    it('rejects two args that end up with one name', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'echo', type: 'string', args: ['text' => new Field(type: 'string'), 'text ' => new Field(type: 'string')])]))
            ->toThrow(LogicException::class);
    });

    it('rejects #[Field(resolve:)] and #[Field(args:)] on a declared member', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'resolve on a property' => [
            fn() => new #[Type] class {
                #[Field(resolve: static function (): string {
                    return 'x';
                })]
                public string $name = 'Acme';
            },
            'Property %1$s::$name has #[Field(resolve:)] or #[Field(args:)], which only a field yielded by a TypeFactory can set.',
        ],
        'args on a method' => [
            fn() => new #[Type] class {
                #[Field(args: ['a' => new Field(type: 'string')])]
                public function name(): string
                {
                    return 'Acme';
                }
            },
            'Method %1$s::name() has #[Field(resolve:)] or #[Field(args:)], which only a field yielded by a TypeFactory can set.',
        ],
    ]);
});
