<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Emitter;

use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Emitter\Exceptions\EmitBodyException;
use InitPHP\HTTP\Emitter\Exceptions\EmitHeaderException;
use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Strict-mode guard rails. The emitter MUST refuse to start emitting when:
 *
 *   - headers have already been sent  -> {@see EmitHeaderException}
 *   - the output buffer already has bytes -> {@see EmitBodyException}
 *
 * Triggering `headers_sent() === true` from CLI normally requires an
 * actual SAPI; under phpdbg/cli we use a non-output-buffered echo to flush
 * implicit output, which flips headers_sent() to true. Each test runs in
 * its own process so neighbours don't inherit the dirty state.
 *
 * Note on platform brittleness: PHPUnit 10 spawns a separate process via
 * proc_open and pipes; on some CI runners the stdout pipe is not a TTY,
 * which can keep headers_sent() false even after a write. The body-buffer
 * test does not depend on headers_sent() at all (just ob_get_length()),
 * so it provides the more portable coverage. The header-side test is
 * gated on platform behaviour and skipped when the trigger doesn't fire.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class EmitterStrictModeTest extends TestCase
{
    public function testStrictModeBlocksEmissionWhenBufferDirty(): void
    {
        // ob_start() + write something => ob_get_length() > 0 => guard fires.
        ob_start();
        echo 'leaked bytes';

        $emitter = new Emitter(true);
        $response = new Response(200, [], 'body');

        $threw = null;
        try {
            $emitter->emit($response);
        } catch (EmitBodyException $e) {
            $threw = $e;
        } finally {
            // Always tidy the buffer so the rest of the suite sees a clean
            // slate regardless of pass/fail.
            ob_end_clean();
        }

        self::assertInstanceOf(EmitBodyException::class, $threw);
    }

    public function testStrictModeDoesNotBlockOnEmptyBuffer(): void
    {
        // ob_start() but no writes => ob_get_length() === 0 => guard passes.
        ob_start();
        $emitter = new Emitter(true);
        $response = new Response(200, [], 'ok');

        try {
            $emitter->emit($response);
        } catch (\Throwable $e) {
            ob_end_clean();
            self::fail('Unexpected throw on clean buffer: ' . $e->getMessage());
        }
        $output = (string) ob_get_clean();

        self::assertSame('ok', $output);
    }

    public function testNonStrictModeIgnoresDirtyBuffer(): void
    {
        ob_start();
        echo 'leaked';

        $emitter = new Emitter(false);
        $response = new Response(200, [], 'body');

        try {
            $emitter->emit($response);
            $output = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        // Non-strict simply concatenates — both the leak and the body land.
        self::assertSame('leakedbody', $output);
    }

    public function testStrictModeRefusesEmissionAfterHeadersSent(): void
    {
        // PHPUnit wraps every test in its own output buffer, which prevents
        // headers_sent() from flipping inside the test process — even under
        // @runInSeparateProcess, the wrapper still starts an ob layer.
        //
        // To exercise the header guard reliably we spawn a clean PHP
        // subprocess that:
        //   1. emits a direct stdout byte so headers_sent() flips to true
        //   2. calls Emitter::emit() under strict mode
        //   3. exits 0 if EmitHeaderException was raised, 1 otherwise
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $emitterClass = Emitter::class;
        $responseClass = Response::class;
        $exceptionClass = EmitHeaderException::class;

        $script = <<<PHP
<?php
require {$this->phpQuote($autoload)};
echo 'force-flush';
if (!headers_sent()) {
    fwrite(STDERR, 'headers_sent stayed false');
    exit(2);
}
\$emitter = new \\{$emitterClass}(true);
\$response = new \\{$responseClass}(200, [], 'body');
try {
    \$emitter->emit(\$response);
    exit(1);
} catch (\\{$exceptionClass} \$e) {
    exit(0);
}
PHP;

        $tmp = tempnam(sys_get_temp_dir(), 'init-emitter-');
        self::assertIsString($tmp);
        file_put_contents($tmp, $script);

        try {
            $cmd = sprintf('php %s 2>&1', escapeshellarg($tmp));
            $output = [];
            $exitCode = 1;
            exec($cmd, $output, $exitCode);

            if ($exitCode === 2) {
                self::markTestSkipped('headers_sent() did not flip on this SAPI; ' . implode("\n", $output));
            }

            self::assertSame(
                0,
                $exitCode,
                'Strict-mode emitter did not raise EmitHeaderException after stdout flush. Subprocess output: ' . implode("\n", $output)
            );
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * var_export()-style single-quote string escaper for embedding paths in
     * the subprocess script body. Keeps the script readable and dodges the
     * heredoc/curly-brace conflict that would arise if we tried to inline
     * the path with interpolation.
     */
    private function phpQuote(string $value): string
    {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }
}
