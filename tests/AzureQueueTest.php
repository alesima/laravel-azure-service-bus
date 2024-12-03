<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureJobTest extends TestCase
{
    public function testDelete()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);

        $mockServiceBus = $this->createMock(IServiceBus::class);
        $mockServiceBus->expects($this->once())
            ->method('deleteMessage')
            ->with($mockMessage);

        $job = new AzureJob(new Container(), $mockServiceBus, $mockMessage, 'testQueue', 'testPayload');
        $job->delete();

        $this->assertTrue(true); // No exception means success
    }

    public function testRelease()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);

        $mockServiceBus = $this->createMock(IServiceBus::class);
        $mockServiceBus->expects($this->once())
            ->method('unlockMessage')
            ->with($mockMessage);

        $job = new AzureJob(new Container(), $mockServiceBus, $mockMessage, 'testQueue', 'testPayload');
        $job->release(60);

        $this->assertTrue(true); // No exception means success
    }

    public function testGetRawBody()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"job":"TestJob"}');

        $job = new AzureJob(new Container(), $this->createMock(IServiceBus::class), $mockMessage, 'testQueue', 'testPayload');

        $this->assertEquals('testPayload', $job->getRawBody());
    }
}
