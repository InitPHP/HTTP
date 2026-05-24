<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit;

/**
 * Shared lifecycle helpers for tests that need to spin up the PSR-18
 * fixture server (tests/Psr18/fixture/server.php). Mirrors the pattern used
 * by {@see \InitPHP\HTTP\Tests\Psr18\HttpClientTest} but parameterises the
 * port so multiple suites can run side-by-side without colliding on
 * 127.0.0.1:18766.
 *
 * Consumers MUST override {@see self::fixturePort()} so each suite picks a
 * unique port — running multiple servers on the same port causes
 * intermittent ECONNRESET on macOS.
 */
trait FixtureServerTrait
{
    /**
     * PHP 8.1 and earlier do not allow constants inside traits — that was
     * relaxed in PHP 8.2. Expose the host as a static method instead so the
     * trait keeps working across the whole PHP 7.4 – 8.4 matrix.
     */
    private static function fixtureHost(): string
    {
        return '127.0.0.1';
    }

    /** @var resource|null */
    private static $serverProcess;
    /** @var array<int,resource|null> */
    private static $pipes = [];
    /** @var string */
    private static $baseUrl = '';

    /**
     * Concrete suites override this with a unique TCP port. The trait
     * intentionally returns 0 so a forgotten override fails loudly in
     * {@see self::bootFixtureServer()} instead of colliding with another
     * suite at runtime.
     */
    private static function fixturePort(): int
    {
        return 0;
    }

    private static function bootFixtureServer(): void
    {
        $port = static::fixturePort();
        if ($port <= 0) {
            self::fail('FIXTURE_PORT constant must be set by the consuming test class.');
        }
        $fixture = dirname(__DIR__) . '/Psr18/fixture/server.php';
        if (!is_file($fixture)) {
            self::fail('Fixture server file is missing: ' . $fixture);
        }
        self::$baseUrl = 'http://' . self::fixtureHost() . ':' . $port;

        self::killFixturePortListeners($port);

        $cmd = sprintf(
            'PHP_CLI_SERVER_WORKERS=4 exec php -S %s:%d %s',
            self::fixtureHost(),
            $port,
            escapeshellarg($fixture)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        self::$serverProcess = proc_open($cmd, $descriptors, self::$pipes);
        if (!is_resource(self::$serverProcess)) {
            self::fail('Could not start PHP built-in test server on port ' . $port);
        }

        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @fsockopen(self::fixtureHost(), $port, $errno, $errstr, 0.2);
            if (is_resource($sock)) {
                fclose($sock);
                return;
            }
            usleep(50000);
        }
        self::fail('Test server did not become ready on port ' . $port);
    }

    private static function shutdownFixtureServer(): void
    {
        if (is_resource(self::$serverProcess)) {
            foreach (self::$pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate(self::$serverProcess, 15);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
        self::killFixturePortListeners(static::fixturePort());
    }

    private static function killFixturePortListeners(int $port): void
    {
        if ($port <= 0) {
            return;
        }
        $pidList = @shell_exec(sprintf('lsof -ti tcp:%d 2>/dev/null', $port));
        if (!is_string($pidList) || trim($pidList) === '') {
            return;
        }
        foreach (preg_split('/\s+/', trim($pidList)) ?: [] as $pid) {
            if (ctype_digit($pid) && function_exists('posix_kill')) {
                @posix_kill((int) $pid, 9);
            }
        }
        usleep(100000);
    }

    private static function fixtureBaseUrl(): string
    {
        return self::$baseUrl;
    }
}
