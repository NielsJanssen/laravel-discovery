# Routes

Declare HTTP routes with attributes. No `routes/web.php` or `routes/api.php` edits are required for discovered
controllers, though those files still work fine alongside discovery.

```php
use NielsJanssen\Laravel\Discovery\Router\Get;

class HealthController
{
    #[Get('/health')]
    public function check(): array
    {
        return ['ok' => true];
    }
}
```

## HTTP method attributes

One attribute per HTTP verb, all in the `NielsJanssen\Laravel\Discovery\Router` namespace:

| Attribute             | HTTP method |
|-----------------------|-------------|
| `#[Get('/path')]`     | `GET`       |
| `#[Post('/path')]`    | `POST`      |
| `#[Put('/path')]`     | `PUT`       |
| `#[Patch('/path')]`   | `PATCH`     |
| `#[Delete('/path')]`  | `DELETE`    |
| `#[Head('/path')]`    | `HEAD`      |
| `#[Options('/path')]` | `OPTIONS`   |

All of them are **repeatable** and can be placed on a method or directly on a class. Placing the attribute on a class registers the class itself as a single-action (invokable) controller; see [Single-action controllers](#single-action-controllers) below. Placing it on a method is the standard form for multi-action controllers, and a single method can answer multiple URIs:

```php
#[Get('/users/{id}')]
#[Get('/v2/users/{id}')]
public function show(int $id): array { /* ... */ }
```

Each method attribute takes the same options:

```php
#[Get(
    uri: '/admin/users',
    middleware: ['auth', 'verified'],
    withoutMiddleware: ['throttle'],
    domain: 'admin.example.com',
    name: 'admin.users.index',
)]
```

| Parameter           | Type                         | Default    |
|---------------------|------------------------------|------------|
| `uri`               | `string`                     | (required) |
| `middleware`        | `list<string\|class-string>` | `[]`       |
| `withoutMiddleware` | `list<string\|class-string>` | `[]`       |
| `domain`            | `string\|\BackedEnum\|null`  | `null`     |
| `name`              | `string\|\BackedEnum\|null`  | `null`     |

When you set `name`, the route is registered with that name and works with the `route()` helper, `route:list`, model
binding, and everything else that takes a route name. Leave it `null` (the default) and the route stays anonymous.

Both `name` and `domain` also accept a string-backed enum, mirroring Laravel's own `Route::name()` and `Route::domain()`
signatures. Handy if you've collected your route names into a `RouteName` enum:

```php
enum RouteName: string
{
    case UsersIndex = 'users.index';
    case UsersShow  = 'users.show';
}

#[Get('/users', name: RouteName::UsersIndex)]
public function index(): array { /* ... */ }
```

## Multi-verb routes

When an action should respond to several HTTP methods at the same URI, use `#[Route]` and pass the methods explicitly:

```php
use NielsJanssen\Laravel\Discovery\Router\Method;
use NielsJanssen\Laravel\Discovery\Router\Route;

class InventoryController
{
    #[Route([Method::Get, Method::Post], '/inventory')]
    public function handle(): array { /* ... */ }
}
```

`#[Route]` accepts the same optional parameters as the verb-specific attributes, with `methods` and `uri` as the
leading positional arguments:

| Parameter           | Type                         | Default    |
|---------------------|------------------------------|------------|
| `methods`           | `list<Method>`               | (required) |
| `uri`               | `string`                     | (required) |
| `middleware`        | `list<string\|class-string>` | `[]`       |
| `withoutMiddleware` | `list<string\|class-string>` | `[]`       |
| `domain`            | `string\|\BackedEnum\|null`  | `null`     |
| `name`              | `string\|\BackedEnum\|null`  | `null`     |

Like the verb-specific attributes, `#[Route]` is repeatable.

## Class-level decorators

Three attributes can be placed on a controller class to apply to every action in it. They can also be placed on
individual methods. For how class-level and method-level decorators combine,
see [Decorator composition](#decorator-composition) below.

### `#[Prefix]`

```php
use NielsJanssen\Laravel\Discovery\Router\Prefix;

#[Prefix('/api')]
class UserController
{
    #[Get('/users')]       // → /api/users
    public function index(): array { /* ... */ }

    #[Get('/users/{id}')]  // → /api/users/{id}
    public function show(int $id): array { /* ... */ }
}
```

### `#[Middleware]`

```php
use NielsJanssen\Laravel\Discovery\Router\Middleware;

#[Middleware(['auth:api'])]
class UserController
{
    #[Get('/users')]
    public function index(): array { /* ... */ }
}
```

You can also exclude middleware from specific methods with the `without` parameter:

```php
#[Middleware(middleware: ['auth'], without: ['throttle'])]
```

### `#[Domain]`

```php
use NielsJanssen\Laravel\Discovery\Router\Domain;

#[Domain('api.example.com')]
class ApiController
{
    #[Get('/status')]
    public function status(): array { /* ... */ }
}
```

Like the `domain` parameter on the HTTP method attributes, `#[Domain]` accepts a string-backed enum as well as a plain
string.

## Decorator composition

Class-level decorators are applied first, then method-level. For middleware, both lists are merged. For prefix, they
concatenate. For domain, the method-level decorator overrides the class-level one.

```php
#[Prefix('/api')]
#[Middleware(['auth:api'])]
class UserController
{
    #[Get('/users')]
    #[Middleware(['can:admin'])]  // stacks: ['auth:api', 'can:admin']
    public function index(): array { /* ... */ }

    #[Prefix('/v2')]              // stacks: /api/v2/users
    #[Get('/users')]
    public function indexV2(): array { /* ... */ }
}
```

## Named routes

```php
#[Get('/users/{id}', name: 'users.show')]
public function show(int $id): array { /* ... */ }
```

Then anywhere in your app:

```php
route('users.show', ['id' => 1]);   // → /users/1
```

If you stack multiple `#[Get]` attributes on the same handler, give each one its own name. Route names must be unique
across the app.

## Multiple URIs per action

The verb-specific attributes are repeatable, so a single method can serve more than one URI:

```php
#[Get('/orders/{id}')]
#[Get('/v2/orders/{id}')]
public function show(int $id): array { /* ... */ }
```

Two routes are registered, both pointing at the same method. To register multiple verbs at the same URI, use
[`#[Route]`](#multi-verb-routes) instead.

## Route caching

Laravel's `php artisan route:cache` is supported. When routes are cached, route discovery skips re-registration, so any
impact on performance is removed.

```bash
php artisan route:cache
php artisan discovery:cache   # for the rest of the discovery surface
```

## Single-action controllers

For classes that handle only one route, place the attribute on the class itself. The class must define `__invoke`; Laravel
resolves it as an invokable controller and the action registered is the class name, with no `@method` suffix.

```php
use NielsJanssen\Laravel\Discovery\Router\Post;

#[Post('/orders')]
class CreateOrder
{
    public function __invoke(Request $request): JsonResponse
    {
        // ...
    }
}
```

Class-level decorators apply to the class-level route as they would to any method route:

```php
#[Prefix('/api')]
#[Middleware(['auth:api'])]
#[Post('/orders')]
class CreateOrder
{
    public function __invoke(Request $request): JsonResponse
    {
        // ...
    }
}
```

Alternatively, place the attribute on `__invoke` directly. Both register the same route:

```php
class CreateOrder
{
    #[Post('/orders')]
    public function __invoke(Request $request): JsonResponse
    {
        // ...
    }
}
```

The class-level form is more concise for controllers that expose a single endpoint. Either works fine.

## Livewire components

A full-page Livewire component is an invokable class: Livewire's base `Component` defines `__invoke`, which renders the
component as a full page. Route it the same way as any other [single-action controller](#single-action-controllers), by
placing an HTTP method attribute on the component class.

```php
use Illuminate\Contracts\View\View;
use Livewire\Component;
use NielsJanssen\Laravel\Discovery\Router\Get;

#[Get('/orders', middleware: ['web'])]
class OrdersOverview extends Component
{
    public function render(): View
    {
        return view('livewire.orders-overview');
    }
}
```

Full-page Livewire routes belong in the `web` middleware group, where the session and CSRF token are available; set that
on the attribute as shown. Class-level decorators apply here as well: `#[Prefix]`, `#[Middleware]`, and `#[Domain]`
decorate the component route exactly as they do a single-action controller.

There is no dedicated Livewire attribute. The standard HTTP method attributes already cover the case, since a full-page
component is an invokable controller.

## Controllers without a base class

Discovered controllers don't need to extend `Illuminate\Routing\Controller`. The framework resolves them through the
container the same way it would any other class.
