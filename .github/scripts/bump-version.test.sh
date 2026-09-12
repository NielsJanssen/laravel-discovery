#!/usr/bin/env bash
#
# Table-driven tests for bump-version.sh. Run with:
#   .github/scripts/bump-version.test.sh
set -uo pipefail

script="$(dirname "$0")/bump-version.sh"
failures=0

pass() { # <current> <bump> <expected>
    actual="$("$script" "$1" "$2" 2>/dev/null)" || actual="<error>"
    if [ "$actual" = "$3" ]; then
        printf '  ok   %-18s %-6s -> %s\n' "${1:-<none>}" "$2" "$actual"
    else
        printf '  FAIL %-18s %-6s -> %s (expected %s)\n' "${1:-<none>}" "$2" "$actual" "$3"
        failures=$((failures + 1))
    fi
}

fails() { # <current> <bump>
    if "$script" "$1" "$2" >/dev/null 2>&1; then
        printf '  FAIL %-18s %-6s -> succeeded (expected failure)\n' "${1:-<none>}" "$2"
        failures=$((failures + 1))
    else
        printf '  ok   %-18s %-6s -> rejected\n' "${1:-<none>}" "$2"
    fi
}

echo "stable bumps"
pass "1.2.3" patch "1.2.4"
pass "1.2.3" minor "1.3.0"
pass "1.2.3" major "2.0.0"

echo "prereleases promote to the stable release they were staging"
pass "1.0.0-beta.8" patch "1.0.0"
pass "1.0.0-beta.8" minor "1.0.0"
pass "1.0.0-beta.8" major "1.0.0"
pass "1.2.3-beta.1" patch "1.2.3"
pass "1.2.3-beta.1" minor "1.3.0"
pass "1.2.3-beta.1" major "2.0.0"
pass "1.2.0-beta.1" minor "1.2.0"
pass "2.0.0-rc.2" major "2.0.0"

echo "prerelease channels"
pass "1.0.0-beta.8" beta "1.0.0-beta.9"
pass "1.0.0-alpha.3" alpha "1.0.0-alpha.4"
pass "1.0.0-alpha.3" beta "1.0.0-beta.1"
pass "1.0.0-alpha.3" rc "1.0.0-rc.1"
pass "1.0.0-beta.8" rc "1.0.0-rc.1"
pass "1.2.3" beta "1.2.4-beta.1"
pass "1.2.3" rc "1.2.4-rc.1"

echo "first release of a package (no prior tag)"
pass "" patch "0.0.1"
pass "" minor "0.1.0"
pass "" major "1.0.0"
pass "" beta "0.0.1-beta.1"

echo "rejected input"
fails "1.0.0-rc.1" beta
fails "1.0.0-rc.1" alpha
fails "1.0.0-beta.2" alpha
fails "not-a-version" patch
fails "1.2.3" sideways
fails "1.2.3" ""

echo
if [ "$failures" -eq 0 ]; then
    echo "all tests passed"
else
    echo "$failures test(s) failed"
    exit 1
fi
