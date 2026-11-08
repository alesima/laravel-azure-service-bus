<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class AzureJob extends Job implements JobContract
{
    protected ServiceBusClient $client;

    protected IncomingMessage $job;

    /** @var string Matches parent signature (untyped in illuminate <= 8). */
    protected $queue;

    protected string $rawMessage;

    public function __construct(
        Container $container,
        ServiceBusClient $client,
        IncomingMessage $job,
        string $queue,
        string $rawMessage
    ) {
        $this->container = $container;
        $this->client = $client;
        $this->job = $job;
        $this->queue = $queue;
        $this->rawMessage = $rawMessage;
    }

    public function delete(): void
    {
        $this->client->deleteMessage($this->job);
        parent::delete();
    }

    /**
     * Unlock the message. Note: Azure PeekLock unlock does not honor
     * a server-side delay — the message becomes visible immediately.
     * Callers needing a true delay should delete + re-push with later().
     *
     * @param int $delay Ignored by the Service Bus REST API on unlock; kept for contract compat.
     */
    public function release($delay = 0): void
    {
        parent::release($delay);
        $this->client->unlockMessage($this->job);
    }

    public function attempts(): int
    {
        return $this->job->getDeliveryCount();
    }

    // NOTE: failed-job cleanup goes through fail() -> delete(), same as
    // Illuminate\Queue\Jobs\SqsJob. No failed() override on purpose: the
    // parent signature differs between illuminate versions, so overriding
    // it breaks one side or the other.

    public function getJobId(): string
    {
        return (string) $this->job->getMessageId();
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getClient(): ServiceBusClient
    {
        return $this->client;
    }

    public function getMessage(): IncomingMessage
    {
        return $this->job;
    }

    public function getRawBody(): string
    {
        return $this->rawMessage;
    }
}
