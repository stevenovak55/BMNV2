<?php

declare(strict_types=1);

namespace BMN\Platform\Cache;

/**
 * Cache service abstraction.
 *
 * Provides a consistent caching API backed by WordPress transients,
 * object cache, or an external store (Redis, Memcached).
 */
interface CacheService
{
    /**
     * Retrieve a cached value.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group for namespacing.
     * @return mixed Cached value or null if not found.
     */
    public function get(string $key, string $group = 'default'): mixed;

    /**
     * Store a value in the cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @param int    $ttl   Time-to-live in seconds. 0 = use group default.
     * @param string $group Cache group for namespacing.
     * @return bool True on success.
     */
    public function set(string $key, mixed $value, int $ttl = 3600, string $group = 'default'): bool;

    /**
     * Remove a cached value.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group for namespacing.
     * @return bool True on success.
     */
    public function forget(string $key, string $group = 'default'): bool;

    /**
     * Get a cached value or execute the callback and cache its result.
     *
     * @param string   $key      Cache key.
     * @param int      $ttl      Time-to-live in seconds.
     * @param callable $callback Callback that produces the value to cache.
     * @param string   $group    Cache group for namespacing.
     * @return mixed The cached or freshly computed value.
     */
    public function remember(string $key, int $ttl, callable $callback, string $group = 'default'): mixed;

    /**
     * Invalidate all cached entries in a group.
     *
     * @param string $group Cache group to invalidate.
     * @return int Number of entries deleted.
     */
    public function invalidateGroup(string $group): int;

    /**
     * Flush all known cached entries across all groups.
     *
     * @return bool True on success.
     */
    public function flush(): bool;

    /**
     * Get cache statistics for the current request.
     *
     * @return array{hits: int, misses: int, sets: int, deletes: int, hit_ratio: float}
     */
    public function getStats(): array;
}
