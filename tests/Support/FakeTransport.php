<?php

namespace Alesima\LaravelAzureServiceBus\Tests\Support;

use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\HttpResponse;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\HttpTransport;

/**
 * Scriptable fake HTTP transport for REST client tests.
 */
final class FakeTransport implements HttpTransport
{
    /**
     * @var list<array{method: string, url: string, headers: array<string,string>, body: string}>
     */
    public array $requests = [];

    /**
     * @var list<HttpResponse>
     */
    private array $scripted = [];

    private HttpResponse $default;

    public function __construct(?HttpResponse $default = null)
    {
        $this->default = $default ?? new HttpResponse(200, [], '');
    }

    public function queue(HttpResponse ...$responses): void
    {
        array_push($this->scripted, ...$responses);
    }

    /**
     * @param array<string,string> $headers
     */
    public function request(string $method, string $url, array $headers, string $body = ''): HttpResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        $next = array_shift($this->scripted);

        return $next ?? $this->default;
    }

    /**
     * @return array{method: string, url: string, headers: array<string,string>, body: string}
     */
    public function lastRequest(): array
    {
        if ($this->requests === []) {
            throw new \RuntimeException('No requests recorded.');
        }

        return $this->requests[count($this->requests) - 1];
    }
}
