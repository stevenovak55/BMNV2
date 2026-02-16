<?php

declare(strict_types=1);

namespace BMN\Platform\Core;

use BMN\Platform\Providers\PlatformServiceProvider;
use RuntimeException;

/**
 * Application bootstrap.
 *
 * Holds the DI container, manages service-provider registration, and
 * orchestrates the two-phase boot lifecycle (register, then boot).
 *
 * Access the singleton via Application::getInstance().
 */
final class Application
{
    private static ?self $instance = null;

    private readonly Container $container;

    /** @var ServiceProvider[] Registered providers. */
    private array $providers = [];

    private bool $booted = false;

    /**
     * Service provider class names to register during boot.
     *
     * Add platform-level providers here. Plugin-specific providers should
     * be registered via Application::register() from the plugin bootstrap.
     *
     * @var list<class-string<ServiceProvider>>
     */
    private array $coreProviders = [
        PlatformServiceProvider::class,
    ];

    private function __construct()
    {
        $this->container = new Container();
        // Make the container resolvable from itself.
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
    }

    /**
     * Return the singleton application instance, creating it on first call.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the DI container.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Register an additional service provider.
     *
     * If the application has already booted, the provider will be registered
     * and booted immediately.
     *
     * @param ServiceProvider|class-string<ServiceProvider> $provider Instance or FQCN.
     */
    public function register(ServiceProvider|string $provider): void
    {
        if (is_string($provider)) {
            if (! class_exists($provider)) {
                throw new RuntimeException(
                    sprintf('Service provider class [%s] does not exist.', $provider)
                );
            }
            $provider = new $provider();
        }

        $provider->register($this->container);
        $this->providers[] = $provider;

        // Late-registered provider: boot immediately if app already booted.
        if ($this->booted) {
            $provider->boot($this->container);
        }
    }

    /**
     * Boot the application.
     *
     * 1. Registers all core providers.
     * 2. Calls boot() on every registered provider.
     *
     * This method is idempotent -- calling it multiple times has no effect.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Phase 1: Register all core providers.
        foreach ($this->coreProviders as $providerClass) {
            $this->register($providerClass);
        }

        // Phase 2: Boot all providers.
        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;
    }

    /**
     * Whether the application has finished booting.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Reset the singleton (for testing only).
     *
     * @internal
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
