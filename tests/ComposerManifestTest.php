<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ComposerManifestTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function manifest(): array
    {
        $json = file_get_contents(dirname(__DIR__) . '/composer.json');
        self::assertIsString($json);

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testRequiresRuntimeDependenciesFromDocumentation(): void
    {
        $require = self::manifest()['require'] ?? [];

        self::assertSame('>=8.3', $require['php'] ?? null);
        self::assertSame('*', $require['ext-apcu'] ?? null);
        self::assertSame('^2.0', $require['elavora/api-framework'] ?? null);
    }

    public function testTestScriptEnablesApcuOnCli(): void
    {
        $scripts = self::manifest()['scripts'] ?? [];
        $test = $scripts['test'] ?? '';
        $test = is_array($test) ? implode(' ', $test) : (string) $test;

        self::assertStringContainsString('apc.enable_cli=1', $test);
    }
}
