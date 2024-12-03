<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzurePubSub
{
    /**
     * The Azure Service Bus client instance.
     *
     * @var IServiceBus
     */
    protected $azure;

    /**
     * Create a new AzurePubSub instance.
     *
     * @param IServiceBus $azure
     */
    public function __construct(IServiceBus $azure)
    {
        $this->azure = $azure;
    }

    /**
     * Publish a message to a topic.
     *
     * @param string $topic
     * @param string|array $payload
     * @throws \Exception
     */
    public function publish(string $topic, $payload)
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $message = new BrokeredMessage($payload);

        $this->azure->sendTopicMessage($topic, $message);
    }

    /**
     * Subscribe to a topic.
     *
     * @param string $topic
     * @param string $subscription
     * @return BrokeredMessage|null
     * @throws \Exception
     */
    public function subscribe(string $topic, string $subscription): ?BrokeredMessage
    {
        return $this->azure->receiveSubscriptionMessage($topic, $subscription);
    }
}
