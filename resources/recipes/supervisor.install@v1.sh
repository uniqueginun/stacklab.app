STEP_KEY="supervisor.install"

changed="false"
if ! command -v supervisord >/dev/null 2>&1 && ! command -v supervisorctl >/dev/null 2>&1; then
    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        mini_forge_enable_rhel_extras
    fi
    mini_forge_apt_update
    mini_forge_apt_install supervisor
    changed="true"
fi

mini_forge_ensure_supervisor_layout
sudo -n systemctl enable --now supervisor || sudo -n systemctl enable --now supervisord || true
mini_forge_emit "$STEP_KEY" "true" "$changed" "{}"
