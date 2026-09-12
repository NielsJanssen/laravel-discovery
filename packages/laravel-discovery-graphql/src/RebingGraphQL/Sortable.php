<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use GraphQL\Type\Definition\Type as GraphQLType;
use LogicException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class Sortable implements ActionArgProvider
{
    /**
     * @param list<string> $fields
     * @param string|null $defaultField the field to sort by when the caller asks for no sorting
     */
    public function __construct(
        public array  $fields,
        public bool   $unified = false,
        public string $defaultDirection = 'asc',
        public ?string $defaultField = null,
    ) {}

    public function provideArgs(): array
    {
        $this->assertDefaultsAreUsable();

        if ($this->unified) {
            $allowed = [];

            foreach ($this->fields as $field) {
                $allowed[] = "$field:asc";
                $allowed[] = "$field:desc";
            }

            $order = [
                'type'  => GraphQLType::string(),
                'rules' => ['nullable', 'in:' . implode(',', $allowed)],
            ];

            if ($this->defaultField !== null) {
                $order['defaultValue'] = "{$this->defaultField}:{$this->defaultDirection}";
            }

            return ['order' => $order];
        }

        $sortBy = [
            'type'  => GraphQLType::string(),
            'rules' => ['nullable', 'in:' . implode(',', $this->fields)],
        ];

        if ($this->defaultField !== null) {
            $sortBy['defaultValue'] = $this->defaultField;
        }

        return [
            'sortBy' => $sortBy,
            'sortDirection' => [
                'type'         => GraphQLType::string(),
                'rules'        => ['nullable', 'in:asc,desc'],
                'defaultValue' => $this->defaultDirection,
            ],
        ];
    }

    /**
     * A default that the generated `in:` rule would reject is a typo, not a runtime concern, so it
     * is reported while the schema is built rather than on the first request that leaves it out.
     */
    private function assertDefaultsAreUsable(): void
    {
        if (! in_array($this->defaultDirection, ['asc', 'desc'], true)) {
            throw new LogicException(sprintf(
                '#[Sortable(defaultDirection: \'%s\')] must be asc or desc.',
                $this->defaultDirection,
            ));
        }

        if ($this->defaultField !== null && ! in_array($this->defaultField, $this->fields, true)) {
            throw new LogicException(sprintf(
                '#[Sortable(defaultField: \'%s\')] is not one of the sortable fields: %s.',
                $this->defaultField,
                implode(', ', $this->fields),
            ));
        }
    }

    public function provideValueObjects(): array
    {
        return [Sort::class];
    }
}
