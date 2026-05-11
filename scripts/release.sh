#!/bin/bash
#
# scripts/release.sh
#
# Create a release: run tests, bump version, git tag, and archive.
# Usage: bash scripts/release.sh [version]
#
# If version is omitted, the script prompts for it.

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# ---------------------------------------------------------------------------
# Step 1: Check working directory is clean
# ---------------------------------------------------------------------------
echo -e "${CYAN}[1/5]${NC} Checking working directory..."

if ! git diff-index --quiet HEAD -- 2>/dev/null; then
    echo -e "${RED}  Error: Working directory has uncommitted changes.${NC}"
    echo "  Commit or stash changes before creating a release."
    exit 1
fi

echo "  Clean."
echo ""

# ---------------------------------------------------------------------------
# Step 2: Run test suite
# ---------------------------------------------------------------------------
echo -e "${CYAN}[2/5]${NC} Running test suite..."

if [ -f "$PROJECT_ROOT/tests/run.sh" ]; then
    if bash "$PROJECT_ROOT/tests/run.sh"; then
        echo ""
        echo -e "  ${GREEN}All tests passed.${NC}"
    else
        echo ""
        echo -e "${RED}  Tests failed. Fix issues before releasing.${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}  Warning: tests/run.sh not found — skipping tests.${NC}"
fi
echo ""

# ---------------------------------------------------------------------------
# Step 3: Determine version
# ---------------------------------------------------------------------------
echo -e "${CYAN}[3/5]${NC} Version..."

if [ -n "$1" ]; then
    VERSION="$1"
else
    # Suggest next patch by reading latest tag
    LATEST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")
    echo "  Latest tag: $LATEST_TAG"
    read -r -p "  Enter new version (e.g., v1.5.1): " VERSION
fi

if [ -z "$VERSION" ]; then
    echo -e "${RED}  Error: No version provided.${NC}"
    exit 1
fi

# Validate version format
if [[ ! "$VERSION" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+ ]]; then
    echo -e "${RED}  Error: Version must be in format vX.Y.Z or X.Y.Z${NC}"
    exit 1
fi

echo "  Version: $VERSION"
echo ""

# ---------------------------------------------------------------------------
# Step 4: Create git tag
# ---------------------------------------------------------------------------
echo -e "${CYAN}[4/5]${NC} Creating git tag..."

if git rev-parse "$VERSION" >/dev/null 2>&1; then
    echo -e "${RED}  Error: Tag '$VERSION' already exists.${NC}"
    exit 1
fi

git tag -a "$VERSION" -m "Release $VERSION"
echo "  Tagged: $VERSION"
echo ""

# ---------------------------------------------------------------------------
# Step 5: Create release archive
# ---------------------------------------------------------------------------
echo -e "${CYAN}[5/5]${NC} Creating release archive..."

ARCHIVE_NAME="inventory-${VERSION#v}.tar.gz"
CHECKSUM_NAME="$ARCHIVE_NAME.sha256"

# Archive excluding development/dot files
git archive \
    --format=tar.gz \
    --prefix="inventory-${VERSION#v}/" \
    --output="$ARCHIVE_NAME" \
    HEAD

# Generate checksum
if command -v sha256sum &>/dev/null; then
    sha256sum "$ARCHIVE_NAME" > "$CHECKSUM_NAME"
else
    shasum -a 256 "$ARCHIVE_NAME" > "$CHECKSUM_NAME"
fi

ARCHIVE_SIZE=$(du -h "$ARCHIVE_NAME" | cut -f1)
CHECKSUM_VAL=$(head -c 64 "$CHECKSUM_NAME")

echo "  Archive: $ARCHIVE_NAME ($ARCHIVE_SIZE)"
echo "  Checksum: $CHECKSUM_NAME"
echo "  SHA256: $CHECKSUM_VAL"
echo ""

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo "========================================="
echo -e " ${GREEN}Release $VERSION Ready${NC}"
echo "========================================="
echo ""
echo "  Files:"
echo "    $ARCHIVE_NAME"
echo "    $CHECKSUM_NAME"
echo ""
echo "  Next steps:"
echo "    git push origin main $VERSION"
echo "    Upload $ARCHIVE_NAME and $CHECKSUM_NAME to GitHub Releases"
echo ""
