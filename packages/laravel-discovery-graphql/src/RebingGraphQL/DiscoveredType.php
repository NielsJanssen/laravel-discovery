<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Foundation\Application;
use LogicException;
use Rebing\GraphQL\Support\Type as RebingType;

class DiscoveredType
{
    public private(set) ?string $bindName = null;

    /**
     * @param  class-string  $class
     * @param  list<DiscoveredTypeField>  $fields
     * @param  class-string|null  $factory
     * @param  list<class-string>  $interfaces
     */
    public function __construct(
        public string $name,
        public string $class,
        public TypeKind $kind = TypeKind::Object,
        public ?string $description = null,
        public array $fields = [],
        public ?string $factory = null,
        public bool $replace = false,
        public array $interfaces = [],
    ) {}

    public function createType(Application $app): RebingType
    {
        return match ($this->kind) {
            TypeKind::Object => new DiscoveredObjectType($app, $this),
            default => throw new LogicException(sprintf(
                'Cannot build GraphQL type [%s] for %s: %s types are not supported yet.',
                $this->name,
                $this->class,
                $this->kind->value,
            )),
        };
    }

    public function withBindName(): static
    {
        return clone($this, [
            'bindName' => 'discovery.rebing_graphql.type.' . hash('sha256', serialize($this)),
        ]);
    }
}
