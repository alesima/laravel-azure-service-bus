# Laravel Azure Service Bus Queue Driver 🚀

[![Packagist](https://img.shields.io/packagist/v/alesima/laravel-azure-service-bus.svg)](https://packagist.org/packages/alesima/laravel-azure-service-bus)
[![GitHub issues](https://img.shields.io/github/issues/alesima/laravel-azure-service-bus.svg)](https://github.com/alesima/laravel-azure-service-bus/issues)
[![License](https://img.shields.io/github/license/alesima/laravel-azure-service-bus.svg)](https://github.com/alesima/laravel-azure-service-bus/blob/main/LICENSE)

> Integrate **Azure Service Bus** as a queue driver in Laravel.

This package provides a **custom queue driver** for Laravel that integrates with **Azure Service Bus**, allowing you to manage jobs in your queues using Azure's powerful messaging platform.

---

## Features 🎯

- **Azure Service Bus Integration**: Seamlessly integrate Azure's messaging capabilities into your Laravel application.
- **Support for Laravel 5.x - 10.x**: Works with a wide range of Laravel versions.
- **Job Scheduling**: Supports delayed jobs using `later()` with multiple formats (e.g., `DateTime`, `DateInterval`, `int`).
- **Queue Operations**: Push, pop, and manage jobs in Azure Service Bus queues with ease.
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
AZURE_SERVICE_BUS_ENDPOINT=https://<your-namespace>.servicebus.windows.net
AZURE_SERVICE_BUS_KEY_NAME=<your-key-name>
AZURE_SERVICE_BUS_KEY=<your-key>
AZURE_SERVICE_BUS_QUEUE=<your-queue-name>
```

### 4. Add to `config/queue.php`:

Update your `config/queue.php` to add the Azure connection:

```php
'connections' => [
    'azureservicebus' => [
        'driver' => 'azureservicebus',
        'endpoint' => env('AZURE_SERVICE_BUS_ENDPOINT'),
        'shared_access_key_name' => env('AZURE_SERVICE_BUS_KEY_NAME'),
        'shared_access_key' => env('AZURE_SERVICE_BUS_KEY'),
        'queue' => env('AZURE_SERVICE_BUS_QUEUE'),
    ],
],
```

---

## Usage 🛠️

### Push Jobs onto the Queue ⬆️

You can push jobs to Azure Service Bus using the standard Laravel syntax:

```php
use App\Jobs\MyJob;

dispatch(new MyJob($data)); // Push to the default queue
```

### Use `later()` for Delayed Jobs ⏳

You can schedule jobs to be pushed after a delay using various formats:

```php
// Using an integer (seconds)
dispatch((new MyJob($data))->delay(60)); // Delay by 60 seconds

// Using a DateInterval
$interval = new \DateInterval('PT10M'); // 10 minutes
dispatch((new MyJob($data))->delay($interval));

// Using a DateTime
$releaseTime = new \DateTime('+1 hour');
dispatch((new MyJob($data))->delay($releaseTime));
```

### Handling Jobs 🚀

When a job is received from the queue, it will be processed as a standard Laravel job:

```php
public function handle()
{
    // Your job logic here
}
```

### Manually Release a Job 📥

To release a job back into the queue:

```php
$job->release(60); // Release the job back to the queue with a 60-second delay
```

### Delete a Job 🗑️

To delete a job from the queue:

```php
$job->delete();
```

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


## Attribution

Inspired by https://github.com/goavega-software/laravel-azure-servicebus-topic & https://github.com/pawprintdigital/laravel-queue-raw-sqs

---

## Contact 📬

For any questions, feel free to reach out to us via GitHub Issues or email us at [alex@codingwithalex.com](mailto:alex@codingwithalex.com).
