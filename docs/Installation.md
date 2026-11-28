# Installation

## Requirements

| Component             | Minimum               | Notes                                                  |
|-----------------------|-----------------------|--------------------------------------------------------|
| PHP                   | **8.2**               | Tested on 8.2, 8.3, 8.4, 8.5, 8.6 in CI.              |
| `ext-json`            | always                | Used by `Response::json()` and `Helpers::send_request`. |
| `ext-curl`            | only for `Client\Client` | PSR-18 transport. Loaded check happens in the Client constructor — if `ext-curl` is missing you get a `ClientException` saying so. |

Composer dependency floor:

```json
"require": {
    "php": "^8.2",
    "psr/http-message": "^1.0 || ^2.0",
    "psr/http-factory": "^1.0",
    "psr/http-client":  "^1.0"
}
```

## Install via Composer

```bash
composer require initphp/http
```

That's it. The library is autoloaded under the `InitPHP\HTTP\` namespace; the convenience `send_request()` global function is also pulled in via Composer's `files` autoload entry — see [Helpers](Helpers.md) for details.

## Verifying the install

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;

$response = new Response(200);
$response->getBody()->write('it works');
echo (string) $response->getBody(); // "it works"
```

If the script prints `it works`, the autoload is wired correctly.

## Development dependencies

To run the package's own checks from a clone, install the dev dependencies listed in its `composer.json`:

```bash
git clone https://github.com/InitPHP/HTTP.git
cd HTTP
composer install
composer ci   # php-cs-fixer (dry run) + phpstan + phpunit
```

`http-interop/http-factory-tests` and `php-http/psr7-integration-tests` are the upstream PSR-FIG compliance suites that this package is verified against; the test suite runs them next to an in-house PSR-18 smoke test against the PHP built-in server and a dedicated immutability suite. The individual steps are also available as `composer test`, `composer phpstan` and `composer cs-check`.

## CI matrix

The package's GitHub Actions workflow runs the test suite against PHP 8.2, 8.3, 8.4, 8.5 and 8.6 on every push and pull request (plus a PHP 8.2 run with the lowest allowed dependency versions), and runs PHPStan (`level: 5`) and php-cs-fixer on PHP 8.2. See the [tests workflow](https://github.com/InitPHP/HTTP/blob/main/.github/workflows/tests.yml) and the [static analysis workflow](https://github.com/InitPHP/HTTP/blob/main/.github/workflows/static-analysis.yml) for details.

## Next step

Read [Quick Start](Quick-Start.md) for a 10-minute tour, or jump straight to the section that matches your task — see the sidebar.
