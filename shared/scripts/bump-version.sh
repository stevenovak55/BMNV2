#!/usr/bin/env bash
#
# BMN Boston v2 - Version Bump Script
#
# Updates version numbers across a plugin's files:
#   - Main plugin PHP file (header comment + constant)
#   - composer.json (if it has a version field)
#   - CHANGELOG.md (adds new version header)
#
# Usage:
#   ./bump-version.sh <component> <new-version>
#
# Examples:
#   ./bump-version.sh bmn-properties 2.1.0
#   ./bump-version.sh bmn-platform 2.0.1
#   ./bump-version.sh bmn-schools 2.3.0
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

COMPONENT="${1:-}"
NEW_VERSION="${2:-}"

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

# Colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Track what was changed.
CHANGES=()

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

usage() {
    echo "Usage: $0 <component> <new-version>"
    echo ""
    echo "Components:"
    echo "  bmn-platform      (mu-plugin)"
    echo "  bmn-properties"
    echo "  bmn-users"
    echo "  bmn-schools"
    echo "  bmn-appointments"
    echo "  bmn-agents"
    echo "  bmn-extractor"
    echo "  bmn-exclusive"
    echo "  bmn-cma"
    echo "  bmn-analytics"
    echo "  bmn-flip"
    echo ""
    echo "Examples:"
    echo "  $0 bmn-properties 2.1.0"
    echo "  $0 bmn-platform 2.0.1"
    exit 1
}

validate_version() {
    local version="$1"
    # Accept versions like 2.1.0, 2.1.0-beta, 2.1.0-dev, 2.1.0-rc.1
    if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
        echo -e "${RED}Invalid version format: ${version}${NC}"
        echo "Expected format: MAJOR.MINOR.PATCH (e.g. 2.1.0) or MAJOR.MINOR.PATCH-SUFFIX (e.g. 2.1.0-beta)"
        exit 1
    fi
}

resolve_plugin_dir() {
    local component="$1"
    local plugin_dir

    if [[ "$component" == "bmn-platform" ]]; then
        plugin_dir="${PROJECT_ROOT}/wordpress/wp-content/mu-plugins/bmn-platform"
    else
        plugin_dir="${PROJECT_ROOT}/wordpress/wp-content/plugins/${component}"
    fi

    if [[ ! -d "$plugin_dir" ]]; then
        echo -e "${RED}Plugin directory not found: ${plugin_dir}${NC}"
        exit 1
    fi

    echo "$plugin_dir"
}

# ---------------------------------------------------------------------------
# Version Update Functions
# ---------------------------------------------------------------------------

update_plugin_php() {
    local plugin_dir="$1"
    local component="$2"
    local new_version="$3"
    local php_file="${plugin_dir}/${component}.php"

    if [[ ! -f "$php_file" ]]; then
        echo -e "${YELLOW}  Plugin PHP file not found: ${php_file}${NC}"
        echo -e "${YELLOW}  Skipping PHP header update.${NC}"
        return
    fi

    # Update the "Version:" line in the plugin header comment.
    if grep -q "^ \* Version:" "$php_file"; then
        local old_version
        old_version=$(grep "^ \* Version:" "$php_file" | sed 's/.*Version: *//' | tr -d '[:space:]')
        sed -i '' "s/^ \* Version: .*/ * Version: ${new_version}/" "$php_file"
        CHANGES+=("${php_file}: header Version ${old_version} -> ${new_version}")
    fi

    # Update the version constant (e.g. BMN_PROPERTIES_VERSION).
    local constant_prefix
    constant_prefix=$(echo "$component" | tr '[:lower:]-' '[:upper:]_')
    local constant_name="${constant_prefix}_VERSION"

    if grep -q "define('${constant_name}'" "$php_file"; then
        local old_const_version
        old_const_version=$(grep "define('${constant_name}'" "$php_file" | sed "s/.*define('${constant_name}', '//" | sed "s/'.*//" )
        sed -i '' "s/define('${constant_name}', '.*')/define('${constant_name}', '${new_version}')/" "$php_file"
        CHANGES+=("${php_file}: ${constant_name} ${old_const_version} -> ${new_version}")
    fi
}

