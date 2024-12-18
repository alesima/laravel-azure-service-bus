# Laravel Azure Service Bus Queue Driver 🚀

[![Packagist](https://img.shields.io/packagist/v/alesima/laravel-azure-service-bus.svg)](https://packagist.org/packages/alesima/laravel-azure-service-bus)
[![Coverage Status](https://coveralls.io/repos/github/alesima/laravel-azure-service-bus/badge.svg?branch=master)](https://coveralls.io/github/alesima/laravel-azure-service-bus?branch=master)
[![GitHub issues](https://img.shields.io/github/issues/alesima/laravel-azure-service-bus.svg)](https://github.com/alesima/laravel-azure-service-bus/issues)
[![License](https://img.shields.io/github/license/alesima/laravel-azure-service-bus.svg)](https://github.com/alesima/laravel-azure-service-bus/blob/main/LICENSE)

> Integrate **Azure Service Bus** as a queue driver and Pub/Sub module in Laravel, now with support for multiple topics.

This package provides a **custom queue driver** for Laravel that integrates with **Azure Service Bus** and adds support for **Topics and Subscriptions (Pub/Sub)**, enabling both queue-based and publish/subscribe messaging models with the ability to manage multiple topics dynamically.

---

## Features 🎯

- **Azure Service Bus Integration**: Seamlessly integrate Azure's messaging capabilities into your Laravel application.
- **Support for Laravel 5.x - 8.x**: Compatible with older Laravel versions and PHP 7.2 and above.
- **Queue Operations**: Push, pop, and manage jobs in Azure Service Bus queues with ease.
- **Pub/Sub Module**: Publish messages to multiple topics and subscribe to them using Azure Service Bus topics and subscriptions.
- **Job Scheduling**: Supports delayed jobs using `later()` with multiple formats (e.g., `DateTime`, `DateInterval`, `int`).
- **Built with Laravel's Queuing System**: Follows the same conventions, making it easy to work with.

---

## Installation ⚙️

### 1. Install the package via Composer:

```bash
composer require alesima/laravel-azure-service-bus
```

### 2. Publish the configuration:

After installing, publish the configuration file to adjust your Azure Service Bus settings.

```bash
php artisan vendor:publish --provider="LaravelAzureServiceBus\Providers\ServiceProvider" --tag=config
```

### 3. Configure `.env` file:

In your `.env` file, set the Azure Service Bus connection details:

```env
SERVICE_BUS_NAMESPACE=https://<your-namespace>
SERVICE_BUS_SHARED_ACCESS_KEY_NAME=<your-key-name>
SERVICE_BUS_SHARED_ACCESS_KEY=<your-key>

```

### **4. Define Azure Service Bus Configuration**

Update your `config/queue.php` file to include the following configuration for the Azure Service Bus driver:

```php
'connections' => [
    // Other connections...

    'azureservicebus' => [
        'driver' => 'azureservicebus',
        'endpoint' => sprintf('https://%s.servicebus.windows.net/', env('SERVICE_BUS_NAMESPACE')),
        'shared_access_key_name' => env('SERVICE_BUS_SHARED_ACCESS_KEY_NAME'),
        'shared_access_key' => env('SERVICE_BUS_SHARED_ACCESS_KEY'),
        'queue' => 'default',
        'UseTopic' => false,
    ],
],
```

---

### 5. Register the service provider and the queue manager (optional):

If you are using Lumen, you need to register the service provider in `bootstrap/app.php`:

```php
$app->register(Alesima\LaravelAzureServiceBus\Providers\ServiceProvider::class);
```

Also you might to register the queue manager in `config/app.php`:

```php
$app->bind(Illuminate\Queue\QueueManager::class, function ($app) {
    return $app['queue'];
});
```

---

## Local Development & Testing with Docker 🐳

For local development and testing, you can use the official PHP 7.4 Docker image to ensure compatibility.

Run the following command to install dependencies using Docker:

```bash
docker run --rm \
    -v $(pwd):/app \
    -w /app \
    php:7.4-cli \
    bash -c "apt-get update && apt-get install -y zip unzip git && curl -sS https://getcomposer.org/installer | php && php composer.phar install"
```

How to test:

```bash
docker run --rm \
    -v $(pwd):/app \
    -w /app \
    php:7.4-cli \
    bash -c "apt-get update && apt-get install -y zip unzip git && curl -sS https://getcomposer.org/installer | php && php composer.phar install && vendor/bin/phpunit --no-coverage"
```
---

## Usage 🛠️

### **Queue Operations**

#### Push Jobs onto the Queue ⬆️

You can push jobs to Azure Service Bus using the standard Laravel syntax:

```php
use App\Jobs\MyJob;

dispatch(new MyJob($data)); // Push to the default queue
```

#### Use `later()` for Delayed Jobs ⏳

You can schedule jobs to be pushed after a delay using various formats:

```php
dispatch((new MyJob($data))->delay(60)); // Delay by 60 seconds

$interval = new \DateInterval('PT10M'); // 10 minutes
dispatch((new MyJob($data))->delay($interval));

$releaseTime = new \DateTime('+1 hour');
dispatch((new MyJob($data))->delay($releaseTime));
```

#### Handle Jobs 🚀

When a job is received from the queue, it will be processed as a standard Laravel job:

```php
public function handle()
{
    // Your job logic here
}
```

---

### **Pub/Sub Module**

The Pub/Sub module enables publishing messages to Azure Service Bus topics and receiving them from subscriptions. This now supports managing multiple topics dynamically.

#### Publish Messages to a Topic 📢

You can publish a message to a specific topic:

```php
use LaravelAzureServiceBus\Services\AzurePubSubService;

$pubSub = app(AzurePubSubService::class);

// Publish to a specific topic
$pubSub->publishMessage('topic1', [
    'event' => 'user.created',
    'data' => ['user_id' => 123],
]);
```

#### Subscribe to a Specific Topic 🔔

To retrieve messages from a subscription under a specific topic:

```php
use LaravelAzureServiceBus\Services\AzurePubSubService;

$pubSub = app(AzurePubSubService::class);

// Subscribe to messages from 'topic1'
$messages = $pubSub->retrieveMessages('topic1', 'subscription1');

foreach ($messages as $message) {
    echo $message; // Process the message
}
```

#### Retrieve Messages from Multiple Topics 🔄

To work with multiple topics dynamically:

```php
use LaravelAzureServiceBus\Services\AzurePubSubService;

$pubSub = app(AzurePubSubService::class);

// Retrieve messages from multiple topics
$topics = ['topic1', 'topic2', 'topic3'];

foreach ($topics as $topic) {
    $messages = $pubSub->retrieveMessages($topic, 'subscription1');
    foreach ($messages as $message) {
        echo "From {$topic}: " . $message;
    }
}
```

---

## Compatibility 🧩

This package is compatible with:
- **Laravel**: 5.x, 6.x, 7.x, and 8.x.
- **PHP**: 7.2 to 7.4.

---

## Testing ⚡️

You can run tests using PHPUnit:

```bash
vendor/bin/phpunit
```

---

## License 📜

This package is licensed under the **MIT License**. See [LICENSE](https://github.com/alesima/laravel-azure-service-bus/blob/main/LICENSE) for more information.

---

## Contributing 🤝

We welcome contributions to make this package even better!

1. Fork the repository.
2. Create a new branch.
3. Make your changes and commit them.
4. Open a pull request.

---

## Credits 🏆

- **[Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php)**: Provides the integration with Azure Service Bus.
- **[Laravel](https://laravel.com/)**: The PHP framework that powers this package.

---

## Attribution

Inspired by https://github.com/goavega-software/laravel-azure-servicebus-topic & https://github.com/pawprintdigital/laravel-queue-raw-sqs

---

## Contact 📬

For any questions, feel free to reach out to us via GitHub Issues or email us at [alex@codingwithalex.com](mailto:alex@codingwithalex.com).
