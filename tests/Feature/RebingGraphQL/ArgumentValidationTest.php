<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

/**
 * The shipped LaravelValidationRules adapter, driven end to end through real GraphQL requests.
 */
it('applies validation attributes on an action parameter', function () {
    $this->postJson('/graphql', ['query' => '{ attributeHello(name: "ab") }'])
        ->assertOk()
        ->assertJsonPath('data.attributeHello', null)
        ->assertJsonPath('errors.0.extensions.validation.name.0', 'The name field must be at least 3 characters.');

    $this->postJson('/graphql', ['query' => '{ attributeHello(name: "Niels") }'])
        ->assertOk()
        ->assertJsonPath('data.attributeHello', 'hi, Niels');
});

it('merges #[Arg(rules:)] with validation attributes on the same parameter', function () {
    // Fails only the attribute rule.
    $this->postJson('/graphql', ['query' => '{ bothRulesHello(name: "Na") }'])
        ->assertOk()
        ->assertJsonPath('errors.0.extensions.validation.name.0', 'The name field must be at least 3 characters.');

    // Fails only the #[Arg(rules:)] rule — proof the parent's rules were not clobbered.
    $this->postJson('/graphql', ['query' => '{ bothRulesHello(name: "Bert") }'])
        ->assertOk()
        ->assertJsonPath('errors.0.extensions.validation.name.0', 'The name field must start with one of the following: N.');

    $this->postJson('/graphql', ['query' => '{ bothRulesHello(name: "Niels") }'])
        ->assertOk()
        ->assertJsonPath('data.bothRulesHello', 'hi, Niels');
});

it('reports rules under the GraphQL arg name when #[Arg] renames the parameter', function () {
    $this->postJson('/graphql', ['query' => '{ renamedHello(nickname: "ab") }'])
        ->assertOk()
        ->assertJsonPath('errors.0.extensions.validation.nickname.0', 'The nickname field must be at least 3 characters.');
});

it('surfaces a custom message from #[Rule(message:)]', function () {
    $this->postJson('/graphql', ['query' => '{ messageHello(name: "ab") }'])
        ->assertOk()
        ->assertJsonPath('errors.0.extensions.validation.name.0', 'Far too short, that.');
});

it('infers sometimes for an optional parameter, so omitting it passes', function () {
    $this->postJson('/graphql', ['query' => '{ optionalHello }'])
        ->assertOk()
        ->assertJsonPath('data.optionalHello', 'hi, nobody');

    $this->postJson('/graphql', ['query' => '{ optionalHello(name: "ab") }'])
        ->assertOk()
        ->assertJsonPath('errors.0.extensions.validation.name.0', 'The name field must be at least 3 characters.');
});

it('validates every element of a list arg at its own path', function () {
    $this->postJson('/graphql', [
        'query' => '{ notifyAll(recipients: ["a@example.com", "nope"]) }',
    ])
        ->assertOk()
        // Keyed per element, so the offending one is named. Asserted whole, since a dot inside a
        // key cannot be escaped in an assertJsonPath() expression.
        ->assertJsonPath('errors.0.extensions.validation', [
            'recipients.1' => ['The recipients.1 field must be a valid email address.'],
        ]);

    $this->postJson('/graphql', [
        'query' => '{ notifyAll(recipients: ["a@example.com"]) }',
    ])
        ->assertOk()
        ->assertJsonPath('data.notifyAll', ['a@example.com']);
});
