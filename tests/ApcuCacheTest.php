<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheApcu\ApcuCache;
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regras do cache APCu.
 *
 * @see docs/USO.md#regras-do-cache
 */
final class ApcuCacheTest extends TestCase
{
    protected function setUp(): void
    {
        // Sem APCu na CLI o cache nao seria testado e a suite ficaria verde sem valor (docs/USO.md#validacao-local).
        self::assertTrue(
            apcu_enabled(),
            'APCu precisa estar habilitado (apc.enable_cli=1) para testar o adapter.',
        );

        apcu_clear_cache();
    }

    public function testRegistersStoreAndDeletesCachedValue(): void
    {
        $application = Application::create()->extend(new ApcuCacheExtension([
            'prefix' => 'test:cache:',
        ]));
        $cache = $application->container()->get(CacheStore::class);

        self::assertInstanceOf(ApcuCache::class, $cache);

        $cache->set('sample', ['status' => 'ok'], 60);
        self::assertSame(['status' => 'ok'], $cache->get('sample'));

        $cache->delete('sample');
        self::assertNull($cache->get('sample'));
    }

    public function testKeepsValuesIsolatedByPrefix(): void
    {
        $first = new ApcuCache(prefix: 'first:', defaultTtlSeconds: null);
        $second = new ApcuCache(prefix: 'second:', defaultTtlSeconds: null);

        $first->set('key', 'first-value');
        $second->set('key', 'second-value');

        self::assertSame('first-value', $first->get('key'));
        self::assertSame('second-value', $second->get('key'));
    }

    public function testReturnsStoredFalseInsteadOfCacheMiss(): void
    {
        $cache = new ApcuCache(prefix: 'false:');

        $cache->set('key', false);

        self::assertFalse($cache->get('key'));
    }

    public function testDeletesValueWhenTtlIsNotPositive(): void
    {
        $cache = new ApcuCache(prefix: 'ttl:', defaultTtlSeconds: 60);

        $cache->set('key', 'stored');
        $cache->set('key', 'ignored', 0);

        self::assertNull($cache->get('key'));
    }

    public function testImplementsCacheStoreContract(): void
    {
        self::assertInstanceOf(CacheStore::class, new ApcuCache());
    }

    public function testStoresUnderPrefixPlusKey(): void
    {
        $cache = new ApcuCache(prefix: 'app:');

        $cache->set('user', 1);

        self::assertSame(1, apcu_fetch('app:user'));
    }

    public function testEmptyPrefixStoresUnderTheKeyItself(): void
    {
        $cache = new ApcuCache();

        $cache->set('plain', 'value');

        self::assertSame('value', apcu_fetch('plain'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function valuesProvider(): array
    {
        return [
            'string' => ['text'],
            'inteiro' => [42],
            'float' => [1.5],
            'true' => [true],
            'false' => [false],
            'array' => [['a' => 1, 'b' => [2, 3]]],
            'array vazio' => [[]],
        ];
    }

    #[DataProvider('valuesProvider')]
    public function testReturnsStoredValueWithTheSameType(mixed $value): void
    {
        $cache = new ApcuCache(prefix: 'types:');

        $cache->set('key', $value);

        self::assertSame($value, $cache->get('key'));
    }

    public function testMissingKeyReturnsNull(): void
    {
        $cache = new ApcuCache(prefix: 'miss:');

        self::assertNull($cache->get('missing'));
    }

    public function testStoredFalseIsDifferentFromMissingKey(): void
    {
        $cache = new ApcuCache();
        $cache->set('flag', false);

        self::assertFalse($cache->get('flag'));
        self::assertNull($cache->get('missing'));
    }

    public function testExpiredKeyReturnsNull(): void
    {
        $cache = new ApcuCache(prefix: 'exp:');

        $cache->set('key', 'value', 1);
        self::assertSame('value', $cache->get('key'));

        sleep(2);

        self::assertNull($cache->get('key'));
    }

    public function testDeleteRemovesTheKey(): void
    {
        $cache = new ApcuCache(prefix: 'del:');
        $cache->set('key', 'value');

        $cache->delete('key');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('del:key'));
    }

    public function testDeleteOnlyAffectsItsOwnPrefix(): void
    {
        $first = new ApcuCache(prefix: 'first:');
        $second = new ApcuCache(prefix: 'second:');
        $first->set('key', 'one');
        $second->set('key', 'two');

        $first->delete('key');

        self::assertNull($first->get('key'));
        self::assertSame('two', $second->get('key'));
    }

    public function testDeletingMissingKeyDoesNotFail(): void
    {
        $cache = new ApcuCache(prefix: 'nothing:');

        $cache->delete('never-set');

        self::assertNull($cache->get('never-set'));
    }

    public function testNoTtlStoresWithoutExpiration(): void
    {
        $cache = new ApcuCache(prefix: 'forever:');

        $cache->set('key', 'value');

        self::assertSame(0, $this->ttlOf('forever:key'));
        self::assertSame('value', $cache->get('key'));
    }

    public function testPositiveTtlStoresWithThatExpiration(): void
    {
        $cache = new ApcuCache(prefix: 'ttl:');

        $cache->set('key', 'value', 30);

        self::assertSame(30, $this->ttlOf('ttl:key'));
    }

    public function testConstructorDefaultTtlIsUsedWhenCallHasNone(): void
    {
        $cache = new ApcuCache(prefix: 'ttl:', defaultTtlSeconds: 60);

        $cache->set('key', 'stored');

        self::assertSame('stored', $cache->get('key'));
        self::assertSame(60, $this->ttlOf('ttl:key'));
    }

    public function testCallTtlOverridesConstructorDefault(): void
    {
        $cache = new ApcuCache(prefix: 'ttl:', defaultTtlSeconds: 60);

        $cache->set('key', 'value', 10);

        self::assertSame(10, $this->ttlOf('ttl:key'));
    }

    public function testZeroDefaultTtlDoesNotStore(): void
    {
        $cache = new ApcuCache(defaultTtlSeconds: 0);

        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('key'));
    }

    public function testCallTtlBeatsZeroDefaultTtl(): void
    {
        $cache = new ApcuCache(defaultTtlSeconds: 0);

        $cache->set('key', 'value', 30);

        self::assertSame('value', $cache->get('key'));
        self::assertSame(30, $this->ttlOf('key'));
    }

    public function testNegativeTtlRemovesExistingKey(): void
    {
        $cache = new ApcuCache(prefix: 'neg:');
        $cache->set('key', 'stored');

        $cache->set('key', 'ignored', -5);

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('neg:key'));
    }

    public function testZeroTtlOnMissingKeyDoesNotFailAndStoresNothing(): void
    {
        $cache = new ApcuCache(prefix: 'zero:');

        $cache->set('key', 'value', 0);

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('zero:key'));
    }

    public function testNegativeDefaultTtlRemovesExistingKey(): void
    {
        $stored = new ApcuCache(prefix: 'negdef:');
        $stored->set('key', 'stored');

        $cache = new ApcuCache(prefix: 'negdef:', defaultTtlSeconds: -1);
        $cache->set('key', 'ignored');

        self::assertNull($cache->get('key'));
    }

    private function ttlOf(string $key): ?int
    {
        $info = apcu_cache_info(false);

        foreach ($info['cache_list'] as $entry) {
            if (($entry['info'] ?? null) === $key) {
                return (int) $entry['ttl'];
            }
        }

        return null;
    }
}
