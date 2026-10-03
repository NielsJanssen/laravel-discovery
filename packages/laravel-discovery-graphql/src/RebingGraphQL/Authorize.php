<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Foundation\Application;
use LogicException;
use Rebing\GraphQL\Error\AuthorizationError;

/**
 * Authorization for an action or a #[Type] field, in three forms:
 *
 *   #[Authorize]                          must be logged in
 *   #[Authorize(gate: SomeGate::class)]   delegate to an AuthorizationGate
 *   #[Authorize('view')]                  Gate check against the bound model, or against a field's parent object
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class Authorize implements ActionArgProvider, FieldDecorator, FieldDiscoveryVerifier
{
    /** Reported when a parameter or field check fails without a message:. */
    public const string DEFAULT_MESSAGE = 'Forbidden';

    public function __construct(
        /** Gate ability; only meaningful on a model-bound parameter or a field. */
        public readonly ?string $ability = null,
        /** @var class-string<AuthorizationGate>|null $gate */
        public readonly ?string $gate = null,
        public readonly ?string $message = null,
        /** What a denied field reports; only meaningful on a field, where it defaults to Denied::Null. */
        public readonly ?Denied $onDenied = null,
    ) {}

    public function provideArgs(): array
    {
        return [];
    }

    public function provideValueObjects(): array
    {
        return [Authorization::class];
    }

    public function verify(string $member, DiscoveredTypeField $field): void
    {
        if ($this->ability !== null && $this->gate !== null) {
            throw new LogicException(sprintf(
                "%s has #[Authorize] with both an ability and gate:. Use #[Authorize('%s')] to check the parent object, or #[Authorize(gate: ...)] for anything else.",
                $member,
                $this->ability,
            ));
        }

        if ($this->gate !== null && ! is_a($this->gate, AuthorizationGate::class, true)) {
            throw new LogicException(sprintf(
                '%s has #[Authorize(gate: %s)], which does not implement %s.',
                $member,
                $this->gate,
                AuthorizationGate::class,
            ));
        }

        if ($this->message !== null && $this->onDenied !== Denied::Error) {
            throw new LogicException(sprintf(
                '%s has #[Authorize(message:)], but a denied field resolves to null, so the message is never shown. Add onDenied: Denied::Error, or remove message:.',
                $member,
            ));
        }
    }

    public function decorate(FieldBlueprint $field): void
    {
        $field->nullable();
        $app = $field->app;

        if ($this->onDenied !== Denied::Error) {
            $field->addPrivacy(fn(mixed $root, array $args, mixed $context, ResolveInfo $info): bool => $this->allows($app, $root, $args, $context, $info));

            return;
        }

        $message = $this->message ?? self::DEFAULT_MESSAGE;

        $field->wrapResolver(fn(mixed $root, array $args, mixed $context, ?ResolveInfo $info, Closure $next): mixed => $this->allows($app, $root, $args, $context, $info)
            ? $next($root, $args, $context, $info)
            : throw new AuthorizationError($message));
    }

    /**
     * Evaluates this one attribute; the subject is a field's parent object or the record a parameter binds.
     *
     * @param  array<string, mixed>  $args
     */
    public function allows(Application $app, mixed $subject, array $args, mixed $context, ?ResolveInfo $info): bool
    {
        if ($this->gate !== null) {
            $gate = $app->make($this->gate);

            return $gate instanceof AuthorizationGate && $gate->check($subject, $args, $context, $info);
        }

        if ($this->ability !== null) {
            return $app->make(Gate::class)->allows($this->ability, [$subject]);
        }

        return $app->make(AuthFactory::class)->guard()->check();
    }
}
