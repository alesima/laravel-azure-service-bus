<?php

namespace Alesima\LaravelAzureServiceBus\Tests\Rest;

use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\CurlHttpTransport;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\ServiceBusException;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real cURL transport against a local PHP web server.
 * Skipped when the loopback server cannot be started.
 */
class CurlHttpTransportTest extends TestCase
{
    private const HOST = '127.0.0.1';

    private const PORT = 18765;

    /** @var resource|null */
    private static $server;

    private static function baseUrl(): string
    {
        return 'http://' . self::HOST . ':' . self::PORT;
    }

    public static function setUpBeforeClass(): void
    {
        $router = __DIR__ . '/../Support/stub-server.php';
        $command = PHP_BINARY . ' -S ' . self::HOST . ':' . self::PORT . ' ' . escapeshellarg($router);

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $server = proc_open($command, $descriptors, $pipes);

        if (! is_resource($server)) {
            self::markTestSkipped('Could not start local stub server.');
        }

        $ready = false;
        for ($i = 0; $i < 50; $i++) {
            $connection = @stream_socket_client(
                'tcp://' . self::HOST . ':' . self::PORT,
                $errno,
                $errstr,
                0.1
            );

            if ($connection !== false) {
                fclose($connection);
                $ready = true;

                break;
            }

            usleep(100000);
        }

        if (! $ready) {
            proc_terminate($server);
            proc_close($server);
            self::markTestSkipped('Local stub server did not become ready.');
        }

        self::$server = $server;
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function testGetReturnsStatusHeadersAndBody(): void
    {
        $transport = new CurlHttpTransport();

        $response = $transport->request('GET', self::baseUrl() . '/ok', []);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('ok', $response->body);
        // PHP's built-in server appends ";charset=UTF-8" to text/plain.
        $this->assertStringStartsWith('text/plain', (string) $response->header('Content-Type'));
        $this->assertStringStartsWith('text/plain', (string) $response->header('content-type'));
        $this->assertNull($response->header('X-Does-Not-Exist'));
    }

    public function testPostSendsBodyAndHeaders(): void
    {
        $transport = new CurlHttpTransport();

        $response = $transport->request(
            'POST',
            self::baseUrl() . '/echo',
            ['Authorization' => 'SharedAccessSignature sr=x', 'Content-Type' => 'text/plain'],
            'ping-body'
        );

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('method=POST;body=ping-body', $response->body);
        $this->assertSame('POST', $response->header('X-Echo-Method'));
        $this->assertSame('yes', $response->header('X-Echo-Has-Auth'));
    }

    public function testNonSuccessStatusIsReturnedWithoutThrowing(): void
    {
        $transport = new CurlHttpTransport();

        $response = $transport->request('GET', self::baseUrl() . '/missing', []);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame('nope', $response->body);
    }

    public function testUnreachableHostThrows(): void
    {
        $transport = new CurlHttpTransport();

        $this->expectException(ServiceBusException::class);

        // Port 1 is (almost) certainly closed: connection refused, no HTTP status.
        $transport->request('GET', 'http://127.0.0.1:1/unreachable', []);
    }

    public function testEmptyMethodOrUrlThrows(): void
    {
        $transport = new CurlHttpTransport();

        try {
            $transport->request('', self::baseUrl() . '/ok', []);
            $this->fail('Expected ServiceBusException for empty method.');
        } catch (ServiceBusException $e) {
            $this->assertSame(0, $e->getStatusCode());
        }

        try {
            $transport->request('GET', '', []);
            $this->fail('Expected ServiceBusException for empty URL.');
        } catch (ServiceBusException $e) {
            $this->assertSame(0, $e->getStatusCode());
        }
    }
}
