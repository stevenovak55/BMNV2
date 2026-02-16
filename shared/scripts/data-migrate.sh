#!/usr/bin/env bash
#
# BMN Boston v2 - Data Migration Script (v1 -> v2)
#
# WARNING: This script is planned for Phase 13 of the rebuild.
# It is a skeleton with placeholder functions. Do NOT run against
# production databases until every function is fully implemented
# and tested.
#
# Usage:
#   ./data-migrate.sh [--dry-run]
#
# Options:
#   --dry-run   Preview what would be migrated without making changes
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

DRY_RUN=false

for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        --help|-h)
            echo "Usage: $0 [--dry-run]"
            echo ""
            echo "Options:"
            echo "  --dry-run   Preview migration without making changes"
            echo "  --help      Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown argument: $arg"
            echo "Usage: $0 [--dry-run]"
            exit 1
            ;;
    esac
done

# Source and target database configuration (placeholders).
# V1_DB_HOST="localhost"
# V1_DB_NAME="bmnboston_v1"
# V1_DB_USER="root"
# V1_DB_PASS=""

# V2_DB_HOST="localhost"
# V2_DB_NAME="bmnboston_v2"
# V2_DB_USER="root"
# V2_DB_PASS=""

# Colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Migration step tracking.
STEP=0
STEP_TOTAL=8

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

log_step() {
    STEP=$((STEP + 1))
    echo ""
    echo -e "${CYAN}[${STEP}/${STEP_TOTAL}]${NC} ${BOLD}$1${NC}"
}

log_ok() {
    echo -e "  ${GREEN}OK:${NC} $1"
}

log_info() {
    echo -e "  ${CYAN}INFO:${NC} $1"
}

log_warn() {
    echo -e "  ${YELLOW}WARN:${NC} $1"
}

log_error() {
    echo -e "  ${RED}ERROR:${NC} $1"
}

dry_run_notice() {
    if $DRY_RUN; then
        echo -e "  ${YELLOW}[DRY RUN]${NC} No changes will be made."
    fi
}

# ---------------------------------------------------------------------------
# Migration Functions
# ---------------------------------------------------------------------------

backup_v1_database() {
    log_step "Backing up v1 database"
    dry_run_notice

    # TODO: Phase 13 - Implement v1 database backup
    #
    # Steps:
    #   1. Create a timestamped backup of the v1 database
    #   2. Verify the backup file is valid
    #   3. Store backup path for potential rollback
    #
    # Example:
    #   BACKUP_FILE="/backups/bmnboston_v1_pre_migration_$(date +%Y%m%d_%H%M%S).sql.gz"
    #   mysqldump -h "$V1_DB_HOST" -u "$V1_DB_USER" -p"$V1_DB_PASS" \
    #       "$V1_DB_NAME" | gzip > "$BACKUP_FILE"
    #   log_ok "Backup saved to $BACKUP_FILE"

    log_info "TODO: Implement v1 database backup"
}

migrate_properties() {
    log_step "Migrating properties"
    dry_run_notice

    # TODO: Phase 13 - Migrate property data from v1 to v2
    #
    # Source tables (v1):
    #   - bme_listing_summary (main property data)
    #   - bme_listing_photos (property photos)
    #   - bme_listing_details (extended details)
    #
    # Target tables (v2):
    #   - bmn_properties (clean schema)
    #   - bmn_property_photos
    #   - bmn_property_details
    #
    # Key transformations:
    #   - listing_key -> retained for reference but listing_id (MLS#) is primary
    #   - Flatten nested JSON fields into proper columns
    #   - Normalize address components
    #   - Recalculate geo coordinates where missing
    #   - Map status values to new enum set
    #
    # IMPORTANT: Use listing_id (MLS number) as the canonical identifier,
    #            NOT listing_key (hash). See CLAUDE.md rule #4.

    log_info "TODO: Implement property migration"
    log_info "Expected row count: ~15,000 active + ~50,000 historical"
}

