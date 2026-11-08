<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Models;

/**
 * A message read from a queue or subscription.
 */
final class IncomingMessage
{
    public function __construct(
        private string $body,
        private ?string $messageId,
        private int $deliveryCount,
        private ?string $lockLocation
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getDeliveryCount(): int
    {
        return $this->deliveryCount;
    }

    public function getLockLocation(): ?string
    {
        return $this->lockLocation;
    }
}
