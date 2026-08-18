#!/usr/bin/env bash
set -euo pipefail

# Non-login SSH shells often omit sbin; Debian packages like nginx live there.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

mini_forge_on_error() {
    local exit_code=$?
    trap - ERR
    mini_forge_fail "${STEP_KEY:-unknown}" "recipe_failed" "The recipe exited with status ${exit_code}."
}

trap mini_forge_on_error ERR

# Keep the Python helper on one line. Avoid `${var:-{}}` — bash treats the
# first `}` as the end of the expansion and appends a literal `}`.
mini_forge_emit() {
    local step_key="$1"
    local success="$2"
    local changed="$3"
    local data_json="{}"
    local error_code="${5-}"
    local error_message="${6-}"
    local data_b64

    if [[ -n "${4-}" ]]; then
        data_json="$4"
    fi

    data_b64="$(printf '%s' "$data_json" | base64 -w0 2>/dev/null || printf '%s' "$data_json" | base64 | tr -d '\n')"

    STEP_KEY="$step_key" SUCCESS="$success" CHANGED="$changed" DATA_B64="$data_b64" ERROR_CODE="$error_code" ERROR_MESSAGE="$error_message" python3 -c 'import base64,json,os; success=os.environ["SUCCESS"]=="true"; raw=base64.standard_b64decode(os.environ.get("DATA_B64") or "").decode() or "{}"; data=json.loads(raw); print(json.dumps({"step_key":os.environ["STEP_KEY"],"success":success,"changed":os.environ["CHANGED"]=="true","data":data,"error":{"code":None if success else (os.environ.get("ERROR_CODE") or "recipe_failed"),"message":None if success else (os.environ.get("ERROR_MESSAGE") or "Recipe failed."),"details":None}},separators=(",",":")))'
}

mini_forge_fail() {
    local data_json="{}"

    trap - ERR

    if [[ -n "${4-}" ]]; then
        data_json="$4"
    fi

    mini_forge_emit "$1" "false" "false" "$data_json" "$2" "$3"
    exit 1
}

mini_forge_has_cmd() {
    command -v "$1" >/dev/null 2>&1 || [[ -x "/usr/sbin/$1" ]] || [[ -x "/sbin/$1" ]]
}

mini_forge_require_cmd() {
    mini_forge_has_cmd "$1" || mini_forge_fail "${STEP_KEY}" "missing_command" "Required command [$1] is not available."
}

mini_forge_apt_update() {
    sudo -n env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 APT_LISTCHANGES_FRONTEND=none \
        apt-get -o Dpkg::Use-Pty=0 update -y
}

mini_forge_apt_install() {
    sudo -n env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 APT_LISTCHANGES_FRONTEND=none \
        apt-get -o Dpkg::Use-Pty=0 -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
        install -y "$@"
}

mini_forge_retry() {
    local max="${MF_RETRY_ATTEMPTS:-3}"
    local delay="${MF_RETRY_DELAY:-3}"
    local attempt=1

    while (( attempt <= max )); do
        if "$@"; then
            return 0
        fi

        if (( attempt < max )); then
            sleep "$delay"
        fi

        attempt=$((attempt + 1))
    done

    return 1
}

mini_forge_php_bin() {
    if [[ -n "${MF_PHP_VERSION:-}" ]] && mini_forge_has_cmd "php${MF_PHP_VERSION}"; then
        printf '%s' "php${MF_PHP_VERSION}"
        return
    fi

    if mini_forge_has_cmd php; then
        printf '%s' php
        return
    fi

    local candidate
    for candidate in php8.5 php8.4 php8.3 php8.2 php8.1 php8.0 php7.4; do
        if mini_forge_has_cmd "$candidate"; then
            printf '%s' "$candidate"
            return
        fi
    done

    printf '%s' php
}

mini_forge_apt_has_package() {
    apt-cache show "$1" >/dev/null 2>&1 || return 1
    return 0
}

mini_forge_php_packages() {
    local version="$1"

    printf '%s\n' \
        "php${version}-cli" \
        "php${version}-fpm" \
        "php${version}-mbstring" \
        "php${version}-xml" \
        "php${version}-curl" \
        "php${version}-zip" \
        "php${version}-mysql" \
        "php${version}-sqlite3" \
        "php${version}-bcmath"
}

mini_forge_resolve_php_version() {
    local requested="$1"
    local candidate

    if mini_forge_apt_has_package "php${requested}-cli"; then
        printf '%s' "$requested"
        return 0
    fi

    for candidate in 8.5 8.4 8.3 8.2 8.1 8.0 7.4; do
        if [[ "$candidate" == "$requested" ]]; then
            continue
        fi

        if mini_forge_apt_has_package "php${candidate}-cli"; then
            printf '%s' "$candidate"
            return 0
        fi
    done

    return 1
}

mini_forge_enable_php_repo() {
    if ! mini_forge_has_cmd gpg || ! mini_forge_has_cmd curl; then
        mini_forge_apt_update
        mini_forge_apt_install ca-certificates curl gnupg
    fi

    # shellcheck disable=SC1091
    . /etc/os-release

    local os_id="${ID:-debian}"
    local codename="${VERSION_CODENAME:-bookworm}"
    local keyring list

    sudo -n mkdir -p /usr/share/keyrings /etc/apt/sources.list.d

    if [[ "$os_id" == "ubuntu" ]]; then
        keyring="/usr/share/keyrings/ondrej-php.gpg"
        list="/etc/apt/sources.list.d/ondrej-php.list"
        if ! curl -fsI --max-time 15 "https://ppa.launchpadcontent.net/ondrej/php/ubuntu/dists/${codename}/Release" >/dev/null 2>&1; then
            sudo -n rm -f "$list"
            return
        fi
        curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c" \
            | sudo -n gpg --batch --yes --dearmor -o "$keyring"
        printf 'deb [signed-by=%s] https://ppa.launchpadcontent.net/ondrej/php/ubuntu %s main\n' "$keyring" "$codename" \
            | sudo -n tee "$list" >/dev/null
        return
    fi

    keyring="/usr/share/keyrings/deb.sury.org-php.gpg"
    list="/etc/apt/sources.list.d/sury-php.list"
    if ! curl -fsI --max-time 15 "https://packages.sury.org/php/dists/${codename}/Release" >/dev/null 2>&1; then
        sudo -n rm -f "$list"
        return
    fi
    curl -fsSL https://packages.sury.org/php/apt.gpg \
        | sudo -n gpg --batch --yes --dearmor -o "$keyring"
    printf 'deb [signed-by=%s] https://packages.sury.org/php/ %s main\n' "$keyring" "$codename" \
        | sudo -n tee "$list" >/dev/null
}

mini_forge_ensure_composer() {
    if mini_forge_has_cmd composer; then
        return 0
    fi

    local php_bin installer

    php_bin="$(mini_forge_php_bin)"
    mini_forge_require_cmd "$php_bin"
    mini_forge_require_cmd curl

    installer="$(mktemp)"
    curl -fsSL https://getcomposer.org/installer -o "$installer"
    "$php_bin" "$installer" --install-dir=/tmp --filename=composer.phar --quiet
    rm -f "$installer"
    sudo -n mv /tmp/composer.phar /usr/local/bin/composer
    sudo -n chmod 755 /usr/local/bin/composer
}
