# ADR-005: Vite Build System for Frontend Assets

## Status
Accepted

## Context
v1 uses a mix of raw CSS/JS files and a minimal Webpack build. Hot module replacement doesn't work, build times are slow, and there's no TypeScript support. The frontend tooling needs modernization.

## Decision
Use Vite as the build system for the WordPress theme and plugin assets:
- TypeScript for all new JavaScript
- SCSS for stylesheets
- HMR (Hot Module Replacement) in development
- Optimized builds with manifest.json for production
- WordPress integration via custom `bmn_vite_asset()` helper

## Consequences
- **Positive:** Fast dev server with HMR. TypeScript catches errors at build time.
- **Positive:** Modern tooling that the broader ecosystem supports.
- **Negative:** Requires Node.js in the development environment.
- **Mitigated by:** Docker dev environment includes Node.js. CI handles builds.

## Alternatives Considered
1. **Webpack** - Slower, more complex configuration.
2. **No build system** - No TypeScript, no SCSS, no HMR. Too limiting.
3. **WordPress Scripts (@wordpress/scripts)** - Too opinionated for custom theme work.
