STEP_KEY="deploy.build"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"
: "${MF_SITE_TYPE:?}"

ROOT="${MF_ROOT_PATH}"
RELEASE="${ROOT}/releases/${MF_COMMIT_SHA}"
RUN_COMPOSER="${MF_RUN_COMPOSER:-1}"
RUN_NPM="${MF_RUN_NPM:-1}"
RUN_MIGRATIONS="${MF_RUN_MIGRATIONS:-1}"
RUN_CACHES="${MF_RUN_CACHES:-1}"

[[ -d "${RELEASE}" ]] || mini_forge_fail "$STEP_KEY" "missing_release" "Release directory does not exist."

cd "${RELEASE}"

php_bin="$(mini_forge_php_bin)"

if [[ "${RUN_COMPOSER}" == "1" && -f composer.json ]]; then
    mini_forge_ensure_composer
    mini_forge_require_cmd composer
    mini_forge_require_cmd "$php_bin"
    composer_bin="$(command -v composer)"
    if ! COMPOSER_NO_INTERACTION=1 "$php_bin" "$composer_bin" install --no-dev --prefer-dist --optimize-autoloader --no-ansi; then
        php_runtime="$("$php_bin" -r 'echo PHP_VERSION;')"
        mini_forge_fail "$STEP_KEY" "composer_install_failed" "Composer install failed using ${php_bin} (${php_runtime}). composer.lock may require a newer PHP."
    fi
fi

if [[ "${RUN_NPM}" == "1" ]]; then
    if [[ -f package-lock.json ]]; then
        if mini_forge_has_cmd npm; then
            if ! mini_forge_retry npm ci --no-audit --no-fund; then
                mini_forge_fail "$STEP_KEY" "npm_install_failed" "npm ci failed. The registry connection was reset or packages could not be fetched."
            fi
            if jq -e '.scripts.build // empty' package.json >/dev/null 2>&1 || grep -q '"build"' package.json 2>/dev/null; then
                if ! npm run build; then
                    mini_forge_fail "$STEP_KEY" "npm_build_failed" "npm run build failed."
                fi
            fi
        fi
    elif [[ -f package.json ]] && mini_forge_has_cmd npm; then
        if ! mini_forge_retry npm install --no-audit --no-fund; then
            mini_forge_fail "$STEP_KEY" "npm_install_failed" "npm install failed. The registry connection was reset or packages could not be fetched."
        fi
        if grep -q '"build"' package.json 2>/dev/null; then
            if ! npm run build; then
                mini_forge_fail "$STEP_KEY" "npm_build_failed" "npm run build failed."
            fi
        fi
    fi
fi

if [[ "${MF_SITE_TYPE}" == "laravel" && -f artisan ]]; then
    mini_forge_require_cmd "$php_bin"
    if [[ -s "${ROOT}/shared/.env" ]] && grep -qE '^APP_KEY=.+' "${ROOT}/shared/.env"; then
        if [[ "${RUN_MIGRATIONS}" == "1" ]]; then
            "$php_bin" artisan migrate --force --no-ansi || true
        fi
        if [[ "${RUN_CACHES}" == "1" ]]; then
            "$php_bin" artisan config:cache --no-ansi || true
            "$php_bin" artisan route:cache --no-ansi || true
            "$php_bin" artisan view:cache --no-ansi || true
        else
            rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/routes.php bootstrap/cache/events.php
        fi
    else
        rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/routes.php bootstrap/cache/events.php
    fi
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"commit_sha\":\"${MF_COMMIT_SHA}\"}"
