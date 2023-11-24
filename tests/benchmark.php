<?php

declare(strict_types=1);

use Typhoon\Overloading\Overload;

require_once __DIR__ . '/../vendor/autoload.php';

final class WhateverHandler
{
    public function handle(mixed ...$args): mixed
    {
        return Overload::call();
    }

    #[Overload('handle')]
    public function handleInt(int $int): string
    {
        return __METHOD__;
    }

    #[Overload('handle')]
    public function handleString(string $string): string
    {
        return __METHOD__;
    }

    #[Overload('handle')]
    public function handleStdClass(stdClass $object): string
    {
        return __METHOD__;
    }

    #[Overload('handle')]
    public function handleNamedOptionalArguments(int $int = 0, float $float = M_E): string
    {
        return __METHOD__;
    }
}

$handler = new WhateverHandler();
// warm up
$handler->handle();

\DragonCode\Benchmark\Benchmark::start()
    ->withoutData()
    ->round(2)
    ->compare([
        'direct call' => static function () use ($handler): void {
            $handler->handleNamedOptionalArguments();
        },
        'polymorphic call' => static function () use ($handler): void {
            $handler->handle();
        },
    ]);
