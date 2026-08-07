<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class ClassDiscoverer
{
    /**
     * @param  array<int, string>  $paths
     * @return list<class-string>
     */
    public function classesIn(array $paths): array
    {
        $realPaths = array_values(array_filter(array_map(realpath(...), $paths)));

        foreach ($realPaths as $path) {
            $this->requirePhpFiles($path);
        }

        $classes = array_filter(
            get_declared_classes(),
            fn (string $class): bool => $this->classIsInPaths($class, $realPaths),
        );

        sort($classes);

        return $classes;
    }

    private function requirePhpFiles(string $path): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
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

        return array_any($paths, fn (string $path): bool => str_starts_with($file, $path.DIRECTORY_SEPARATOR));
    }
}
