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

    /**
     * The shape rules of a parameter that binds a model, e.g. "the parameter $note in Acme\Notes::get".
     *
     * @internal
     */
    public function verifyOnParameter(string $where): void
    {
        if ($this->ability === null) {
            throw new LogicException(sprintf(
                '#[Authorize] on %s needs an ability, as in #[Authorize(\'view\')]. Bare #[Authorize] and #[Authorize(gate:)] belong on the class or the method.',
                $where,
            ));
        }

        if ($this->gate !== null) {
            throw new LogicException(sprintf(
                '#[Authorize(gate:)] on %s is not supported: a gate class receives the raw args, so it belongs on the class or the method.',
                $where,
            ));
        }

        if ($this->onDenied !== null) {
            throw new LogicException(sprintf(
                '#[Authorize(onDenied:)] on %s only applies to a field of a #[Type]. A denied parameter always reports an error; remove onDenied:.',
                $where,
            ));
        }
    }

    /**
     * The shape rules of a property that binds a model, e.g. "Property Acme\Note::$owner".
     *
     * @internal
     */
    public function verifyOnProperty(string $member, bool $shared): void
    {
        if ($this->ability === null) {
            throw new LogicException(sprintf(
                "#[Authorize] on %s needs an ability, as in #[Authorize('view')], to check the record it binds.",
                lcfirst($member),
            ));
        }

        if ($this->gate !== null) {
            throw new LogicException(sprintf(
                '#[Authorize(gate:)] on %s is not supported: a gate class receives the raw args, so it belongs on the action.',
                lcfirst($member),
            ));
        }

        if ($this->onDenied !== null && ! $shared) {
            throw new LogicException(sprintf(
                '#[Authorize(onDenied:)] on %s only applies to a field of a #[Type]. A denied input always reports an error; remove onDenied:.',
                lcfirst($member),
            ));
        }
    }

    /**
     * The shape rules of the class and method attributes of an action.
     *
     * @param  list<Authorize>  $authorizations  class and method attributes; onDenied: is checked across all first
     *
     * @internal
     */
    public static function verifyOnAction(array $authorizations, string $class, string $method, string $action): void
    {
        if (array_any($authorizations, static fn(self $authorize): bool => $authorize->onDenied !== null)) {
            throw new LogicException(sprintf(
                'Method %s::%s has #[Authorize(onDenied:)], which only applies to a field of a #[Type]. A denied #[%s] always reports an error; remove onDenied:.',
                $class,
                $method,
                $action,
            ));
        }

        foreach ($authorizations as $authorize) {
            if ($authorize->ability !== null && $authorize->gate !== null) {
                throw new LogicException(sprintf(
                    "Method %s::%s has #[Authorize] with both an ability and gate:. A gate decides on its own: remove the ability, or move it to the model-bound parameter as #[Authorize('%s')].",
                    $class,
                    $method,
                    $authorize->ability,
                ));
            }

            if ($authorize->gate !== null && ! is_a($authorize->gate, AuthorizationGate::class, true)) {
                throw new LogicException(sprintf(
                    'Method %s::%s has #[Authorize(gate: %s)], which does not implement %s.',
                    $class,
                    $method,
                    $authorize->gate,
                    AuthorizationGate::class,
                ));
            }

            if ($authorize->ability !== null) {
                throw new LogicException(sprintf(
                    "Method %s::%s has #[Authorize('%s')] on the class or method, where there is no record to check the ability against. Put it on the model-bound parameter, or use #[Authorize(gate: ...)].",
                    $class,
                    $method,
                    $authorize->ability,
                ));
            }
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
