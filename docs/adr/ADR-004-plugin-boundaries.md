# ADR-004: Plugin Boundary Definitions

## Status
Accepted

## Context
v1 has 6 plugins with unclear, overlapping responsibilities. The MLS Listings Display plugin handles properties, users, favorites, saved searches, notifications, and more. This makes testing, deployment, and reasoning about the code difficult.

## Decision
10 focused plugins, each owning a single domain:
- `bmn-properties` - Property search, detail, filters
- `bmn-users` - Auth, favorites, saved searches, notifications
- `bmn-schools` - Rankings, data, school pages
- `bmn-appointments` - Booking, calendar
- `bmn-agents` - Agent-client relationships
- `bmn-extractor` - MLS data pipeline
- `bmn-exclusive` - Agent-created listings
- `bmn-cma` - Comparative market analysis
- `bmn-analytics` - Site tracking
- `bmn-flip` - Investment analysis

Cross-plugin communication happens through:
1. WordPress hooks (actions and filters)
2. Service interfaces defined in bmn-platform
3. Direct service calls via the DI container (for tightly-coupled features)

## Consequences
- **Positive:** Each plugin is independently testable and deployable.
- **Positive:** Clear ownership of tables and endpoints.
- **Negative:** More plugins to manage.
- **Mitigated by:** Shared CI pipeline, consistent structure across all plugins.
