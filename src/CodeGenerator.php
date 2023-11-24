<?php

declare(strict_types=1);

namespace Typhoon\Overloading;

/**
 * @internal
 * @psalm-internal Typhoon\Overloading
 */
final class CodeGenerator
{
    private const TYPE_CHECKERS = [
        'null' => '%s === null',
        'true' => '%s === true',
        'false' => '%s === false',
        'bool' => 'is_bool(%s)',
        'int' => 'is_int(%s)',
        'float' => 'is_float(%s)',
        'string' => 'is_string(%s)',
        'array' => 'is_array(%s)',
        'object' => 'is_object(%s)',
        'resource' => 'is_resource(%s)',
        'iterable' => 'is_iterable(%s)',
        'callable' => 'is_callable(%s)',
        'static' => '%s instanceof static',
        'mixed' => 'true',
    ];

    /**
     * @psalm-suppress UnusedConstructor
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @param ?class-string $class
     */
    public static function generateTypeCheck(?string $class, string $var, ?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'true';
        }

        if ($type instanceof \ReflectionUnionType) {
            return sprintf('(%s)', implode(' || ', array_map(
                static fn (\ReflectionType $type): string => self::generateTypeCheck($class, $var, $type),
                $type->getTypes(),
            )));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode(' && ', array_map(
                static fn (\ReflectionType $type): string => self::generateTypeCheck($class, $var, $type),
                $type->getTypes(),
            ));
        }

        if (!$type instanceof \ReflectionNamedType) {
            throw new \LogicException(sprintf('%s is not supported.', $type::class));
        }

        $name = $type->getName();

        if ($name === 'self') {
            if ($class === null) {
                throw new \LogicException('No scope class.');
            }

            return $var . ' instanceof ' . $class;
        }

        if ($name === 'parent') {
            if ($class === null) {
                throw new \LogicException('No scope class.');
            }

            $parent = get_parent_class($class);

            if ($parent === false) {
                throw new \LogicException(sprintf('%s does not have parent.', $class));
            }

            return $var . ' instanceof ' . $parent;
        }

        $code = isset(self::TYPE_CHECKERS[$name]) ? sprintf(self::TYPE_CHECKERS[$name], $var) : sprintf('%s instanceof \%s', $var, $name);

        if ($type->allowsNull() && $name !== 'null' && $name !== 'mixed') {
            return sprintf('(%s === null || %s)', $var, $code);
        }

        return $code;
    }

    public static function generateArgumentsNumberCheck(\ReflectionFunctionAbstract $function): string
    {
        $numberOfParameters = $function->getNumberOfParameters();
        $numberOfRequiredParameters = $function->getNumberOfRequiredParameters();

        if ($numberOfParameters === $numberOfRequiredParameters) {
            return '$argsNumber === ' . $numberOfParameters;
        }

        $code = '';

        if ($numberOfRequiredParameters > 0) {
            $code = '$argsNumber >= ' . $numberOfRequiredParameters;
        }

        if ($function->isVariadic()) {
            return $code;
        }

        return ($code === '' ? '' : $code . ' && ') . '$argsNumber <= ' . $numberOfParameters;
    }

    public static function generateArgumentsCheck(\ReflectionFunctionAbstract $function): string
    {
        $class = $function instanceof \ReflectionMethod ? $function->class : $function->getClosureScopeClass()?->name;
        $code = self::generateArgumentsNumberCheck($function);

        foreach ($function->getParameters() as $parameter) {
            $position = $parameter->getPosition();
            $name = var_export($parameter->name, true);
            $var = sprintf('($args[%d] ?? $args[%s])', $position, $name);
            $typeCheck = self::generateTypeCheck($class, $var, $parameter->getType());

            if ($parameter->isOptional()) {
                $code .= sprintf(
                    ' && (!array_key_exists(%d, $args) && !array_key_exists(%s, $args) || %s)',
                    $position,
                    $name,
                    $typeCheck,
                );

                continue;
            }

            $code .= sprintf(
                ' && (array_key_exists(%d, $args) || array_key_exists(%s, $args)) && %s',
                $position,
                $name,
                $typeCheck,
            );
        }

        return $code;
    }

    /**
     * @param non-empty-list<\ReflectionFunctionAbstract> $candidates
     */
    public static function generateResolver(\ReflectionFunctionAbstract $function, array $candidates): string
    {
        $code = ($function instanceof \ReflectionFunction || $function->isStatic() ? 'static ' : '') . "function (\$args) {\n";
        $code .= "    \$argsNumber = count(\$args);\n";

        foreach ($candidates as $candidate) {
            $code .= sprintf(
                "\n    if (%s) {\n        return %s%s(%s);\n    }\n",
                self::generateArgumentsCheck($candidate),
                $candidate instanceof \ReflectionMethod ? ($candidate->isStatic() ? 'self::' : '$this->') : '',
                $candidate->name,
                $candidate->getNumberOfParameters() === 0 ? '' : '...$args',
            );
        }

        return $code . sprintf(
            "\n    throw new \\BadMethodCallException(sprintf('No matching overloading %s for %s(%%s).', implode(', ', array_map('get_debug_type', \$args))));\n}",
            $function instanceof \ReflectionFunction ? 'functions' : 'methods',
            self::functionName($function),
        );
    }

    private static function functionName(\ReflectionFunctionAbstract $function): string
    {
        return sprintf('%s%s', $function instanceof \ReflectionMethod ? $function->class . '::' : '', $function->name);
    }
}
