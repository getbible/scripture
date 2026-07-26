#!/usr/bin/env bash
# SPDX-License-Identifier: GPL-2.0-only

set -euo pipefail
IFS=$'\n\t'

if (($# != 1)); then
    echo "Usage: $0 OUTPUT_SWORD_ROOT" >&2
    exit 2
fi

readonly output_root=$1
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly repository_root
readonly data_root="$output_root/modules/texts/rawtext/scripturefixture"
readonly imp_source="$repository_root/tests/Fixtures/Native/verse.imp"
readonly imp2vs='/usr/bin/imp2vs'
readonly expected_source_sha256='7ceaa7330fd234669dfc58c67294639a0a58a5a75c70efb4320a3f7adfc428a5'

if [[ -e "$output_root" ]]; then
    echo "Fixture output already exists: $output_root" >&2
    exit 2
fi
if [[ ! -x "$imp2vs" ]]; then
    echo "The SWORD imp2vs utility is unavailable: $imp2vs" >&2
    exit 2
fi
echo "$expected_source_sha256  $imp_source" | sha256sum --check --strict

export LC_ALL=C
export TZ=UTC
umask 022
mkdir -p -- "$output_root/mods.d" "$data_root"
"$imp2vs" "$imp_source" -o "$data_root" </dev/null >/dev/null

generated_file="$(find "$data_root" -type f -size +0c -print -quit)"
readonly generated_file

if [[ -z "$generated_file" ]]; then
    echo 'imp2vs did not create any non-empty module data.' >&2
    exit 1
fi
empty_file="$(find "$data_root" -type f -empty -print -quit)"
readonly empty_file

if [[ -n "$empty_file" ]]; then
    echo "imp2vs created an empty module-data file: $empty_file" >&2
    exit 1
fi

printf '%s\n' \
    '[ScriptureFixture]' \
    'DataPath=./modules/texts/rawtext/scripturefixture/' \
    'ModDrv=RawText' \
    'SourceType=OSIS' \
    'Encoding=UTF-8' \
    'Lang=en' \
    'Description=GetBible Scripture native integration fixture' \
    'DistributionLicense=Public Domain' \
    > "$output_root/mods.d/scripturefixture.conf"
