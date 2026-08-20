STEP_KEY="deploy.clone"

mini_forge_require_cmd git
mini_forge_require_cmd ssh

: "${MF_ROOT_PATH:?}"
: "${MF_REPOSITORY:?}"
: "${MF_COMMIT_SHA:?}"

ROOT="${MF_ROOT_PATH}"
RELEASE="${ROOT}/releases/${MF_COMMIT_SHA}"
DEPLOY_KEY="${ROOT}/.ssh/deploy_key"

[[ -f "${DEPLOY_KEY}" ]] || mini_forge_fail "$STEP_KEY" "missing_deploy_key" "Deploy key is not installed on the server."

# A finished clone has files and no .git (removed after checkout). Incomplete
# leftovers from a failed fetch still contain .git and must be retried.
if [[ -d "${RELEASE}" && ! -e "${RELEASE}/.git" && -n "$(ls -A "${RELEASE}" 2>/dev/null || true)" ]]; then
    mini_forge_emit "$STEP_KEY" "true" "false" "{\"commit_sha\":\"${MF_COMMIT_SHA}\",\"skipped\":true}"
    exit 0
fi

mkdir -p "${ROOT}/releases"
rm -rf "${RELEASE}"
mkdir -p "${RELEASE}"

export GIT_SSH_COMMAND="ssh -i ${DEPLOY_KEY} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"

clone_fail() {
    local code="$1"
    local fallback="$2"
    local output="$3"
    local summary

    rm -rf "${RELEASE}"
    summary="$(printf '%s' "${output}" | tr '\r' '\n' | grep -E '^ssh:' | tail -1 | tr -d '\n' || true)"
    if [[ -z "${summary}" ]]; then
        summary="$(printf '%s' "${output}" | tr '\r' '\n' | grep -E '^fatal:' | tail -1 | tr -d '\n' || true)"
    fi
    mini_forge_fail "$STEP_KEY" "${code}" "${summary:-${fallback}}"
}

init_output="$(git -C "${RELEASE}" init 2>&1)" || clone_fail "git_init_failed" "Unable to initialize the release repository." "${init_output}"
remote_output="$(git -C "${RELEASE}" remote add origin "git@github.com:${MF_REPOSITORY}.git" 2>&1)" || clone_fail "git_remote_failed" "Unable to add the GitHub remote." "${remote_output}"
fetch_output="$(git -C "${RELEASE}" fetch --depth 1 origin "${MF_COMMIT_SHA}" 2>&1)" || clone_fail "git_fetch_failed" "Unable to fetch the commit from GitHub." "${fetch_output}"
checkout_output="$(git -C "${RELEASE}" checkout --force FETCH_HEAD 2>&1)" || clone_fail "git_checkout_failed" "Unable to check out the fetched commit." "${checkout_output}"
rm -rf "${RELEASE}/.git"

mini_forge_emit "$STEP_KEY" "true" "true" "{\"commit_sha\":\"${MF_COMMIT_SHA}\"}"
