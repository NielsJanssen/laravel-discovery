#!/usr/bin/env bash
#
# Table-driven tests for changelog-tags.sh. Run with:
#   .github/scripts/changelog-tags.test.sh
set -uo pipefail

script="$(dirname "$0")/changelog-tags.sh"
failures=0

STABLE_NOTES='^pkg@v[0-9]+\.[0-9]+\.[0-9]+$'
ANY_NOTES='^pkg@v[0-9]'

pass() { # <description> <tags> <version> <expected notes_tag_pattern> <expected ignore_tags>
    actual="$(printf '%s' "$2" | "$script" pkg "$3" 2>/dev/null)" || actual="<error>"
    expected="$(printf 'notes_tag_pattern=%s\nignore_tags=%s' "$4" "$5")"
    if [ "$actual" = "$expected" ]; then
        printf '  ok   %s\n' "$1"
    else
        printf '  FAIL %s\n%s\n  expected\n%s\n' "$1" "$actual" "$expected"
        failures=$((failures + 1))
    fi
}

echo "stable releases"
pass "first release, no tags" "" "1.0.0" "$STABLE_NOTES" '^pkg@v(1\.0\.0)-'
pass "promotes its own rcs" $'pkg@v1.0.0-rc.1\npkg@v1.0.0-rc.2' "1.0.0" "$STABLE_NOTES" '^pkg@v(1\.0\.0)-'
pass "keeps earlier stables folded" $'pkg@v1.0.0\npkg@v1.0.0-rc.1\npkg@v1.1.0-beta.1' "1.1.0" \
    "$STABLE_NOTES" '^pkg@v(1\.0\.0|1\.1\.0)-'

echo "prereleases"
pass "first prerelease, no tags" "" "1.0.0-beta.1" "$ANY_NOTES" ""
pass "rcs before any stable" $'pkg@v1.0.0-rc.1' "1.0.0-rc.2" "$ANY_NOTES" ""
pass "after a stable release" $'pkg@v1.0.0\npkg@v1.0.0-rc.1' "1.1.0-beta.1" "$ANY_NOTES" '^pkg@v(1\.0\.0)-'

echo "other packages' tags are ignored"
pass "foreign stable tag" $'other@v2.0.0\npkg@v1.0.0-rc.1' "1.0.0-rc.2" "$ANY_NOTES" ""

echo "rejected input"
if "$script" pkg </dev/null >/dev/null 2>&1; then
    echo "  FAIL missing version -> succeeded (expected failure)"
    failures=$((failures + 1))
else
    echo "  ok   missing version -> rejected"
fi

echo
if [ "$failures" -eq 0 ]; then
    echo "all tests passed"
else
    echo "$failures test(s) failed"
    exit 1
fi
