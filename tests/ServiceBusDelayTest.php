<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\ServiceBus;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class ServiceBusDelayTest extends TestCase
{
    private ServiceBus $bus;

    /** @var BrokeredMessage|null */
    private $lastMessage;

    /** @var \PHPUnit\Framework\MockObject\MockObject&\WindowsAzure\ServiceBus\Internal\IServiceBus */
    private $mockServiceBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServiceBus = $this->createMock(IServiceBus::class);
        $this->lastMessage = null;

        $captured = &$this->lastMessage;
        $this->mockServiceBus->method('sendQueueMessage')
            ->willReturnCallback(function (string $queue, BrokeredMessage $message) use (&$captured) {
                $captured = $message;
            });
        $this->mockServiceBus->method('receiveQueueMessage')->willReturn(null);

        $this->bus = new ServiceBus($this->mockServiceBus, 'default');
        $this->bus->setContainer(new Container());
    }

    public function testLaterWithDateTime(): void
    {
        $at = new DateTime('+1 hour', new \DateTimeZone('America/Sao_Paulo'));

        $this->bus->later($at, 'TestJob');

        $this->assertNotNull($this->lastMessage);
        $scheduled = $this->lastMessage->getScheduledEnqueueTimeUtc();
        $this->assertInstanceOf(\DateTime::class, $scheduled);
        $this->assertSame($at->getTimestamp(), $scheduled->getTimestamp());
        $this->assertSame('UTC', $scheduled->getTimezone()->getName());
    }

    public function testLaterWithDateTimeImmutable(): void
    {
        $at = new DateTimeImmutable('+30 minutes');

        $this->bus->later($at, 'TestJob');

        $this->assertNotNull($this->lastMessage);
        $this->assertSame($at->getTimestamp(), $this->lastMessage->getScheduledEnqueueTimeUtc()->getTimestamp());
    }

    public function testLaterWithDateInterval(): void
    {
        $before = time();
        $this->bus->later(new DateInterval('PT10M'), 'TestJob');

        $this->assertNotNull($this->lastMessage);
        $delta = $this->lastMessage->getScheduledEnqueueTimeUtc()->getTimestamp() - $before;
        $this->assertGreaterThanOrEqual(595, $delta);
        $this->assertLessThanOrEqual(605, $delta);
    }

    public function testLaterRejectsNegativeInt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->bus->later(-5, 'TestJob');
    }

    public function testLaterRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->bus->later('tomorrow', 'TestJob'); // @phpstan-ignore-line
    }

    public function testPushUsesDefaultQueueWhenEmpty(): void
    {
        $queues = [];
        $serviceBus = $this->createMock(IServiceBus::class);
        $serviceBus->method('sendQueueMessage')
            ->willReturnCallback(function (string $queue) use (&$queues) {
                $queues[] = $queue;
            });

        $bus = new ServiceBus($serviceBus, 'fallback');
        $bus->setContainer(new Container());
        $bus->pushRaw('payload', null);
        $bus->pushRaw('payload', '');

        $this->assertSame(['fallback', 'fallback'], $queues);
    }
}
