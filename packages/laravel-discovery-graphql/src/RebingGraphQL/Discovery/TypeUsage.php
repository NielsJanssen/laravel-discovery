<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Tempest\Discovery\DiscoveryItems;

/** Which discovered types the actions and types refer to, and so which of them are registered. */
final class TypeUsage
{
    public function __construct(
        private readonly TypeRegistry $registry,
    ) {}

    /**
     * The references of an action's return, args and #[AsArgs] fields.
     *
     * @return iterable<TypeReference>
     */
    public function ofAction(DiscoveredAction $action): iterable
    {
        $method = "Method {$action->class}::{$action->method}";

        if ($action->returnType !== null && self::refers($action->returnType)) {
            yield new TypeReference($action->returnType, Position::Output, $method, class_basename($action->action::class));
        }

        foreach ($action->args as $arg) {
            $ref = $arg->type;

            if (self::refers($ref)) {
                yield new TypeReference($ref, Position::Input, "Argument {$arg->name} of " . lcfirst($method), 'Arg');
            }
        }

        foreach ($action->flattenedInputs as $flattened) {
            foreach ($flattened->type->fields as $field) {
                if (self::refers($field->type)) {
                    yield new TypeReference($field->type, Position::Input, "Property {$flattened->type->class}::\${$field->phpName}, flattened into " . lcfirst($method) . ',', 'Field');
                }
            }
        }
    }

    /**
     * The references of a type's fields and their args.
     *
     * @return iterable<TypeReference>
     */
    public function ofType(DiscoveredType $type): iterable
    {
        $position = $type->kind === TypeKind::Input ? Position::Input : Position::Output;

        foreach ($type->fields as $field) {
            $member = "Field {$type->name}.{$field->name}";

            if (self::refers($field->type)) {
                yield new TypeReference($field->type, $position, $member, 'Field');
            }

            foreach ($field->parameters->args as $arg) {
                $ref = $arg->type;

                if (self::refers($ref)) {
                    yield new TypeReference($ref, Position::Input, "Argument {$arg->name} of " . lcfirst($member), 'Arg');
                }
            }
        }
    }

    /**
     * Every reference of the actions among the items, then of the given types.
     *
     * @param  iterable<DiscoveredType>  $types
     * @return iterable<TypeReference>
     */
    public function references(DiscoveryItems $items, iterable $types): iterable
    {
        foreach ($items as $item) {
            if ($item instanceof DiscoveredAction) {
                yield from $this->ofAction($item);
            }
        }

        foreach ($types as $type) {
            yield from $this->ofType($type);
        }
    }

    /**
     * The types to register: every used input, and each implicit enum nothing else covers.
     *
     * @return list<DiscoveredType>
     */
    public function typesToRegister(DiscoveryItems $items): array
    {
        $explicit = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && ! $item->implicit) {
                $explicit[$item->class] = true;
            }
        }

        $usedInputs = $this->usedInputs($items);
        $referenced = $this->referencedClasses($items, $usedInputs);
        $seen = [];
        $types = [];

        foreach ($items as $item) {
            if (! $item instanceof DiscoveredType || $item->bindName === null) {
                continue;
            }

            if ($item->kind === TypeKind::Input && ! isset($usedInputs[$item->class])) {
                continue;
            }

            if ($item->implicit && (! isset($referenced[$item->class]) || isset($explicit[$item->class]) || isset($seen[$item->class]) || $this->registry->has($item->class))) {
                continue;
            }

            if ($item->implicit) {
                $seen[$item->class] = true;
            }

            $types[] = $item;
        }

        return $types;
    }

    /**
     * The enum classes among the references that no registry and no item covers yet.
     *
     * @param  iterable<TypeReference>  $references
     * @return list<class-string>
     */
    public function missingEnums(DiscoveryItems $items, iterable $references): array
    {
        $missing = [];

        foreach ($references as $reference) {
            $class = $reference->ref->class;

            if ($class === null || ! enum_exists($class) || isset($missing[$class]) || $this->registry->has($class) || $this->hasTypeFor($items, $class)) {
                continue;
            }

            $missing[$class] = $class;
        }

        return array_values($missing);
    }

    /**
     * The #[Input] types an argument uses, directly or through the fields of another used input.
     *
     * @return array<class-string, DiscoveredType>
     */
    private function usedInputs(DiscoveryItems $items): array
    {
        $inputs = [];
        $byName = [];
        $pending = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->kind === TypeKind::Input) {
                $inputs[$item->class] = $item;
                $byName[$item->name] = $item->class;
            } elseif ($item instanceof DiscoveredAction) {
                foreach ($this->ofAction($item) as $reference) {
                    if ($reference->position === Position::Input) {
                        $pending[] = $reference->ref->target();
                    }
                }
            }
        }

        $used = [];

        while ($pending !== []) {
            $key = array_pop($pending);
            $class = $byName[$key] ?? $key;
            $input = $inputs[$class] ?? null;

            if ($input === null || isset($used[$class])) {
                continue;
            }

            $used[$input->class] = $input;

            foreach ($this->ofType($input) as $reference) {
                $pending[] = $reference->ref->target();
            }
        }

        return $used;
    }

    /**
     * Every class an action, an output type or a used input refers to.
     *
     * @param  array<class-string, DiscoveredType>  $usedInputs
     * @return array<string, true>
     */
    private function referencedClasses(DiscoveryItems $items, array $usedInputs): array
    {
        $types = array_values($usedInputs);

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->kind !== TypeKind::Input) {
                $types[] = $item;
            }
        }

        $referenced = [];

        foreach ($this->references($items, $types) as $reference) {
            if ($reference->ref->class !== null) {
                $referenced[$reference->ref->class] = true;
            }
        }

        return $referenced;
    }

    private function hasTypeFor(DiscoveryItems $items, string $class): bool
    {
        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->class === $class) {
                return true;
            }
        }

        return false;
    }

    private static function refers(TypeRef $ref): bool
    {
        return $ref->scalar === null;
    }
}
