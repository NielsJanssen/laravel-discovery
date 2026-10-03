<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchedFieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\VerifiesLoadOptions;

/** A class with #[TypeExtension]: the fields it adds to its target, as a serializable description. */
final readonly class DiscoveredExtension
{
    /**
     * @param  string  $target  the class of the extended type, or its GraphQL name
     * @param  bool  $targetIsClass  whether $target is a class rather than a GraphQL name
     * @param  class-string  $class  the contributor, resolved from the container
     * @param  list<DiscoveredTypeField>  $fields
     */
    public function __construct(
        public string $target,
        public bool $targetIsClass,
        public string $class,
        public array $fields = [],
    ) {}

    /** How errors name the extension, e.g. "#[TypeExtension(Acme\User)] on Acme\UserBilling". */
    public function label(): string
    {
        return "#[TypeExtension({$this->target})] on {$this->class}";
    }

    /**
     * The fields as part of a type of the given class, checking the loader options a GraphQL name target left open.
     *
     * @param  class-string|null  $class
     * @return list<DiscoveredTypeField>
     */
    public function fieldsFor(?string $class): array
    {
        if ($this->targetIsClass) {
            return $this->fields;
        }

        return array_map(fn(DiscoveredTypeField $field): DiscoveredTypeField => $this->verifyLoading($field->withTypeClass($class)), $this->fields);
    }

    private function verifyLoading(DiscoveredTypeField $field): DiscoveredTypeField
    {
        foreach ($field->decorators as $decorator) {
            $decorator = $decorator instanceof FieldDecoratorReference ? $decorator->resolve($this->class) : $decorator;

            if ($decorator instanceof BatchedFieldDecorator && is_a($loader = $decorator->loader(), VerifiesLoadOptions::class, true)) {
                $loader::verifyOptions("Method {$field->member()}", '#[' . class_basename($decorator::class) . ']', $field, $decorator->options($field->phpName));
            }
        }

        return $field;
    }
}
