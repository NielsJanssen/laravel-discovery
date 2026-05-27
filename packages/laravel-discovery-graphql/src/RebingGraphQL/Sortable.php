<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use GraphQL\Type\Definition\Type as GraphQLType;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class Sortable implements ActionArgProvider
{
    public function __construct(
        public array  $fields,
        public bool   $unified = false,
        public string $defaultDirection = 'asc',
    ) {}

    public function provideArgs(): array
    {
        if ($this->unified) {
            $allowed = [];

            foreach ($this->fields as $field) {
                $allowed[] = "$field:asc";
                $allowed[] = "$field:desc";
            }

            return [
                'order' => [
                    'type'  => GraphQLType::string(),
                    'rules' => ['nullable', 'in:' . implode(',', $allowed)],
                ],
            ];
        }

        return [
            'sortBy' => [
                'type'  => GraphQLType::string(),
                'rules' => ['nullable', 'in:' . implode(',', $this->fields)],
            ],
            'sortDirection' => [
                'type'         => GraphQLType::string(),
                'rules'        => ['nullable', 'in:asc,desc'],
                'defaultValue' => $this->defaultDirection,
            ],
        ];
    }

    public function provideValueObjects(): array
    {
        return [Sort::class];
    }
}
