<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Contracts;

use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\ReceiveMode;

/**
 * Minimal Azure Service Bus client contract.
 *
 * Implemented over the Service Bus REST API (SAS authentication),
 * so the package no longer depends on the abandoned
 * microsoft/windowsazure SDK.
 */
interface ServiceBusClient
{
    public function sendQueueMessage(string $queue, OutgoingMessage $message): void;

    public function receiveQueueMessage(
        string $queue,
        ReceiveMode $mode = ReceiveMode::PeekLock
    ): ?IncomingMessage;

    public function sendTopicMessage(string $topic, OutgoingMessage $message): void;

    public function receiveSubscriptionMessage(
        string $topic,
        string $subscription,
        ReceiveMode $mode = ReceiveMode::PeekLock
    ): ?IncomingMessage;

    public function deleteMessage(IncomingMessage $message): void;

    public function unlockMessage(IncomingMessage $message): void;

    public function subscriptionExists(string $topic, string $subscription): bool;

    public function createSubscription(string $topic, string $subscription): void;
}
