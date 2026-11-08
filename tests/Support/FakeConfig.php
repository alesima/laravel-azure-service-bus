<?php

namespace Alesima\LaravelAzureServiceBus\Tests\Support;

/**
 * Minimal config repository with get/set.
 * Stands in for Illuminate\Config\Repository (not a dependency here).
 */
final class FakeConfig
{
    /**
     * @param array<string,mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $items = &$this->items;
        $segments = explode('.', $key);
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (! isset($items[$segment]) || ! is_array($items[$segment])) {
                $items[$segment] = [];
            }

            $items = &$items[$segment];
        }

        $items[$last] = $value;
    }
}
