<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class ClassDiscoverer
{
    /**
     * @var array<string, list<class-string>>
     */
    private array $discoveredClassesByPathSet = [];

    /**
     * @param  array<int, string>  $paths
     * @return list<class-string>
     */
    public function classesIn(array $paths): array
    {
        $realPaths = collect($paths)
            ->map(fn (string $path): string|false => realpath($path))
            ->filter()
            ->values()
            ->all();

        $pathSet = implode('|', $realPaths);

        if (isset($this->discoveredClassesByPathSet[$pathSet])) {
            return $this->discoveredClassesByPathSet[$pathSet];
        }

        foreach ($realPaths as $path) {
            $this->requirePhpFiles($path);
        }

        return $this->discoveredClassesByPathSet[$pathSet] = collect(get_declared_classes())
            ->filter(fn (string $class): bool => $this->classIsInPaths($class, $realPaths))
            ->sort()
            ->values()
            ->all();
    }

    private function requirePhpFiles(string $path): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
        );

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function classIsInPaths(string $class, array $paths): bool
    {
        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();

        if (! is_string($file)) {
            return false;
        }

        $file = realpath($file);

        if ($file === false) {
            return false;
        }

        foreach ($paths as $path) {
            if (str_starts_with($file, $path.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
