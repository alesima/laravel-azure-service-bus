<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use Alesima\LaravelAzureServiceBus\ServiceBus;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\ServiceBusException;
use InvalidArgumentException;
use Throwable;

/**
 * Topic / subscription (Pub/Sub) driver on top of ServiceBus.
 */
class AzureTopic extends ServiceBus
{
    /**
     * @param mixed $payload String body or array (JSON-encoded).
     */
    public function publish(string $topic, $payload): void
    {
        if (is_array($payload)) {
            try {
                $payload = json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    'Topic payload is not JSON serializable: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        if (! is_string($payload)) {
            throw new InvalidArgumentException('Topic payload must be a string or an array.');
        }

        if (trim($topic) === '') {
            throw new InvalidArgumentException('Topic name must not be empty.');
        }

        $this->client->sendTopicMessage($topic, new OutgoingMessage($payload));
    }

    public function subscribe(string $topic, string $subscription): ?IncomingMessage
    {
        if (trim($topic) === '' || trim($subscription) === '') {
            throw new InvalidArgumentException('Topic and subscription names must not be empty.');
        }

        $this->ensureSubscriptionExists($topic, $subscription);

        return $this->client->receiveSubscriptionMessage($topic, $subscription);
    }

    protected function ensureSubscriptionExists(string $topic, string $subscription): void
    {
        if (trim($topic) === '' || trim($subscription) === '') {
            throw new InvalidArgumentException('Topic and subscription names must not be empty.');
        }

        if ($this->client->subscriptionExists($topic, $subscription)) {
            return;
        }

        try {
            $this->client->createSubscription($topic, $subscription);
        } catch (ServiceBusException $e) {
            if ($e->getStatusCode() !== 409) {
                throw $e;
            }
            // 409: another worker created it between the check and the PUT.
        }
    }
}
