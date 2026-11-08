<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Providers\ServiceProvider;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    public function testProvidesConnectorKey(): void
    {
        $provider = $this->getMockBuilder(ServiceProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(['azureservicebus'], $provider->provides());
    }

    public function testConfigFileExistsAndHasRequiredKeys(): void
    {
        $path = __DIR__ . '/../config/azureservicebus.php';
        $this->assertFileExists($path);

        // Parse as text: requiring the file would execute env(), which
        // needs phpoption/phpoption (absent in some lock files).
        $contents = (string) file_get_contents($path);

        foreach (['endpoint', 'shared_access_key_name', 'shared_access_key', 'queue', 'timeout'] as $key) {
            $this->assertStringContainsString("'{$key}'", $contents, "config missing key: {$key}");
        }
    }
}
