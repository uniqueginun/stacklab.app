STEP_KEY="deploy.verify"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"

ROOT="${MF_ROOT_PATH}"
CURRENT="${ROOT}/current"
EXPECTED="${ROOT}/releases/${MF_COMMIT_SHA}"

[[ -L "${CURRENT}" || -d "${CURRENT}" ]] || mini_forge_fail "$STEP_KEY" "missing_current" "The current release symlink does not exist."
[[ -d "${EXPECTED}" ]] || mini_forge_fail "$STEP_KEY" "missing_release" "The activated release directory does not exist."

TARGET="$(readlink -f "${CURRENT}" 2>/dev/null || true)"

if [[ -n "${TARGET}" && "${TARGET}" != "${EXPECTED}" ]]; then
    mini_forge_fail "$STEP_KEY" "current_mismatch" "The current symlink does not point at the deployed release."
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"commit_sha\":\"${MF_COMMIT_SHA}\"}"
