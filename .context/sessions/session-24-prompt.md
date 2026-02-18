This is Session 24 for BMN Boston V2, starting Phase 12 (iOS SwiftUI Rebuild).

Read these files first to get full context:
- ~/Development/BMNBoston-v2/CLAUDE.md
- ~/Development/BMNBoston-v2/.context/sessions/latest-session.md

Session 23 summary:
- Verified all 7 remaining QA items for Phase 11f (all passed)
- Closed out Phase 11f (Map Search UX Polish + Clustering)
- Updated CLAUDE.md, REBUILD_PROGRESS.md, wrote session-23 handoff

Phase 11f is COMPLETE. The entire web platform is done:
- Phases 0-10: Backend (platform, data pipeline, properties, users, schools,
  appointments, agents, CMA, analytics, flip analyzer, exclusive listings)
- Phase 11a-f: Theme (foundation, search, detail, remaining pages, QA, map search,
  UX polish, clustering)
- 1,643 tests across 11 plugin suites, all passing
- REST API: 100+ endpoints across /bmn/v1/ namespace
- Theme: Alpine.js + HTMX + Tailwind + Vite, full responsive design

Phase 12: iOS SwiftUI Rebuild
- Build the native iOS app using SwiftUI
- Connect to V2 REST API (/bmn/v1/)
- Reference v1 iOS app at ~/Development/BMNBoston/ios/ (READ-ONLY)
- Reference v1 iOS CLAUDE.md at ~/Development/BMNBoston/ios/CLAUDE.md

Docker environment:
- Start: cd ~/Development/BMNBoston-v2/wordpress && docker-compose up -d
- WordPress: http://localhost:8082 (admin: novak55 / Google44*)
- phpMyAdmin: http://localhost:8083
