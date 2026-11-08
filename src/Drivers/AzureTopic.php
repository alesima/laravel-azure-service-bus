<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use Alesima\LaravelAzureServiceBus\ServiceBus;
use InvalidArgumentException;
use Throwable;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\SubscriptionInfo;

/**
 * Topic / subscription (Pub/Sub) driver on top of ServiceBus.
 */
class AzureTopic extends ServiceBus
{
    /**
     * @param string $topic Topic name.
     * @param array<array-key,mixed>|string $payload Message body.
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

        $message = new BrokeredMessage($payload);
        $this->sendMessageToTopic($topic, $message);
    }

    public function subscribe(string $topic, string $subscription): ?BrokeredMessage
    {
        $this->ensureSubscriptionExists($topic, $subscription);

        return $this->receiveMessageFromSubscription($topic, $subscription);
    }

    protected function sendMessageToTopic(string $topic, BrokeredMessage $message): void
    {
        $this->azure->sendTopicMessage($topic, $message);
    }

    protected function receiveMessageFromSubscription(string $topic, string $subscription): ?BrokeredMessage
    {
        return $this->azure->receiveSubscriptionMessage($topic, $subscription, $this->receiveOptions);
    }

    protected function ensureSubscriptionExists(string $topic, string $subscription): void
    {
        if (trim($topic) === '' || trim($subscription) === '') {
            throw new InvalidArgumentException('Topic and subscription names must not be empty.');
        }

        // Fast path: subscription already exists — skip creation.
        try {
            if (method_exists($this->azure, 'getSubscription')) {
                $existing = $this->azure->getSubscription($topic, $subscription);
                if ($existing !== null) {
                    return;
                }
            }
        } catch (Throwable $ignored) {
            // Fall through to create; a 404 here just means "does not exist".
        }

        try {
            $subscriptionInfo = new SubscriptionInfo($subscription);
            $this->azure->createSubscription($topic, $subscriptionInfo);
        } catch (Throwable $e) {
            if ($this->isAlreadyExistsError($e)) {
                return;
            }

            throw $e;
        }
    }

    private function isAlreadyExistsError(Throwable $e): bool
    {
        $message = $e->getMessage();
        if (stripos($message, '409') !== false) {
            return true;
        }

        if (stripos($message, 'Conflict') !== false) {
            return true;
        }

        if (stripos($message, 'already exists') !== false) {
            return true;
        }

        $code = (string) $e->getCode();
        if ($code === '409') {
            return true;
        }

        return false;
    }
}
