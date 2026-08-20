STEP_KEY="supervisor.install"

changed="false"
if ! command -v supervisord >/dev/null 2>&1 && ! command -v supervisorctl >/dev/null 2>&1; then
    mini_forge_apt_update
    mini_forge_apt_install supervisor
    changed="true"
fi

sudo -n systemctl enable --now supervisor || sudo -n systemctl enable --now supervisord || true
mini_forge_emit "$STEP_KEY" "true" "$changed" "{}"
