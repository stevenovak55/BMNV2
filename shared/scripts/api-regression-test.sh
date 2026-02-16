#!/usr/bin/env bash
#
# BMN Boston v2 - API Regression Test Suite
#
# Tests API endpoints to verify they return expected HTTP status codes.
# Most endpoints will fail initially until implemented -- that is expected.
#
# Usage:
#   ./api-regression-test.sh [BASE_URL]
#
# Examples:
#   ./api-regression-test.sh
#   ./api-regression-test.sh https://staging.bmnboston.com/wp-json/bmn/v1
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

BASE_URL="${1:-http://localhost:8080/wp-json/bmn/v1}"

# Strip trailing slash if present.
BASE_URL="${BASE_URL%/}"

# Counters.
PASSED=0
FAILED=0
TOTAL=0

# Colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

print_header() {
    echo ""
    echo -e "${BOLD}============================================${NC}"
    echo -e "${BOLD}  BMN Boston v2 - API Regression Tests${NC}"
    echo -e "${BOLD}============================================${NC}"
    echo -e "  Base URL: ${CYAN}${BASE_URL}${NC}"
    echo -e "  Date:     $(date '+%Y-%m-%d %H:%M:%S')"
    echo -e "${BOLD}============================================${NC}"
    echo ""
}

# test_endpoint NAME EXPECTED_STATUS METHOD URL [EXTRA_CURL_ARGS...]
#
# Hits the given URL with curl, checks the HTTP status code against the
# expected value, and prints a colored pass/fail line.
test_endpoint() {
    local name="$1"
    local expected_status="$2"
    local method="$3"
    local url="${BASE_URL}$4"
    shift 4
    local extra_args=("$@")

    TOTAL=$((TOTAL + 1))

    # Build the curl command.
    local curl_args=(
        -s
        -o /dev/null
        -w "%{http_code}"
        -X "$method"
        --max-time 10
        --connect-timeout 5
    )

    if [[ ${#extra_args[@]} -gt 0 ]]; then
        curl_args+=("${extra_args[@]}")
    fi

    curl_args+=("$url")

    # Execute the request.
    local actual_status
    actual_status=$(curl "${curl_args[@]}" 2>/dev/null) || actual_status="000"

    # Check if the expected status allows multiple values (e.g. "200|404").
    local match=false
    IFS='|' read -ra EXPECTED_PARTS <<< "$expected_status"
    for part in "${EXPECTED_PARTS[@]}"; do
        if [[ "$actual_status" == "$part" ]]; then
            match=true
            break
        fi
    done

    # Print result.
    if $match; then
        PASSED=$((PASSED + 1))
        echo -e "  ${GREEN}PASS${NC}  ${name}"
        echo -e "        ${method} ${url}"
        echo -e "        Expected: ${expected_status}  Got: ${actual_status}"
    else
        FAILED=$((FAILED + 1))
        echo -e "  ${RED}FAIL${NC}  ${name}"
        echo -e "        ${method} ${url}"
        echo -e "        Expected: ${expected_status}  Got: ${actual_status}"
    fi
    echo ""
}

print_summary() {
    echo -e "${BOLD}============================================${NC}"
    echo -e "${BOLD}  Summary${NC}"
    echo -e "${BOLD}============================================${NC}"
    echo -e "  Total:  ${TOTAL}"
    echo -e "  ${GREEN}Passed: ${PASSED}${NC}"
    echo -e "  ${RED}Failed: ${FAILED}${NC}"
    echo -e "${BOLD}============================================${NC}"
    echo ""

    if [[ $FAILED -eq 0 ]]; then
        echo -e "  ${GREEN}All tests passed.${NC}"
    else
        echo -e "  ${YELLOW}${FAILED} test(s) did not match expected status codes.${NC}"
        echo -e "  ${YELLOW}This is normal during early development.${NC}"
    fi
    echo ""
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

print_header

echo -e "${BOLD}--- Property Endpoints ---${NC}"
echo ""

test_endpoint \
    "List properties" \
    "200" \
    "GET" \
    "/properties"

test_endpoint \
    "Get single property (may not exist yet)" \
    "200|404" \
    "GET" \
    "/properties/1"

test_endpoint \
    "Search autocomplete" \
    "200" \
    "GET" \
    "/search/autocomplete?q=boston"

test_endpoint \
    "Filter options" \
    "200" \
    "GET" \
    "/filter-options"

echo -e "${BOLD}--- Auth Endpoints ---${NC}"
echo ""

test_endpoint \
    "Login without body (expect 400)" \
    "400" \
    "POST" \
    "/auth/login" \
    -H "Content-Type: application/json"

test_endpoint \
    "Get current user without token (expect 401)" \
    "401" \
    "GET" \
    "/auth/me"

echo -e "${BOLD}--- User Data Endpoints (require auth) ---${NC}"
echo ""

test_endpoint \
    "Favorites without token (expect 401)" \
    "401" \
    "GET" \
    "/favorites"

echo -e "${BOLD}--- School Endpoints ---${NC}"
echo ""

test_endpoint \
    "List schools" \
    "200" \
    "GET" \
    "/schools"

echo -e "${BOLD}--- Appointment Endpoints ---${NC}"
echo ""

test_endpoint \
    "Appointments without token (expect 401)" \
    "401" \
    "GET" \
    "/appointments"

# ---------------------------------------------------------------------------
# Summary & Exit
# ---------------------------------------------------------------------------

print_summary

if [[ $FAILED -eq 0 ]]; then
    exit 0
else
    exit 1
fi
