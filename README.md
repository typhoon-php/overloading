# Typhoon Overloard

The missing method overloading feature for PHP.

[![Latest Stable Version](https://poser.pugx.org/typhoon/overloading/v/stable.png)](https://packagist.org/packages/typhoon/overloading)
[![Total Downloads](https://poser.pugx.org/typhoon/overloading/downloads.png)](https://packagist.org/packages/typhoon/overloading)
[![psalm-level](https://shepherd.dev/github/typhoon-php/overloading/level.svg)](https://shepherd.dev/github/typhoon-php/overloading)
[![type-coverage](https://shepherd.dev/github/typhoon-php/overloading/coverage.svg)](https://shepherd.dev/github/typhoon-php/overloading)
[![Code Coverage](https://codecov.io/gh/typhoon-php/overloading/branch/0.1.x/graph/badge.svg)](https://codecov.io/gh/typhoon-php/overloading/tree/0.1.x)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Ftyphoon-php%2Foverloading%2F0.1.x)](https://dashboard.stryker-mutator.io/reports/github.com/typhoon-php/overloading/0.1.x)

## Installation

`composer require typhoon/overloading`

## Usage

To mark methods `handleInt` and `handleString` as overloading method `handle`, 
add `#[Overload('handle')]` attribute to `handleInt` and `handleString` and
call `Overload::call()` from `handle`. You do not need to pass arguments to `Overload::call()`, 
this happens automagically. However, return `Overload::call()` explicitly if you need to. 
After this you will be able to call `handle` with any arguments and reach overloading methods 
when their signature matches.

```php
final class WhateverHandler
{
    public function handle(mixed ...$args): string
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
    public function handleStdClass(\stdClass $object): string
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

// WhateverHandler::handleInt
var_dump($handler->handle(300));

// WhateverHandler::handleString
var_dump($handler->handle('Hello world!'));

// WhateverHandler::handleStdClass
var_dump($handler->handle(new \stdClass()));

// WhateverHandler::handleNamedOptionalArguments
var_dump($handler->handle(float: 1.5));

// WhateverHandler::handleNamedOptionalArguments
var_dump($handler->handle());

// No matching overloading methods for WhateverHandler::handle(string, bool).
var_dump($handler->handle('Hey!', true));
```

## What about speed?

Well, using overloading is obviously slower, than calling a method directly, but not awfully slower.
Here's a simple benchmark for the `WhateverHandler`:

```php
// warm up
$handler->handle();

\DragonCode\Benchmark\Benchmark::start()
    ->withoutData()
    ->round(2)
    ->compare([
        'direct call' => static fn (): string => $handler->handleNamedOptionalArguments(),
        'overloaded call' => static fn (): string => $handler->handle(),
    ]);
```

```shell
 ------- ---------------- ------------------- 
  #       direct call      overloaded call   
 ------- ---------------- ------------------- 
  min     0 ms - 0 bytes   0 ms - 0 bytes     
  max     0 ms - 0 bytes   0.02 ms - 0 bytes  
  avg     0 ms - 0 bytes   0 ms - 0 bytes     
  total   0.95 ms          1.16 ms            
 ------- ---------------- ------------------- 
  Order   - 1 -            - 2 -              
 ------- ---------------- ------------------- 
```

It's important to understand that memoization plays a very important role here. CLI workers and applications, served
via Roadrunner, for instance, will benefit from this. For PHP-FPM you can enable file cache suitable for OPcaching via 
`Overload::useFileCache('/path/to/cache');`.

## TODO

- [ ] Finish tests.
- [ ] Explain caching in README.
- [ ] Optimize generated code.
- [ ] Inherit attributes from upstream method declarations.
- [ ] Allow to warm up classes.
- [ ] Psalm plugin.
- [ ] PHPStan plugin.
- [ ] Support static analysis types.
