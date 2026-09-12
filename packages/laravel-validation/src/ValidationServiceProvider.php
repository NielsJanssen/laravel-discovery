<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use NielsJanssen\Laravel\Validation\Infer\NullableInferrer;
use NielsJanssen\Laravel\Validation\Infer\SometimesInferrer;
use NielsJanssen\Laravel\Validation\Infer\TypeInferrer;

/**
 * Rebinds the `validator` singleton to our factory subclass so `Validator::makeFromObject()`
 * and friends are available through the facade. Mirrors Illuminate's registerValidationFactory()
 * so the presence verifier (exists/unique rules) keeps working.
 */
final class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RuleFinder::class, static function (): RuleFinder {
            return new RuleFinder([
                new NullableInferrer(),
                new SometimesInferrer(),
                new TypeInferrer(),
            ]);
        });

        $this->app->scoped(RuleCompiler::class, function (): RuleCompiler {
            return new RuleCompiler($this->app->make(RuleFinder::class));
        });

        $this->app->extend('validator', function (Factory $original): ValidatorFactory {
            $factory = new ValidatorFactory(
                $this->app->make(Translator::class),
                $this->app,
            );

            if ($this->app->bound('db')) {
                $factory->setPresenceVerifier($this->app->make(DatabasePresenceVerifier::class));
            }

            // Anything another provider already registered on the original factory — a
            // Validator::extend() call, a replacer — carries over rather than being lost.
            return $factory->adopting($original);
        });
    }
}
