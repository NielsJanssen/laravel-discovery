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
    #[Query(name: 'attributeHello')]
    public function hello(
        #[Min(3)]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    #[Query(name: 'bothRulesHello')]
    public function both(
        #[Arg(rules: ['starts_with:N'])]
        #[Min(3)]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    #[Query(name: 'renamedHello')]
    public function renamed(
        #[Arg('nickname')]
        #[Min(3)]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    #[Query(name: 'messageHello')]
    public function message(
        #[Rule('min:5', message: 'Far too short, that.')]
        string $name,
    ): string {
        return "hi, {$name}";
    }

    #[Query(name: 'optionalHello')]
    public function optional(
        #[Min(3)]
        ?string $name = null,
    ): string {
        return 'hi, ' . ($name ?? 'nobody');
    }

    #[Query(name: 'notifyAll', type: 'String', list: true)]
    public function notify(
        #[Arg('recipients', type: '[String!]')]
        #[Each('email')]
        array $notify = [],
    ): array {
        return $notify;
    }
}
