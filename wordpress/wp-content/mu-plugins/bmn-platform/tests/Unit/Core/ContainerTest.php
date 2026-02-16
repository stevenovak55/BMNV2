<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Core;

use BMN\Platform\Core\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBindAndMake(): void
    {
        $this->container->bind('greeting', fn() => 'hello');
        $this->assertSame('hello', $this->container->make('greeting'));
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton('counter', fn() => new \stdClass());
        $first = $this->container->make('counter');
        $second = $this->container->make('counter');
        $this->assertSame($first, $second);
    }

    public function testBindReturnsNewInstanceEachTime(): void
    {
        $this->container->bind('obj', fn() => new \stdClass());
        $first = $this->container->make('obj');
        $second = $this->container->make('obj');
        $this->assertNotSame($first, $second);
    }

    public function testHasReturnsTrueForBound(): void
    {
        $this->container->bind('foo', fn() => 'bar');
        $this->assertTrue($this->container->has('foo'));
    }

    public function testHasReturnsFalseForUnbound(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testMakeThrowsForUnbound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('nonexistent');
    }

    public function testBindWithClassName(): void
    {
        $this->container->bind(\stdClass::class, fn() => new \stdClass());
        $result = $this->container->make(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function testContainerPassesItselfToClosure(): void
    {
        $this->container->bind('inner', fn() => 'inner_value');
        $this->container->bind('outer', fn(Container $c) => $c->make('inner'));
        $this->assertSame('inner_value', $this->container->make('outer'));
    }
}
