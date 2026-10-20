<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheApcu\ApcuCache;
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\CacheStore;
use PHPUnit\Framework\TestCase;

final class FrameworkBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            self::markTestSkipped('APCu precisa estar habilitado (apc.enable_cli=1) para testar o bootstrap.');
        }

        apcu_clear_cache();
    }

    private static function ttlOf(string $apcuKey): ?int
    {
        $info = apcu_key_info($apcuKey);

        return is_array($info) ? (int) $info['ttl'] : null;
    }

    public function testEnvironmentExtensionsLoadsSingleApcuExtension(): void
    {
        $extensions = EnvironmentExtensions::load('/app', ['CACHE_DRIVER' => 'apcu']);

        self::assertCount(1, $extensions);
        self::assertInstanceOf(ApcuCacheExtension::class, array_values($extensions)[0]);
    }

    public function testBootstrapAppliesCachePrefixAndTtlFromEnvironment(): void
    {
        $app = ApplicationBootstrap::create(
            basePath: '/app',
            environment: ['CACHE_DRIVER' => 'apcu', 'CACHE_PREFIX' => 'env:', 'CACHE_TTL' => '30'],
        );
        $cache = $app->container()->get(CacheStore::class);

        self::assertInstanceOf(ApcuCache::class, $cache);

        $cache->set('k', 'v');
        self::assertSame('v', apcu_fetch('env:k'));
        self::assertSame(30, self::ttlOf('env:k'));
    }

    public function testBootstrapUsesDefaultPrefixWhenCachePrefixIsMissing(): void
    {
        $app = ApplicationBootstrap::create(basePath: '/app', environment: ['CACHE_DRIVER' => 'apcu']);
        $app->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame('v', apcu_fetch('api:cache:k'));
        self::assertSame(0, self::ttlOf('api:cache:k'));
    }

    public function testBootstrapUsesDefaultPrefixAndNoTtlWhenValuesAreEmpty(): void
    {
        $app = ApplicationBootstrap::create(
            basePath: '/app',
            environment: ['CACHE_DRIVER' => 'apcu', 'CACHE_PREFIX' => '', 'CACHE_TTL' => ''],
        );
        $app->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame('v', apcu_fetch('api:cache:k'));
        self::assertSame(0, self::ttlOf('api:cache:k'));
    }
}
