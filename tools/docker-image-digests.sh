#!/usr/bin/env bash
# Print RepoDigests for images used in Dockerfile / docker-compose.yml (after docker pull).
# Usage: bash tools/docker-image-digests.sh
set -euo pipefail

images=(
	"php:8.5-fpm"
	"nginx:1.27-alpine"
	"manticoresearch/manticore:17.5.1"
	"mysql:8.0"
)

for img in "${images[@]}"; do
	echo "[INFO] pulling $img"
	docker pull "$img" >/dev/null
	digest=$(docker image inspect "$img" --format '{{index .RepoDigests 0}}' 2>/dev/null || true)
	if [[ -z "$digest" ]]; then
		echo "[WARN] no RepoDigest for $img (multi-arch or local tag?)"
	else
		echo "[INFO] $digest"
	fi
done
