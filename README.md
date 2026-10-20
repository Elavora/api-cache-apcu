# elavora/api-cache-apcu

Adapter opcional de cache APCu para o framework Elavora 2
(`elavora/api-framework` `^2.0`, PHP `>=8.3`).

O pacote requer a extensão PHP `apcu`. Em execuções pela CLI, habilite
`apc.enable_cli=1` quando o cache precisar estar ativo.

## Uso pelo ambiente

Com o bootstrap do framework 2, basta instalar o pacote e definir
`CACHE_DRIVER=apcu`. `CACHE_PREFIX` e `CACHE_TTL` são opcionais.

## Registro manual

Registre `ApcuCacheExtension` com as opções `prefix` e `ttl`. Sem `ttl`, os
itens ficam guardados sem expiração. Use `ttl` `3600` para reproduzir o tempo
padrão da integração APCu antiga.

```php
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;

$application->extend(new ApcuCacheExtension([
    'prefix' => 'app:',
    'ttl' => 3600,
]));
```

O pacote implementa `Elavora\Api\Framework\Contracts\CacheStore`. Registre
somente uma extensão de cache na aplicação, pois os adapters de cache
compartilham esse contrato.

A especificação completa (API, regras de TTL, exemplos) está em
[docs/USO.md](docs/USO.md).
