STEP_KEY="php.install"

: "${MF_PHP_VERSION:=8.3}"

case "${MF_PHP_VERSION}" in
    7.4|8.0|8.1|8.2|8.3|8.4|8.5) ;;
    *)
        mini_forge_fail "$STEP_KEY" "invalid_php_version" "Unsupported PHP version [${MF_PHP_VERSION}]."
        ;;
esac

mini_forge_php_stack_missing() {
    local version="$1"
    local package

    while IFS= read -r package; do
        if ! dpkg -s "$package" >/dev/null 2>&1; then
            return 0
        fi
    done < <(mini_forge_php_packages "$version")

    if ! mini_forge_has_cmd unzip; then
        return 0
    fi

    return 1
}

changed="false"

if mini_forge_php_stack_missing "$MF_PHP_VERSION"; then
    mini_forge_enable_php_repo
    mini_forge_apt_update

    MF_PHP_VERSION="$(mini_forge_resolve_php_version "$MF_PHP_VERSION")" || mini_forge_fail "$STEP_KEY" "php_packages_unavailable" "No installable phpX.Y-cli package was found for the requested PHP version."

    if mini_forge_php_stack_missing "$MF_PHP_VERSION"; then
        mapfile -t packages < <(mini_forge_php_packages "$MF_PHP_VERSION")
        packages+=(unzip)
        mini_forge_apt_install "${packages[@]}"
        changed="true"
    fi
fi

php_bin="php${MF_PHP_VERSION}"
if ! mini_forge_has_cmd "$php_bin"; then
    php_bin="php"
fi

mini_forge_require_cmd "$php_bin"
php_version="$("$php_bin" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"

if [[ "$php_version" != "$MF_PHP_VERSION" ]]; then
    mini_forge_fail "$STEP_KEY" "php_version_mismatch" "Expected PHP ${MF_PHP_VERSION} but found ${php_version}."
fi

sudo -n systemctl enable --now "php${php_version}-fpm" >/dev/null 2>&1 \
    || sudo -n systemctl enable --now php-fpm >/dev/null 2>&1 \
    || true

mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"php_version\":\"${php_version}\"}"
