<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Action;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionTypeBuilder;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\OmittableType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use ReflectionNamedType;
use RuntimeException;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

/** Resolves the return type of a #[Query] or #[Mutation] method: a type builder, an explicit type or an inferred one. */
final readonly class ReturnTypeResolver
{
    public function __construct(
        private TypeInferrer $inferrer,
    ) {}

    /**
     * Widens $action with what its return type says; an explicit type: is kept, an inferred one fills in.
     *
     * @param  ClassReflector<object>  $class
     */
    public function resolve(Action $action, ClassReflector $class, MethodReflector $method): ResolvedReturn
    {
        $this->inferrer->assertNotBothTypeAndOf($action->type, $action->of, sprintf('Method %s::%s', $class->getName(), $method->getName()), class_basename($action::class));

        $this->assertNotOmittable($class, $method);

        $typeBuilder = $this->typeBuilder($class, $method);
        $inferred = null;

        if ($typeBuilder === null && $action->type === null && $action->of === null) {
            $inferred = $this->infer($action, $class, $method);
            $action->type = $inferred->target();
            $action->nullable = $inferred->nullable;
            $action->list = $action->list || $inferred->list;
            $action->nullableItems = $action->nullableItems || $inferred->nullableItems;
        } elseif ($method->getReturnType()?->isNullable() === true) {
            $action->nullable = true;
        }

        return new ResolvedReturn($typeBuilder, $inferred);
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function typeBuilder(ClassReflector $class, MethodReflector $method): ?ActionTypeBuilder
    {
        $methodBuilders = $method->getAttributes(ActionTypeBuilder::class);

        if (count($methodBuilders) > 1) {
            throw new RuntimeException(sprintf(
                'Method %s::%s has multiple ActionTypeBuilder attributes (%s). At most one is allowed per method.',
                $class->getName(),
                $method->getName(),
                implode(', ', array_map(static fn(ActionTypeBuilder $builder): string => $builder::class, $methodBuilders)),
            ));
        }

        if ($methodBuilders !== []) {
            return $methodBuilders[0];
        }

        $classBuilders = $class->getAttributes(ActionTypeBuilder::class);

        if (count($classBuilders) > 1) {
            throw new RuntimeException(sprintf(
                'Class %s has multiple ActionTypeBuilder attributes (%s). At most one is allowed per class.',
                $class->getName(),
                implode(', ', array_map(static fn(ActionTypeBuilder $builder): string => $builder::class, $classBuilders)),
            ));
        }

        return $classBuilders[0] ?? null;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function assertNotOmittable(ClassReflector $class, MethodReflector $method): void
    {
        $returnType = $method->getReflection()->getReturnType();

        if (OmittableType::of($returnType) === null) {
            return;
        }

        throw new LogicException(sprintf(
            'Method %s::%s is typed %s, but Omitted only applies to a property of an #[Input] class, in input position. Remove Omitted from the return type.',
            $class->getName(),
            $method->getName(),
            $returnType,
        ));
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function infer(Action $action, ClassReflector $class, MethodReflector $method): TypeRef
    {
        $returnType = $method->getReflection()->getReturnType();

        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
            return TypeRef::scalar('void', nullable: true);
        }

        try {
            return $this->inferrer->output(
                $returnType,
                $class->getName(),
                sprintf('Method %s::%s', $class->getName(), $method->getName()),
                class_basename($action::class),
                nullable: $action->nullable,
                member: new Member($method->getName(), $method->getReflection()->getDeclaringClass()->getName(), Position::Output, MemberKind::MethodReturn),
            );
        } catch (LogicException $e) {
            throw new RuntimeException(
                $e->getMessage() . ' A scalar, void, enum or #[Type] class return type is inferred.',
                previous: $e,
            );
        }
    }
}
