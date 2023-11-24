<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

/**
 * @internal
 * @psalm-internal Typhoon\Overloading
 * @psalm-import-type Resolver from ResolverCache
 */
final class FileResolverCache implements ResolverCache
{
    private static ?bool $opcacheEnabled = null;

    private readonly string $directory;

    public function __construct(
        ?string $directory = null,
    ) {
        $this->directory = $directory ?? sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'typhoon_overload' . \DIRECTORY_SEPARATOR . hash('xxh128', __DIR__);
    }

    /**
     * @psalm-suppress MixedArgument
     * @infection-ignore-all
     */
    private static function opcacheEnabled(): bool
    {
        return self::$opcacheEnabled ??= (\function_exists('opcache_invalidate')
            && filter_var(\ini_get('opcache.enable'), FILTER_VALIDATE_BOOL)
            && (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) || filter_var(\ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL)));
    }

    public function get(string $class, string $method, callable $codeGenerator): \Closure
    {
        return $this->handleErrors(function () use ($class, $method, $codeGenerator): \Closure {
            $file = $this->file($class, $method);

            try {
                /**
                 * @psalm-suppress UnresolvableInclude
                 * @var Resolver
                 */
                return include $file;
            } catch (\Throwable $exception) {
                if (!str_contains($exception->getMessage(), 'No such file or directory')) {
                    throw $exception;
                }
            }

            $directory = \dirname($file);

            if (!is_dir($directory)) {
                mkdir($directory, recursive: true);
            }

            /** @infection-ignore-all */
            $tmp = $directory . uniqid(more_entropy: true);
            $handle = fopen($tmp, 'x');
            fwrite($handle, "<?php return {$codeGenerator()};\n");
            fclose($handle);

            /**
             * Set mtime in the past to enable OPcache compilation for this file.
             * @infection-ignore-all
             */
            touch($tmp, ($_SERVER['REQUEST_TIME'] ?? time()) - 10);

            rename($tmp, $file);

            if (self::opcacheEnabled()) {
                opcache_invalidate($file, true);
                opcache_compile_file($file);
            }

            /**
             * @psalm-suppress UnresolvableInclude
             * @var Resolver
             */
            return include $file;
        });
    }

    public function clear(): void
    {
        $this->handleErrors(function (): void {
            if (!is_dir($this->directory)) {
                return;
            }

            /** @var iterable<\SplFileInfo> */
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                $pathname = $file->getPathname();

                if ($file->isDir()) {
                    rmdir($pathname);

                    continue;
                }

                try {
                    unlink($pathname);
                } catch (\Throwable $exception) {
                    if (!str_contains($exception->getMessage(), 'No such file or directory')) {
                        throw $exception;
                    }

                    continue;
                }

                if (self::opcacheEnabled()) {
                    opcache_invalidate($pathname, true);
                }
            }
        });
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function file(string $class, string $method): string
    {
        $hash = hash('xxh128', $class . '::' . $method);

        /** @infection-ignore-all */
        return $this->directory . \DIRECTORY_SEPARATOR . $hash[0] . \DIRECTORY_SEPARATOR . $hash[1] . \DIRECTORY_SEPARATOR . substr($hash, 2) . '.php';
    }

    /**
     * @template T
     * @param \Closure(): T $function
     * @return T
     */
    private function handleErrors(\Closure $function): mixed
    {
        set_error_handler(static fn (int $level, string $message, string $file, int $line) => throw new \ErrorException(
            message: $message,
            severity: $level,
            filename: $file,
            line: $line,
        ));

        try {
            return $function();
        } finally {
            restore_error_handler();
        }
    }
}
