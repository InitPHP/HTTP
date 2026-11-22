# Static Facades

The package ships three `final` static facades:

| Facade                              | Wraps                                |
|-------------------------------------|--------------------------------------|
| `InitPHP\HTTP\Facade\Client`        | `InitPHP\HTTP\Client\Client`         |
| `InitPHP\HTTP\Facade\Emitter`       | `InitPHP\HTTP\Emitter\Emitter`       |
| `InitPHP\HTTP\Facade\Factory`       | `InitPHP\HTTP\Factory\Factory`       |

Each is a `final` class with a single static method (`getInstance()`) plus the magic `__call` / `__callStatic` machinery supplied by the `Facadable` trait. The first call constructs the wrapped service via `new`, caches it in a private static property, and forwards the call; every subsequent call reuses the same instance.

```php
use InitPHP\HTTP\Facade\Factory;
use InitPHP\HTTP\Facade\Client;
use InitPHP\HTTP\Facade\Emitter;

$request  = Factory::createRequest('GET', 'https://example.com');
$response = Client::sendRequest($request);
Emitter::emit($response);
```

## When to use them

- **Quick scripts and prototypes.** Saves wiring an instance per file.
- **Legacy codebases without a DI container.** Provides a single source of truth.
- **Library code that needs a "fall through" default** — pass the facade class name as a default factory and let consumers override per-instance.

## When *not* to use them

- **Anywhere you'd otherwise inject a `ClientInterface`** — direct DI is testable without `@runInSeparateProcess`; the facade isn't.
- **Multiple concurrent configurations of the same service** (e.g. two clients with different timeouts). Facades are singletons; create separate instances yourself.
- **Inside libraries you ship to other people** — leave the lifecycle choice to the consumer.

## Customising the underlying instance

The facades cache their wrapped service in a private static property. That works for "default everything" but breaks the moment you want a `Client` with custom timeouts or a custom user-agent. Three options:

### 1. Bypass the facade

If you only need the custom instance in one place, just instantiate the service directly:

```php
use InitPHP\HTTP\Client\Client;

$client = (new Client())->withTimeout(5);
$response = $client->sendRequest($request);
```

Mixing both styles is fine — the facade keeps its own shared instance; the explicit one is yours.

### 2. Subclass the facade

Each facade declares its `getInstance()` on the **concrete** facade class. You can subclass and override it:

```php
namespace App\Http;

final class Client extends \InitPHP\HTTP\Facade\Client
{
    public static function getInstance(): object
    {
        static $instance;
        if ($instance === null) {
            $instance = (new \InitPHP\HTTP\Client\Client())
                ->withTimeout(5)
                ->withUserAgent('app/1.0');
        }
        return $instance;
    }
}

// Then call App\Http\Client::sendRequest(...) instead.
```

The `@mixin` PHPDoc on the parent carries over, so IDE autocomplete keeps working on the subclass.

### 3. Reset between requests in long-running PHP

Under Swoole / RoadRunner / Octane the static property persists across requests. If you stashed per-request state on the wrapped instance (you really shouldn't, but it happens), null it out manually:

```php
$ref = new \ReflectionClass(\InitPHP\HTTP\Facade\Client::class);
$prop = $ref->getProperty('instance');
$prop->setAccessible(true);
$prop->setValue(null, null);
```

This is the kind of escape hatch you reach for once, while you migrate the offending code to direct DI. Don't ship it as architecture.

## The deprecated `Facadeble` naming

Earlier versions misspelled the trait and interface as `Facadeble` (note the missing `a`). The canonical names are now `Facadable` and `FacadableInterface`. The old symbols remain as `@deprecated` aliases for backward compatibility:

```php
// Canonical:
use InitPHP\HTTP\Facade\Traits\Facadable;
use InitPHP\HTTP\Facade\Interfaces\FacadableInterface;

// Still works, but deprecated:
use InitPHP\HTTP\Facade\Traits\Facadeble;
use InitPHP\HTTP\Facade\Interfaces\FacadebleInterface;
```

The deprecated names will be removed in the next major release.

## See also

- [PSR-18 Client](PSR-18-Client.md) — what `Facade\Client` forwards to.
- [PSR-17 Factory](PSR-17-Factory.md) — what `Facade\Factory` forwards to.
- [Emitter](Emitter.md) — what `Facade\Emitter` forwards to.
