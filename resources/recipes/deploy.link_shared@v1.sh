STEP_KEY="deploy.link_shared"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"
: "${MF_SITE_TYPE:?}"

ROOT="${MF_ROOT_PATH}"
RELEASE="${ROOT}/releases/${MF_COMMIT_SHA}"
DOMAIN="${MF_DOMAIN:-localhost}"
CREATED_ENV="false"

[[ -d "${RELEASE}" ]] || mini_forge_fail "$STEP_KEY" "missing_release" "Release directory does not exist."

mkdir -p "${ROOT}/shared"

if [[ ! -s "${ROOT}/shared/.env" ]]; then
    CREATED_ENV="true"
    if [[ -f "${RELEASE}/.env.example" ]]; then
        cp "${RELEASE}/.env.example" "${ROOT}/shared/.env"
    else
        printf 'APP_NAME=Laravel\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=http://%s\nDB_CONNECTION=sqlite\n' "${DOMAIN}" > "${ROOT}/shared/.env"
    fi
fi

ln -sfn "${ROOT}/shared/.env" "${RELEASE}/.env"

if mini_forge_is_laravel; then
    mkdir -p "${ROOT}/shared/storage/app/public" \
        "${ROOT}/shared/storage/framework/cache/data" \
        "${ROOT}/shared/storage/framework/sessions" \
        "${ROOT}/shared/storage/framework/views" \
        "${ROOT}/shared/storage/logs"

    rm -rf "${RELEASE}/storage"
    ln -sfn "${ROOT}/shared/storage" "${RELEASE}/storage"

    if [[ ! -L "${RELEASE}/storage" ]]; then
        mini_forge_fail "$STEP_KEY" "storage_not_linked" "Release storage is not a symlink to shared/storage."
    fi

    if [[ -d "${RELEASE}/public" ]]; then
        rm -rf "${RELEASE}/public/storage"
        ln -sfn "${ROOT}/shared/storage/app/public" "${RELEASE}/public/storage"
    fi

    ln -sfn "${ROOT}/shared/.env" "${RELEASE}/.env"

    if [[ "${CREATED_ENV}" == "true" ]]; then
        # Fresh Forge-style sites boot on sqlite until the operator customizes shared/.env.
        SQLITE_PATH="${ROOT}/shared/database.sqlite"
        touch "${SQLITE_PATH}"
        if grep -qE '^DB_CONNECTION=' "${ROOT}/shared/.env"; then
            sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' "${ROOT}/shared/.env"
        else
            printf '\nDB_CONNECTION=sqlite\n' >> "${ROOT}/shared/.env"
        fi
        if grep -qE '^DB_DATABASE=' "${ROOT}/shared/.env"; then
            sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${SQLITE_PATH}|" "${ROOT}/shared/.env"
        else
            printf 'DB_DATABASE=%s\n' "${SQLITE_PATH}" >> "${ROOT}/shared/.env"
        fi
        if grep -qE '^APP_URL=' "${ROOT}/shared/.env"; then
            sed -i "s|^APP_URL=.*|APP_URL=http://${DOMAIN}|" "${ROOT}/shared/.env"
        fi
    fi

    if [[ -f "${RELEASE}/artisan" ]] && ! grep -qE '^APP_KEY=.+' "${ROOT}/shared/.env"; then
        php_bin="php"
        if [[ -n "${MF_PHP_VERSION:-}" ]] && mini_forge_has_cmd "php${MF_PHP_VERSION}"; then
            php_bin="php${MF_PHP_VERSION}"
        fi
        mini_forge_require_cmd "$php_bin"

        # link_shared runs before composer install, so artisan is usually unavailable.
        if [[ -f "${RELEASE}/vendor/autoload.php" ]]; then
            (
                cd "${RELEASE}"
                "$php_bin" artisan key:generate --force --no-ansi
            ) || mini_forge_fail "$STEP_KEY" "missing_app_key" "Unable to generate APP_KEY for shared/.env."
        else
            APP_KEY="$("$php_bin" -r 'echo "base64:".base64_encode(random_bytes(32));')" \
                || mini_forge_fail "$STEP_KEY" "missing_app_key" "Unable to generate APP_KEY for shared/.env."
            if grep -qE '^APP_KEY=' "${ROOT}/shared/.env"; then
                sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" "${ROOT}/shared/.env"
            else
                printf 'APP_KEY=%s\n' "${APP_KEY}" >> "${ROOT}/shared/.env"
            fi
        fi
    fi

    # php-fpm runs as www-data; grant group read/write without making files world-readable.
    if mini_forge_has_cmd getent && getent group www-data >/dev/null; then
        sudo -n chgrp www-data "${ROOT}/shared/.env" || true
        sudo -n chgrp -R www-data "${ROOT}/shared/storage" || true
        if [[ -f "${ROOT}/shared/database.sqlite" ]]; then
            sudo -n chgrp www-data "${ROOT}/shared/database.sqlite" || true
            sudo -n chmod 660 "${ROOT}/shared/database.sqlite" || true
        fi
    fi
    sudo -n chmod 640 "${ROOT}/shared/.env" || chmod 640 "${ROOT}/shared/.env" || true
    sudo -n chmod -R g+rwX "${ROOT}/shared/storage" || chmod -R g+rwX "${ROOT}/shared/storage" || true
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"commit_sha\":\"${MF_COMMIT_SHA}\",\"created_env\":${CREATED_ENV}}"
