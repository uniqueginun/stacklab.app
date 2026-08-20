STEP_KEY="nginx.install"

changed="false"
if ! mini_forge_has_cmd nginx; then
    mini_forge_apt_update
    mini_forge_apt_install nginx
    changed="true"
fi

sudo -n systemctl enable --now nginx
mini_forge_disable_default_nginx_site
mini_forge_require_cmd nginx
sudo -n nginx -t
mini_forge_reload_nginx
mini_forge_emit "$STEP_KEY" "true" "$changed" "{}"
