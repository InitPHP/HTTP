# Static Facades — Overview

This package ships three static facades:

| Facade                              | Wraps                                   |
|-------------------------------------|-----------------------------------------|
| `InitPHP\HTTP\Facade\Client`        | `InitPHP\HTTP\Client\Client`            |
| `InitPHP\HTTP\Facade\Emitter`       | `InitPHP\HTTP\Emitter\Emitter`          |
| `InitPHP\HTTP\Facade\Factory`       | `InitPHP\HTTP\Factory\Factory`          |

Each is a `final` class with a single static method (`getInstance()`) plus the magic `__call`/`__callStatic` machinery from the `Facadable` trait. The first static call creates the wrapped service via `new`, caches it in a private static property, and forwards the call; every subsequent call reuses the same instance.

```php
use InitPHP\HTTP\Facade\Factory;
use InitPHP\HTTP\Facade\Client;
use InitPHP\HTTP\Facade\Emitter;

$request  = Factory::createRequest('GET', 'https://example.com');
$response = Client::sendRequest($request);
Emitter::emit($response);
```

## When to use them

- **Quick scripts and prototypes.** Saves you wiring an instance.
- **Legacy codebases without a DI container.** Provides a single source of truth without you owning lifecycle.
- **Library code that needs a "fall through" default** — pass the facade class name as a default factory and let consumers override per-instance.

## When *not* to use them

- **Anywhere you'd otherwise inject a `ClientInterface`** — direct DI is testable without `runInSeparateProcess`, the facade isn't.
- **Multiple concurrent configurations** of the same service (e.g. two clients with different timeouts). Facades are singletons; create separate instances yourself.
- **Inside libraries you ship to other people** — leave the lifecycle choice to the consumer.

## Customising the underlying instance

See [Customisation](customization.md) for how to inject a pre-configured service into a facade (e.g. a `Client` with a 5-second timeout) before the first use.

## The deprecated `Facadeble` naming

Earlier versions misspelled the trait and interface as `Facadeble` (note the missing `a`). The canonical names are now `Facadable` and `FacadableInterface`. The old symbols remain as `@deprecated` aliases:

```php
// New (canonical):
use InitPHP\HTTP\Facade\Traits\Facadable;
use InitPHP\HTTP\Facade\Interfaces\FacadableInterface;

// Old (works, but deprecated):
use InitPHP\HTTP\Facade\Traits\Facadeble;
use InitPHP\HTTP\Facade\Interfaces\FacadebleInterface;
```

The deprecated names will be removed in the next major release.
