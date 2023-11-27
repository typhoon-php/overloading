<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

/**
 * @internal
 * @psalm-internal Typhoon\Overloading
 */
final class CandidatesFinder
{
    /**
     * @psalm-suppress UnusedConstructor
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @param class-string $class
     * @return list<\ReflectionMethod>
     */
    public static function findCandidates(\ReflectionMethod $method, string $class): array
    {
        $static = $method->isStatic();
        $private = $method->isPrivate();
        $protected = $method->isProtected();
        $candidates = [];

        foreach (self::findCandidatesByAttribute($method, $class) as $candidate) {
            if ($candidate->isStatic() !== $static) {
                throw new \LogicException(sprintf(
                    '%s %s::%s() is not a valid overloading method for %s %s::%s().',
                    $candidate->isStatic() ? 'Static' : 'Non-static',
                    $candidate->class,
                    $candidate->name,
                    $static ? 'static' : 'non-static',
                    $method->class,
                    $method->name,
                ));
            }

            if ($candidate->isPrivate() !== $private || $candidate->isProtected() !== $protected) {
                throw new \LogicException(sprintf(
                    '%s %s::%s() is not a valid overloading method for %s %s::%s().',
                    $candidate->isPrivate() ? 'Private' : ($candidate->isProtected() ? 'Protected' : 'Public'),
                    $candidate->class,
                    $candidate->name,
                    $private ? 'private' : ($protected ? 'protected' : 'public'),
                    $method->class,
                    $method->name,
                ));
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * @param class-string $class
     * @return \Generator<int, \ReflectionMethod>
     */
    private static function findCandidatesByAttribute(\ReflectionMethod $method, string $class): \Generator
    {
        foreach ((new \ReflectionClass($class))->getMethods() as $candidate) {
            if ($candidate->name === $method->name) {
                continue;
            }

            foreach ($candidate->getAttributes(Overload::class) as $attribute) {
                if ($attribute->newInstance()->name === $method->name) {
                    yield $candidate;

                    continue 2;
                }
            }
        }
    }
}
