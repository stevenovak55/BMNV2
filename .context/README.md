# BMN Boston v2 - Context Directory

## Contents

| File | Purpose |
|------|---------|
| sessions/latest-session.md | Last session handoff document |

## Session Protocol

### At Session Start
1. Read `/CLAUDE.md` - project context and current phase
2. Read `/docs/REBUILD_PROGRESS.md` - what's done
3. Read `.context/sessions/latest-session.md` - last session handoff

### At Session End
1. Update `/CLAUDE.md` current phase and active work
2. Write `.context/sessions/latest-session.md` with handoff details
3. Commit and push all changes
