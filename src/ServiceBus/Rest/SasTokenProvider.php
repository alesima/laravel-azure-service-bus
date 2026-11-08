<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Rest;

/**
 * Shared Access Signature token provider.
 *
 * Mirrors the signing algorithm of the retired microsoft/windowsazure
 * SDK (SASFilter): scope = lowerUrlencode(url) + "\n" + expiry,
 * HMAC-SHA256 with the raw shared access key, base64-encoded.
 */
final class SasTokenProvider
{
    public function __construct(
        private string $keyName,
        private string $key,
        private int $ttlSeconds = 3600
    ) {
    }

    public function createForUrl(string $url, ?int $now = null): string
    {
        $expiry = ($now ?? time()) + $this->ttlSeconds;
        $encodedUrl = self::lowerUrlencode($url);
        $signature = base64_encode(hash_hmac('sha256', $encodedUrl . "\n" . $expiry, $this->key, true));

        return sprintf(
            'SharedAccessSignature sig=%s&se=%s&skn=%s&sr=%s',
            self::lowerUrlencode($signature),
            $expiry,
            $this->keyName,
            $encodedUrl
        );
    }

    public static function lowerUrlencode(string $value): string
    {
        $encoded = preg_replace_callback(
            '/%[0-9A-F]{2}/',
            static fn (array $matches): string => strtolower($matches[0]),
            urlencode($value)
        );

        return $encoded ?? $value;
    }
}
