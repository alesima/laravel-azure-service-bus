<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureJobTest extends TestCase
{
    /**
     * Mocked BrokeredMessage instance
     *
     * @var BrokeredMessage&MockObject
     */
    protected $mockMessage;

    /**
     * Mocked IServiceBus instance
     *
     * @var IServiceBus&MockObject
     */
    protected $mockServiceBus;

    /**
     * Container instance
     *
     * @var Container
     */
    protected $container;

    /**
     * AzureJob instance
     *
     * @var AzureJob
     */
    protected $job;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks and dependencies
        $this->mockMessage = $this->createMock(BrokeredMessage::class);
        $this->mockServiceBus = $this->createMock(IServiceBus::class);
        $this->container = new Container();

        // Create the AzureJob instance
        $this->job = new AzureJob(
            $this->container,
            $this->mockServiceBus,
            $this->mockMessage,
            'testQueue',
            'testPayload'
        );
    }

    public function testDelete()
    {
        $this->mockServiceBus->expects($this->once())
            ->method('deleteMessage')
            ->with($this->mockMessage);

        $this->job->delete();

        $this->assertTrue(true); // No exception means success
    }

    public function testRelease()
    {
        $this->mockServiceBus->expects($this->once())
            ->method('unlockMessage')
            ->with($this->mockMessage);

        $this->job->release(60);

        $this->assertTrue(true); // No exception means success
    }

    public function testGetRawBody()
    {
        $this->mockMessage->method('getBody')->willReturn('{"job":"TestJob"}');

        $this->assertEquals('testPayload', $this->job->getRawBody());
    }
}
