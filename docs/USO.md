# Guia de uso

## Instalacao

```bash
composer require elavora/api-cache-apcu:^1.0
```

Requisitos de runtime:

- PHP `>=8.3`
- `ext-apcu`
- `elavora/api-framework` `^1.0`

## Registro

```php
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;

$application->extend(new ApcuCacheExtension([
    'prefix' => 'app:',
    'ttl' => 3600,
]));
```

Sem `ttl`, os valores nao expiram. Um TTL explicito em `set()` prevalece sobre
o padrao. TTL zero ou negativo remove a entrada.

## Qualidade

Os comandos nao dependem de Bash, `find` ou `xargs`:

```bash
composer validate --strict --no-check-publish
composer lint
composer analyse
composer test
composer check
```

`composer test` habilita APCu na CLI. `composer check` executa lint portatil,
PHPStan nivel 8 e PHPUnit.
