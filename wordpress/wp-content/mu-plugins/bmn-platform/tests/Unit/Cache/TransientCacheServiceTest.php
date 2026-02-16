<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Cache;

use BMN\Platform\Cache\TransientCacheService;
use PHPUnit\Framework\TestCase;

class TransientCacheServiceTest extends TestCase
{
    private TransientCacheService $cache;

    protected function setUp(): void
    {
        $GLOBALS['wp_transients'] = [];
        $GLOBALS['wp_options'] = [];

        $this->cache = new TransientCacheService();
    }

    // ------------------------------------------------------------------
    // 1. get() returns null for a key that was never set
    // ------------------------------------------------------------------

    public function testGetReturnsNullForMissingKey(): void
    {
        $result = $this->cache->get('nonexistent', 'default');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // 2. set() stores a value that get() can retrieve
    // ------------------------------------------------------------------

    public function testSetAndGet(): void
    {
        $this->cache->set('city', 'Boston', 300, 'default');

        $result = $this->cache->get('city', 'default');

        $this->assertSame('Boston', $result);
    }

    // ------------------------------------------------------------------
    // 3. set() honours an explicit TTL passed by the caller
    // ------------------------------------------------------------------

    public function testSetWithExplicitTtl(): void
    {
        $this->cache->set('key1', 'value1', 600, 'listings');

        // The transient key follows the pattern bmn_{group}_{md5(key)}.
        $transientKey = 'bmn_listings_' . md5('key1');
        $entry = $GLOBALS['wp_transients'][$transientKey];

        // The stub stores expiration as time() + ttl. Verify it is roughly
        // 600 seconds from now (allow 2 seconds of clock drift).
        $expectedExpiration = time() + 600;
        $this->assertEqualsWithDelta($expectedExpiration, $entry['expiration'], 2);
    }

    // ------------------------------------------------------------------
    // 4. set() falls back to the group default TTL when ttl=0
    // ------------------------------------------------------------------

    public function testSetUsesGroupDefaultTtl(): void
    {
        // The 'geography' group has a default TTL of 86400 (24 hours).
        $this->cache->set('neighborhood', 'Back Bay', 0, 'geography');

        $transientKey = 'bmn_geography_' . md5('neighborhood');

        $this->assertArrayHasKey($transientKey, $GLOBALS['wp_transients']);

        $entry = $GLOBALS['wp_transients'][$transientKey];
        $expectedExpiration = time() + 86400;
        $this->assertEqualsWithDelta($expectedExpiration, $entry['expiration'], 2);
    }

    // ------------------------------------------------------------------
    // 5. forget() removes a previously cached key
    // ------------------------------------------------------------------

    public function testForgetRemovesKey(): void
    {
        $this->cache->set('temp', 'data', 300, 'default');
        $this->cache->forget('temp', 'default');

        $this->assertNull($this->cache->get('temp', 'default'));
    }

    // ------------------------------------------------------------------
    // 6. forget() returns true when the key existed
    // ------------------------------------------------------------------

    public function testForgetReturnsTrueForExistingKey(): void
    {
        $this->cache->set('temp', 'data', 300, 'default');

        $result = $this->cache->forget('temp', 'default');

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // 7. remember() returns the cached value when a cache hit occurs
    // ------------------------------------------------------------------

    public function testRememberReturnsCachedValueOnHit(): void
    {
        $this->cache->set('greeting', 'hello', 300, 'default');

        $callbackExecuted = false;
        $result = $this->cache->remember('greeting', 300, function () use (&$callbackExecuted) {
            $callbackExecuted = true;
            return 'goodbye';
        }, 'default');

        $this->assertSame('hello', $result);
        $this->assertFalse($callbackExecuted);
    }

    // ------------------------------------------------------------------
    // 8. remember() executes the callback on a cache miss
    // ------------------------------------------------------------------

    public function testRememberExecutesCallbackOnMiss(): void
    {
        $callbackExecuted = false;
        $result = $this->cache->remember('missing', 300, function () use (&$callbackExecuted) {
            $callbackExecuted = true;
            return 'computed';
        }, 'default');

        $this->assertTrue($callbackExecuted);
        $this->assertSame('computed', $result);
    }

    // ------------------------------------------------------------------
    // 9. remember() caches the callback result for subsequent reads
    // ------------------------------------------------------------------

    public function testRememberCachesCallbackResult(): void
    {
        $this->cache->remember('counter', 300, fn() => 42, 'default');

        // Subsequent get should return the cached value.
        $result = $this->cache->get('counter', 'default');

        $this->assertSame(42, $result);
    }

    // ------------------------------------------------------------------
    // 10. invalidateGroup() deletes all keys tracked in a group
    // ------------------------------------------------------------------

    public function testInvalidateGroupDeletesAllGroupKeys(): void
    {
        $this->cache->set('a', 'alpha', 300, 'listings');
        $this->cache->set('b', 'bravo', 300, 'listings');
        $this->cache->set('c', 'charlie', 300, 'listings');

        $this->cache->invalidateGroup('listings');

        $this->assertNull($this->cache->get('a', 'listings'));
        $this->assertNull($this->cache->get('b', 'listings'));
        $this->assertNull($this->cache->get('c', 'listings'));
    }

    // ------------------------------------------------------------------
    // 11. invalidateGroup() returns the count of deleted entries
    // ------------------------------------------------------------------

    public function testInvalidateGroupReturnsCount(): void
    {
        $this->cache->set('x', '1', 300, 'searches');
        $this->cache->set('y', '2', 300, 'searches');

        $count = $this->cache->invalidateGroup('searches');

        $this->assertSame(2, $count);
    }

    // ------------------------------------------------------------------
    // 12. flush() clears all known groups
    // ------------------------------------------------------------------

    public function testFlushClearsAllGroups(): void
    {
        $this->cache->set('l1', 'v1', 300, 'listings');
        $this->cache->set('s1', 'v2', 300, 'searches');
        $this->cache->set('a1', 'v3', 300, 'agents');
        $this->cache->set('d1', 'v4', 300, 'default');

        $result = $this->cache->flush();

        $this->assertTrue($result);
        $this->assertNull($this->cache->get('l1', 'listings'));
        $this->assertNull($this->cache->get('s1', 'searches'));
        $this->assertNull($this->cache->get('a1', 'agents'));
        $this->assertNull($this->cache->get('d1', 'default'));
    }

    // ------------------------------------------------------------------
    // 13. getStats() tracks cache hits
    // ------------------------------------------------------------------

    public function testGetStatsTracksHits(): void
    {
        $this->cache->set('hit_test', 'value', 300, 'default');
        $this->cache->get('hit_test', 'default');
        $this->cache->get('hit_test', 'default');

        $stats = $this->cache->getStats();

        $this->assertSame(2, $stats['hits']);
    }

    // ------------------------------------------------------------------
    // 14. getStats() tracks cache misses
    // ------------------------------------------------------------------

    public function testGetStatsTracksMisses(): void
    {
        $this->cache->get('no_such_key', 'default');
        $this->cache->get('another_miss', 'default');
        $this->cache->get('third_miss', 'default');

        $stats = $this->cache->getStats();

        $this->assertSame(3, $stats['misses']);
    }

    // ------------------------------------------------------------------
    // 15. getStats() tracks set operations
    // ------------------------------------------------------------------

    public function testGetStatsTracksSets(): void
    {
        $this->cache->set('a', 1, 300, 'default');
        $this->cache->set('b', 2, 300, 'default');

        $stats = $this->cache->getStats();

        $this->assertSame(2, $stats['sets']);
    }

    // ------------------------------------------------------------------
    // 16. getStats() calculates the correct hit ratio
    // ------------------------------------------------------------------

    public function testGetStatsCalculatesHitRatio(): void
    {
        $this->cache->set('ratio_key', 'val', 300, 'default');

        // 3 hits
        $this->cache->get('ratio_key', 'default');
        $this->cache->get('ratio_key', 'default');
        $this->cache->get('ratio_key', 'default');

        // 1 miss
        $this->cache->get('nonexistent', 'default');

        $stats = $this->cache->getStats();

        // 3 hits / (3 hits + 1 miss) = 0.75
        $this->assertSame(3, $stats['hits']);
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(0.75, $stats['hit_ratio']);
    }

    // ------------------------------------------------------------------
    // 17. Group tracking trims to MAX_KEYS_PER_GROUP (1000)
    // ------------------------------------------------------------------

    public function testGroupTrackingLimitsMaxKeys(): void
    {
        // Seed the tracking option with 1000 keys already present.
        $optionName = 'bmn_cache_keys_listings';
        $existingKeys = [];
        for ($i = 0; $i < 1000; $i++) {
            $existingKeys[] = 'bmn_listings_' . md5("old_key_{$i}");
        }
        $GLOBALS['wp_options'][$optionName] = $existingKeys;

        // Add one more key via the cache service - this should trigger trimming.
        $this->cache->set('new_key', 'new_value', 300, 'listings');

        $trackedKeys = $GLOBALS['wp_options'][$optionName];

        // Should still be exactly 1000 (trimmed the oldest, kept the newest 1000).
        $this->assertCount(1000, $trackedKeys);

        // The newly added key must be present (last element).
        $newTransientKey = 'bmn_listings_' . md5('new_key');
        $this->assertSame($newTransientKey, end($trackedKeys));

        // The very first old key should have been trimmed away.
        $firstOldKey = 'bmn_listings_' . md5('old_key_0');
        $this->assertNotContains($firstOldKey, $trackedKeys);
    }

    // ------------------------------------------------------------------
    // 18. buildKey() uses the group as a prefix in the transient key
    // ------------------------------------------------------------------

    public function testBuildKeyUsesGroupPrefix(): void
    {
        $this->cache->set('my_key', 'my_value', 300, 'agents');

        $expectedTransientKey = 'bmn_agents_' . md5('my_key');

        // Verify that the transient was stored under the group-prefixed key.
        $this->assertArrayHasKey($expectedTransientKey, $GLOBALS['wp_transients']);
        $this->assertSame('my_value', $GLOBALS['wp_transients'][$expectedTransientKey]['value']);
    }
}
