STEP_KEY="queue_worker.install"

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

if [[ ! "${MF_PHP_BINARY}" =~ ^/usr/bin/php[0-9]+\.[0-9]+$ ]]; then
    mini_forge_fail "$STEP_KEY" "invalid_php_binary" "The PHP binary path is invalid."
fi

if [[ ! -x "${MF_PHP_BINARY}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_php_binary" "PHP binary ${MF_PHP_BINARY} is not installed."
fi

if [[ ! "${MF_SUPERVISOR_PROGRAM}" =~ ^stacklab-site-[0-9]+-worker-[0-9]+$ ]]; then
    mini_forge_fail "$STEP_KEY" "invalid_program" "The Supervisor program name is invalid."
fi

if [[ "${MF_SUPERVISOR_CONFIG_PATH}" != "/etc/supervisor/conf.d/${MF_SUPERVISOR_PROGRAM}.conf" ]]; then
    mini_forge_fail "$STEP_KEY" "invalid_config_path" "The Supervisor configuration path is invalid."
fi

if [[ ! -f "${MF_ARTISAN_PATH}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_artisan" "artisan was not found at ${MF_ARTISAN_PATH}. Deploy the site before installing a queue worker."
fi

if [[ ! -d "${MF_ROOT_PATH}/current" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_current" "The current release directory is missing."
fi

log_dir="$(dirname "${MF_WORKER_LOG_PATH}")"
mkdir -p "${log_dir}"
if [[ -n "${MF_SITE_USER}" && "${MF_SITE_USER}" != "root" ]]; then
    sudo -n chown "${MF_SITE_USER}:${MF_SITE_USER}" "${log_dir}" 2>/dev/null || true
fi

tmp_config="$(mktemp)"
trap 'rm -f "${tmp_config}" "${MF_SUPERVISOR_CONFIG_PATH}.tmp"' EXIT

printf '%s' "${MF_SUPERVISOR_CONFIG_B64}" | base64 -d > "${tmp_config}"

if [[ ! -s "${tmp_config}" ]] || ! grep -q "^\[program:${MF_SUPERVISOR_PROGRAM}\]" "${tmp_config}"; then
    mini_forge_fail "$STEP_KEY" "invalid_supervisor_config" "The generated Supervisor configuration is invalid."
fi

sudo -n mkdir -p /etc/supervisor/conf.d
sudo -n cp "${tmp_config}" "${MF_SUPERVISOR_CONFIG_PATH}.tmp"
sudo -n mv "${MF_SUPERVISOR_CONFIG_PATH}.tmp" "${MF_SUPERVISOR_CONFIG_PATH}"
sudo -n chmod 644 "${MF_SUPERVISOR_CONFIG_PATH}"

if ! sudo -n supervisorctl reread; then
    sudo -n rm -f "${MF_SUPERVISOR_CONFIG_PATH}"
    mini_forge_fail "$STEP_KEY" "supervisor_reread_failed" "Supervisor rejected the worker configuration."
fi

if ! sudo -n supervisorctl update; then
    sudo -n rm -f "${MF_SUPERVISOR_CONFIG_PATH}"
    sudo -n supervisorctl reread >/dev/null 2>&1 || true
    sudo -n supervisorctl update >/dev/null 2>&1 || true
    mini_forge_fail "$STEP_KEY" "supervisor_update_failed" "Supervisor could not apply the worker configuration."
fi

running=0
expected="${MF_PROCESSES}"
status_output=""

for _ in 1 2 3 4 5 6 7 8; do
    status_output="$(sudo -n supervisorctl status "${MF_SUPERVISOR_PROGRAM}:*" 2>&1 || true)"
    running="$(printf '%s\n' "${status_output}" | grep -cE '[[:space:]]RUNNING[[:space:]]' || true)"

    if [[ "${running}" -eq "${expected}" ]]; then
        break
    fi

    sleep 1
done

if [[ "${running}" -ne "${expected}" ]]; then
    mini_forge_fail "$STEP_KEY" "worker_verify_failed" "Supervisor did not start ${expected} worker process(es). Running: ${running}." "{\"running\":${running},\"configured\":${expected}}"
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"running\":${running},\"configured\":${expected}}"
