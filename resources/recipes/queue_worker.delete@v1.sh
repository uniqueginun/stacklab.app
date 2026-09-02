STEP_KEY="queue_worker.delete"

: "${MF_SUPERVISOR_PROGRAM:?}"
: "${MF_SUPERVISOR_CONFIG_PATH:?}"

mini_forge_require_cmd supervisorctl
mini_forge_assert_queue_worker_config_path "${MF_SUPERVISOR_PROGRAM}" "${MF_SUPERVISOR_CONFIG_PATH}"

sudo -n supervisorctl stop "${MF_SUPERVISOR_PROGRAM}:*" >/dev/null 2>&1 || true

if sudo -n test -f "${MF_SUPERVISOR_CONFIG_PATH}"; then
    sudo -n rm -f "${MF_SUPERVISOR_CONFIG_PATH}"
fi

sudo -n supervisorctl reread >/dev/null 2>&1 || true
sudo -n supervisorctl update >/dev/null 2>&1 || true

if ! mini_forge_supervisor_program_missing "${MF_SUPERVISOR_PROGRAM}"; then
    mini_forge_fail "$STEP_KEY" "worker_still_present" "Supervisor still reports the worker program after deletion."
fi

if sudo -n test -f "${MF_SUPERVISOR_CONFIG_PATH}"; then
    mini_forge_fail "$STEP_KEY" "config_still_present" "The managed Supervisor configuration is still on disk."
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{}"
