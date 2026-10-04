<?php

declare(strict_types=1);

namespace Benchmarks\Support;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class BenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $target = $this->app->make(Target::class);
        $config = $this->app->make('config');

        $config->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $config->set('auth.defaults.guard', 'bench');
        $config->set('auth.guards.bench', ['driver' => 'bench']);

        if ($target->setup->usesDiscovery()) {
            $config->set('discovery.autoload', Paths::base($target->size));
            $config->set('discovery.cache_store', 'files');
            $config->set('discovery.cache_environments', $target->setup === Setup::Cached ? [$this->app->environment()] : ['production']);

            return;
        }

        /** @var array{types: array<string, class-string>, query: array<string, class-string>, mutation: array<string, class-string>, eager: array<string, class-string>} $rebing */
        $rebing = require Paths::base($target->size) . '/rebing.php';

        $config->set('graphql.types', $rebing['types']);
        $config->set('graphql.schemas.default.query', $target->setup === Setup::RebingEager ? [...$rebing['query'], ...$rebing['eager']] : $rebing['query']);
        $config->set('graphql.schemas.default.mutation', $rebing['mutation']);
    }

    public function boot(): void
    {
        Auth::viaRequest('bench', static fn(): Authenticatable => new GenericUser(['id' => 1, 'name' => 'Acme']));

        Gate::define('viewBalance', static fn(?Authenticatable $user, object $account): bool => property_exists($account, 'number') && is_int($account->number) && $account->number % 2 === 0);
        Gate::define('openVault', static fn(?Authenticatable $user, int $number): bool => $number % 2 === 0);
    }
}
