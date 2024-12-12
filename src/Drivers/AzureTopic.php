<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use Alesima\LaravelAzureServiceBus\ServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\SubscriptionInfo;

/**
 * Class AzureTopic
 *
 * Extends ServiceBus for Azure Service Bus topics.
 */
class AzureTopic extends ServiceBus
{
    /**
     * Publish a message to a topic.
     *
     * @param string $topic Topic name.
     * @param string|array $payload Message payload.
     * @return void
     * @throws \Exception If sending the message fails.
     */
    public function publish(string $topic, $payload): void
    {
        $message = new BrokeredMessage(is_array($payload) ? json_encode($payload) : $payload);
        $this->sendMessageToTopic($topic, $message);
    }

    /**
     * Subscribe to a topic subscription and receive a message.
     *
     * @param string $topic Topic name.
     * @param string $subscription Subscription name.
     * @return BrokeredMessage|null Received message or null if none.
     * @throws \Exception If receiving the message fails.
     */
    public function subscribe(string $topic, string $subscription): ?BrokeredMessage
    {
        $this->ensureSubscriptionExists($topic, $subscription);
        return $this->receiveMessageFromSubscription($topic, $subscription);
    }

    /**
     * Send a message to a topic.
     *
     * @param string $topic Topic name.
     * @param BrokeredMessage $message The message to send.
     * @return void
     * @throws \Exception If sending the message fails.
     */
    protected function sendMessageToTopic(string $topic, BrokeredMessage $message): void
    {
        $this->azure->sendTopicMessage($topic, $message);
    }

    /**
     * Receive a message from a topic subscription.
     *
     * @param string $topic Topic name.
     * @param string $subscription Subscription name.
     * @return BrokeredMessage|null The received message or null if none.
     * @throws \Exception If receiving the message fails.
     */
    protected function receiveMessageFromSubscription(string $topic, string $subscription): ?BrokeredMessage
    {
        return $this->azure->receiveSubscriptionMessage($topic, $subscription, $this->receiveOptions);
    }

    /**
     * Ensure that a subscription exists for a topic.
     *
     * @param string $topic Topic name.
     * @param string $subscription Subscription name.
     * @return void
     * @throws \Exception If creating the subscription fails.
     */
    protected function ensureSubscriptionExists(string $topic, string $subscription): void
    {
        $subscriptionInfo = new SubscriptionInfo($subscription);
        $this->azure->createSubscription($topic, $subscriptionInfo);
    }
}
