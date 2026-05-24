<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr18;

use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Client\Exceptions\NetworkException;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 davranışını kontrollü bir PHP built-in server fixture'ına karşı kapsar.
 *
 * Tek-thread/multi-thread macOS uyumsuzlukları nedeniyle php-http/client-integration-tests
 * paketi yerine yazılmış lokal-ortamda güvenilir koşan smoke testi.
 */
final class HttpClientTest extends TestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 18766;

    /** @var resource */
    private static $serverProcess;
    /** @var array */
    private static $pipes;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $fixture = __DIR__ . '/fixture/server.php';
        self::$baseUrl = 'http://' . self::HOST . ':' . self::PORT;

        // Önceki çalışmalardan kalan worker'ları temizle
        self::killPortListeners();

        $cmd = sprintf(
            'PHP_CLI_SERVER_WORKERS=4 exec php -S %s:%d %s',
            self::HOST,
            self::PORT,
            escapeshellarg($fixture)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        self::$serverProcess = proc_open($cmd, $descriptors, self::$pipes);
        if (!is_resource(self::$serverProcess)) {
            self::fail('Could not start PHP built-in test server.');
        }

        // Sunucu hazır olana kadar bekle (maks 5 sn)
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @fsockopen(self::HOST, self::PORT, $errno, $errstr, 0.2);
            if (is_resource($sock)) {
                fclose($sock);
                return;
            }
            usleep(50_000);
        }
        self::fail('Test server did not become ready in time.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            foreach (self::$pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate(self::$serverProcess, 15);
            proc_close(self::$serverProcess);
        }
        // PHP_CLI_SERVER_WORKERS fork edilmiş worker'ları parent ölünce hayatta kalabilir.
        // Port üzerinde dinleyen tüm process'leri öldür.
        self::killPortListeners();
    }

    private static function killPortListeners(): void
    {
        $pidList = @shell_exec(sprintf('lsof -ti tcp:%d 2>/dev/null', self::PORT));
        if (!is_string($pidList) || trim($pidList) === '') {
            return;
        }
        foreach (preg_split('/\s+/', trim($pidList)) as $pid) {
            if (ctype_digit($pid)) {
                @posix_kill((int) $pid, 9);
            }
        }
        usleep(100_000);
    }

    public function testSendRequestReturnsResponseInterface(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('GET', self::$baseUrl . '/echo'));

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testFourXXResponseIsReturnedNotThrown(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('GET', self::$baseUrl . '/status?code=404'));

        // PSR-18: 4xx ClientException atmaz, response olarak döner
        self::assertSame(404, $response->getStatusCode());
    }

    public function testFiveXXResponseIsReturnedNotThrown(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('GET', self::$baseUrl . '/status?code=500'));

        // PSR-18: 5xx ClientException atmaz, response olarak döner
        self::assertSame(500, $response->getStatusCode());
    }

    public function testNetworkErrorThrowsNetworkExceptionInterface(): void
    {
        $client = new Client();
        $request = new Request('GET', 'http://127.0.0.1:1/'); // unreachable

        try {
            $client->sendRequest($request);
            self::fail('Expected NetworkExceptionInterface to be thrown.');
        } catch (\Throwable $e) {
            self::assertInstanceOf(NetworkExceptionInterface::class, $e);
            self::assertSame($request, $e->getRequest());
        }
    }

    public function testHeadersAreSent(): void
    {
        $client = new Client();
        $request = new Request(
            'GET',
            self::$baseUrl . '/echo',
            ['X-Custom' => 'hello', 'X-Multi' => ['a', 'b']]
        );

        $response = $client->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('hello', $data['headers']['x-custom'] ?? null);
        self::assertSame('a, b', $data['headers']['x-multi'] ?? null);
    }

    public function testRequestBodyIsTransmittedVerbatim(): void
    {
        $client = new Client();
        $body = '{"name":"safak","n":42}';
        $request = new Request(
            'POST',
            self::$baseUrl . '/echo',
            ['Content-Type' => 'application/json'],
            new Stream($body, null)
        );

        $response = $client->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('POST', $data['method']);
        self::assertSame($body, $data['body']);
    }

    public function testResponseBodyIsReadable(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('GET', self::$baseUrl . '/echo'));

        $stream = $response->getBody();
        self::assertTrue($stream->isReadable());
        // İlk read → ikinci read aynı içerik (Stream->__toString rewind eder)
        self::assertSame((string) $stream, (string) $stream);
    }

    public function testSetCookieMultiValueIsPreserved(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('GET', self::$baseUrl . '/cookies'));

        $cookies = $response->getHeader('Set-Cookie');
        self::assertCount(2, $cookies);
        self::assertContains('a=1', $cookies);
        self::assertContains('b=2', $cookies);
    }

    public function testHeadRequestUsesNoBody(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('HEAD', self::$baseUrl . '/echo'));

        self::assertSame(200, $response->getStatusCode());
        // HEAD response body PSR-7'ye göre boş olmalı
        self::assertSame('', (string) $response->getBody());
    }

    public function testRedirectIsFollowedAndFinalStateReturned(): void
    {
        $client = new Client();
        $response = $client->sendRequest(new Request('GET', self::$baseUrl . '/redirect'));

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('GET', $data['method']);
    }
}
