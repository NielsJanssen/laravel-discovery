<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AuthorizationGate;
use Tests\Fixtures\RebingGraphQL\AbilityAndGateOnMethodQuery;
use Tests\Fixtures\RebingGraphQL\AbilityOnClassQuery;
use Tests\Fixtures\RebingGraphQL\AbilityOnMethodQuery;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\GateNotAGateQuery;

it('rejects an #[Authorize] shape that cannot work on an action', function (string $class, string $message) {
    expect(fn() => discoverGraphQL($class))->toThrow(LogicException::class, $message);
})->with([
    'an ability on the method' => [
        AbilityOnMethodQuery::class,
        "Method " . AbilityOnMethodQuery::class . "::abilityOnMethod has #[Authorize('view')] on the class or method, where there is no record to check the ability against. Put it on the model-bound parameter, or use #[Authorize(gate: ...)].",
    ],
    'an ability on the class' => [
        AbilityOnClassQuery::class,
        "Method " . AbilityOnClassQuery::class . "::abilityOnClass has #[Authorize('viewAny')] on the class or method",
    ],
    'an ability together with gate:' => [
        AbilityAndGateOnMethodQuery::class,
        "Method " . AbilityAndGateOnMethodQuery::class . "::abilityAndGate has #[Authorize] with both an ability and gate:. A gate decides on its own: remove the ability, or move it to the model-bound parameter as #[Authorize('view')].",
    ],
    'a gate: class that is no AuthorizationGate' => [
        GateNotAGateQuery::class,
        'Method ' . GateNotAGateQuery::class . '::notAGate has #[Authorize(gate: ' . ContainerService::class . ')], which does not implement ' . AuthorizationGate::class . '.',
    ],
]);
