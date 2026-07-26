#!/usr/bin/env bash
# SPDX-License-Identifier: GPL-2.0-only

set -euo pipefail
IFS=$'\n\t'

if (($# > 1)); then
    echo "Usage: $0 [EXPECTED_TAG]" >&2
    exit 2
fi

readonly expected_tag=${1:-}
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly repository_root
readonly version_file="$repository_root/VERSION"
readonly changelog="$repository_root/CHANGELOG.md"

if [[ ! -f "$version_file" || ! -f "$changelog" ]]; then
    echo "VERSION and CHANGELOG.md are required for a release." >&2
    exit 1
fi

version="$(tr -d '\r\n' < "$version_file")"
readonly version
readonly tag="v$version"

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "VERSION is not a stable semantic version: $version" >&2
    exit 1
fi

if [[ -n "$expected_tag" && "$expected_tag" != "$tag" ]]; then
    echo "Tag $expected_tag does not match VERSION $version." >&2
    exit 1
fi

escaped_version=${version//./\\.}

if ! grep --extended-regexp --quiet \
    "^## \\[$escaped_version\\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$" \
    "$changelog"; then
    echo "CHANGELOG.md has no dated [$version] release section." >&2
    exit 1
fi

printf 'Validated release metadata for %s (%s).\n' "$version" "$tag"
