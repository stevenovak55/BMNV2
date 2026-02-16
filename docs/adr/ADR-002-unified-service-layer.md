# ADR-002: Unified Service Layer (One Service, Two Interfaces)

## Status
Accepted

## Context
The v1 system has duplicated business logic: `class-mld-mobile-rest-api.php` (7,500 lines) for iOS and `class-mld-query.php` (2,400 lines) for web. Features implemented in one path are often missing or different in the other. Bug fixes must be applied twice.

## Decision
Every feature has ONE service class that contains all business logic. REST controllers (for iOS) and AJAX/template handlers (for web) are thin wrappers that call the service.

```
iOS  -> REST Controller  -\
                            -> Service -> Repository -> Database
Web  -> AJAX Handler     -/
```

## Consequences
- **Positive:** Single source of truth for business logic. Fix once, fixed everywhere.
- **Positive:** Services are independently testable without HTTP layer.
- **Negative:** Requires discipline to keep controllers thin.
- **Mitigated by:** Code review, max 300 lines per controller rule, linting.

## Alternatives Considered
1. **Keep dual paths** - Rejected. Root cause of most v1 bugs.
2. **GraphQL** - Considered but adds complexity. REST with unified services achieves the same goal.
