<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Validation\Rule\Each;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Rule;

class AttributeValidatedQuery
{
    /** Validation attributes alone. */
    #[Query(name: 'attributeHello')]
    public function hello(
        #[Min(3)]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    /** #[Arg(rules:)] and validation attributes on the same parameter must both apply. */
    #[Query(name: 'bothRulesHello')]
    public function both(
        #[Arg(rules: ['starts_with:N'])]
        #[Min(3)]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    /** A renamed arg: rule and message keys must come back under the arg name. */
    #[Query(name: 'renamedHello')]
    public function renamed(
        #[Arg('nickname')]
        #[Min(3)]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    /** A custom message from #[Rule(message:)] must reach the error payload. */
    #[Query(name: 'messageHello')]
    public function message(
        #[Rule('min:5', message: 'Far too short, that.')]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    /** An optional parameter gets `sometimes`, so omitting it is fine. */
    #[Query(name: 'optionalHello')]
    public function optional(
        #[Min(3)]
        ?string $name = null,
    ): string {
        return 'hi, ' . ($name ?? 'nobody');
    }

    /** #[Each] on a list arg validates every element at its own path. */
    #[Query(name: 'notifyAll', type: 'String', list: true)]
    public function notify(
        #[Arg('recipients', type: '[String!]')]
        #[Each('email')]
        array $notify = [],
    ): array {
        return $notify;
    }
}
