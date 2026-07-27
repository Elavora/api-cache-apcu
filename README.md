# elavora/api-cache-apcu

Adapter opcional de cache APCu para o framework Elavora.

## Requisitos

- PHP `>=8.3`
- `ext-apcu`
- `elavora/api-framework` `^1.0`

Em execucoes pela CLI, habilite `apc.enable_cli=1`.

```php
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;

$application->extend(new ApcuCacheExtension([
    'prefix' => 'app:',
    'ttl' => 3600,
]));
```

Sem `ttl`, os itens permanecem sem expiracao. TTL zero ou negativo remove a
entrada.

Consulte [docs/USO.md](docs/USO.md) para os comandos de qualidade.
