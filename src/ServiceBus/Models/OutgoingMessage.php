<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Models;

use DateTimeInterface;

/**
 * A message to be sent to a queue or topic.
 */
final class OutgoingMessage
{
    public function __construct(
        private string $body,
        private ?DateTimeInterface $scheduledEnqueueTimeUtc = null,
        private ?string $contentType = null
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getScheduledEnqueueTimeUtc(): ?DateTimeInterface
    {
        return $this->scheduledEnqueueTimeUtc;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}
