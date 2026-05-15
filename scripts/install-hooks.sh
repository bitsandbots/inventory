#!/bin/bash
#
# scripts/install-hooks.sh
#
# Point this clone's git hooks at the tracked .githooks/ directory so the
# pre-commit php -l check runs locally. Safe to re-run.

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

git config core.hooksPath .githooks
echo "core.hooksPath -> $(git config core.hooksPath)"
echo "Hooks active:"
ls -1 .githooks