migrate_users() {
    log_step "Migrating users"
    dry_run_notice

    # TODO: Phase 13 - Migrate user accounts from v1 to v2
    #
    # Source tables (v1):
    #   - wp_users
    #   - wp_usermeta (custom fields: phone, agent_id, preferences)
    #
    # Target tables (v2):
    #   - wp_users (standard WordPress)
    #   - bmn_user_profiles (extended profile data)
    #   - bmn_user_preferences (notification settings, search defaults)
    #
    # Key transformations:
    #   - Preserve password hashes (same WordPress hashing)
    #   - Extract usermeta into structured bmn_user_profiles rows
    #   - Map user roles to v2 role system
    #   - Preserve user registration dates

    log_info "TODO: Implement user migration"
    log_info "Expected row count: ~5,000 users"
}

migrate_favorites() {
    log_step "Migrating favorites"
    dry_run_notice

    # TODO: Phase 13 - Migrate user favorites/saved properties
    #
    # Source (v1):
    #   - wp_usermeta key 'mld_favorites' (serialized PHP array of listing_keys)
    #
    # Target (v2):
    #   - bmn_favorites (user_id, property_id, created_at)
    #
    # Key transformations:
    #   - Unserialize PHP array from usermeta
    #   - Map listing_key to listing_id via v1 lookup
    #   - Resolve listing_id to v2 property_id
    #   - Skip favorites for properties that no longer exist (log them)

    log_info "TODO: Implement favorites migration"
    log_info "Expected: ~2,000 users with favorites"
}

migrate_saved_searches() {
    log_step "Migrating saved searches"
    dry_run_notice

    # TODO: Phase 13 - Migrate saved searches
    #
    # Source (v1):
    #   - wp_usermeta key 'mld_saved_searches' (serialized PHP array)
    #
    # Target (v2):
    #   - bmn_saved_searches (user_id, name, criteria JSON, frequency, created_at)
    #
    # Key transformations:
    #   - Unserialize PHP array from usermeta
    #   - Map v1 filter keys to v2 filter schema
    #   - Convert price ranges, bedroom counts, etc. to new format
    #   - Preserve notification frequency settings

    log_info "TODO: Implement saved searches migration"
    log_info "Expected: ~1,500 saved searches"
}

migrate_schools() {
    log_step "Migrating schools"
    dry_run_notice

    # TODO: Phase 13 - Migrate school data
    #
    # Source (v1):
    #   - bmn_schools (school data with rankings)
    #   - bmn_school_rankings (year-over-year ranking data)
    #
    # Target (v2):
    #   - bmn_schools (clean schema)
    #   - bmn_school_rankings (normalized)
    #
    # Key transformations:
    #   - Preserve all historical ranking data
    #   - Normalize grade level categorization
    #   - Validate geo coordinates
    #   - IMPORTANT: Use MAX(year) for latest data, never date('Y')
    #     (see CLAUDE.md rule #2 - Year Rollover)

    log_info "TODO: Implement school data migration"
    log_info "Expected: ~400 schools with ~5 years of ranking data"
}

migrate_appointments() {
    log_step "Migrating appointments"
    dry_run_notice

    # TODO: Phase 13 - Migrate appointment history
    #
    # Source (v1):
    #   - sn_appointments (appointment records)
    #   - sn_appointment_meta (extra fields)
    #
    # Target (v2):
    #   - bmn_appointments (clean schema)
    #
    # Key transformations:
    #   - Map user IDs to v2 user IDs
    #   - Map listing references to v2 property IDs via listing_id
    #   - Preserve all timestamps using proper timezone handling
    #   - IMPORTANT: Use current_time('timestamp') not time()
    #     (see CLAUDE.md rule #3 - Timezone)
    #   - Only migrate appointments from last 2 years (configurable)

    log_info "TODO: Implement appointment migration"
    log_info "Expected: ~3,000 appointments (last 2 years)"
}

