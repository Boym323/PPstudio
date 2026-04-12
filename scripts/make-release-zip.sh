#!/usr/bin/env bash
set -euo pipefail

ref="${1:-}"

if [[ -z "${ref}" ]]; then
  echo "Usage: $0 <git-ref-or-tag>"
  echo "Example: $0 v2026-04-12"
  exit 2
fi

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${root}"

git rev-parse --verify "${ref}^{commit}" >/dev/null 2>&1 || {
  echo "Error: ref '${ref}' not found."
  exit 1
}

safe_ref="${ref//\//-}"
out_dir="dist"
out_zip="${out_dir}/ppstudio-${safe_ref}.zip"

mkdir -p "${out_dir}"

# Creates a deterministic deploy ZIP from a git ref/tag.
git archive --format=zip --output "${out_zip}" "${ref}"

echo "Created: ${out_zip}"

