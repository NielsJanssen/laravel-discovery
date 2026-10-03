#!/usr/bin/env bash
#
# Compute the git-cliff tag filters for a package release.
#
#   git tag --list "<package>@v*" | changelog-tags.sh <package> <version>
#
# Reads the package's existing tags on stdin and prints two GITHUB_OUTPUT lines:
#
#   notes_tag_pattern  --tag-pattern for the release notes. A stable release
#                      only sees stable tags, so its notes span everything since
#                      the previous stable release.
#   ignore_tags        --ignore-tags for the CHANGELOG. Prereleases of every
#                      version that went stable (including <version>) fold into
#                      that version's section. Empty when there are none.
set -euo pipefail

package="${1-}"
version="${2-}"

if [ -z "$package" ] || [ -z "$version" ]; then
    echo "usage: changelog-tags.sh <package> <version>" >&2
    exit 2
fi

stable=$(sed -nE "s/^$package@v([0-9]+\.[0-9]+\.[0-9]+)$/\1/p")
notes_tag_pattern="^$package@v[0-9]"

case "$version" in
    *-*) ;;
    *)
        notes_tag_pattern="^$package@v[0-9]+\.[0-9]+\.[0-9]+$"
        stable=$(printf '%s\n%s' "$stable" "$version")
        ;;
esac

versions=$(printf '%s\n' "$stable" | sed '/^$/d; s/\./\\./g' | sort -u | paste -sd '|' -)

ignore_tags=""
if [ -n "$versions" ]; then
    ignore_tags="^$package@v($versions)-"
fi

echo "notes_tag_pattern=$notes_tag_pattern"
echo "ignore_tags=$ignore_tags"
