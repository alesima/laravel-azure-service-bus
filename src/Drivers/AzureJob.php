<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureJob extends Job implements JobContract
{
    protected IServiceBus $azure;

    protected BrokeredMessage $job;

    /** @var string Matches parent signature (untyped in illuminate <= 8). */
    protected $queue;

    protected string $rawMessage;

    public function __construct(
        Container $container,
        IServiceBus $azure,
        BrokeredMessage $job,
        string $queue,
        string $rawMessage
    ) {
        $this->container = $container;
        $this->azure = $azure;
        $this->job = $job;
        $this->queue = $queue;
        $this->rawMessage = $rawMessage;
    }

    public function delete(): void
    {
        $this->azure->deleteMessage($this->job);
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

        if (is_int($delay) && $delay > 0) {
            $releaseTime = new DateTime('now', new DateTimeZone('UTC'));
            $releaseTime->add(new DateInterval('PT' . $delay . 'S'));
            $this->job->setScheduledEnqueueTimeUtc($releaseTime);
        }

        $this->azure->unlockMessage($this->job);
    }

    public function attempts(): int
    {
        return (int) $this->job->getDeliveryCount();
    }

    // NOTE: failed-job cleanup goes through fail() -> delete(), same as
    // Illuminate\Queue\Jobs\SqsJob. No failed() override on purpose: the
    // parent signature differs between illuminate 8 (protected, untyped)
    // and 10/11 (typed), so overriding it breaks one side or the other.

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

    public function getAzure(): IServiceBus
    {
        return $this->azure;
    }

    public function getAzureJob(): BrokeredMessage
    {
        return $this->job;
    }

    public function getRawBody(): string
    {
        return $this->rawMessage;
    }
}
