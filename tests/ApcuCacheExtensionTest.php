<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheApcu\ApcuCache;
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\Extension;
use PHPUnit\Framework\TestCase;

final class ApcuCacheExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            self::markTestSkipped('APCu precisa estar habilitado (apc.enable_cli=1) para testar a extensão.');
        }

        apcu_clear_cache();
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function storeFor(array $config): CacheStore
    {
        $application = Application::create()->extend(new ApcuCacheExtension($config));

        return $application->container()->get(CacheStore::class);
    }

    private static function ttlOf(string $apcuKey): ?int
    {
        $info = apcu_key_info($apcuKey);

        return is_array($info) ? (int) $info['ttl'] : null;
    }

    public function testImplementsExtensionContract(): void
    {
        self::assertInstanceOf(Extension::class, new ApcuCacheExtension());
    }

    public function testRegistersApcuCacheAsCacheStore(): void
    {
        $cache = self::storeFor(['prefix' => 'app:', 'ttl' => 3600]);

        self::assertInstanceOf(ApcuCache::class, $cache);

        $cache->set('sample', ['status' => 'ok']);
        self::assertSame(['status' => 'ok'], $cache->get('sample'));
        self::assertSame(['status' => 'ok'], apcu_fetch('app:sample'));
        self::assertSame(3600, self::ttlOf('app:sample'));

        $cache->delete('sample');
        self::assertNull($cache->get('sample'));
    }

    public function testContainerReturnsSameInstance(): void
    {
        $application = Application::create()->extend(new ApcuCacheExtension(['prefix' => 'same:']));

        $first = $application->container()->get(CacheStore::class);
        $second = $application->container()->get(CacheStore::class);

        self::assertSame($first, $second);
    }

    public function testNoArgumentConstructorUsesEmptyPrefixAndNoExpiration(): void
    {
        $application = Application::create()->extend(new ApcuCacheExtension());
        $cache = $application->container()->get(CacheStore::class);

        self::assertInstanceOf(ApcuCache::class, $cache);

        $cache->set('plain', 'value');
        self::assertSame('value', apcu_fetch('plain'));
        self::assertSame(0, self::ttlOf('plain'));
    }

    public function testNullTtlMeansNoExpiration(): void
    {
        $cache = self::storeFor(['prefix' => 'n:', 'ttl' => null]);
        $cache->set('key', 'value');

        self::assertSame('value', $cache->get('key'));
        self::assertSame(0, self::ttlOf('n:key'));
    }

    public function testNumericStringTtlIsCastToInt(): void
    {
        $cache = self::storeFor(['prefix' => 's:', 'ttl' => '60']);
        $cache->set('key', 'value');

        self::assertSame('value', $cache->get('key'));
        self::assertSame(60, self::ttlOf('s:key'));
    }

    public function testPrefixIsCastToString(): void
    {
        $cache = self::storeFor(['prefix' => 123]);
        $cache->set('key', 'value');

        self::assertSame('value', apcu_fetch('123key'));
    }

    public function testZeroTtlDoesNotStoreWithoutCallTtl(): void
    {
        $cache = self::storeFor(['prefix' => 'z:', 'ttl' => 0]);
        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('z:key'));

        $cache->set('key', 'value', 30);
        self::assertSame('value', $cache->get('key'));
        self::assertSame(30, self::ttlOf('z:key'));
    }

    public function testNegativeTtlIsAcceptedAndDoesNotStore(): void
    {
        $cache = self::storeFor(['prefix' => 'neg:', 'ttl' => -1]);
        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('neg:key'));
    }

    public function testUnknownConfigKeysAreIgnored(): void
    {
        $cache = self::storeFor(['prefix' => 'u:', 'unknown' => 'x', 'driver' => 'redis']);
        $cache->set('key', 'value');

        self::assertSame('value', apcu_fetch('u:key'));
        self::assertSame(0, self::ttlOf('u:key'));
    }
}
