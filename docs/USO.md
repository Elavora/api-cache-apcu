# Guia de uso

Adapter opcional de cache APCu para o framework Elavora.

## Instalacao

```bash
composer require elavora/api-cache-apcu
```

## Quando usar

- Registrar um backend de cache para o contrato do framework.
- Evitar acoplamento da aplicacao a APCu ou Redis diretamente.
- Centralizar prefixo e TTL usados pela aplicacao.

## Exemplo rapido

```php
use Elavora\Api\Extension\CacheApcu\ApcuCacheExtension;

$application->extend(new ApcuCacheExtension([
    'prefix' => 'app:',
    'ttl' => 3600,
]));
```

## Principais pontos de entrada

- `Elavora\Api\Extension\CacheApcu\ApcuCache`
- `Elavora\Api\Extension\CacheApcu\ApcuCacheExtension`

## Dependencias de runtime

- `ext-apcu` `*`
- `elavora/api-framework` `^0.3.1`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-cache-apcu" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-cache-apcu" composer:2 sh -lc "find . \\( -path ./.git -o -path ./vendor \\) -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l"
```

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.