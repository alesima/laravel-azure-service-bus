<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzurePubSub;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use PHPUnit\Framework\MockObject\MockObject;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzurePubSubTest extends TestCase
{
    /**
     * Instance of AzurePubSub
     *
     * @var AzurePubSub
     */
    protected $pubsub;

    /**
     * Mock for IServiceBus
     *
     * @var IServiceBus&MockObject
     */
    protected $mockServiceBus;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for IServiceBus
        $this->mockServiceBus = $this->createMock(IServiceBus::class);

        // Create the AzurePubSub instance with the mock
        $this->pubsub = new AzurePubSub($this->mockServiceBus);
    }

    public function testPublishMessage()
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendTopicMessage');

        $this->pubsub->publish('testTopic', ['data' => 'test']);
    }

    public function testSubscribeMessage()
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"event":"user.created"}');

        $this->mockServiceBus->expects($this->once())
            ->method('receiveSubscriptionMessage')
            ->willReturn($mockMessage);

        $message = $this->pubsub->subscribe('testTopic', 'testSubscription');

        $this->assertEquals('{"event":"user.created"}', $message->getBody());
    }
}
