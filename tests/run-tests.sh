#!/bin/bash
#
# Super Speedy Chat — Test Runner
#
# Loops over every tests/test-*.php file, runs it via `wp eval-file`, and
# reports pass/fail. Each test file is a self-contained PHP script that
# prints PASS:/FAIL: lines and exits 0 on success, 1 on failure.
#
# Usage:
#   bash tests/run-tests.sh                    # run all tests
#   bash tests/run-tests.sh <substring>        # run only tests whose path contains <substring>
#   bash tests/run-tests.sh --verbose          # print full per-test output
#
# Exit code: 0 if every test passes, 1 if any test fails.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[0;33m'
NC='\033[0m'

VERBOSE=false
FILTER=""

for arg in "$@"; do
    case $arg in
        --verbose) VERBOSE=true ;;
        *)         FILTER="$arg" ;;
    esac
done

# Find tests. Sorted so output order is stable.
mapfile -t TESTS < <(find "$SCRIPT_DIR" -maxdepth 1 -name 'test-*.php' -type f | sort)

if [ ${#TESTS[@]} -eq 0 ]; then
    echo -e "${RED}No test-*.php files found in $SCRIPT_DIR${NC}"
    exit 1
fi

# Sanity-check that wp-cli works and the plugin is active.
if ! command -v wp >/dev/null 2>&1; then
    echo -e "${RED}wp-cli not found in PATH${NC}"
    exit 1
fi

if ! wp plugin is-active super-speedy-chat >/dev/null 2>&1; then
    echo -e "${YELLOW}super-speedy-chat is not active. Activating...${NC}"
    if ! wp plugin activate super-speedy-chat >/dev/null 2>&1; then
        echo -e "${RED}Failed to activate super-speedy-chat${NC}"
        exit 1
    fi
fi

PASSED=0
FAILED=0
FAILED_NAMES=()

for t in "${TESTS[@]}"; do
    name="$(basename "$t")"
    if [ -n "$FILTER" ] && [[ "$name" != *"$FILTER"* ]]; then
        continue
    fi

    echo -e "\n${BLUE}===== $name =====${NC}"

    if $VERBOSE; then
        if wp eval-file "$t"; then
            echo -e "${GREEN}✓ $name passed${NC}"
            ((PASSED++)) || true
        else
            echo -e "${RED}✗ $name failed${NC}"
            ((FAILED++)) || true
            FAILED_NAMES+=("$name")
        fi
    else
        output=$(wp eval-file "$t" 2>&1)
        status=$?
        # Always show PASS/FAIL lines + summary; suppress noise from WP itself.
        echo "$output" | grep -E '^(PASS|FAIL|---|Total|Failures|  -|=== )' || true
        if [ $status -eq 0 ]; then
            echo -e "${GREEN}✓ $name passed${NC}"
            ((PASSED++)) || true
        else
            echo -e "${RED}✗ $name failed${NC}"
            ((FAILED++)) || true
            FAILED_NAMES+=("$name")
            # On failure in non-verbose mode, dump the full output for context.
            echo -e "${YELLOW}--- full output ---${NC}"
            echo "$output"
        fi
    fi
done

echo ""
echo -e "${BLUE}===== Summary =====${NC}"
echo "Passed: $PASSED"
echo "Failed: $FAILED"
if [ $FAILED -gt 0 ]; then
    echo "Failed tests:"
    for n in "${FAILED_NAMES[@]}"; do
        echo "  - $n"
    done
    exit 1
fi
exit 0
