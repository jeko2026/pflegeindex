<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use SplFileInfo;

final class PlatformDependencyTest extends TestCase
{
    private const PLATFORM_NAMESPACE = 'App\\Platform\\';

    /**
     * Protects the inward-only Platform boundary.
     *
     * Platform code may depend only on other App\Platform symbols and PHP's
     * built-in classes or functions. Laravel, project code, application models
     * and third-party packages must depend on Platform, never the reverse.
     */
    public function test_platform_uses_only_platform_or_builtin_php_dependencies(): void
    {
        $platformPath = dirname(__DIR__, 3).'/app/Platform';

        $this->assertDirectoryExists($platformPath);

        $violations = [];

        foreach ($this->phpFiles($platformPath) as $file) {
            $source = file_get_contents($file->getPathname());

            if (! is_string($source)) {
                $violations[] = $this->relativePath($file->getPathname()).': unreadable PHP file';

                continue;
            }

            foreach ($this->dependencies($source) as [$dependency, $line]) {
                if (! $this->isAllowedDependency($dependency)) {
                    $violations[] = $this->violation($file, $line, 'dependency', $dependency);
                }
            }

            foreach ($this->functionCalls($source) as [$function, $line]) {
                if (! $this->isBuiltinFunction($function)) {
                    $violations[] = $this->violation($file, $line, 'function', $function);
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, [
            'Platform allowlist violations found:',
            ...$violations,
        ]));
    }

    public function test_allowlist_rule_accepts_only_platform_and_builtin_php_symbols(): void
    {
        $this->assertTrue($this->isAllowedDependency('App\\Platform\\DirectoryCore\\Domain\\EntrySort'));
        $this->assertTrue($this->isAllowedDependency('InvalidArgumentException'));
        $this->assertFalse($this->isAllowedDependency('App\\Models\\Facility'));
        $this->assertFalse($this->isAllowedDependency('Vendor\\Package\\Client'));
        $this->assertTrue($this->isBuiltinFunction('trim'));
        $this->assertFalse($this->isBuiltinFunction('route'));
    }

    /** @return list<SplFileInfo> */
    private function phpFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** @return list<array{string, int}> */
    private function dependencies(string $source): array
    {
        $dependencies = [];
        $braceDepth = 0;
        $tokens = $this->significantTokens($source);

        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $braceDepth++;

                continue;
            }

            if ($token === '}') {
                $braceDepth--;

                continue;
            }

            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                $dependencies[] = [$token[1], $token[2]];
            }

            if ($token[0] === T_USE && $braceDepth === 0) {
                $import = $tokens[$index + 1] ?? null;

                if (is_array($import) && $import[0] === T_STRING) {
                    $dependencies[] = [$import[1], $import[2]];
                }
            }
        }

        return $dependencies;
    }

    private function isAllowedDependency(string $dependency): bool
    {
        $dependency = ltrim($dependency, '\\');

        if (str_starts_with($dependency, 'namespace\\') || str_starts_with($dependency, self::PLATFORM_NAMESPACE)) {
            return true;
        }

        if (! class_exists($dependency) && ! interface_exists($dependency) && ! trait_exists($dependency)) {
            return false;
        }

        return (new ReflectionClass($dependency))->isInternal();
    }

    /** @return list<array{string, int}> */
    private function functionCalls(string $source): array
    {
        $tokens = $this->significantTokens($source);
        $calls = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $previousType = is_array($previous) ? $previous[0] : $previous;
            $next = $tokens[$index + 1] ?? null;

            if ($next !== '(' || in_array($previousType, [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }

            $calls[] = [$token[1], $token[2]];
        }

        return $calls;
    }

    /** @return list<array{int, string, int}|string> */
    private function significantTokens(string $source): array
    {
        return array_values(array_filter(
            token_get_all($source),
            fn (array|string $token): bool => ! is_array($token)
                || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
    }

    private function isBuiltinFunction(string $function): bool
    {
        return function_exists($function) && (new ReflectionFunction($function))->isInternal();
    }

    private function violation(SplFileInfo $file, int $line, string $type, string $symbol): string
    {
        return sprintf(
            '%s:%d: disallowed %s `%s`',
            $this->relativePath($file->getPathname()),
            $line,
            $type,
            $symbol,
        );
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(dirname(__DIR__, 3)) + 1));
    }
}
