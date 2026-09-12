#!/usr/bin/env bash
#
# Compute the next semver for a package release.
#
#   bump-version.sh <current> <bump>
#
# <current> is the version currently released (e.g. "1.2.3" or "1.2.3-beta.4"),
# or empty when the package has never been released — that is treated as 0.0.0.
# <bump> is one of: major, minor, patch, alpha, beta, rc.
#
# major/minor/patch follow npm's `semver inc` semantics: bumping a prerelease
# whose base is already the target simply promotes it to the stable release
# (1.0.0-beta.8 + patch -> 1.0.0). Prerelease channels are numbered from .1 to
# match this repository's existing tags (beta.1, beta.2, ...), not npm's .0.
set -euo pipefail

current="${1-}"
bump="${2-}"

if [ -z "$bump" ]; then
    echo "usage: bump-version.sh <current-version> <major|minor|patch|alpha|beta|rc>" >&2
    exit 2
fi

[ -n "$current" ] || current="0.0.0"

if ! printf '%s' "$current" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)\.[0-9]+)?$'; then
    echo "cannot bump unrecognised version '$current'" >&2
    exit 1
fi

base="${current%%-*}"
major="${base%%.*}"
patch="${base##*.}"
minor="${base#*.}"
minor="${minor%%.*}"

pre_id=""
pre_num=0
if [ "$current" != "$base" ]; then
    pre="${current#*-}"
    pre_id="${pre%%.*}"
    pre_num="${pre##*.}"
fi

rank() {
    case "$1" in
        alpha) echo 1 ;;
        beta) echo 2 ;;
        rc) echo 3 ;;
        *) echo 0 ;;
    esac
}

case "$bump" in
    major)
        if [ -n "$pre_id" ] && [ "$minor" -eq 0 ] && [ "$patch" -eq 0 ]; then
            printf '%s.%s.%s\n' "$major" "$minor" "$patch"
        else
            printf '%s.0.0\n' "$((major + 1))"
        fi
        ;;
    minor)
        if [ -n "$pre_id" ] && [ "$patch" -eq 0 ]; then
            printf '%s.%s.%s\n' "$major" "$minor" "$patch"
        else
            printf '%s.%s.0\n' "$major" "$((minor + 1))"
        fi
        ;;
    patch)
        if [ -n "$pre_id" ]; then
            printf '%s.%s.%s\n' "$major" "$minor" "$patch"
        else
            printf '%s.%s.%s\n' "$major" "$minor" "$((patch + 1))"
        fi
        ;;
    alpha | beta | rc)
        if [ -z "$pre_id" ]; then
            printf '%s.%s.%s-%s.1\n' "$major" "$minor" "$((patch + 1))" "$bump"
        elif [ "$pre_id" = "$bump" ]; then
            printf '%s.%s.%s-%s.%s\n' "$major" "$minor" "$patch" "$bump" "$((pre_num + 1))"
        elif [ "$(rank "$bump")" -gt "$(rank "$pre_id")" ]; then
            printf '%s.%s.%s-%s.1\n' "$major" "$minor" "$patch" "$bump"
        else
            echo "cannot bump $current down to a '$bump' prerelease" >&2
            exit 1
        fi
        ;;
    *)
        echo "unknown bump '$bump' (expected major, minor, patch, alpha, beta or rc)" >&2
        exit 2
        ;;
esac
