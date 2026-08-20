STEP_KEY="deploy.prune"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"

ROOT="${MF_ROOT_PATH}"
RETAIN="${MF_RETAIN:-5}"
CURRENT_TARGET="$(readlink -f "${ROOT}/current" 2>/dev/null || true)"

mkdir -p "${ROOT}/releases"
cd "${ROOT}/releases"

mapfile -t remaining < <(ls -1dt -- */ 2>/dev/null | sed 's:/$::' || true)
index=0

for release in "${remaining[@]+"${remaining[@]}"}"; do
    index=$((index + 1))
    absolute="${ROOT}/releases/${release}"

    if [[ "${absolute}" == "${CURRENT_TARGET}" || "${release}" == "${MF_COMMIT_SHA}" ]]; then
        continue
    fi

    if (( index > RETAIN )); then
        rm -rf "${absolute}"
    fi
done

mini_forge_emit "$STEP_KEY" "true" "true" "{\"retain\":${RETAIN}}"
