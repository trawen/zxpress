#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail=0

check_contains() {
	local file="$1"
	local pattern="$2"
	local label="$3"

	if ! grep -Eq "$pattern" "$file"; then
		echo "[ERROR] Missing: $label in $file"
		fail=1
	fi
}

check_contains "docs/operations.md" 'Text storage migration contract \(`uploads/\*` -> `content-store/\*`\)' "migration contract section"
check_contains "docs/operations.md" "Go/No-Go критерии перед purge" "go/no-go criteria"
check_contains "docs/operations.md" "Rollback:" "rollback section"

check_contains "data/README.md" 'Text storage migration status \(`uploads/\*` -> `content-store/\*`\)' "migration status section"
check_contains "data/README.md" "Gate criteria before physical purge" "purge gate criteria"
check_contains "data/README.md" "tools/pre-purge-uploads-text-gate.sh" "pre-purge gate script reference"

if [ "$fail" -ne 0 ]; then
	echo "test_storage_migration_docs.sh: FAIL"
	exit 1
fi

echo "test_storage_migration_docs.sh: OK"
