# Static Facades — Customisation

The facades cache their wrapped instance in a private static property and create it lazily on first call. That works for "default everything" but breaks the moment you want a `Client` with custom timeouts or a custom user agent.

You have two options.

## Option 1 — Bypass the facade

If you only need the custom instance in one place, instantiate the service directly:

```php
use InitPHP\HTTP\Client\Client;

$client = (new Client())->withTimeout(5);
$response = $client->sendRequest($request);
```

Mixing both is fine — the facade keeps a single shared instance; the explicit instance is your own.

## Option 2 — Subclass the facade

Each facade declares its `getInstance()` method on the **concrete** facade class (not the trait), so you can subclass and override it:

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

// Then use App\Http\Client::sendRequest(...) instead of the package facade.
```

The `@mixin` PHPDoc on the facade carries over, so IDE autocomplete keeps working.

Note: the package's own facades are `final` because we want a single canonical short-import surface — your subclass effectively becomes a separate facade in a separate namespace. That's deliberate; it stops one application's facade configuration from leaking into another's via a shared static property.

## Option 3 — Reset between requests in long-running PHP

Under Swoole / RoadRunner / Octane the static property persists across requests. If you've stashed per-request state on the wrapped instance (you really shouldn't, but it happens) you can null it out manually:

```php
$ref = new \ReflectionClass(\InitPHP\HTTP\Facade\Client::class);
$prop = $ref->getProperty('instance');
$prop->setAccessible(true);
$prop->setValue(null, null);  // forces re-creation on next call
```

This is the kind of escape hatch you reach for *once*, while you migrate the offending code to direct DI. Don't ship it as architecture.
