# Laravel Azure Service Bus Queue Driver

Integrate **Azure Service Bus** as a Laravel queue driver, with support for queues and topics/subscriptions (Pub/Sub).

## Requirements

- PHP `^8.1`
- Laravel `10.*` / `11.*`
- `microsoft/windowsazure ^0.5` (legacy SDK — isolated behind `IServiceBus`; migration to a maintained REST client is the recommended next step)

## Installation

```bash
composer require alesima/laravel-azure-service-bus
```

Publish the config:

```bash
php artisan vendor:publish --provider="Alesima\LaravelAzureServiceBus\Providers\ServiceProvider" --tag=config
```

## Configuration

`config/azureservicebus.php`:

```php
return [
    'endpoint' => env('SERVICE_BUS_ENDPOINT', 'https://<your-namespace>.servicebus.windows.net'),
    'shared_access_key_name' => env('SERVICE_BUS_SHARED_ACCESS_KEY_NAME'),
    'shared_access_key' => env('SERVICE_BUS_SHARED_ACCESS_KEY'),
    'queue' => env('SERVICE_BUS_QUEUE', 'default'),
];
```

`config/queue.php`:

```php
'connections' => [
    'azureservicebus' => [
        'driver' => 'azureservicebus',
        'endpoint' => config('azureservicebus.endpoint'),
        'shared_access_key_name' => config('azureservicebus.shared_access_key_name'),
        'shared_access_key' => config('azureservicebus.shared_access_key'),
        'queue' => config('azureservicebus.queue', 'default'),
    ],
],
```

Set `QUEUE_CONNECTION=azureservicebus` or dispatch explicitly.

## Usage

### Queues

```php
dispatch(new MyJob($data));
dispatch((new MyJob($data))->delay(60));
dispatch((new MyJob($data))->delay(new DateInterval('PT10M')));
dispatch((new MyJob($data))->delay(new DateTime('+1 hour')));
```

`size()` always returns `0` — the legacy SDK exposes no cheap count API. Don't use it for Horizon autoscaling.

`release($delay)` unlocks the message; Azure PeekLock unlock does **not** honor a server-side delay. For a true delay, delete + re-push with `later()`.

### Topics / Subscriptions (Pub/Sub)

```php
use Alesima\LaravelAzureServiceBus\Drivers\AzureTopic;

/** @var AzureTopic $topics */
$topics = app('queue')->connection('azureservicebus');

// Publish (array is JSON-encoded, string is sent raw)
$topics->publish('orders', ['event' => 'order.created', 'id' => 123]);

// Subscribe (creates the subscription if missing; 409 Conflict is ignored)
$message = $topics->subscribe('orders', 'billing');
if ($message !== null) {
    $body = $message->getBody();
}
```

## Lumen

```php
$app->register(Alesima\LaravelAzureServiceBus\Providers\ServiceProvider::class);
```

## Testing

```bash
composer test
composer analyse
composer lint
```

## License

MIT.
