<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecoratorReference;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldSource;
use RuntimeException;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\GuardedCatalog;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\Member;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\MemberQuery;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\StaffOnlyGate;
use Tests\Fixtures\RebingGraphQL\Types\Decorators\Headline;
use Tests\Fixtures\RebingGraphQL\Types\Decorators\HeadlineQuery;
use Tests\Fixtures\RebingGraphQL\Types\Decorators\Uppercase;
use Tests\Fixtures\RebingGraphQL\Types\Decorators\UppercaseOnInt;
use Workbench\App\Models\User;

/**
 * @return array<string, mixed>
 */
function queryMember(string $selection, string $name = 'Ada'): array
{
    $json = test()->postJson('/graphql', ['query' => "{ member(name: \"$name\") { $selection } }"])
        ->assertOk()
        ->json();

    return is_array($json) ? $json : [];
}

/**
 * @param  class-string  $class
 */
function discoveredTypeOf(string $class): DiscoveredType
{
    foreach (discoverGraphQL($class)->getItems() as $item) {
        if ($item instanceof DiscoveredType) {
            return $item;
        }
    }

    throw new LogicException("$class was not discovered.");
}

/**
 * @return array<string, list<object>> decorators keyed by field name
 */
function decoratorsOf(DiscoveredType $type): array
{
    $decorators = [];

    foreach ($type->fields as $field) {
        $decorators[$field->name] = $field->decorators;
    }

    return $decorators;
}

