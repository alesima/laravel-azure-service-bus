<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class AzureJobTest extends TestCase
{
    private IncomingMessage $message;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ServiceBusClient */
    private $mockClient;

    private Container $container;

    private AzureJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->message = new IncomingMessage('{"job":"TestJob"}', 'id-7', 3, 'lock-location');
        $this->mockClient = $this->createMock(ServiceBusClient::class);
        $this->container = new Container();

        $this->job = new AzureJob(
            $this->container,
            $this->mockClient,
            $this->message,
            'testQueue',
            '{"job":"TestJob"}'
        );
    }

    public function testDeleteDeletesMessageBeforeMarkingDeleted(): void
    {
        $client = $this->createMock(ServiceBusClient::class);
        $message = new IncomingMessage('body', 'id-1', 1, 'lock');

        $client->expects($this->once())->method('deleteMessage')->with($message);

        $job = new AzureJob(new Container(), $client, $message, 'q', 'body');
        $job->delete();

        $this->assertTrue($job->isDeleted());
    }

    public function testReleaseUnlocksMessage(): void
    {
        $this->mockClient->expects($this->once())
            ->method('unlockMessage')
            ->with($this->message);

        $this->job->release();

        $this->assertTrue($this->job->isReleased());
    }

    public function testAttemptsReturnsDeliveryCount(): void
    {
        $this->assertSame(3, $this->job->attempts());
    }

    public function testGetJobIdReturnsMessageId(): void
    {
        $this->assertSame('id-7', $this->job->getJobId());
    }

    public function testGetJobIdFallsBackToEmptyString(): void
    {
        $job = new AzureJob(
            $this->container,
            $this->mockClient,
            new IncomingMessage('body', null, 0, null),
            'q',
            'body'
        );

        $this->assertSame('', $job->getJobId());
    }

    public function testGetRawBody(): void
    {
        $this->assertSame('{"job":"TestJob"}', $this->job->getRawBody());
    }

    public function testGetQueue(): void
    {
        $this->assertSame('testQueue', $this->job->getQueue());
    }

    public function testGetMessage(): void
    {
        $this->assertSame($this->message, $this->job->getMessage());
    }
}
