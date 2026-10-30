# elavora/api-cache-apcu

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-cache-apcu.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-apcu)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-cache-apcu.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-apcu)
[![Qualidade](https://github.com/Elavora/api-cache-apcu/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-apcu/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-cache-apcu/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-apcu/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-cache-apcu.svg?style=flat-square)](LICENSE)

Adapter opcional de cache APCu para o framework Elavora.

O pacote requer a extensão PHP `apcu`. Em execuções pela CLI, habilite
`apc.enable_cli=1` quando o cache precisar estar ativo.

Registre `ApcuCacheExtension` com as opções `prefix` e `ttl`. Sem `ttl`, os
itens permanecem armazenados sem expiração, como no adapter Redis modular.
Defina `ttl` como `3600` para reproduzir o tempo padrão da integração APCu
anterior.

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

Mais exemplos: [docs/USO.md](docs/USO.md).

## Desenvolvimento e testes

Você só precisa de Docker. O ambiente é a imagem `ghcr.io/elavora/php-dev:8.3-latest`: abra o repositório no VS Code
com **Dev Containers: Reopen in Container**, ou rode no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

Scripts do Composer:

```bash
composer check     # lint, testes e PHPStan
composer test      # testes PHPUnit
composer lint      # sintaxe (elavora-standards lint)
composer analyse   # PHPStan, nível 8
```

A imagem já traz a extensão `apcu` e o `apc.enable_cli = 1`, então os testes rodam sem instalar nada e sem rede
(detalhes em [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md#extensão-apcu-nos-testes)).

O código segue o padrão PER-CS, com as regras do pacote `elavora/code-standards`. No devcontainer, o arquivo é
formatado ao salvar; no PR, o CI formata o que faltou e commita a correção (`style: organiza o código`).
Os testes rodam no CI, no workflow **Qualidade**, em todo PR. O passo a passo, o que roda no CI e como reproduzi-lo
estão em [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md).

## Como contribuir

1. Crie uma branch a partir da `main` (`feat/...`, `fix/...` ou `docs/...`).
2. Faça commits em português do Brasil, no padrão Conventional Commits (`feat: ...`, `fix: ...`, `docs: ...`).
3. Rode `composer check` antes de abrir o PR.
4. Abra o PR para a `main`, preencha o template e aplique as labels que definem a versão
   ([Versões e release](docs/AUTOMACAO.md#versões-e-release)).
5. Confira o comentário do pipeline de qualidade no PR e só integre com os checks verdes.

## Versões

Cada PR integrado na `main` gera uma versão automaticamente, conforme as labels do PR: sem a label `release` sai uma
beta (`0.3.2-beta.1`), com ela sai a estável (`0.3.2`). As tags novas não têm o prefixo `v`, e as notas de cada
GitHub Release são geradas em português a partir dos PRs. Veja [Versões e release](docs/AUTOMACAO.md#versões-e-release).

## Documentação

1. [Guia de uso](docs/USO.md): o uso do pacote e a API pública.
2. [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md): ambiente, como rodar os testes e o que roda no CI.
3. [Automação do repositório](docs/AUTOMACAO.md): workflows, Dependabot, estilo de código, labels e versões.

## Licença

MIT