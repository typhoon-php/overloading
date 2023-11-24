<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeGenerator::class)]
final class CodeGeneratorTest extends TestCase
{
    /**
     * @return \Generator<int, array{\Closure, string}>
     */
    public static function typeChecks(): \Generator
    {
        yield [static function (): void {}, 'true'];
        // yield [static function (null $_a): void {}, '$arg === null'];
        // yield [static function (true $_a): void {}, '$arg === true'];
        // yield [static function (false $_a): void {}, '$arg === false'];
        yield [static function (bool $_a): void {}, 'is_bool($arg)'];
        yield [static function (int $_a): void {}, 'is_int($arg)'];
        yield [static function (float $_a): void {}, 'is_float($arg)'];
        yield [static function (string $_a): void {}, 'is_string($arg)'];
        yield [static function (?string $_a): void {}, '($arg === null || is_string($arg))'];
        yield [static function (array $_a): void {}, 'is_array($arg)'];
        yield [static function (object $_a): void {}, 'is_object($arg)'];
        yield [static function (iterable $_a): void {}, 'is_iterable($arg)'];
        yield [static function (callable $_a): void {}, 'is_callable($arg)'];
        yield [static function (self $_a): void {}, '$arg instanceof ' . self::class];
        yield [static function (parent $_a): void {}, '$arg instanceof ' . parent::class];
        yield [static function (int|string $_a): void {}, '(is_string($arg) || is_int($arg))'];
        yield [static function (TestCase $_a): void {}, '$arg instanceof \PHPUnit\Framework\TestCase'];
        yield [static function (\Countable&\stdClass $_a): void {}, '$arg instanceof \Countable && $arg instanceof \stdClass'];
        yield [static function (mixed $_a): void {}, 'true'];
    }

    /**
     * @return \Generator<int, array{\Closure, string}>
     */
    public static function argumentsNumberChecks(): \Generator
    {
        yield [static function (): void {}, '$argsNumber === 0'];
        yield [static function (string $_a): void {}, '$argsNumber === 1'];
        yield [static function (?string $_a): void {}, '$argsNumber === 1'];
        yield [static function (string $_a = ''): void {}, '$argsNumber <= 1'];
        yield [static function (string $_a, int $_b = 1): void {}, '$argsNumber >= 1 && $argsNumber <= 2'];
        yield [static function (string ...$_a): void {}, ''];
        yield [static function (string $_a, int ...$_b): void {}, '$argsNumber >= 1'];
    }

    /**
     * @return \Generator<int, array{\Closure, string}>
     */
    public static function argumentsChecks(): \Generator
    {
        yield [static function (): void {}, '$argsNumber === 0'];
        yield [static function (string $_a): void {}, "\$argsNumber === 1 && (array_key_exists(0, \$args) || array_key_exists('_a', \$args)) && is_string((\$args[0] ?? \$args['_a']))"];
        yield [static function (string $_a, int $_b): void {}, "\$argsNumber === 2 && (array_key_exists(0, \$args) || array_key_exists('_a', \$args)) && is_string((\$args[0] ?? \$args['_a'])) && (array_key_exists(1, \$args) || array_key_exists('_b', \$args)) && is_int((\$args[1] ?? \$args['_b']))"];
        yield [static function (string $_a = ''): void {}, "\$argsNumber <= 1 && (!array_key_exists(0, \$args) && !array_key_exists('_a', \$args) || is_string((\$args[0] ?? \$args['_a'])))"];
        yield [static function (string $_a = '', int $_b = 0): void {}, "\$argsNumber <= 2 && (!array_key_exists(0, \$args) && !array_key_exists('_a', \$args) || is_string((\$args[0] ?? \$args['_a']))) && (!array_key_exists(1, \$args) && !array_key_exists('_b', \$args) || is_int((\$args[1] ?? \$args['_b'])))"];
    }

    #[DataProvider('typeChecks')]
    public function testItGeneratesCorrectTypeChecks(\Closure $function, ?string $expectedCode): void
    {
        $type = ((new \ReflectionFunction($function))->getParameters()[0] ?? null)?->getType();

        $code = CodeGenerator::generateTypeCheck(self::class, '$arg', $type);

        self::assertSame($expectedCode, $code);
    }

    public function testItThrowsWhenUnknownReflectionType(): void
    {
        $type = $this->createMock(\ReflectionType::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/^[\w\\\]+ is not supported\.$/');

        CodeGenerator::generateTypeCheck(self::class, '$var', $type);
    }

    #[DataProvider('argumentsNumberChecks')]
    public function testItGeneratesCorrectArgumentsNumberCheck(\Closure $function, ?string $expectedCode): void
    {
        $reflectionFunction = new \ReflectionFunction($function);

        $code = CodeGenerator::generateArgumentsNumberCheck($reflectionFunction);

        self::assertSame($expectedCode, $code);
    }

    #[DataProvider('argumentsChecks')]
    public function testItGeneratesCorrectArgumentsCheck(\Closure $function, ?string $expectedCode): void
    {
        $reflectionFunction = new \ReflectionFunction($function);

        $code = CodeGenerator::generateArgumentsCheck($reflectionFunction);

        self::assertSame($expectedCode, $code);
    }

    public function testItGeneratesCorrectStaticResolver(): void
    {
        $object = new class () {
            public static function b(int $_int): void {}
        };
        $bReflection = new \ReflectionMethod($object, 'b');
        $cReflection = new \ReflectionFunction('json_last_error');

        $code = CodeGenerator::generateResolver(new \ReflectionFunction('fclose'), [$bReflection, $cReflection]);

        self::assertSame(
            <<<'PHP'
                static function ($args) {
                    $argsNumber = count($args);
                
                    if ($argsNumber === 1 && (array_key_exists(0, $args) || array_key_exists('_int', $args)) && is_int(($args[0] ?? $args['_int']))) {
                        return self::b(...$args);
                    }
                
                    if ($argsNumber === 0) {
                        return json_last_error();
                    }
                
                    throw new \BadMethodCallException(sprintf('No matching overloading functions for fclose(%s).', implode(', ', array_map('get_debug_type', $args))));
                }
                PHP,
            $code,
        );
    }

    public function testItGeneratesCorrectNonStaticResolver(): void
    {
        $object = new class () {
            public function a(string $_string): void {}

            public function b(int $_int): void {}

            public static function c(): void {}
        };
        $aReflection = new \ReflectionMethod($object, 'a');
        $bReflection = new \ReflectionMethod($object, 'b');
        $cReflection = new \ReflectionMethod($object, 'c');

        $code = CodeGenerator::generateResolver($aReflection, [$bReflection, $cReflection]);

        self::assertSame(
            <<<PHP
                function (\$args) {
                    \$argsNumber = count(\$args);
                
                    if (\$argsNumber === 1 && (array_key_exists(0, \$args) || array_key_exists('_int', \$args)) && is_int((\$args[0] ?? \$args['_int']))) {
                        return \$this->b(...\$args);
                    }
                
                    if (\$argsNumber === 0) {
                        return self::c();
                    }
                
                    throw new \\BadMethodCallException(sprintf('No matching overloading methods for {$aReflection->class}::a(%s).', implode(', ', array_map('get_debug_type', \$args))));
                }
                PHP,
            $code,
        );
    }
}
