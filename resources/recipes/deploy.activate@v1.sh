STEP_KEY="deploy.activate"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"
: "${MF_DOMAIN:?}"
: "${MF_NGINX_CONFIG_B64:?}"

ROOT="${MF_ROOT_PATH}"
RELEASE="${ROOT}/releases/${MF_COMMIT_SHA}"
DOMAIN="${MF_DOMAIN}"
AVAILABLE="/etc/nginx/sites-available/${DOMAIN}"
ENABLED="/etc/nginx/sites-enabled/${DOMAIN}"
BACKUP=""
RUN_QUEUE_RESTART="${MF_RUN_QUEUE_RESTART:-1}"
SITE_TYPE="${MF_SITE_TYPE:-}"

[[ -d "${RELEASE}" ]] || mini_forge_fail "$STEP_KEY" "missing_release" "Release directory does not exist."

ln -sfn "releases/${MF_COMMIT_SHA}" "${ROOT}/current"

mini_forge_ensure_www_data_readable "${ROOT}" "${RELEASE}" "${MF_SSH_USER:-}"

mini_forge_disable_default_nginx_site

if [[ -f "${AVAILABLE}" ]]; then
    BACKUP="$(mktemp)"
    sudo -n cp "${AVAILABLE}" "${BACKUP}"
fi

printf '%s' "${MF_NGINX_CONFIG_B64}" | base64 -d | sudo -n tee "${AVAILABLE}" >/dev/null
sudo -n ln -sfn "${AVAILABLE}" "${ENABLED}"

if ! sudo -n nginx -t; then
    if [[ -n "${BACKUP}" && -f "${BACKUP}" ]]; then
        sudo -n cp "${BACKUP}" "${AVAILABLE}"
        sudo -n nginx -t
        mini_forge_reload_nginx || true
    fi
    if [[ -n "${MF_PREVIOUS_SHA}" && -d "${ROOT}/releases/${MF_PREVIOUS_SHA}" ]]; then
        ln -sfn "releases/${MF_PREVIOUS_SHA}" "${ROOT}/current"
    fi
    rm -f "${BACKUP}"
    mini_forge_fail "$STEP_KEY" "nginx_test_failed" "Nginx configuration failed validation."
fi

mini_forge_reload_nginx
rm -f "${BACKUP}"

if [[ -n "${MF_PHP_VERSION:-}" ]]; then
    sudo -n systemctl reload "php${MF_PHP_VERSION}-fpm" 2>/dev/null \
        || sudo -n systemctl reload php-fpm 2>/dev/null \
        || true
fi

if [[ "${RUN_QUEUE_RESTART}" == "1" && "${SITE_TYPE}" == "laravel" && -f "${RELEASE}/artisan" ]]; then
    (
        cd "${RELEASE}"
        php artisan queue:restart --no-ansi || true
    )
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"commit_sha\":\"${MF_COMMIT_SHA}\"}"