describe('field authorization', function () {
    beforeEach(function () {
        Member::$resolved = [];
        StaffOnlyGate::$calls = [];
        Gate::define('viewContactDetails', fn(?User $user, Member $member) => $user !== null && $member->name === 'Ada');

        schemaSdl(MemberQuery::class, Member::class);
    });

    it('forces every authorized field nullable in the SDL', function () {
        expect(schemaSdl(MemberQuery::class, Member::class))->toContain(<<<'GRAPHQL'
            type Member {
              handle: String
              email: String
              salary: String
              notes: String
              phone: String
              address: String
              postcode: String
              vault: String
              name: String!
              birthday: String
              diary: String
            }
            GRAPHQL);

        buildAllSchemas();
    });

    it('keeps the #[Authorize] instances on the field, in declaration order, through serialization', function () {
        $type = discoveredTypeOf(Member::class);
        $address = decoratorsOf($type)['address'];

        expect(decoratorsOf($type)['name'])->toBe([])
            ->and($address)->toHaveCount(2)
            ->and($address[0])->toBeInstanceOf(Authorize::class)
            ->and($address[0]->ability ?? null)->toBeNull()
            ->and($address[1]->ability ?? null)->toBe('viewContactDetails')
            ->and(unserialize(serialize($type)))->toEqual($type);
    });

    describe('bare', function () {
        it('resolves the field for a signed-in caller', function () {
            $this->actingAs(new User());

            expect(queryMember('handle'))->toBe(['data' => ['member' => ['handle' => 'ada']]]);
        });

        it('resolves the field to null for a guest', function () {
            expect(queryMember('name handle'))->toBe(['data' => ['member' => ['name' => 'Ada', 'handle' => null]]]);
        });
    });

    describe('ability', function () {
        it('checks the ability against the parent object and resolves the field when allowed', function () {
            $this->actingAs(new User());

            expect(queryMember('email birthday'))->toBe(['data' => ['member' => [
                'email' => 'ada@example.com',
                'birthday' => '1815-12-10',
            ]]])
                ->and(Member::$resolved)->toBe(['birthday']);
        });

        it('resolves the field to null without running the resolver when denied', function () {
            $this->actingAs(new User());

            expect(queryMember('name email birthday', 'Grace'))->toBe(['data' => ['member' => [
                'name' => 'Grace',
                'email' => null,
                'birthday' => null,
            ]]])
                ->and(Member::$resolved)->toBe([]);
        });
    });

    describe('gate', function () {
        it('resolves the field when the gate allows, passing it the parent object', function () {
            $this->actingAs(new User(['name' => 'staff']));

            expect(queryMember('salary'))->toBe(['data' => ['member' => ['salary' => '100']]])
                ->and(StaffOnlyGate::$calls)->toHaveCount(1)
                ->and(StaffOnlyGate::$calls[0][0])->toBeInstanceOf(Member::class)
                ->and(StaffOnlyGate::$calls[0][1])->toBe('salary');
        });

        it('resolves the field to null when the gate denies', function () {
            $this->actingAs(new User(['name' => 'visitor']));

            expect(queryMember('salary'))->toBe(['data' => ['member' => ['salary' => null]]]);
        });
    });

    describe('onDenied: Denied::Error', function () {
        it('resolves the field when allowed', function () {
            $this->actingAs(new User(['name' => 'staff']));

            expect(queryMember('notes phone diary'))->toBe(['data' => ['member' => [
                'notes' => 'Prefers mornings',
                'phone' => '555-0100',
                'diary' => 'Dear diary',
            ]]]);
        });

        it('reports a Forbidden field error and leaves the rest of the object intact', function () {
            $this->actingAs(new User(['name' => 'visitor']));

            $this->postJson('/graphql', ['query' => '{ member { name notes } }'])
                ->assertOk()
                ->assertJsonPath('data.member', ['name' => 'Ada', 'notes' => null])
                ->assertJsonPath('errors.0.message', 'Forbidden')
                ->assertJsonPath('errors.0.path', ['member', 'notes'])
                ->assertJsonPath('errors.0.extensions.category', 'authorization');
        });

        it('reports the message: of the attribute', function () {
            $this->postJson('/graphql', ['query' => '{ member { phone } }'])
                ->assertOk()
                ->assertJsonPath('data.member.phone', null)
                ->assertJsonPath('errors.0.message', 'Sign in to see the phone number');
        });

        it('checks an ability against the parent object', function () {
            $this->actingAs(new User());

            expect(queryMember('postcode'))->toBe(['data' => ['member' => ['postcode' => '1815 AL']]]);

            $this->postJson('/graphql', ['query' => '{ member(name: "Grace") { name postcode } }'])
                ->assertOk()
                ->assertJsonPath('data.member', ['name' => 'Grace', 'postcode' => null])
                ->assertJsonPath('errors.0.message', 'Forbidden')
                ->assertJsonPath('errors.0.path', ['member', 'postcode']);
        });

        it('does not run the resolver when denied', function () {
            $this->postJson('/graphql', ['query' => '{ member { diary } }'])
                ->assertOk()
                ->assertJsonPath('data.member.diary', null)
                ->assertJsonPath('errors.0.message', 'Forbidden');

            expect(Member::$resolved)->toBe([]);
        });
    });

    describe('several #[Authorize] on one field', function () {
        it('resolves the field when every one passes', function () {
            $this->actingAs(new User());

            expect(queryMember('address'))->toBe(['data' => ['member' => ['address' => 'Analytical Street 1']]]);
        });

        it('resolves the field to null when any one fails', function () {
            $this->actingAs(new User());

            expect(queryMember('address', 'Grace'))->toBe(['data' => ['member' => ['address' => null]]]);
        });

        it('resolves the field to null for a guest', function () {
            Gate::define('viewContactDetails', fn(?User $user) => true);

            expect(queryMember('address'))->toBe(['data' => ['member' => ['address' => null]]]);
        });
    });

    describe('Denied::Null and Denied::Error on one field', function () {
        it('resolves the field when both pass', function () {
            $this->actingAs(new User(['name' => 'staff']));

            expect(queryMember('vault'))->toBe(['data' => ['member' => ['vault' => 'Engine plans']]]);
        });

        it('resolves to null without an error when the null check fails, and never reaches the error check', function () {
            $this->actingAs(new User(['name' => 'staff']));

            expect(queryMember('vault', 'Grace'))->toBe(['data' => ['member' => ['vault' => null]]])
                ->and(StaffOnlyGate::$calls)->toBe([]);
        });

        it('reports the error when only the error check fails', function () {
            $this->actingAs(new User(['name' => 'visitor']));

            $this->postJson('/graphql', ['query' => '{ member { vault } }'])
                ->assertOk()
                ->assertJsonPath('data.member.vault', null)
                ->assertJsonPath('errors.0.message', 'Forbidden');
        });
    });
});

describe('class-level #[Authorize] on a #[Type] with actions', function () {
    beforeEach(function () {
        schemaSdl(GuardedCatalog::class);
    });

    it('leaves the fields alone', function () {
        expect(schemaSdl(GuardedCatalog::class))->toContain(<<<'GRAPHQL'
            type GuardedCatalog {
              title: String!
            }
            GRAPHQL)
            ->and(decoratorsOf(discoveredTypeOf(GuardedCatalog::class)))->toBe(['title' => []]);
    });

    it('still authorizes the action', function () {
        $this->postJson('/graphql', ['query' => '{ catalog { title } }'])
            ->assertOk()
            ->assertJsonPath('data.catalog', null)
            ->assertJsonPath('errors.0.message', 'Unauthorized');

        $this->actingAs(new User());

        $this->postJson('/graphql', ['query' => '{ catalog { title } }'])
            ->assertOk()
            ->assertExactJson(['data' => ['catalog' => ['title' => 'Spring catalog']]]);
    });
});

