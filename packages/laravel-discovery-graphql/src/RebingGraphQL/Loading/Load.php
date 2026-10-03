<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use Attribute;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDiscoveryVerifier;

/** Resolves the field through a BatchLoader; named arguments after the loader become its options. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Load implements BatchedFieldDecorator, FieldDiscoveryVerifier
{
    use ResolvesThroughBatchLoader;

    /** @var array<int|string, mixed> */
    public array $arguments;

    /**
     * @param  class-string<BatchLoader>  $loader
     */
    public function __construct(
        public string $loader,
        mixed ...$options,
    ) {
        $this->arguments = $options;
    }

    public function loader(): string
    {
        return $this->loader;
    }

    public function options(string $fieldName): array
    {
        return array_filter($this->arguments, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    public function verify(string $member, DiscoveredTypeField $field): void
    {
        foreach (array_keys($this->arguments) as $key) {
            if (is_int($key)) {
                throw new LogicException(sprintf(
                    '%s has #[Load(%s, ...)] with an option at position %d. Name every option, as in #[Load(%s, key: \'authorId\')].',
                    $member,
                    class_basename($this->loader),
                    $key + 2,
                    class_basename($this->loader),
                ));
            }
        }
    }
}
