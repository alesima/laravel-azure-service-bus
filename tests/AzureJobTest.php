<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureJobTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\WindowsAzure\ServiceBus\Models\BrokeredMessage */
    private $mockMessage;

    /** @var \PHPUnit\Framework\MockObject\MockObject&\WindowsAzure\ServiceBus\Internal\IServiceBus */
    private $mockServiceBus;

    private Container $container;

    private AzureJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMessage = $this->createMock(BrokeredMessage::class);
        $this->mockServiceBus = $this->createMock(IServiceBus::class);
        $this->container = new Container();

        $this->job = new AzureJob(
            $this->container,
            $this->mockServiceBus,
            $this->mockMessage,
            'testQueue',
            '{"job":"TestJob"}'
        );
    }

    public function testDeleteDeletesMessageBeforeMarkingDeleted(): void
    {
        $serviceBus = $this->createMock(IServiceBus::class);
        $message = $this->createMock(BrokeredMessage::class);

        $serviceBus->expects($this->once())->method('deleteMessage')->with($message);

        $job = new AzureJob(new Container(), $serviceBus, $message, 'q', 'body');
        $job->delete();

        $this->assertTrue($job->isDeleted());
    }

    public function testReleaseUnlocksMessage(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('unlockMessage')
            ->with($this->mockMessage);

        $this->job->release();

        $this->assertTrue($this->job->isReleased());
    }

    public function testAttemptsCastsNullToZero(): void
    {
        $this->mockMessage->method('getDeliveryCount')->willReturn(null);

        $this->assertSame(0, $this->job->attempts());
    }

    public function testAttemptsReturnsDeliveryCount(): void
    {
        $this->mockMessage->method('getDeliveryCount')->willReturn(3);

        $this->assertSame(3, $this->job->attempts());
    }

    public function testGetJobIdFallsBackToEmptyString(): void
    {
        $this->mockMessage->method('getMessageId')->willReturn(null);

        $this->assertSame('', $this->job->getJobId());
    }

    public function testGetRawBody(): void
    {
        $this->assertSame('{"job":"TestJob"}', $this->job->getRawBody());
    }

    public function testGetQueue(): void
    {
        $this->assertSame('testQueue', $this->job->getQueue());
    }
}
