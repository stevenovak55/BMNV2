<?php

declare(strict_types=1);

namespace BMN\Platform\Core;

/**
 * Abstract base for all BMN service providers.
 *
 * Service providers are the central place to configure and bind services
 * into the DI container. Each plugin or subsystem should ship one or more
 * providers.
 *
 * Lifecycle:
 *   1. register() -- called first for every provider (bind services).
 *   2. boot()     -- called after *all* providers have registered
 *                    (safe to resolve cross-provider dependencies).
 */
abstract class ServiceProvider
{
    /**
     * Register bindings into the container.
     *
     * This method is called before any provider is booted, so you must NOT
     * resolve services from other providers here.
     */
    abstract public function register(Container $container): void;

    /**
     * Boot the service provider.
     *
     * Called after every provider's register() has run. Use this method to
     * wire up event listeners, register REST routes, or perform any work
     * that depends on other services being available in the container.
     */
    abstract public function boot(Container $container): void;
}