update_composer_json() {
    local plugin_dir="$1"
    local new_version="$2"
    local composer_file="${plugin_dir}/composer.json"

    if [[ ! -f "$composer_file" ]]; then
        echo -e "${YELLOW}  composer.json not found: ${composer_file}${NC}"
        echo -e "${YELLOW}  Skipping composer.json update.${NC}"
        return
    fi

    # Only update if there is already a "version" field.
    if grep -q '"version"' "$composer_file"; then
        local old_version
        old_version=$(grep '"version"' "$composer_file" | sed 's/.*"version": *"//' | sed 's/".*//')
        sed -i '' "s/\"version\": *\"[^\"]*\"/\"version\": \"${new_version}\"/" "$composer_file"
        CHANGES+=("${composer_file}: version ${old_version} -> ${new_version}")
    else
        # Add version field after the "description" line.
        if grep -q '"description"' "$composer_file"; then
            sed -i '' "/\"description\":/a\\
\\    \"version\": \"${new_version}\",
" "$composer_file"
            CHANGES+=("${composer_file}: added version ${new_version}")
        else
            echo -e "${YELLOW}  Could not find a suitable place to add version in composer.json.${NC}"
            echo -e "${YELLOW}  Add manually: \"version\": \"${new_version}\"${NC}"
        fi
    fi
}

update_changelog() {
    local plugin_dir="$1"
    local component="$2"
    local new_version="$3"
    local changelog_file="${plugin_dir}/CHANGELOG.md"
    local today
    today=$(date '+%Y-%m-%d')

    if [[ ! -f "$changelog_file" ]]; then
        # Create a new CHANGELOG.md.
        cat > "$changelog_file" <<CHANGELOG_EOF
# Changelog

All notable changes to the ${component} plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [${new_version}] - ${today}

### Changed
- Version bump to ${new_version}
CHANGELOG_EOF
        CHANGES+=("${changelog_file}: created with version ${new_version}")
        return
    fi

    # Insert the new version header after the first "## " line or after the preamble.
    if grep -q "^## \[" "$changelog_file"; then
        # Insert before the first version entry.
        local new_entry
        new_entry="## [${new_version}] - ${today}\\
\\
### Changed\\
- Version bump to ${new_version}\\
"
        sed -i '' "0,/^## \[/s/^## \[/${new_entry}\\
## [/" "$changelog_file"
        CHANGES+=("${changelog_file}: added version ${new_version} entry")
    else
        # No existing version entries; append to end of file.
        cat >> "$changelog_file" <<APPEND_EOF

## [${new_version}] - ${today}

### Changed
- Version bump to ${new_version}
APPEND_EOF
        CHANGES+=("${changelog_file}: appended version ${new_version} entry")
    fi
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

if [[ -z "$COMPONENT" || -z "$NEW_VERSION" ]]; then
    usage
fi

validate_version "$NEW_VERSION"

PLUGIN_DIR="$(resolve_plugin_dir "$COMPONENT")"

echo ""
echo -e "${BOLD}============================================${NC}"
echo -e "${BOLD}  Version Bump: ${COMPONENT}${NC}"
echo -e "${BOLD}============================================${NC}"
echo -e "  Component:   ${COMPONENT}"
echo -e "  New version: ${NEW_VERSION}"
echo -e "  Plugin dir:  ${PLUGIN_DIR}"
echo -e "${BOLD}============================================${NC}"
echo ""

# Run updates.
echo -e "${CYAN}Updating plugin PHP file...${NC}"
update_plugin_php "$PLUGIN_DIR" "$COMPONENT" "$NEW_VERSION"

echo -e "${CYAN}Updating composer.json...${NC}"
update_composer_json "$PLUGIN_DIR" "$NEW_VERSION"

echo -e "${CYAN}Updating CHANGELOG.md...${NC}"
update_changelog "$PLUGIN_DIR" "$COMPONENT" "$NEW_VERSION"

# Print summary.
echo ""
echo -e "${BOLD}--- Changes Made ---${NC}"
echo ""
if [[ ${#CHANGES[@]} -eq 0 ]]; then
    echo -e "${YELLOW}  No changes were made. Check that the plugin files exist.${NC}"
else
    for change in "${CHANGES[@]}"; do
        echo -e "  ${GREEN}*${NC} ${change}"
    done
fi
echo ""
echo -e "${BOLD}Next steps:${NC}"
echo "  1. Review the changes: git diff"
echo "  2. Update the CHANGELOG.md with actual release notes"
echo "  3. Commit: git commit -am 'Bump ${COMPONENT} to ${NEW_VERSION}'"
echo "  4. Tag: git tag -a ${COMPONENT}/v${NEW_VERSION} -m '${COMPONENT} v${NEW_VERSION}'"
echo ""
