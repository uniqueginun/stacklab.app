STEP_KEY="ssl.certbot.install"

changed="false"
if ! mini_forge_has_cmd certbot; then
    mini_forge_apt_update
    mini_forge_apt_install certbot
    changed="true"
fi

mini_forge_require_cmd certbot
mini_forge_ensure_acme_webroot

hook="/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh"
if [[ ! -f "${hook}" ]]; then
    sudo -n mkdir -p /etc/letsencrypt/renewal-hooks/deploy
    printf '%s\n' '#!/bin/sh' 'systemctl reload nginx || nginx -s reload || true' \
        | sudo -n tee "${hook}" >/dev/null
    sudo -n chmod 755 "${hook}"
    changed="true"
fi

mini_forge_emit "$STEP_KEY" "true" "$changed" "{}"
