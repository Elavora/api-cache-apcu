# Guia de uso

Adapter opcional de cache APCu para o framework Elavora 2.

Este guia é a especificação do pacote. Tudo o que está aqui é comportamento
garantido e pode ser coberto por teste.

## Instalação

```bash
composer require elavora/api-cache-apcu
```

## Dependências de runtime

| Dependência             | Versão  |
|-------------------------|---------|
| `php`                   | `>=8.3` |
| `ext-apcu`              | `*`     |
| `elavora/api-framework` | `^2.0`  |

O pacote não aceita mais o framework `0.x` nem o `1.x`. A versão mínima do PHP
acompanha o framework 2, que exige PHP 8.3.

Em execuções pela CLI (testes, workers), o APCu só guarda valores com
`apc.enable_cli=1`.

## API pública

Namespace: `Elavora\Api\Extension\CacheApcu`.

### `ApcuCache`

```php
final class ApcuCache implements \Elavora\Api\Framework\Contracts\CacheStore
{
    public function __construct(string $prefix = '', ?int $defaultTtlSeconds = null);
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void;
    public function delete(string $key): void;
}
```

Os argumentos do construtor podem ser passados por nome (`prefix:`,
`defaultTtlSeconds:`). Nenhum método lança exceção.

### `ApcuCacheExtension`

```php
final class ApcuCacheExtension implements \Elavora\Api\Framework\Contracts\Extension
{
    /** @param array{prefix?: string, ttl?: int|null} $config */
    public function __construct(array $config = []);
    public function register(\Elavora\Api\Framework\Application $application): void;
}
```

`register()` liga `Elavora\Api\Framework\Contracts\CacheStore` no container da
aplicação a uma instância de `ApcuCache`. Como o container do framework guarda
a instância depois da primeira resolução, duas chamadas a
`container()->get(CacheStore::class)` devolvem o mesmo objeto.

## Regras do cache

### Chaves e prefixo

A chave gravada no APCu é `prefix . key`. Exemplo: com `prefix: 'app:'`,
`set('user', 1)` grava a chave APCu `app:user`.

Duas instâncias com prefixos diferentes não enxergam os valores uma da outra:

```php
$a = new ApcuCache(prefix: 'first:');
$b = new ApcuCache(prefix: 'second:');
$a->set('key', 'first-value');
$b->set('key', 'second-value');
$a->get('key'); // 'first-value'
$b->get('key'); // 'second-value'
```

### Leitura

- `get()` devolve o valor guardado, com o mesmo tipo (arrays, inteiros,
  strings, `false`, `null`...).
- Chave inexistente, expirada ou removida devolve `null`.
- Um `false` guardado volta como `false`, e não como ausência:

```php
$cache = new ApcuCache();
$cache->set('flag', false);
$cache->get('flag');    // false
$cache->get('missing'); // null
```

### TTL

O TTL usado por `set()` é escolhido assim:

1. `$ttlSeconds` informado na chamada, se não for `null`;
2. senão, o `defaultTtlSeconds` do construtor;
3. se os dois forem `null`, o item fica sem expiração.

Com o TTL escolhido:

| TTL efetivo     | Resultado                                                    |
|-----------------|--------------------------------------------------------------|
| `null`          | grava sem expiração                                          |
| maior que zero  | grava com expiração nesse número de segundos                 |
| zero ou negativo| não grava e **remove** a chave, se ela existir               |

Exemplos:

```php
$cache = new ApcuCache(prefix: 'ttl:', defaultTtlSeconds: 60);
$cache->set('key', 'stored');      // grava por 60 s
$cache->set('key', 'ignored', 0);  // remove a chave
$cache->get('key');                // null

$cache = new ApcuCache(defaultTtlSeconds: 0);
$cache->set('key', 'value');       // não grava
$cache->get('key');                // null

$cache = new ApcuCache(defaultTtlSeconds: 0);
$cache->set('key', 'value', 30);   // o TTL da chamada vence: grava por 30 s
$cache->get('key');                // 'value'
```

### Remoção

`delete()` remove a chave. Remover uma chave que não existe não gera erro.

## Configuração da extensão

| Opção    | Tipo         | Padrão | Uso                                         |
|----------|--------------|--------|---------------------------------------------|
| `prefix` | `string`     | `''`   | vira o `prefix` do `ApcuCache`              |
| `ttl`    | `int\|null`  | `null` | vira o `defaultTtlSeconds` do `ApcuCache`   |

Normalização:

- `prefix` é convertido para string (`(string)`).
- `ttl` é convertido para inteiro (`(int)`) quando não é `null`. Exemplo: `'60'` vira `60`.
- `ttl` ausente ou `null` significa sem expiração.
- `ttl` zero ou negativo é aceito e segue a regra do TTL acima (nada é gravado
  sem TTL explícito na chamada).
- Chaves desconhecidas em `$config` são ignoradas.

Registro manual:

```php
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;

$application = Application::create()->extend(new ApcuCacheExtension([
    'prefix' => 'app:',
    'ttl' => 3600,
]));

$cache = $application->container()->get(CacheStore::class); // ApcuCache
```

Use `ttl` `3600` para reproduzir o tempo padrão da integração APCu antiga.

## Uso pelo bootstrap do framework 2

O framework 2 carrega a extensão sozinho a partir do ambiente, por meio de
`Elavora\Api\Framework\Bootstrap\EnvironmentExtensions` e
`ApplicationBootstrap`. Com `CACHE_DRIVER=apcu`, ele cria
`new ApcuCacheExtension($config)` com:

- `prefix`: `CACHE_PREFIX`, ou `'api:cache:'` quando vazia ou ausente;
- `ttl`: `(int) CACHE_TTL`, só quando `CACHE_TTL` está definida e não vazia.

Por isso o construtor da extensão precisa continuar recebendo um único array.

Exemplos:

```php
use Elavora\Api\Extension\CacheApcu\ApcuCache;
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\CacheStore;

$extensions = EnvironmentExtensions::load('/app', ['CACHE_DRIVER' => 'apcu']);
// lista com um único item, instância de ApcuCacheExtension

$app = ApplicationBootstrap::create(
    basePath: '/app',
    environment: ['CACHE_DRIVER' => 'apcu', 'CACHE_PREFIX' => 'env:', 'CACHE_TTL' => '30'],
);
$cache = $app->container()->get(CacheStore::class); // ApcuCache
$cache->set('k', 'v');
apcu_fetch('env:k'); // 'v' (o prefixo veio de CACHE_PREFIX)

$app = ApplicationBootstrap::create(basePath: '/app', environment: ['CACHE_DRIVER' => 'apcu']);
$app->container()->get(CacheStore::class)->set('k', 'v');
apcu_fetch('api:cache:k'); // 'v' (prefixo padrão do framework)
```

Quem registra a extensão pelo ambiente não deve registrá-la de novo na lista
`extensions` do bootstrap. Registre somente um adapter de cache por aplicação,
porque todos usam o mesmo contrato `CacheStore`.

## Validação local

Os testes rodam no container `ghcr.io/elavora/php-dev:8.3-latest`, montando o
repositório em `/origem` (PowerShell, na raiz do repo):

```powershell
docker run --rm -v "${PWD}:/origem" -w /origem ghcr.io/elavora/php-dev:8.3-latest sh -lc "composer install --no-interaction && composer validate --strict --no-check-publish && composer test"
```

O script `composer test` já liga `apc.enable_cli=1`.

## Observações

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensões no bootstrap da aplicação.
- Instale apenas os módulos que a aplicação realmente usa.
