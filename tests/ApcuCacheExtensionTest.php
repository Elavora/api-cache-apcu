<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheApcu\ApcuCache;
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\Extension;
use PHPUnit\Framework\TestCase;

/**
 * Configuracao, registro e bootstrap da extensao.
 *
 * @see docs/USO.md#configuracao-da-extensao
 * @see docs/USO.md#uso-pelo-bootstrap-do-framework-2
 */
final class ApcuCacheExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertTrue(
            apcu_enabled(),
            'APCu precisa estar habilitado (apc.enable_cli=1) para testar o adapter.',
        );

        apcu_clear_cache();
    }

    public function testImplementsExtensionContract(): void
    {
        self::assertInstanceOf(Extension::class, new ApcuCacheExtension());
    }

    public function testConstructorConfigIsOptional(): void
    {
        $application = Application::create()->extend(new ApcuCacheExtension());

        self::assertInstanceOf(ApcuCache::class, $application->container()->get(CacheStore::class));
    }

    public function testRegisterBindsCacheStoreToApcuCache(): void
    {
        $application = Application::create();

        (new ApcuCacheExtension())->register($application);

        self::assertInstanceOf(ApcuCache::class, $application->container()->get(CacheStore::class));
    }

    public function testContainerReturnsTheSameInstanceOnEveryResolution(): void
    {
        $application = Application::create()->extend(new ApcuCacheExtension());

        self::assertSame(
            $application->container()->get(CacheStore::class),
            $application->container()->get(CacheStore::class),
        );
    }

    public function testPrefixOptionBecomesCachePrefix(): void
    {
        $cache = $this->cacheFor(['prefix' => 'app:']);

        $cache->set('user', 1);

        self::assertSame(1, apcu_fetch('app:user'));
    }

    public function testDefaultPrefixIsEmpty(): void
    {
        $cache = $this->cacheFor([]);

        $cache->set('plain', 'value');

        self::assertSame('value', apcu_fetch('plain'));
    }

    public function testPrefixIsConvertedToString(): void
    {
        $cache = $this->cacheFor(['prefix' => 123]);

        $cache->set('key', 'value');

        self::assertSame('value', apcu_fetch('123key'));
    }

    public function testTtlOptionBecomesDefaultTtl(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'ttl' => 3600]);

        $cache->set('key', 'value');

        self::assertSame(3600, $this->ttlOf('cfg:key'));
    }

    public function testTtlIsConvertedToInteger(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'ttl' => '60']);

        $cache->set('key', 'value');

        self::assertSame(60, $this->ttlOf('cfg:key'));
    }

    public function testMissingTtlMeansNoExpiration(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:']);

        $cache->set('key', 'value');

        self::assertSame(0, $this->ttlOf('cfg:key'));
    }

    public function testNullTtlMeansNoExpiration(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'ttl' => null]);

        $cache->set('key', 'value');

        self::assertSame('value', $cache->get('key'));
        self::assertSame(0, $this->ttlOf('cfg:key'));
    }

    public function testZeroTtlIsAcceptedAndNothingIsStoredWithoutExplicitTtl(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'ttl' => 0]);

        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse(apcu_exists('cfg:key'));
    }

    public function testNegativeTtlIsAcceptedAndNothingIsStoredWithoutExplicitTtl(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'ttl' => -10]);

        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
    }

    public function testExplicitTtlInTheCallBeatsConfiguredZeroTtl(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'ttl' => 0]);

        $cache->set('key', 'value', 30);

        self::assertSame('value', $cache->get('key'));
        self::assertSame(30, $this->ttlOf('cfg:key'));
    }

    public function testUnknownConfigKeysAreIgnored(): void
    {
        $cache = $this->cacheFor(['prefix' => 'cfg:', 'unknown' => 'x', 'servers' => ['a']]);

        $cache->set('key', 'value');

        self::assertSame('value', apcu_fetch('cfg:key'));
    }

    public function testEnvironmentExtensionsLoadsASingleApcuExtension(): void
    {
        $extensions = EnvironmentExtensions::load('/app', ['CACHE_DRIVER' => 'apcu']);

        self::assertCount(1, $extensions);
        self::assertInstanceOf(ApcuCacheExtension::class, $extensions[0]);
    }

    public function testBootstrapUsesPrefixAndTtlFromEnvironment(): void
    {
        $app = ApplicationBootstrap::create(
            basePath: '/app',
            environment: ['CACHE_DRIVER' => 'apcu', 'CACHE_PREFIX' => 'env:', 'CACHE_TTL' => '30'],
        );
        $cache = $app->container()->get(CacheStore::class);

        self::assertInstanceOf(ApcuCache::class, $cache);

        $cache->set('k', 'v');

        self::assertSame('v', apcu_fetch('env:k'));
        self::assertSame(30, $this->ttlOf('env:k'));
    }

    public function testBootstrapUsesFrameworkDefaultPrefixAndNoTtl(): void
    {
        $app = ApplicationBootstrap::create(basePath: '/app', environment: ['CACHE_DRIVER' => 'apcu']);

        $app->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame('v', apcu_fetch('api:cache:k'));
        self::assertSame(0, $this->ttlOf('api:cache:k'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function cacheFor(array $config): CacheStore
    {
        $application = Application::create()->extend(new ApcuCacheExtension($config));

        return $application->container()->get(CacheStore::class);
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
