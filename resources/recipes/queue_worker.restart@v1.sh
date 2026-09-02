STEP_KEY="queue_worker.restart"

: "${MF_PROCESSES:?}"
: "${MF_SUPERVISOR_PROGRAM:?}"
: "${MF_SUPERVISOR_CONFIG_PATH:?}"

mini_forge_require_cmd supervisorctl
mini_forge_assert_queue_worker_config_path "${MF_SUPERVISOR_PROGRAM}" "${MF_SUPERVISOR_CONFIG_PATH}"

if ! sudo -n test -f "${MF_SUPERVISOR_CONFIG_PATH}"; then
    mini_forge_fail "$STEP_KEY" "missing_supervisor_config" "The managed Supervisor configuration was not found."
fi

if ! sudo -n supervisorctl restart "${MF_SUPERVISOR_PROGRAM}:*"; then
    mini_forge_fail "$STEP_KEY" "supervisor_restart_failed" "Supervisor could not restart the worker."
fi

running=0
expected="${MF_PROCESSES}"

if ! running="$(mini_forge_supervisor_wait_running "${MF_SUPERVISOR_PROGRAM}" "${expected}")"; then
    mini_forge_fail "$STEP_KEY" "worker_verify_failed" "Supervisor did not restart ${expected} worker process(es). Running: ${running}." "{\"running\":${running},\"configured\":${expected}}"
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"running\":${running},\"configured\":${expected}}"
