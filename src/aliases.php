<?php
/**
 * aliases.php
 *
 * Backwards-compatibility shims for users migrating from the legacy
 * `initphp/http-factory` package. Aliases the old fully-qualified
 * class name to the canonical class that now lives in this package.
 *
 * `initphp/http-client` and `initphp/curl` are intentionally NOT aliased
 * here: their public APIs differ enough from the canonical
 * `\InitPHP\HTTP\Client\Client` that a transparent alias would mislead
 * rather than help. Migration is documented in the README.
 *
 * The class_exists guard with autoload disabled prevents fatal
 * "cannot declare class" errors if a legacy package somehow remains
 * installed side-by-side.
 *
 * @see https://github.com/InitPHP/HTTP#migrating-from-deprecated-packages
 */

if (!class_exists(\InitPHP\HTTPFactory\HTTPFactory::class, false)) {
    class_alias(
        \InitPHP\HTTP\Factory\Factory::class,
        'InitPHP\\HTTPFactory\\HTTPFactory'
    );
}
