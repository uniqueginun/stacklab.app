STEP_KEY="queue_worker.update"

: "${MF_ROOT_PATH:?}"
: "${MF_PHP_BINARY:?}"
: "${MF_ARTISAN_PATH:?}"
: "${MF_SITE_USER:?}"
: "${MF_PROCESSES:?}"
: "${MF_SUPERVISOR_PROGRAM:?}"
: "${MF_SUPERVISOR_CONFIG_PATH:?}"
: "${MF_SUPERVISOR_CONFIG_B64:?}"
: "${MF_WORKER_LOG_PATH:?}"

mini_forge_require_cmd supervisorctl
mini_forge_assert_queue_worker_config_path "${MF_SUPERVISOR_PROGRAM}" "${MF_SUPERVISOR_CONFIG_PATH}"

if [[ ! "${MF_PHP_BINARY}" =~ ^/usr/bin/php[0-9]+\.[0-9]+$ ]]; then
    mini_forge_fail "$STEP_KEY" "invalid_php_binary" "The PHP binary path is invalid."
fi

if [[ ! -x "${MF_PHP_BINARY}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_php_binary" "PHP binary ${MF_PHP_BINARY} is not installed."
fi

mini_forge_ensure_php_redis "${MF_PHP_BINARY}" "${MF_PHP_VERSION:-}"

if [[ ! -f "${MF_ARTISAN_PATH}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_artisan" "artisan was not found at ${MF_ARTISAN_PATH}."
fi

log_dir="$(dirname "${MF_WORKER_LOG_PATH}")"
mkdir -p "${log_dir}"
if [[ -n "${MF_SITE_USER}" && "${MF_SITE_USER}" != "root" ]]; then
    sudo -n chown "${MF_SITE_USER}:${MF_SITE_USER}" "${log_dir}" 2>/dev/null || true
fi

tmp_config="$(mktemp)"
backup=""
trap 'rm -f "${tmp_config}" "${MF_SUPERVISOR_CONFIG_PATH}.tmp"; [[ -n "${backup}" ]] && rm -f "${backup}"' EXIT

printf '%s' "${MF_SUPERVISOR_CONFIG_B64}" | base64 -d > "${tmp_config}"

if [[ ! -s "${tmp_config}" ]] || ! grep -q "^\[program:${MF_SUPERVISOR_PROGRAM}\]" "${tmp_config}"; then
    mini_forge_fail "$STEP_KEY" "invalid_supervisor_config" "The generated Supervisor configuration is invalid."
fi

if sudo -n test -f "${MF_SUPERVISOR_CONFIG_PATH}"; then
    backup="$(mktemp)"
    sudo -n cp "${MF_SUPERVISOR_CONFIG_PATH}" "${backup}"
fi

restore_previous_config() {
    if [[ -n "${backup}" && -f "${backup}" ]]; then
        sudo -n cp "${backup}" "${MF_SUPERVISOR_CONFIG_PATH}"
        sudo -n chmod 644 "${MF_SUPERVISOR_CONFIG_PATH}"
    else
        sudo -n rm -f "${MF_SUPERVISOR_CONFIG_PATH}"
    fi

    sudo -n supervisorctl reread >/dev/null 2>&1 || true
    sudo -n supervisorctl update >/dev/null 2>&1 || true
}

sudo -n mkdir -p /etc/supervisor/conf.d
sudo -n cp "${tmp_config}" "${MF_SUPERVISOR_CONFIG_PATH}.tmp"
sudo -n mv "${MF_SUPERVISOR_CONFIG_PATH}.tmp" "${MF_SUPERVISOR_CONFIG_PATH}"
sudo -n chmod 644 "${MF_SUPERVISOR_CONFIG_PATH}"

if ! sudo -n supervisorctl reread; then
    restore_previous_config
    mini_forge_fail "$STEP_KEY" "supervisor_reread_failed" "Supervisor rejected the updated worker configuration."
fi

if ! sudo -n supervisorctl update; then
    restore_previous_config
    mini_forge_fail "$STEP_KEY" "supervisor_update_failed" "Supervisor could not apply the updated worker configuration."
fi

running=0
expected="${MF_PROCESSES}"

if ! running="$(mini_forge_supervisor_wait_running "${MF_SUPERVISOR_PROGRAM}" "${expected}")"; then
    restore_previous_config
    mini_forge_fail "$STEP_KEY" "worker_verify_failed" "Supervisor did not start ${expected} worker process(es) after the update. Running: ${running}." "{\"running\":${running},\"configured\":${expected}}"
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"running\":${running},\"configured\":${expected}}"
