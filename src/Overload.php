<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Overload
{
    private static ?ResolverCache $resolverCache = null;

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
    ) {}

    public static function call(): mixed
    {
        self::$resolverCache ??= new InMemoryResolverCache();

        $trace = debug_backtrace(limit: 2)[1] ?? null;

        if ($trace === null || !isset($trace['class'])) {
            throw new \BadMethodCallException(__METHOD__ . '() must be called from a class method.');
        }

        $class = $trace['class'];
        /** @var non-empty-string */
        $method = $trace['function'];

        /** @psalm-suppress PossiblyNullFunctionCall, PossiblyNullReference */
        return self::$resolverCache
            ->get($class, $method, static function () use ($class, $method): string {
                $reflectionMethod = new \ReflectionMethod($class, $method);
                $candidates = CandidatesFinder::findCandidates($reflectionMethod);

                if ($candidates === []) {
                    throw new \BadMethodCallException(sprintf('No overloading methods for %s::%s().', $class, $method));
                }

                return CodeGenerator::generateResolver($reflectionMethod, $candidates);
            })
            ->bindTo($trace['object'] ?? null, $class)
            ->__invoke($trace['args'] ?? []);
    }

    public static function useFileCache(?string $directory = null): void
    {
        self::$resolverCache = new FileResolverCache($directory);
    }

    public static function useInMemoryCache(): void
    {
        self::$resolverCache = null;
    }

    public static function clearCache(): void
    {
        self::$resolverCache?->clear();
    }
}
