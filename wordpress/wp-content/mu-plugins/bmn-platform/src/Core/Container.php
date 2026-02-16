<?php

declare(strict_types=1);

namespace BMN\Platform\Core;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Simple dependency-injection container.
 *
 * Supports binding closures or class names as concrete implementations,
 * with optional singleton semantics.
 */
final class Container
{
    /** @var array<string, Closure> Registered factory closures keyed by abstract name. */
    private array $bindings = [];

    /** @var array<string, true> Abstracts that should be resolved as singletons. */
    private array $singletons = [];

    /** @var array<string, mixed> Cached singleton instances. */
    private array $instances = [];

    /**
     * Register a binding in the container.
     *
     * @param string          $abstract The service identifier (typically an interface or class name).
     * @param Closure|string  $concrete A closure that returns the instance, or a fully-qualified class name.
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $this->normalizeConcrete($concrete);
        // Clear any cached singleton so the new binding takes effect.
        unset($this->instances[$abstract]);
    }

    /**
     * Register a shared (singleton) binding in the container.
     *
     * The concrete will be resolved once and the same instance returned on subsequent calls.
     *
     * @param string          $abstract The service identifier.
     * @param Closure|string  $concrete A closure that returns the instance, or a fully-qualified class name.
     */
    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    /**
     * Resolve an abstract from the container.
     *
     * @template T
     *
     * @param class-string<T>|string $abstract The service identifier to resolve.
     *
     * @return T|mixed The resolved instance.
     *
     * @throws RuntimeException If the abstract has not been registered.
     */
    public function make(string $abstract): mixed
    {
        // Return cached singleton if available.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (! isset($this->bindings[$abstract])) {
            throw new RuntimeException(
                sprintf('No binding registered for [%s].', $abstract)
            );
        }

        $instance = ($this->bindings[$abstract])($this);

        // Cache if registered as singleton.
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Determine whether a binding exists for the given abstract.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * Store an already-resolved instance directly into the container.
     *
     * Useful for sharing objects that are created outside the container (e.g. $wpdb).
     *
     * @param string $abstract The service identifier.
     * @param mixed  $instance The pre-built instance.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->singletons[$abstract] = true;
        // Create a no-op binding so has() returns true.
        $this->bindings[$abstract] = static fn (): mixed => $instance;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Normalize a concrete value into a Closure.
     */
    private function normalizeConcrete(Closure|string $concrete): Closure
    {
        if ($concrete instanceof Closure) {
            return $concrete;
        }

        // Treat string as a class name to instantiate.
        if (! class_exists($concrete)) {
            throw new InvalidArgumentException(
                sprintf('Class [%s] does not exist.', $concrete)
            );
        }

        return static fn (Container $container): object => new $concrete();
    }
}
