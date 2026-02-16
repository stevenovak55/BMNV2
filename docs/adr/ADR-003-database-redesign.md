# ADR-003: Consolidated Property Table Design

## Status
Accepted

## Context
v1 uses 6 normalized tables for active properties and 6 archive tables (12 total): `bme_listing_summary`, `bme_listings`, `bme_listing_details`, `bme_listing_location`, `bme_listing_financial`, `bme_listing_features`, plus archive variants. Every property query requires 5-6 JOINs, causing performance issues and query complexity.

## Decision
Consolidate to 2 tables: `bmn_properties` (active) and `bmn_properties_archive` (sold/closed/expired). One wide table with all commonly-queried columns. Use JSON columns for flexible data (features, amenities) that doesn't need WHERE clause filtering.

## Consequences
- **Positive:** Single-table queries for search (no JOINs). Dramatically simpler SQL.
- **Positive:** Active/archive separation preserved (proven performance pattern).
- **Positive:** JSON columns for rarely-filtered data keeps table width manageable.
- **Negative:** Wide table with 80+ columns.
- **Mitigated by:** Proper indexing, SELECT only needed columns, summary projections.

## Alternatives Considered
1. **Keep normalized** - Rejected. JOIN performance is the #1 v1 complaint.
2. **NoSQL (MongoDB)** - Considered. WordPress ecosystem makes this impractical.
3. **Materialized view** - MySQL doesn't support true materialized views natively.
