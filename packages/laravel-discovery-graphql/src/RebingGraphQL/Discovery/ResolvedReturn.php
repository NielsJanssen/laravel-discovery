<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Action;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionTypeBuilder;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;

/** The type builder and the inferred return type of an action. */
final readonly class ResolvedReturn
{
    public function __construct(
        public ?ActionTypeBuilder $typeBuilder,
        private ?TypeRef $inferred,
    ) {}

    /** The action's return type with its final list and nullability, or null when a type builder supplies it. */
    public function typeRef(Action $action): ?TypeRef
    {
        return match (true) {
            $this->inferred !== null => $this->inferred->wrapped($action->list, $action->nullable, $action->nullableItems),
            $action->type === null && $action->of === null => null,
            default => TypeRef::from((string) ($action->of ?? $action->type), $action->list, $action->nullable, $action->nullableItems),
        };
    }
}
