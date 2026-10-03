<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

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
use Tests\Fixtures\RebingGraphQL\Factories\AcmeCompanyQuery;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeYielded;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeYieldedFields;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeYieldedQuery;

/**
 * @param  list<Field>  $fields
 */
function yieldFactoryFields(array $fields): string
{
    app()->instance(AcmeYieldedFields::class, new AcmeYieldedFields($fields));

    return schemaSdl(AcmeYieldedQuery::class, AcmeYielded::class);
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
        expect(sdlDefinitions(schemaSdl(AcmeCompanyQuery::class, AcmeCompany::class)))->toContain(<<<'GRAPHQL'
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
        schemaSdl(AcmeCompanyQuery::class, AcmeCompany::class);

        expect(GraphQL::query('{ company { name region tags } }')['data']['company'] ?? null)
            ->toBe(['name' => 'Acme', 'region' => 'EU', 'tags' => ['anvils']]);
    });

    it('resolves a factory field the root does not have to null', function () {
        yieldFactoryFields([new Field(name: 'region', type: 'string', nullable: true)]);

        expect(GraphQL::query('{ yielded { name region } }'))
            ->toBe(['data' => ['yielded' => ['name' => 'Acme', 'region' => null]]]);
    });

    it('rejects a factory field named like a declared field', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'name', type: 'string')]))
            ->toThrow(LogicException::class, sprintf(
                'The type factory %s yields a field "name" that type [AcmeYielded] (%s) already has.',
                AcmeYieldedFields::class,
                AcmeYielded::class,
            ));
    });

    it('rejects two factory fields with one name', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'region', type: 'string'), new Field(name: 'region', type: 'int')]))
            ->toThrow(LogicException::class, 'yields a field "region" that type [AcmeYielded]');
    });

    it('rejects a factory field without a name', function () {
        expect(fn() => yieldFactoryFields([new Field(type: 'string')]))
            ->toThrow(LogicException::class, sprintf('yielded a field without a name for type [AcmeYielded] (%s). Set name: on the Field.', AcmeYielded::class));
    });

    it('rejects a factory field without a type', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'region')]))
            ->toThrow(LogicException::class, sprintf('Field "region" from the type factory %s for type [AcmeYielded] (%s) has no type.', AcmeYieldedFields::class, AcmeYielded::class));
    });

    it('rejects a factory field with both type and of', function () {
        expect(fn() => yieldFactoryFields([new Field(name: 'region', type: 'string', of: 'string')]))
            ->toThrow(LogicException::class, 'sets both type: and of:');
    });

    it('rejects a container binding that is no TypeFactory', function () {
        app()->instance(AcmeYieldedFields::class, new stdClass());

        expect(fn() => schemaSdl(AcmeYieldedQuery::class, AcmeYielded::class))
            ->toThrow(LogicException::class, 'resolves to stdClass, which is no ' . TypeFactory::class);
    });

    it('does not run the factory during discovery', function () {
        app()->instance(AcmeYieldedFields::class, new class implements TypeFactory {
            public function fields(TypeContext $context): iterable
            {
                throw new RuntimeException('ran');
            }
        });

        expect(discoveredTypes(AcmeYielded::class))->toHaveCount(1);
    });
});
