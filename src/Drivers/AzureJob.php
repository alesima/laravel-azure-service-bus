<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureJob extends Job implements JobContract
{
    /**
     * The Azure IServiceBus instance.
     *
     * @var IServiceBus
     */
    protected $azure;

    /**
     * The Azure ServiceBus job instance.
     *
     * @var BrokeredMessage
     */
    protected $job;

    /**
     * The queue that the job belongs to.
     *
     * @var string
     */
    protected $queue;

    /**
     * The raw payload on the queue.
     *
     * @var string
     */
    protected $rawMessage;

    /**
     * Create a new job instance.
     *
     * @param Container $container
     * @param IServiceBus $azure
     * @param BrokeredMessage $job
     * @param string $queue
     * @param string $rawMessage
     */
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

    /**
     * Delete the job from the queue.
     *
     * @throws \Exception
     */
    public function delete(): void
    {
        parent::delete();
        $this->azure->deleteMessage($this->job);
    }

    /**
     * Release the job back into the queue with an optional delay.
     *
     * @param int $delay Delay in seconds before the job is available.
     * @throws \Exception
     */
    public function release($delay = 0): void
    {
        $releaseTime = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->add(new \DateInterval('PT' . $delay . 'S'));

        $this->job->setScheduledEnqueueTimeUtc($releaseTime);
        $this->azure->unlockMessage($this->job);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return $this->job->getDeliveryCount();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId(): string
    {
        return $this->job->getMessageId();
    }

    /**
     * Get the IoC container instance.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the underlying Azure client instance.
     *
     * @return IServiceBus
     */
    public function getAzure(): IServiceBus
    {
        return $this->azure;
    }

    /**
     * Get the underlying raw Azure job.
     *
     * @return BrokeredMessage
     */
    public function getAzureJob(): BrokeredMessage
    {
        return $this->job;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->rawMessage;
    }
}
