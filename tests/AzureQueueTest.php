<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureQueueTest extends TestCase
{
    /**
     * The AzureQueue instance
     *
     * @var AzureQueue
     */
    protected $queue;

    /**
     * The mock IServiceBus
     *
     * @var IServiceBus&MockObject
     */
    protected $mockServiceBus;

    protected function setUp(): void
    {
        // Create a mock for the IServiceBus
        $this->mockServiceBus = $this->createMock(IServiceBus::class);

        // Create the AzureQueue instance with the mock
        $this->queue = new AzureQueue($this->mockServiceBus, 'testQueue');

        // Inject the container
        $container = new Container();
        $this->queue->setContainer($container);
    }

    public function testPushRaw()
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with('testQueue', $this->callback(function ($message) {
                return $message instanceof BrokeredMessage && $message->getBody() === 'testPayload';
            }));

        $this->queue->pushRaw('testPayload');
    }

    public function testPop()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"job":"TestJob"}');

        $this->mockServiceBus->method('receiveQueueMessage')
            ->willReturn($mockMessage);

        $job = $this->queue->pop();

        $this->assertInstanceOf(AzureJob::class, $job);
    }

    public function testLater()
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with('testQueue', $this->callback(function ($message) {
                $scheduledTime = $message->getScheduledEnqueueTimeUtc();
                return $message instanceof BrokeredMessage &&
                    $scheduledTime instanceof \DateTime &&
                    $scheduledTime > new \DateTime();
            }));

        $this->queue->later(60, 'TestJob', ['data' => 'value']);
    }
}
