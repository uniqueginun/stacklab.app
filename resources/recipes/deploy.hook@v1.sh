STEP_KEY="deploy.hook"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"

ROOT="${MF_ROOT_PATH}"
RELEASE="${ROOT}/releases/${MF_COMMIT_SHA}"
HOOK="${RELEASE}/deploy.sh"
RUN_HOOK="${MF_RUN_HOOK:-1}"

[[ -d "${RELEASE}" ]] || mini_forge_fail "$STEP_KEY" "missing_release" "Release directory does not exist."

if [[ "${RUN_HOOK}" != "1" ]]; then
    mini_forge_emit "$STEP_KEY" "true" "false" "{\"ran\":false,\"skipped\":true}"
    exit 0
fi

if [[ -f "${HOOK}" && -x "${HOOK}" ]]; then
    mini_forge_require_cmd timeout
    (
        cd "${RELEASE}"
        timeout 300 ./deploy.sh
    ) || mini_forge_fail "$STEP_KEY" "hook_failed" "deploy.sh exited with a non-zero status."
    mini_forge_emit "$STEP_KEY" "true" "true" "{\"ran\":true}"
    exit 0
fi

mini_forge_emit "$STEP_KEY" "true" "false" "{\"ran\":false}"
