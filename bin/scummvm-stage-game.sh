#!/usr/bin/env bash
# scummvm-stage-game.sh
#
# Stages game data from a "ScummVM Collection" source dir into public/games/<id>/
# so the launcher can serve it. Designed for the dot-prefixed inner subdir
# convention used by our collection (e.g. "Loom/.Loom/000.lfl"). Filenames are
# optionally lowercased to match the lowercase convention in config/gaming.php.
#
# Usage:
#   bin/scummvm-stage-game.sh <source-dir> <catalog-id> [--lowercase] [--dry-run]
#
# Examples:
#   bin/scummvm-stage-game.sh "gaming/ScummVM Collection 2.0/TESTING/The Dig" dig --lowercase
#   bin/scummvm-stage-game.sh "gaming/ScummVM Collection 2.0/TESTING/Yonkey Island 1.0 (Charnego Translations)" yonkey
#
# Skips: *.scummvm metadata files, *.svm legacy ScummVM containers, INI files,
#        the original DOS *.EXE, and Windows backup files (*.bak).
# Requires sudo (sets ownership to 82:82 for the docker container user).

set -euo pipefail

SOURCE_DIR=""
CATALOG_ID=""
LOWERCASE=0
DRY_RUN=0

usage() {
    sed -n '2,/^$/p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
}

# Parse args
while [ $# -gt 0 ]; do
    case "$1" in
        --lowercase) LOWERCASE=1; shift ;;
        --dry-run)   DRY_RUN=1;   shift ;;
        -h|--help)   usage ;;
        *)
            if [ -z "$SOURCE_DIR" ]; then SOURCE_DIR="$1"
            elif [ -z "$CATALOG_ID" ]; then CATALOG_ID="$1"
            else echo "ERROR: unexpected arg: $1" >&2; usage
            fi
            shift
            ;;
    esac
done

[ -z "$SOURCE_DIR" ] && { echo "ERROR: source dir required" >&2; usage; }
[ -z "$CATALOG_ID" ] && { echo "ERROR: catalog id required" >&2; usage; }

# Validate catalog id (matches the regex in GamingController::catalog())
if ! [[ "$CATALOG_ID" =~ ^[a-z][a-z0-9-]*$ ]]; then
    echo "ERROR: catalog-id '$CATALOG_ID' must match ^[a-z][a-z0-9-]*$" >&2
    exit 1
fi

if [ ! -d "$SOURCE_DIR" ]; then
    echo "ERROR: source dir does not exist: $SOURCE_DIR" >&2
    exit 1
fi

# The collection nests data inside a hidden sibling subdir whose name matches
# the parent (e.g. "Loom/.Loom/", "The Dig/.The Dig/"). If that's missing, fall
# back to the source dir itself (already-flat layouts).
INNER="$SOURCE_DIR/.$( basename "$SOURCE_DIR" )"
if [ ! -d "$INNER" ]; then
    echo "INFO: no hidden subdir at $INNER — using source dir directly"
    INNER="$SOURCE_DIR"
fi

REPO_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
DEST="$REPO_ROOT/public/games/$CATALOG_ID"

run() {
    if [ "$DRY_RUN" = 1 ]; then echo "  [DRY] $*"; else "$@"; fi
}

echo "=== Staging $SOURCE_DIR -> $DEST ==="
echo "  inner-dir: $INNER"
echo "  lowercase: $LOWERCASE"
[ "$DRY_RUN" = 1 ] && echo "  *** DRY RUN ***"
echo

run sudo mkdir -p "$DEST"

# Iterate top-level files + subdirs; skip metadata and noise
shopt -s nullglob dotglob
copied=0
skipped=0
for f in "$INNER"/*; do
    bn="$( basename "$f" )"
    case "$bn" in
        *.scummvm|*.svm|*.ini|*.EXE|*.exe|*.bak|*.BAK|.|..)
            echo "  skip: $bn"
            skipped=$(( skipped + 1 ))
            continue
            ;;
    esac

    if [ "$LOWERCASE" = 1 ]; then
        target_name="$( echo "$bn" | tr '[:upper:]' '[:lower:]' )"
    else
        target_name="$bn"
    fi

    if [ -d "$f" ]; then
        run sudo cp -rp "$f" "$DEST/$target_name"
        echo "  copy dir:  $bn  ->  $target_name/"
    else
        run sudo cp -p "$f" "$DEST/$target_name"
    fi
    copied=$(( copied + 1 ))
done
shopt -u nullglob dotglob

run sudo chown -R 82:82 "$DEST"

if [ "$DRY_RUN" = 0 ]; then
    files_count="$( find "$DEST" -type f | wc -l )"
    total_size="$( du -sh "$DEST" | cut -f1 )"
    echo
    echo "Done: $files_count files, $total_size in $DEST"
    echo "      ($copied items copied, $skipped skipped)"
fi
