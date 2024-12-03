<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureQueueTest extends TestCase
{
    protected $queue;

    protected function setUp(): void
    {
        $mockServiceBus = $this->createMock(IServiceBus::class);
        $this->queue = new AzureQueue($mockServiceBus, 'testQueue');
    }

    public function testPushRaw()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);

        $mockServiceBus = $this->createMock(IServiceBus::class);
        $mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with('testQueue', $mockMessage);

        $queue = new AzureQueue($mockServiceBus, 'testQueue');
        $queue->pushRaw('testPayload');
    }

    public function testPop()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"job":"TestJob"}');

        $mockServiceBus = $this->createMock(IServiceBus::class);
        $mockServiceBus->method('receiveQueueMessage')
            ->willReturn($mockMessage);

        $queue = new AzureQueue($mockServiceBus, 'testQueue');
        $job = $queue->pop();

        $this->assertInstanceOf(AzureJob::class, $job);
    }

    public function testLater()
    {
        $mockServiceBus = $this->createMock(IServiceBus::class);

        $queue = new AzureQueue($mockServiceBus, 'testQueue');
        $queue->later(60, 'TestJob', ['data' => 'value']);

        $this->assertTrue(true); // No exception means success
    }
}
