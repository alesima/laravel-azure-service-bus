<?php

namespace Alesima\LaravelAzureServiceBus\Tests\Rest;

use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\SasTokenProvider;
use PHPUnit\Framework\TestCase;

class SasTokenProviderTest extends TestCase
{
    public function testTokenFormatMatchesSdkAlgorithm(): void
    {
        $provider = new SasTokenProvider('keyName', 'keyValue');
        $url = 'https://myns.servicebus.windows.net/orders/messages';
        $now = 1700000000;

        $token = $provider->createForUrl($url, $now);

        $encodedUrl = SasTokenProvider::lowerUrlencode($url);
        $expectedSig = SasTokenProvider::lowerUrlencode(
            base64_encode(hash_hmac('sha256', $encodedUrl . "\n" . ($now + 3600), 'keyValue', true))
        );

        $this->assertSame(
            "SharedAccessSignature sig={$expectedSig}&se=1700003600&skn=keyName&sr={$encodedUrl}",
            $token
        );
    }

    public function testLowerUrlencodeLowercasesPercentEscapes(): void
    {
        // urlencode('a/b') === 'a%2Fb' → escapes become lowercase.
        $this->assertSame('a%2fb', SasTokenProvider::lowerUrlencode('a/b'));
        $this->assertSame('AB', SasTokenProvider::lowerUrlencode('AB'));
    }

    public function testCustomTtlIsHonored(): void
    {
        $provider = new SasTokenProvider('k', 'v', 60);

        $token = $provider->createForUrl('https://ns/x', 1000);

        $this->assertStringContainsString('se=1060', $token);
    }
}
