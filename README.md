# elavora/api-cache-apcu

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-cache-apcu.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-apcu)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-cache-apcu.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-apcu)
[![Composer Quality](https://github.com/Elavora/api-cache-apcu/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-apcu/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-cache-apcu/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-apcu/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-cache-apcu.svg?style=flat-square)](LICENSE)
Adapter opcional de cache APCu para o framework Elavora.

O pacote requer a extensao PHP `apcu`. Em execucoes pela CLI, habilite
`apc.enable_cli=1` quando o cache precisar estar ativo.

Registre `ApcuCacheExtension` com as opcoes `prefix` e `ttl`. Sem `ttl`, os
itens permanecem armazenados sem expiracao, como no adapter Redis modular.
Defina `ttl` como `3600` para reproduzir o tempo padrao da integracao APCu
anterior.

```php
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;

$application->extend(new ApcuCacheExtension([
    'prefix' => 'app:',
    'ttl' => 3600,
]));
```

O pacote implementa `Elavora\Api\Framework\Contracts\CacheStore`. Registre
somente uma extensao de cache na aplicacao, pois os adapters de cache
compartilham esse contrato.
