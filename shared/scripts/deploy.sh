#!/usr/bin/env bash
#
# BMN Boston v2 - Deployment Script
#
# Deploys the BMN Boston v2 application to staging or production.
#
# Usage:
#   ./deploy.sh staging
#   ./deploy.sh production --confirm
#
# Safety checks:
#   - Production deployments require the --confirm flag
#   - Production deployments must be run from the main branch
#   - Production deployments require a git tag on the current commit
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

ENVIRONMENT="${1:-}"
CONFIRM_FLAG="${2:-}"

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

# Colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Deployment targets (placeholders -- update with real values).
# STAGING_HOST="staging.bmnboston.com"
# STAGING_USER="deploy"
# STAGING_PATH="/var/www/bmnboston-staging"

# PROD_SERVER1="prod1.bmnboston.com"
# PROD_SERVER2="prod2.bmnboston.com"
# PROD_USER="deploy"
# PROD_PATH="/var/www/bmnboston"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

log_step() {
    echo -e "${CYAN}[STEP]${NC} $1"
}

log_ok() {
    echo -e "${GREEN}[  OK]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[FAIL]${NC} $1"
}

usage() {
    echo "Usage: $0 <environment> [--confirm]"
    echo ""
    echo "  environment   staging | production"
    echo "  --confirm     Required for production deployments"
    echo ""
    echo "Examples:"
    echo "  $0 staging"
    echo "  $0 production --confirm"
    exit 1
}

get_current_branch() {
    git -C "$PROJECT_ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown"
}

get_current_tag() {
    git -C "$PROJECT_ROOT" describe --tags --exact-match HEAD 2>/dev/null || echo ""
}

get_current_commit() {
    git -C "$PROJECT_ROOT" rev-parse --short HEAD 2>/dev/null || echo "unknown"
}

check_clean_working_tree() {
    if ! git -C "$PROJECT_ROOT" diff --quiet HEAD 2>/dev/null; then
        log_error "Working tree has uncommitted changes. Commit or stash before deploying."
        exit 1
    fi
}

# ---------------------------------------------------------------------------
# Staging Deployment
# ---------------------------------------------------------------------------

deploy_staging() {
    echo ""
    echo -e "${BOLD}========================================${NC}"
    echo -e "${BOLD}  Deploying to STAGING${NC}"
    echo -e "${BOLD}========================================${NC}"
    echo -e "  Branch: $(get_current_branch)"
    echo -e "  Commit: $(get_current_commit)"
    echo -e "  Time:   $(date '+%Y-%m-%d %H:%M:%S')"
    echo -e "${BOLD}========================================${NC}"
    echo ""

    # Step 1: Build
    log_step "Building application..."
    # TODO: Implement build steps
    # cd "$PROJECT_ROOT/wordpress/wp-content/themes/bmn-theme" && npm run build
    # cd "$PROJECT_ROOT/wordpress/wp-content/plugins/bmn-properties" && composer install --no-dev
    sleep 1
    log_ok "Build complete (placeholder)."

    # Step 2: Sync files to staging server
    log_step "Syncing files to staging server..."
    # TODO: Implement rsync
    # rsync -avz --delete \
    #     --exclude=".git" \
    #     --exclude="node_modules" \
    #     --exclude=".env" \
    #     --exclude="docker-compose*.yml" \
    #     "$PROJECT_ROOT/wordpress/" \
    #     "${STAGING_USER}@${STAGING_HOST}:${STAGING_PATH}/"
    sleep 1
    log_ok "File sync complete (placeholder)."

    # Step 3: Run remote post-deploy tasks
    log_step "Running post-deploy tasks on staging..."
    # TODO: Run migrations, clear caches, etc.
    # ssh "${STAGING_USER}@${STAGING_HOST}" "cd ${STAGING_PATH} && wp cache flush && wp rewrite flush"
    sleep 1
    log_ok "Post-deploy tasks complete (placeholder)."

    # Step 4: Verify
    log_step "Verifying staging deployment..."
    # TODO: Run health checks
    # curl -sf "https://staging.bmnboston.com/wp-json/bmn/v1/properties?per_page=1" > /dev/null
    sleep 1
    log_ok "Staging verification complete (placeholder)."

    echo ""
    echo -e "${GREEN}Staging deployment complete.${NC}"
    echo ""
}

# ---------------------------------------------------------------------------
# Production Deployment
# ---------------------------------------------------------------------------

