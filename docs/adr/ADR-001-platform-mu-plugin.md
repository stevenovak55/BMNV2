# ADR-001: Platform as Must-Use Plugin

## Status
Accepted

## Context
The v1 system has shared infrastructure scattered across 6 plugins, leading to load-order bugs and circular dependencies. Services like authentication, caching, email, and database utilities are needed by every plugin but have no guaranteed load order.

## Decision
Create a single must-use plugin (`bmn-platform`) that provides all shared infrastructure. MU-plugins load before regular plugins, guaranteeing availability.

## Consequences
- **Positive:** Guaranteed load order. Single place for shared services. Clean DI container.
- **Positive:** Other plugins declare dependency on platform, not on each other.
- **Negative:** Platform plugin becomes a critical single point. Must be carefully maintained.
- **Mitigated by:** Comprehensive test suite, clear service boundaries within the platform.

## Alternatives Considered
1. **Composer package** - Would require each plugin to bundle it, leading to version conflicts.
2. **WordPress framework plugin** - No guaranteed load order, same problem as v1.
3. **Functions in theme** - Themes load after plugins, wrong order.
