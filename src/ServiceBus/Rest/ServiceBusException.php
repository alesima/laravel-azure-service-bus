<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Rest;

use RuntimeException;

/**
 * Service Bus operation failure. The HTTP status code is available
 * via getStatusCode() (0 when the request never reached the server).
 */
class ServiceBusException extends RuntimeException
{
    public function __construct(string $message, private int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
