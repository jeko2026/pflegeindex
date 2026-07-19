<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PlatformDependencyTest extends TestCase
{
    /** @var array<string, string> */
    private const FORBIDDEN_NAMESPACE_MARKERS = [
        'Illuminate\\' => 'Laravel or Eloquent namespace',
        'Laravel\\' => 'Laravel package namespace',
        'App\\Models\\' => 'application model namespace',
        'App\\Projects\\' => 'project namespace',
    ];

    /** @var list<string> */
    private const FORBIDDEN_HELPERS = [
        '__',
        'abort',
        'abort_if',
        'abort_unless',
        'app',
        'asset',
        'auth',
        'back',
        'base_path',
        'cache',
        'collect',
        'config',
        'cookie',
        'csrf_field',
        'csrf_token',
        'database_path',
        'dispatch',
        'encrypt',
        'env',
        'event',
        'info',
        'logger',
        'now',
        'old',
        'public_path',
        'redirect',
        'report',
        'request',
        'resolve',
        'resource_path',
        'response',
        'route',
        'session',
        'storage_path',
        'to_route',
        'trans',
        'url',
        'validator',
        'view',
    ];

    public function test_platform_does_not_depend_on_laravel_eloquent_or_projects(): void
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

            foreach (self::FORBIDDEN_NAMESPACE_MARKERS as $marker => $description) {
                $this->recordStringViolations($violations, $file, $source, $marker, $description);
            }

            $this->recordPatternViolations(
                $violations,
                $file,
                $source,
                '/(?<![A-Za-z0-9_\\\\])\\\\?(?:Route|DB|Blade)::/',
                'Laravel facade',
            );
            $this->recordPatternViolations(
                $violations,
                $file,
                $source,
                '/(?<!->)(?<!::)(?<![A-Za-z0-9_\\\\])\\\\?(?:'.implode('|', self::FORBIDDEN_HELPERS).')\s*\(/',
                'Laravel helper',
            );
        }

        $this->assertSame([], $violations, implode(PHP_EOL, [
            'Platform dependency violations found:',
            ...$violations,
        ]));
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

    /** @param list<string> $violations */
    private function recordStringViolations(
        array &$violations,
        SplFileInfo $file,
        string $source,
        string $marker,
        string $description,
    ): void {
        $offset = 0;

        while (($position = strpos($source, $marker, $offset)) !== false) {
            $violations[] = $this->violation($file, $source, $position, $description, $marker);
            $offset = $position + strlen($marker);
        }
    }

    /** @param list<string> $violations */
    private function recordPatternViolations(
        array &$violations,
        SplFileInfo $file,
        string $source,
        string $pattern,
        string $description,
    ): void {
        preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$match, $position]) {
            $violations[] = $this->violation($file, $source, $position, $description, $match);
        }
    }

    private function violation(
        SplFileInfo $file,
        string $source,
        int $position,
        string $description,
        string $match,
    ): string {
        $line = substr_count(substr($source, 0, $position), "\n") + 1;

        return sprintf(
            '%s:%d: %s `%s`',
            $this->relativePath($file->getPathname()),
            $line,
            $description,
            trim($match),
        );
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(dirname(__DIR__, 3)) + 1));
    }
}