deploy_production() {
    local branch
    branch="$(get_current_branch)"

    local tag
    tag="$(get_current_tag)"

    echo ""
    echo -e "${BOLD}========================================${NC}"
    echo -e "${RED}  Deploying to PRODUCTION${NC}"
    echo -e "${BOLD}========================================${NC}"
    echo -e "  Branch: ${branch}"
    echo -e "  Tag:    ${tag:-none}"
    echo -e "  Commit: $(get_current_commit)"
    echo -e "  Time:   $(date '+%Y-%m-%d %H:%M:%S')"
    echo -e "${BOLD}========================================${NC}"
    echo ""

    # Safety check: must be on main branch.
    if [[ "$branch" != "main" ]]; then
        log_error "Production deployments must be run from the 'main' branch."
        log_error "Current branch: ${branch}"
        exit 1
    fi

    # Safety check: must have a git tag.
    if [[ -z "$tag" ]]; then
        log_error "Production deployments require a git tag on the current commit."
        log_error "Create a tag first: git tag -a v2.x.x -m 'Release v2.x.x'"
        exit 1
    fi

    # Safety check: clean working tree.
    check_clean_working_tree

    # Step 1: Backup
    log_step "Backing up production database..."
    # TODO: Implement database backup
    # ssh "${PROD_USER}@${PROD_SERVER1}" \
    #     "mysqldump -u root bmnboston_prod | gzip > /backups/bmnboston_pre_${tag}_$(date +%Y%m%d_%H%M%S).sql.gz"
    sleep 1
    log_ok "Database backup complete (placeholder)."

    # Step 2: Build
    log_step "Building application for production..."
    # TODO: Implement production build
    # cd "$PROJECT_ROOT/wordpress/wp-content/themes/bmn-theme" && npm run build
    # for plugin in "$PROJECT_ROOT"/wordpress/wp-content/plugins/bmn-*; do
    #     cd "$plugin" && composer install --no-dev --optimize-autoloader
    # done
    sleep 1
    log_ok "Production build complete (placeholder)."

    # Step 3: Deploy to server 1
    log_step "Deploying to production server 1..."
    # TODO: Implement deployment to server 1
    # rsync -avz --delete \
    #     --exclude=".git" \
    #     --exclude="node_modules" \
    #     --exclude=".env" \
    #     --exclude="docker-compose*.yml" \
    #     "$PROJECT_ROOT/wordpress/" \
    #     "${PROD_USER}@${PROD_SERVER1}:${PROD_PATH}/"
    # ssh "${PROD_USER}@${PROD_SERVER1}" "cd ${PROD_PATH} && wp cache flush"
    sleep 1
    log_ok "Server 1 deployed (placeholder)."

    # Step 4: Verify server 1
    log_step "Verifying server 1..."
    # TODO: Health check server 1
    # if ! curl -sf "https://prod1.bmnboston.com/wp-json/bmn/v1/properties?per_page=1" > /dev/null; then
    #     log_error "Server 1 health check failed! Rolling back..."
    #     rollback_production
    #     exit 1
    # fi
    sleep 1
    log_ok "Server 1 verified (placeholder)."

    # Step 5: Deploy to server 2
    log_step "Deploying to production server 2..."
    # TODO: Implement deployment to server 2
    # rsync -avz --delete \
    #     --exclude=".git" \
    #     --exclude="node_modules" \
    #     --exclude=".env" \
    #     --exclude="docker-compose*.yml" \
    #     "$PROJECT_ROOT/wordpress/" \
    #     "${PROD_USER}@${PROD_SERVER2}:${PROD_PATH}/"
    # ssh "${PROD_USER}@${PROD_SERVER2}" "cd ${PROD_PATH} && wp cache flush"
    sleep 1
    log_ok "Server 2 deployed (placeholder)."

    # Step 6: Final verification
    log_step "Running final production verification..."
    # TODO: Full health check across both servers
    # curl -sf "https://bmnboston.com/wp-json/bmn/v1/properties?per_page=1" > /dev/null
    # curl -sf "https://bmnboston.com/wp-json/bmn/v1/schools" > /dev/null
    sleep 1
    log_ok "Production verification complete (placeholder)."

    echo ""
    echo -e "${GREEN}Production deployment of ${tag} complete.${NC}"
    echo ""
}

rollback_production() {
    log_warn "Rolling back production deployment..."
    # TODO: Implement rollback
    # ssh "${PROD_USER}@${PROD_SERVER1}" "cd ${PROD_PATH} && git checkout HEAD~1"
    # ssh "${PROD_USER}@${PROD_SERVER2}" "cd ${PROD_PATH} && git checkout HEAD~1"
    # ssh "${PROD_USER}@${PROD_SERVER1}" "cd ${PROD_PATH} && wp cache flush"
    # ssh "${PROD_USER}@${PROD_SERVER2}" "cd ${PROD_PATH} && wp cache flush"
    log_warn "Rollback complete (placeholder). Verify manually!"
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

if [[ -z "$ENVIRONMENT" ]]; then
    log_error "Environment argument is required."
    usage
fi

case "$ENVIRONMENT" in
    staging)
        deploy_staging
        ;;
    production)
        if [[ "$CONFIRM_FLAG" != "--confirm" ]]; then
            log_error "Production deployment requires the --confirm flag."
            echo ""
            echo "  This will deploy to the LIVE production servers."
            echo "  If you are sure, run:"
            echo ""
            echo "    $0 production --confirm"
            echo ""
            exit 1
        fi
        deploy_production
        ;;
    *)
        log_error "Unknown environment: ${ENVIRONMENT}"
        usage
        ;;
esac
