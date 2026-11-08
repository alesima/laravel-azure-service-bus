<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Rest;

/**
 * HTTP response with lower-cased header names.
 */
final class HttpResponse
{
    /**
     * @param array<string,string> $headers Lower-cased header names.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $body
    ) {
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);

        return $this->headers[$key] ?? null;
    }
}
