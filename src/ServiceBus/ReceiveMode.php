<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus;

/**
 * How a received message is settled on read.
 */
enum ReceiveMode: string
{
    case PeekLock = 'peek-lock';
    case ReceiveAndDelete = 'receive-and-delete';
}
