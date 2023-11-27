<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CandidatesFinder::class)]
final class CandidatesFinderTest extends TestCase
{
    public function testItDoesNotFindCandidatesIfNoMethodIsAttributed(): void
    {
        $object = new class () {
            public function a(): void {}

            public function b(): void {}
        };
        $method = new \ReflectionMethod($object, 'a');

        $candidates = CandidatesFinder::findCandidates($method, $object::class);

        self::assertSame([], $candidates);
    }

    public function testItDoesNotFindCandidatesIfAttributeNameIsDifferent(): void
    {
        $object = new class () {
            public function a(): void {}

            #[Overload('c')]
            public function b(): void {}
        };
        $method = new \ReflectionMethod($object, 'a');

        $candidates = CandidatesFinder::findCandidates($method, $object::class);

        self::assertSame([], $candidates);
    }

    public function testItFindsValidCandidates(): void
    {
        $object = new class () {
            public function a(): void {}

            #[Overload('a')]
            public function b(): void {}

            #[Overload('a')]
            public function c(): void {}
        };
        $method = new \ReflectionMethod($object, 'a');

        $candidates = CandidatesFinder::findCandidates($method, $object::class);

        self::assertEquals(
            [new \ReflectionMethod($object, 'b'), new \ReflectionMethod($object, 'c')],
            $candidates,
        );
    }

    public function testItThrowsIfStaticOverloadsNonStatic(): void
    {
        $object = new class () {
            public function a(): void {}

            #[Overload('a')]
            public static function b(): void {}
        };
        $method = new \ReflectionMethod($object, 'a');

        $this->expectExceptionObject(new \LogicException(sprintf(
            'Static %s::b() is not a valid overloading method for non-static %1$s::a().',
            $object::class,
        )));

        CandidatesFinder::findCandidates($method, $object::class);
    }

    public function testItThrowsIfNonStaticOverloadsStatic(): void
    {
        $object = new class () {
            public static function a(): void {}

            #[Overload('a')]
            public function b(): void {}
        };
        $method = new \ReflectionMethod($object, 'a');

        $this->expectExceptionObject(new \LogicException(sprintf(
            'Non-static %s::b() is not a valid overloading method for static %1$s::a().',
            $object::class,
        )));

        CandidatesFinder::findCandidates($method, $object::class);
    }

    /**
     * @psalm-suppress UnusedParam
     */
    #[TestWith(['private', 'protected'])]
    #[TestWith(['private', 'public'])]
    #[TestWith(['protected', 'private'])]
    #[TestWith(['protected', 'public'])]
    #[TestWith(['private', 'protected'])]
    #[TestWith(['private', 'public'])]
    public function testItThrowsIfVisibilityIsDifferent(string $aVisibility, string $bVisibility): void
    {
        /**
         * @psalm-suppress ForbiddenCode
         * @var object
         */
        $object = eval(<<<PHP
                return new class {
                    {$aVisibility} function a(): void {}

                    #[\\Typhoon\\Overloading\\Overload('a')]
                    {$bVisibility} function b(): void {}
                };
            PHP);
        $method = new \ReflectionMethod($object, 'a');

        $this->expectExceptionObject(new \LogicException(sprintf(
            '%s %s::b() is not a valid overloading method for %s %2$s::a().',
            ucfirst($bVisibility),
            $object::class,
            $aVisibility,
        )));

        CandidatesFinder::findCandidates($method, $object::class);
    }
}
