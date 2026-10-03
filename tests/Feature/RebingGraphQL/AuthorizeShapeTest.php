<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AuthorizationGate;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Validation\Rule\Can;
use stdClass;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\Member;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\StaffOnlyGate;
use Workbench\App\Models\User;

describe('Authorize::verifyOnParameter()', function () {
    it('rejects #[Authorize] on a parameter that cannot work', function (Authorize $authorize, string $message) {
        expect(fn() => $authorize->verifyOnParameter('the parameter $note in Acme::get'))->toThrow(LogicException::class, $message);
    })->with([
        'no ability' => [new Authorize(), '#[Authorize] on the parameter $note in Acme::get needs an ability'],
        'gate' => [new Authorize('view', gate: stdClass::class), '#[Authorize(gate:)] on the parameter $note in Acme::get is not supported'],
        'onDenied' => [new Authorize('view', onDenied: Denied::Error), '#[Authorize(onDenied:)] on the parameter $note in Acme::get only applies to a field'],
    ]);

    it('accepts an ability on a parameter', function () {
        expect(fn() => new Authorize('view')->verifyOnParameter('the parameter $note in Acme::get'))->not->toThrow(LogicException::class);
    });
});

describe('Authorize::verifyOnProperty()', function () {
    it('rejects #[Authorize] on a property that cannot work', function (Authorize $authorize, bool $shared, string $message) {
        expect(fn() => $authorize->verifyOnProperty('Property Acme::$owner', $shared))->toThrow(LogicException::class, $message);
    })->with([
        'no ability' => [new Authorize(), false, '#[Authorize] on property Acme::$owner needs an ability'],
        'gate' => [new Authorize('view', gate: stdClass::class), true, '#[Authorize(gate:)] on property Acme::$owner is not supported'],
        'onDenied on an input' => [new Authorize('view', onDenied: Denied::Error), false, '#[Authorize(onDenied:)] on property Acme::$owner only applies to a field'],
    ]);

    it('allows onDenied on a property of a shared class', function () {
        expect(fn() => new Authorize('view', onDenied: Denied::Error)->verifyOnProperty('Property Acme::$owner', true))->not->toThrow(LogicException::class);
    });
});

describe('Authorize::verifyOnAction()', function () {
    it('rejects #[Authorize] on an action that cannot work', function (array $authorizations, string $message) {
        expect(fn() => Authorize::verifyOnAction($authorizations, 'Acme', 'run', 'Query'))->toThrow(LogicException::class, $message);
    })->with([
        'onDenied' => [[new Authorize(onDenied: Denied::Error)], 'Method Acme::run has #[Authorize(onDenied:)], which only applies to a field of a #[Type]. A denied #[Query] always reports an error; remove onDenied:.'],
        'ability and gate' => [[new Authorize('view', gate: stdClass::class)], "Method Acme::run has #[Authorize] with both an ability and gate:. A gate decides on its own: remove the ability, or move it to the model-bound parameter as #[Authorize('view')]."],
        'gate that is no gate' => [[new Authorize(gate: stdClass::class)], 'Method Acme::run has #[Authorize(gate: stdClass)], which does not implement ' . AuthorizationGate::class . '.'],
        'ability' => [[new Authorize('view')], "has #[Authorize('view')] on the class or method"],
        'onDenied before another violation' => [[new Authorize('view'), new Authorize(onDenied: Denied::Error)], 'has #[Authorize(onDenied:)]'],
    ]);

    it('accepts a bare #[Authorize] on an action', function () {
        expect(fn() => Authorize::verifyOnAction([new Authorize()], 'Acme', 'run', 'Query'))->not->toThrow(LogicException::class);
    });
});

describe('discovery', function () {
    it('rejects an #[Authorize] that cannot work where discovery meets it', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'an ability and a gate on a field' => [
            fn() => new #[Type] class {
                #[Authorize('view', gate: StaffOnlyGate::class)]
                public string $name = 'x';
            },
            'Property %1$s::$name has #[Authorize] with both an ability and gate:.',
        ],
        'a gate that is no AuthorizationGate on a field' => [
            fn() => new #[Type] class {
                #[Authorize(gate: Member::class)]
                public string $name = 'x';
            },
            'Property %1$s::$name has #[Authorize(gate: ' . Member::class . ')], which does not implement',
        ],
        'message: without onDenied: on a field' => [
            fn() => new #[Type] class {
                #[Authorize(message: 'Nope')]
                public string $name = 'x';
            },
            'Property %1$s::$name has #[Authorize(message:)], but a denied field resolves to null, so the message is never shown. Add onDenied: Denied::Error, or remove message:.',
        ],
        'on an ignored property' => [
            fn() => new #[Type] class {
                #[Ignore, Authorize]
                public string $name = 'x';
            },
            'Property %1$s::$name has #[Authorize] but is not a field',
        ],
        'on a private property' => [
            fn() => new #[Type] class {
                #[Authorize]
                private string $name = 'x';
            },
            'Property %1$s::$name has #[Authorize] but is not a field',
        ],
        'on a method without #[Field]' => [
            fn() => new #[Type] class {
                public string $name = 'x';

                #[Authorize]
                public function secret(): string
                {
                    return 'secret';
                }
            },
            'Method %1$s::secret() has #[Authorize] but is not a field. Add #[Field], or remove #[Authorize].',
        ],
        'on a #[Type] class without actions' => [
            fn() => new #[Type(), Authorize] class {
                public string $name = 'x';
            },
            '#[Authorize] on the #[Type] class %1$s has nothing to apply to: on a class it only reaches #[Query] and #[Mutation] methods, never fields. Put it on each property or #[Field] method it should guard.',
        ],
        'an ability on an action method' => [
            fn() => new class {
                #[Query]
                #[Authorize('view')]
                public function run(): string
                {
                    return 'ok';
                }
            },
            "Method %1\$s::run has #[Authorize('view')] on the class or method, where there is no record to check the ability against. Put it on the model-bound parameter, or use #[Authorize(gate: ...)].",
        ],
        'an ability on an action class' => [
            fn() => new #[Authorize('viewAny')] class {
                #[Query]
                public function run(): string
                {
                    return 'ok';
                }
            },
            "Method %1\$s::run has #[Authorize('viewAny')] on the class or method",
        ],
        'onDenied: on an action' => [
            fn() => new class {
                #[Query]
                #[Authorize(onDenied: Denied::Null)]
                public function run(): string
                {
                    return 'ok';
                }
            },
            'Method %1$s::run has #[Authorize(onDenied:)], which only applies to a field of a #[Type].',
        ],
        'no ability on a bound parameter' => [
            fn() => new class {
                #[Query]
                public function get(#[Arg('id')] #[Authorize] User $note): string
                {
                    return $note->name;
                }
            },
            '#[Authorize] on the parameter $note in %1$s::get needs an ability',
        ],
        'on a parameter that binds no model' => [
            fn() => new class {
                #[Query]
                public function get(#[Authorize('view')] string $name): string
                {
                    return $name;
                }
            },
            '#[Authorize] on the parameter $name in %1$s::get only applies to a model-bound parameter',
        ],
        '#[Can] on a bound parameter' => [
            fn() => new class {
                #[Query]
                public function get(#[Arg('id')] #[Can('view')] User $user): string
                {
                    return $user->name;
                }
            },
            'would authorize the raw id, not the User it binds',
        ],
    ]);
});
