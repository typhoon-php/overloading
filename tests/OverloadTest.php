<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(Overload::class)]
final class OverloadTest extends TestCase
{
    private string $cacheDir = '';

    protected function setUp(): void
    {
        $this->cacheDir = uniqid(__DIR__ . '/../var/cache/', more_entropy: true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    #[RunInSeparateProcess]
    public function testItChoosesOverloadingMethod(): void
    {
        $stringMethod = $this->overloaded('string');
        $intMethod = $this->overloaded(123);

        self::assertSame('Typhoon\Overloading\OverloadTest::overloadingString', $stringMethod);
        self::assertSame('Typhoon\Overloading\OverloadTest::overloadingInt', $intMethod);
    }

    #[RunInSeparateProcess]
    public function testItThrowsIfCalledOutsideOfClassScope(): void
    {
        $call = (static fn (): mixed => Overload::call())->bindTo(null, null);

        $this->expectExceptionObject(new \BadMethodCallException('Typhoon\Overloading\Overload::call() must be called from a class method.'));

        self::assertNotNull($call);
        $call();
    }

    #[RunInSeparateProcess]
    public function testItThrowsIfNoOverloadedMethods(): void
    {
        $this->expectExceptionObject(new \BadMethodCallException(sprintf('No overloading methods for %s().', __METHOD__)));

        Overload::call();
    }

    #[RunInSeparateProcess]
    public function testItUsesFileCacheIfConfigured(): void
    {
        Overload::useFileCache($this->cacheDir);

        $this->overloaded('string');

        self::assertTrue((new \FilesystemIterator($this->cacheDir))->valid());
    }

    #[RunInSeparateProcess]
    public function testItSwitchesBackToInMemoryCache(): void
    {
        Overload::useFileCache($this->cacheDir);
        Overload::useInMemoryCache();

        $this->overloaded('string');

        self::assertDirectoryDoesNotExist($this->cacheDir);
    }

    #[RunInSeparateProcess]
    public function testItClearsCacheWithoutErrorIfNoCacheConfigured(): void
    {
        Overload::clearCache();

        $this->expectNotToPerformAssertions();
    }

    #[RunInSeparateProcess]
    public function testItClearsCache(): void
    {
        Overload::useFileCache($this->cacheDir);
        $this->overloaded('string');

        Overload::clearCache();

        self::assertFalse((new \FilesystemIterator($this->cacheDir))->valid());
    }

    private function overloaded(mixed $_value): mixed
    {
        return Overload::call();
    }

    /**
     * @psalm-suppress UnusedMethod
     */
    #[Overload('overloaded')]
    private function overloadingString(string $_string): string
    {
        return __METHOD__;
    }

    /**
     * @psalm-suppress UnusedMethod
     */
    #[Overload('overloaded')]
    private function overloadingInt(int $_int): string
    {
        return __METHOD__;
    }
}
