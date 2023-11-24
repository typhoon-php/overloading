<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

/**
 * @internal
 * @psalm-internal Typhoon\Overloading
 * @psalm-type Resolver = \Closure(list<mixed>): mixed
 */
interface ResolverCache
{
    /**
     * @param class-string $class
     * @param non-empty-string $method
     * @param callable(): string $codeGenerator
     * @return Resolver
     */
    public function get(string $class, string $method, callable $codeGenerator): \Closure;

    public function clear(): void;
}