verify_migration() {
    log_step "Verifying migration"
    dry_run_notice

    # TODO: Phase 13 - Verify data integrity after migration
    #
    # Verification checks:
    #   1. Row counts match (with tolerance for skipped records)
    #   2. Spot-check 100 random properties for data accuracy
    #   3. Verify all user accounts can authenticate
    #   4. Verify favorites reference valid properties
    #   5. Verify saved search criteria is valid JSON
    #   6. Verify school rankings have correct year data
    #   7. Verify appointment timestamps are in correct timezone
    #   8. Run API regression tests against migrated data
    #
    # Example:
    #   V1_PROPS=$(mysql -h "$V1_DB_HOST" -u "$V1_DB_USER" -p"$V1_DB_PASS" \
    #       "$V1_DB_NAME" -sNe "SELECT COUNT(*) FROM bme_listing_summary WHERE status='Active'")
    #   V2_PROPS=$(mysql -h "$V2_DB_HOST" -u "$V2_DB_USER" -p"$V2_DB_PASS" \
    #       "$V2_DB_NAME" -sNe "SELECT COUNT(*) FROM bmn_properties WHERE status='active'")
    #   if [[ "$V1_PROPS" -ne "$V2_PROPS" ]]; then
    #       log_error "Property count mismatch: v1=$V1_PROPS v2=$V2_PROPS"
    #   fi

    log_info "TODO: Implement migration verification"
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

echo ""
echo -e "${BOLD}============================================${NC}"
echo -e "${RED}  BMN Boston v2 - Data Migration (v1 -> v2)${NC}"
echo -e "${BOLD}============================================${NC}"
echo ""
echo -e "${YELLOW}  WARNING: This migration script is planned for Phase 13.${NC}"
echo -e "${YELLOW}  All functions are currently placeholders with TODO comments.${NC}"
echo -e "${YELLOW}  Do NOT run this against production until fully implemented.${NC}"
echo ""
if $DRY_RUN; then
    echo -e "${CYAN}  Mode: DRY RUN (no changes will be made)${NC}"
else
    echo -e "${RED}  Mode: LIVE (changes WILL be applied)${NC}"
fi
echo ""
echo -e "${BOLD}============================================${NC}"

# Run all migration steps in order.
backup_v1_database
migrate_properties
migrate_users
migrate_favorites
migrate_saved_searches
migrate_schools
migrate_appointments
verify_migration

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo ""
echo -e "${BOLD}============================================${NC}"
echo -e "${BOLD}  Migration Complete${NC}"
echo -e "${BOLD}============================================${NC}"
echo ""
if $DRY_RUN; then
    echo -e "${CYAN}  This was a dry run. No data was modified.${NC}"
else
    echo -e "${GREEN}  All migration steps executed.${NC}"
fi
echo ""

# ---------------------------------------------------------------------------
# Rollback Instructions
# ---------------------------------------------------------------------------

echo -e "${BOLD}--- Rollback Instructions ---${NC}"
echo ""
echo "  If the migration needs to be rolled back:"
echo ""
echo "  1. Stop the v2 application:"
echo "     docker-compose -f ~/Development/BMNBoston-v2/wordpress/docker-compose.yml down"
echo ""
echo "  2. Restore the v2 database from the pre-migration backup:"
echo "     gunzip < /backups/bmnboston_v2_pre_migration_TIMESTAMP.sql.gz | mysql -u root bmnboston_v2"
echo ""
echo "  3. The v1 database is untouched by this migration (read-only source)."
echo "     No v1 rollback is needed."
echo ""
echo "  4. Restart the v2 application:"
echo "     docker-compose -f ~/Development/BMNBoston-v2/wordpress/docker-compose.yml up -d"
echo ""
echo "  5. Verify v2 is back to pre-migration state:"
echo "     ./shared/scripts/api-regression-test.sh"
echo ""
