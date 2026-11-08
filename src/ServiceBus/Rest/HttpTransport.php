<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Rest;

/**
 * Minimal HTTP transport. Implementations must return the response
 * for any HTTP status (no exceptions on 4xx/5xx) and use
 * lower-cased response header names.
 */
interface HttpTransport
{
    /**
     * @param array<string,string> $headers
     */
    public function request(string $method, string $url, array $headers, string $body = ''): HttpResponse;
}
