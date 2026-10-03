<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeAccount;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeAccountActions;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeAccountOutputOnly;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeAccountWithPlan;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeCreateTeam;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeCreateUser;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeCreateUserWithRole;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeTeamMutations;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserMutations;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserQuery;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserWithBilling;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserWithTier;
use Tests\Fixtures\RebingGraphQL\Replacement\Invalid\AcmeFlattenedMutation;
use Tests\Fixtures\RebingGraphQL\Replacement\Invalid\AcmeOrphanUser;
use Tests\Fixtures\RebingGraphQL\Replacement\Invalid\AcmeRivalUser;

/** The text of the top-level definition that starts with the prefix. */
function definitionStartingWith(string $sdl, string $prefix): string
{
    return array_values(array_filter(sdlDefinitions($sdl), static fn(string $definition): bool => str_contains($definition, "\n" . $prefix . " ") || str_starts_with($definition, $prefix . " ")))[0] ?? '';
}

describe('output types', function () {
    it('serves one type under the parent name with the replacer fields', function () {
        $sdl = schemaSdl(AcmeUserQuery::class, AcmeUserWithBilling::class, AcmeUser::class);
        $type = definitionStartingWith($sdl, 'type AcmeUser');

        expect($type)->toContain('name: String!')->toContain('billingReference: String')
            ->and($sdl)->not->toContain('AcmeUserWithBilling')
            ->and(substr_count($sdl, 'type AcmeUser'))->toBe(1);
    });

    it('resolves the replacer and the parent through the same type', function () {
        schemaSdl(AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class);
        $registry = app(TypeRegistry::class);

        expect($registry->nameOf(AcmeUser::class, Position::Output))->toBe('AcmeUser')
            ->and($registry->nameOf(AcmeUserWithBilling::class, Position::Output))->toBe('AcmeUser')
            ->and(GraphQL::query('{ billedUser { name billingReference } }'))->toBe(['data' => ['billedUser' => ['name' => 'Ada', 'billingReference' => 'B-1']]]);
    });

    it('resolves a field the returned object lacks to null without an error', function () {
        schemaSdl(AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class);

        expect(GraphQL::query('{ user { name billingReference } }'))->toBe(['data' => ['user' => ['name' => 'Ada', 'billingReference' => null]]]);
    });

    it('registers a single type in the config', function () {
        schemaSdl(AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class);

        expect(array_keys(array_filter(config()->array('graphql.types'), static fn(string $bind): bool => str_starts_with($bind, 'discovery.'))))->toBe(['AcmeUser']);
    });

    it('inherits the description of the replaced type', function () {
        schemaSdl(AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class);

        expect(app(TypeRegistry::class)->typeNamed('AcmeUser')?->description)->toBe('An Acme user');
    });

    it('keeps its own description when it has one', function () {
        schemaSdl(AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class, AcmeUserWithTier::class);

        expect(app(TypeRegistry::class)->typeNamed('AcmeUser')?->description)->toBe('A user with a tier');
    });

    it('lets a replacement be replaced again', function () {
        $sdl = schemaSdl(AcmeUserWithTier::class, AcmeUserQuery::class, AcmeUserWithBilling::class, AcmeUser::class);
        $registry = app(TypeRegistry::class);

        expect(definitionStartingWith($sdl, 'type AcmeUser'))->toContain('billingReference: String')->toContain('tier: String')
            ->and($sdl)->not->toContain('AcmeUserWithBilling')->not->toContain('AcmeUserWithTier')
            ->and($registry->effective(AcmeUser::class, TypeKind::Object))->toBe(AcmeUserWithTier::class)
            ->and($registry->nameOf(AcmeUserWithBilling::class, Position::Output))->toBe('AcmeUser')
            ->and(GraphQL::query('{ billedUser { name billingReference } }'))->toBe(['data' => ['billedUser' => ['name' => 'Ada', 'billingReference' => 'B-1']]]);
    });

    it('serves the replaced type from the discovery cache', function () {
        applyCachedGraphQL([AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class]);

        expect(GraphQL::query('{ billedUser { name billingReference } user { billingReference } }'))->toBe(['data' => [
            'billedUser' => ['name' => 'Ada', 'billingReference' => 'B-1'],
            'user' => ['billingReference' => null],
        ]]);
    });

    it('binds only the replacement while the config is cached', function () {
        $registry = assertBoundWhenConfigCached(
            [AcmeUserQuery::class, AcmeUser::class, AcmeUserWithBilling::class],
            static fn($type): bool => $type->class === AcmeUserWithBilling::class,
        );

        expect($registry->nameOf(AcmeUser::class, Position::Output))->toBe('AcmeUser');
    });
});