describe('custom field decorators', function () {
    it('adjusts the definition and wraps the resolver', function () {
        expect(schemaSdl(HeadlineQuery::class, Headline::class))->toContain(<<<'GRAPHQL'
            type Headline {
              plain: String!
              shouted: String
              empty: String
              reversed: String!
              teaser(prefix: String = ""): String
            }
            GRAPHQL);

        buildAllSchemas();

        $this->postJson('/graphql', ['query' => '{ headline { plain shouted empty reversed teaser(prefix: "> ") } }'])
            ->assertOk()
            ->assertExactJson(['data' => ['headline' => [
                'plain' => 'quiet news',
                'shouted' => 'LOUD NEWS',
                'empty' => null,
                'reversed' => '[cba]',
                'teaser' => '> READ MORE',
            ]]]);
    });

    it('stores serializable decorators as instances, and the rest as references read again by reflection', function () {
        $type = discoveredTypeOf(Headline::class);
        $decorators = decoratorsOf($type);

        expect($decorators['shouted'])->toEqual([new Uppercase()])
            ->and($decorators['reversed'])->toEqual([
                new FieldDecoratorReference(FieldSource::Property, 'reversed', 0),
                new FieldDecoratorReference(FieldSource::Property, 'reversed', 1),
            ])
            ->and(unserialize(serialize($type)))->toEqual($type);
    });

    it('reports a reference whose attribute is gone', function () {
        expect(fn() => new FieldDecoratorReference(FieldSource::Property, 'plain', 0)->resolve(Headline::class))
            ->toThrow(RuntimeException::class, 'Field decorator #0 on ' . Headline::class . '::plain is gone. Run discovery:clear after changing a #[Type] class.');
    });

    it('lets a decorator reject a field it cannot apply to', function () {
        expect(fn() => discoverGraphQL(UppercaseOnInt::class))
            ->toThrow(LogicException::class, 'Property ' . UppercaseOnInt::class . '::$count has #[Uppercase] but is not a string.');
    });
});

describe('rejections', function () {
    it('rejects shapes that cannot work', function (string $class, string $message) {
        expect(fn() => discoverGraphQL($class))->toThrow(LogicException::class, $message);
    })->with([
        '#[Authorize] on a #[Type] class without actions' => [
            Invalid\AuthorizeOnTypeClass::class,
            '#[Authorize] on the #[Type] class ' . Invalid\AuthorizeOnTypeClass::class . ' has nothing to apply to: on a class it only reaches #[Query] and #[Mutation] methods, never fields. Put it on each property or #[Field] method it should guard.',
        ],
        '#[Authorize] on a method without #[Field]' => [
            Invalid\AuthorizeOnPlainMethod::class,
            'Method ' . Invalid\AuthorizeOnPlainMethod::class . '::secret() has #[Authorize] but is not a field. Add #[Field], or remove #[Authorize].',
        ],
        'an ability and a gate' => [
            Invalid\AbilityAndGate::class,
            'Property ' . Invalid\AbilityAndGate::class . '::$name has #[Authorize] with both an ability and gate:.',
        ],
        'a gate that is no AuthorizationGate' => [
            Invalid\GateNotAGate::class,
            'Property ' . Invalid\GateNotAGate::class . '::$name has #[Authorize(gate: ' . Member::class . ')], which does not implement',
        ],
        'message: on a field that resolves to null' => [
            Invalid\MessageWithoutError::class,
            'Property ' . Invalid\MessageWithoutError::class . '::$name has #[Authorize(message:)], but a denied field resolves to null, so the message is never shown. Add onDenied: Denied::Error, or remove message:.',
        ],
        '#[Authorize] on an ignored property' => [
            Invalid\AuthorizeOnIgnoredProperty::class,
            'Property ' . Invalid\AuthorizeOnIgnoredProperty::class . '::$name has #[Authorize] but is not a field',
        ],
        '#[Authorize] on a private property' => [
            Invalid\AuthorizeOnPrivateProperty::class,
            'Property ' . Invalid\AuthorizeOnPrivateProperty::class . '::$name has #[Authorize] but is not a field',
        ],
        'onDenied: on a query' => [
            Invalid\OnDeniedQuery::class,
            'Method ' . Invalid\OnDeniedQuery::class . '::secret has #[Authorize(onDenied:)], which only applies to a field of a #[Type].',
        ],
        'onDenied: on a parameter' => [
            Invalid\OnDeniedParameterQuery::class,
            '#[Authorize(onDenied:)] on the parameter $user in ' . Invalid\OnDeniedParameterQuery::class . '::userName only applies to a field of a #[Type].',
        ],
    ]);
});
