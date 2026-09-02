STEP_KEY="queue_worker.graceful_restart"

: "${MF_ROOT_PATH:?}"
: "${MF_PHP_BINARY:?}"
: "${MF_ARTISAN_PATH:?}"

if [[ ! "${MF_PHP_BINARY}" =~ ^/usr/bin/php[0-9]+\.[0-9]+$ ]]; then
    mini_forge_fail "$STEP_KEY" "invalid_php_binary" "The PHP binary path is invalid."
fi

if [[ ! -x "${MF_PHP_BINARY}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_php_binary" "PHP binary ${MF_PHP_BINARY} is not installed."
fi

if [[ ! -f "${MF_ARTISAN_PATH}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_artisan" "artisan was not found at ${MF_ARTISAN_PATH}."
fi

mini_forge_ensure_php_redis "${MF_PHP_BINARY}" "${MF_PHP_VERSION:-}"

CURRENT="${MF_ROOT_PATH}/current"

cd "${CURRENT}" || mini_forge_fail "$STEP_KEY" "missing_current" "The current release directory is missing."

release="$(readlink -f "${CURRENT}" 2>/dev/null || printf '%s' "${CURRENT}")"
mini_forge_ensure_www_data_readable "${MF_ROOT_PATH}" "${release}" "${MF_SITE_USER:-}"

trap - ERR
set +e
output="$("${MF_PHP_BINARY}" "${MF_ARTISAN_PATH}" queue:restart --no-ansi --no-interaction 2>&1)"
status=$?
set -e
trap mini_forge_on_error ERR

if [[ "${status}" -ne 0 ]]; then
    summary="$(printf '%s\n' "${output}" | sed '/^$/d' | tail -n 3 | tr '\n' ' ' | tr -d '\r' | cut -c1-300)"
    if [[ -z "${summary}" ]]; then
        summary="php artisan queue:restart exited with status ${status}."
    fi
    mini_forge_fail "$STEP_KEY" "queue_restart_failed" "Laravel could not signal queue workers to restart. ${summary}"
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{}"
