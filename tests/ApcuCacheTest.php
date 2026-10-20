<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheApcu\ApcuCache;
use Elavora\Api\Framework\Contracts\CacheStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApcuCacheTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            self::markTestSkipped('APCu precisa estar habilitado (apc.enable_cli=1) para testar o adapter.');
        }

        apcu_clear_cache();
    }

    private static function ttlOf(string $apcuKey): ?int
    {
        $info = apcu_key_info($apcuKey);

        return is_array($info) ? (int) $info['ttl'] : null;
    }

    public function testImplementsCacheStoreContract(): void
    {
        self::assertInstanceOf(CacheStore::class, new ApcuCache());
    }

    public function testAcceptsPositionalConstructorArguments(): void
    {
        $cache = new ApcuCache('pos:', 45);
        $cache->set('key', 'value');

        self::assertSame('value', apcu_fetch('pos:key'));
        self::assertSame(45, self::ttlOf('pos:key'));
    }

    public function testStoresKeyAsPrefixPlusKey(): void
    {
        $cache = new ApcuCache(prefix: 'app:');
        $cache->set('user', 1);

        self::assertTrue(apcu_exists('app:user'));
        self::assertSame(1, apcu_fetch('app:user'));
        self::assertFalse(apcu_exists('user'));
    }

    public function testEmptyPrefixUsesRawKey(): void
    {
        $cache = new ApcuCache();
        $cache->set('raw', 'v');

        self::assertSame('v', apcu_fetch('raw'));
    }

    public function testKeepsValuesIsolatedByPrefix(): void
    {
        $first = new ApcuCache(prefix: 'first:');
        $second = new ApcuCache(prefix: 'second:');

        $first->set('key', 'first-value');
        $second->set('key', 'second-value');

        self::assertSame('first-value', $first->get('key'));
        self::assertSame('second-value', $second->get('key'));

        $first->delete('key');
        self::assertNull($first->get('key'));
        self::assertSame('second-value', $second->get('key'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function storedValues(): iterable
    {
        yield 'array' => [['status' => 'ok', 'list' => [1, 2, 3]]];
        yield 'int' => [42];
        yield 'zero' => [0];
        yield 'string' => ['text'];
        yield 'empty string' => [''];
        yield 'float' => [1.5];
        yield 'true' => [true];
        yield 'false' => [false];
    }

    #[DataProvider('storedValues')]
    public function testReturnsStoredValueWithSameType(mixed $value): void
    {
        $cache = new ApcuCache(prefix: 'types:');
        $cache->set('key', $value);

        self::assertSame($value, $cache->get('key'));
    }

    public function testStoredNullReadsAsNull(): void
    {
        $cache = new ApcuCache(prefix: 'null:');
        $cache->set('key', null);

        self::assertNull($cache->get('key'));
    }

    public function testStoredFalseIsNotACacheMiss(): void
    {
        $cache = new ApcuCache();
        $cache->set('flag', false);

        self::assertFalse($cache->get('flag'));
        self::assertNull($cache->get('missing'));
    }

    public function testMissingKeyReturnsNull(): void
    {
        self::assertNull((new ApcuCache(prefix: 'miss:'))->get('nothing'));
    }

    public function testDeleteRemovesKey(): void
    {
        $cache = new ApcuCache(prefix: 'del:');
        $cache->set('key', 'value');
        $cache->delete('key');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('del:key'));
    }

    public function testDeleteOfMissingKeyDoesNotFail(): void
    {
        $cache = new ApcuCache(prefix: 'del:');
        $cache->delete('never-existed');

        self::assertNull($cache->get('never-existed'));
    }

    public function testWithoutAnyTtlStoresWithoutExpiration(): void
    {
        $cache = new ApcuCache(prefix: 'nottl:');
        $cache->set('key', 'value');

        self::assertSame('value', $cache->get('key'));
        self::assertSame(0, self::ttlOf('nottl:key'));
    }

    public function testDefaultTtlIsUsedWhenCallTtlIsNull(): void
    {
        $cache = new ApcuCache(prefix: 'def:', defaultTtlSeconds: 60);
        $cache->set('key', 'value');
        $cache->set('explicit-null', 'value', null);

        self::assertSame(60, self::ttlOf('def:key'));
        self::assertSame(60, self::ttlOf('def:explicit-null'));
    }

    public function testCallTtlWinsOverDefault(): void
    {
        $cache = new ApcuCache(prefix: 'win:', defaultTtlSeconds: 60);
        $cache->set('key', 'value', 30);

        self::assertSame('value', $cache->get('key'));
        self::assertSame(30, self::ttlOf('win:key'));
    }

    public function testCallTtlWinsOverZeroDefault(): void
    {
        $cache = new ApcuCache(defaultTtlSeconds: 0);
        $cache->set('key', 'value', 30);

        self::assertSame('value', $cache->get('key'));
        self::assertSame(30, self::ttlOf('key'));
    }

    public function testZeroCallTtlRemovesExistingKey(): void
    {
        $cache = new ApcuCache(prefix: 'ttl:', defaultTtlSeconds: 60);
        $cache->set('key', 'stored');
        $cache->set('key', 'ignored', 0);

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('ttl:key'));
    }

    public function testNegativeCallTtlRemovesExistingKey(): void
    {
        $cache = new ApcuCache(prefix: 'neg:');
        $cache->set('key', 'stored');
        $cache->set('key', 'ignored', -5);

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('neg:key'));
    }

    public function testNonPositiveTtlOnMissingKeyDoesNotStore(): void
    {
        $cache = new ApcuCache(prefix: 'neg:');
        $cache->set('a', 'value', 0);
        $cache->set('b', 'value', -1);

        self::assertNull($cache->get('a'));
        self::assertNull($cache->get('b'));
        self::assertFalse(apcu_exists('neg:a'));
        self::assertFalse(apcu_exists('neg:b'));
    }

    public function testZeroDefaultTtlDoesNotStore(): void
    {
        $cache = new ApcuCache(defaultTtlSeconds: 0);
        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('key'));
    }

    public function testNegativeDefaultTtlRemovesExistingKey(): void
    {
        apcu_store('negdef:key', 'old');

        $cache = new ApcuCache(prefix: 'negdef:', defaultTtlSeconds: -10);
        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('negdef:key'));
    }
}
