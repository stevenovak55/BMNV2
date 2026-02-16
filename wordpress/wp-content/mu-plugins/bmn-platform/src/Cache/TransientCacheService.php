<?php

declare(strict_types=1);

namespace BMN\Platform\Cache;

/**
 * WordPress transient-backed cache service.
 *
 * Implements the CacheService interface using WordPress transients as the
 * underlying storage mechanism. Tracks keys per group for bulk invalidation
 * and maintains per-request hit/miss statistics.
 */
class TransientCacheService implements CacheService
{
    /**
     * Default TTLs per cache group (in seconds).
     */
    private const GROUP_TTLS = [
        'listings'   => 3600,   // 1 hour
        'searches'   => 1800,   // 30 minutes
        'filters'    => 3600,   // 1 hour
        'agents'     => 7200,   // 2 hours
        'geography'  => 86400,  // 24 hours
        'statistics' => 1800,   // 30 minutes
        'default'    => 3600,   // 1 hour
    ];

    /**
     * Maximum number of tracked keys per group.
     */
    private const MAX_KEYS_PER_GROUP = 1000;

    /**
     * Maximum length for a WordPress transient key.
     */
    private const MAX_KEY_LENGTH = 172;

    /** @var int Cache hit count for this request. */
    private int $hits = 0;

    /** @var int Cache miss count for this request. */
    private int $misses = 0;

    /** @var int Cache set count for this request. */
    private int $sets = 0;

    /** @var int Cache delete count for this request. */
    private int $deletes = 0;

    /**
     * Build a namespaced transient key.
     *
     * Pattern: bmn_{group}_{md5_of_key}
     * Truncated to 172 characters (WordPress transient name limit).
     *
     * @param string $key   Original cache key.
     * @param string $group Cache group.
     * @return string The transient key.
     */
    private function buildKey(string $key, string $group): string
    {
        $transientKey = 'bmn_' . $group . '_' . md5($key);

        return substr($transientKey, 0, self::MAX_KEY_LENGTH);
    }

    /**
     * Get the default TTL for a given group.
     *
     * @param string $group Cache group.
     * @return int TTL in seconds.
     */
    private function getDefaultTtl(string $group): int
    {
        return self::GROUP_TTLS[$group] ?? self::GROUP_TTLS['default'];
    }

    /**
     * Get the option name used to track keys for a group.
     *
     * @param string $group Cache group.
     * @return string Option name.
     */
    private function getGroupTrackingKey(string $group): string
    {
        return 'bmn_cache_keys_' . $group;
    }

    /**
     * Track a transient key within its group for later bulk invalidation.
     *
     * @param string $transientKey The built transient key.
     * @param string $group        Cache group.
     */
    private function trackKeyInGroup(string $transientKey, string $group): void
    {
        $optionName = $this->getGroupTrackingKey($group);
        $keys = get_option($optionName, []);

        if (! is_array($keys)) {
            $keys = [];
        }

        // Only add if not already tracked.
        if (! in_array($transientKey, $keys, true)) {
            $keys[] = $transientKey;

            // Enforce max keys per group - keep the most recent entries.
            if (count($keys) > self::MAX_KEYS_PER_GROUP) {
                $keys = array_slice($keys, -self::MAX_KEYS_PER_GROUP);
            }

            update_option($optionName, $keys, false);
        }
    }

    /**
     * Remove a transient key from its group tracking.
     *
     * @param string $transientKey The built transient key.
     * @param string $group        Cache group.
     */
    private function untrackKeyFromGroup(string $transientKey, string $group): void
    {
        $optionName = $this->getGroupTrackingKey($group);
        $keys = get_option($optionName, []);

        if (! is_array($keys)) {
            return;
        }

        $keys = array_values(array_filter($keys, static function (string $k) use ($transientKey): bool {
            return $k !== $transientKey;
        }));

        update_option($optionName, $keys, false);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, string $group = 'default'): mixed
    {
        $transientKey = $this->buildKey($key, $group);
        $value = get_transient($transientKey);

        if ($value === false) {
            $this->misses++;
            return null;
        }

        $this->hits++;
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0, string $group = 'default'): bool
    {
        if ($ttl === 0) {
            $ttl = $this->getDefaultTtl($group);
        }

        $transientKey = $this->buildKey($key, $group);
        $result = set_transient($transientKey, $value, $ttl);

        if ($result) {
            $this->trackKeyInGroup($transientKey, $group);
            $this->sets++;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key, string $group = 'default'): bool
    {
        $transientKey = $this->buildKey($key, $group);
        $result = delete_transient($transientKey);

        if ($result) {
            $this->untrackKeyFromGroup($transientKey, $group);
            $this->deletes++;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int $ttl, callable $callback, string $group = 'default'): mixed
    {
        $value = $this->get($key, $group);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl, $group);

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function invalidateGroup(string $group): int
    {
        $optionName = $this->getGroupTrackingKey($group);
        $keys = get_option($optionName, []);

        if (! is_array($keys)) {
            return 0;
        }

        $count = 0;

        foreach ($keys as $transientKey) {
            if (delete_transient($transientKey)) {
                $count++;
                $this->deletes++;
            }
        }

        // Clear the group tracking option.
        delete_option($optionName);

        return $count;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        // Invalidate all known groups (both defined and default).
        $groups = array_keys(self::GROUP_TTLS);

        foreach ($groups as $group) {
            $this->invalidateGroup($group);
        }

        // Reset in-memory stats.
        $this->hits = 0;
        $this->misses = 0;
        $this->sets = 0;
        $this->deletes = 0;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        $hitRatio = $total > 0 ? round($this->hits / $total, 4) : 0.0;

        return [
            'hits'      => $this->hits,
            'misses'    => $this->misses,
            'sets'      => $this->sets,
            'deletes'   => $this->deletes,
            'hit_ratio' => $hitRatio,
        ];
    }
}
