# Changelog

All notable changes to the BMN Boston Platform v2 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Phase 0: Project infrastructure setup
- WordPress Docker environment (WordPress + MySQL + phpMyAdmin + Mailhog)
- bmn-platform mu-plugin skeleton with PSR-4 autoloader and DI container
- Plugin skeletons for all 10 domain plugins
- iOS Xcode project skeleton (SwiftUI)
- Vite build system for bmn-theme
- PHP_CodeSniffer configuration with custom rules
- SwiftLint configuration
- PHPUnit configuration with Docker MySQL
- GitHub Actions CI pipeline (ci.yml, deploy-staging.yml, deploy-production.yml)
- OpenAPI spec skeleton
- Architecture Decision Records (ADR-001 through ADR-005)
- Project documentation (CLAUDE.md, REBUILD_PROGRESS.md, pitfall-mapping.md)