describe('input types', function () {
    it('serves one input under the parent name with the replacer fields', function () {
        $sdl = schemaSdl(AcmeUserMutations::class, AcmeCreateUserWithRole::class, AcmeCreateUser::class);

        expect(definitionStartingWith($sdl, 'input AcmeCreateUserInput'))->toContain('name: String!')->toContain('role: String')
            ->and($sdl)->not->toContain('AcmeCreateUserWithRole');
    });

    it('hydrates the replacer into a resolver typed against the parent', function () {
        schemaSdl(AcmeUserMutations::class, AcmeCreateUser::class, AcmeCreateUserWithRole::class);

        expect(GraphQL::query('mutation { createUser(input: {name: "Ada", role: "admin"}) }'))->toBe(['data' => ['createUser' => 'Ada']])
            ->and(AcmeUserMutations::$received)->toBe([AcmeCreateUserWithRole::class]);
    });

    it('hydrates the replacer in nested inputs and lists', function () {
        schemaSdl(AcmeUserMutations::class, AcmeTeamMutations::class, AcmeCreateUser::class, AcmeCreateUserWithRole::class, AcmeCreateTeam::class);

        $result = GraphQL::query('mutation { createTeam(team: {owner: {name: "Ada", role: "admin"}, members: [{name: "Grace"}, {name: "Alan", role: "owner"}]}) }');

        expect($result)->toBe(['data' => ['createTeam' => 'Ada']])
            ->and(AcmeUserMutations::$received)->toBe(array_fill(0, 3, AcmeCreateUserWithRole::class));
    });

    it('validates the replacer fields and the inherited ones', function () {
        AcmeUserMutations::$received = [];
        schemaSdl(AcmeUserMutations::class, AcmeTeamMutations::class, AcmeCreateUser::class, AcmeCreateUserWithRole::class, AcmeCreateTeam::class);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation { createTeam(team: {owner: {name: "A", role: "administrator"}, members: [{name: "Grace", role: "superintendent"}]}) }',
        ])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toEqual([
            'team.owner.name' => ['The team.owner.name field must be at least 2 characters.'],
            'team.owner.role' => ['The team.owner.role field must not be greater than 10 characters.'],
            'team.members.0.role' => ['The team.members.0.role field must not be greater than 10 characters.'],
        ])->and(AcmeUserMutations::$received)->toBe([]);
    });

    it('hydrates the parent when nothing replaces it', function () {
        schemaSdl(AcmeUserMutations::class, AcmeCreateUser::class);

        expect(GraphQL::query('mutation { createUser(input: {name: "Ada"}) }'))->toBe(['data' => ['createUser' => 'Ada']])
            ->and(AcmeUserMutations::$received)->toBe([AcmeCreateUser::class]);
    });

    it('applies a replaced input that nothing uses yet', function () {
        isolateGraphQL();
        discoverGraphQL(AcmeCreateUser::class, AcmeCreateUserWithRole::class)->apply();

        expect(app(TypeRegistry::class)->effective(AcmeCreateUser::class, TypeKind::Input))->toBe(AcmeCreateUserWithRole::class)
            ->and(app(TypeRegistry::class)->has(AcmeCreateUser::class, Position::Input))->toBeFalse();
    });

    it('serves the replaced input from the discovery cache', function () {
        applyCachedGraphQL([AcmeUserMutations::class, AcmeCreateUser::class, AcmeCreateUserWithRole::class]);

        expect(GraphQL::query('mutation { createUser(input: {name: "Ada", role: "admin"}) }'))->toBe(['data' => ['createUser' => 'Ada']])
            ->and(AcmeUserMutations::$received)->toBe([AcmeCreateUserWithRole::class]);
    });
});

describe('a class that is both a type and an input', function () {
    it('replaces each kind that asks for it', function () {
        $sdl = schemaSdl(AcmeAccountActions::class, AcmeAccount::class, AcmeAccountWithPlan::class);

        expect(definitionStartingWith($sdl, 'type AcmeAccount'))->toContain('plan: String!')
            ->and(definitionStartingWith($sdl, 'input AcmeAccountInput'))->toContain('plan: String');
    });

    it('leaves the other kind alone', function () {
        $sdl = schemaSdl(AcmeAccountActions::class, AcmeAccount::class, AcmeAccountOutputOnly::class);

        expect(definitionStartingWith($sdl, 'type AcmeAccount'))->toContain('plan: String!')
            ->and(definitionStartingWith($sdl, 'input AcmeAccountInput'))->not->toContain('plan');
    });
});

describe('rejections', function () {
    it('rejects a replacement whose parent is not a discovered type', function () {
        expect(fn() => discoverGraphQL(AcmeOrphanUser::class)->apply())
            ->toThrow(\LogicException::class, '#[Type(replace: true)] on ' . AcmeOrphanUser::class . ', but no parent class of it is a discovered #[Type].');
    });

    it('rejects an input replacement whose parent is only a type', function () {
        expect(fn() => discoverGraphQL(AcmeUser::class, AcmeCreateUserWithRole::class)->apply())
            ->toThrow(\LogicException::class, '#[Input(replace: true)] on ' . AcmeCreateUserWithRole::class . ', but no parent class of it is a discovered #[Input].');
    });

    it('rejects two replacements of one type', function () {
        expect(fn() => discoverGraphQL(AcmeUser::class, AcmeUserWithBilling::class, AcmeRivalUser::class)->apply())
            ->toThrow(\LogicException::class, AcmeUserWithBilling::class . ' and ' . AcmeRivalUser::class . ' both replace ' . AcmeUser::class);
    });

    it('rejects flattening a replaced input', function () {
        expect(fn() => discoverGraphQL(AcmeCreateUser::class, AcmeCreateUserWithRole::class, AcmeFlattenedMutation::class)->apply())
            ->toThrow(\LogicException::class, '#[AsArgs] on the parameter $input in ' . AcmeFlattenedMutation::class . '::createUser flattens ' . AcmeCreateUser::class . ', which ' . AcmeCreateUserWithRole::class . ' replaces');
    });

    it('rejects at discovery', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'a type with a name' => [
            fn() => new #[Type(replace: true, name: 'Other')] class extends AcmeUser {},
            '#[Type(replace: true)] on %1$s cannot set name:',
        ],
        'an input with a name' => [
            fn() => new #[Input(replace: true, name: 'Other')] class ('Ada') extends AcmeCreateUser {},
            '#[Input(replace: true)] on %1$s cannot set name:',
        ],
        'a type without a parent' => [
            fn() => new #[Type(replace: true)] class {},
            '#[Type(replace: true)] on %1$s, which has no parent class.',
        ],
        'an input without a parent' => [
            fn() => new #[Input(replace: true)] class {},
            '#[Input(replace: true)] on %1$s, which has no parent class.',
        ],
    ]);
});
