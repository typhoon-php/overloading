<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

/**
 * @internal
 * @psalm-internal Typhoon\Overloading
 * @psalm-import-type Resolver from ResolverCache
 */
final class InMemoryResolverCache implements ResolverCache
{
    /**
     * @var array<non-empty-string, Resolver>
     */
    private array $resolvers = [];

    public function get(string $class, string $method, callable $codeGenerator): \Closure
    {
        $key = $class . '::' . $method;

        if (isset($this->resolvers[$key])) {
            return $this->resolvers[$key];
        }

        /**
         * It is completely safe to use eval here, since there's no user import involved in $codeGenerator.
         * $codeGenerator produces code purely based on Reflection.
         *
         * @psalm-suppress ForbiddenCode
         * @var Resolver
         */
        $resolver = eval('return ' . $codeGenerator() . ';');

        return $this->resolvers[$key] = $resolver;
    }

    public function clear(): void
    {
        $this->resolvers = [];
    }
}
