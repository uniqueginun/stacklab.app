#!/usr/bin/env bash
set -euo pipefail

# Non-login SSH shells often omit sbin; Debian packages like nginx live there.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

# Git/composer/npm inherit this umask. 077 makes release files unreadable by www-data.
umask 022

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
        "php${version}-bcmath" \
        "php${version}-redis"
}

mini_forge_php_has_redis() {
    local php_bin="$1"

    [[ -n "${php_bin}" ]] && "${php_bin}" -r 'exit(extension_loaded("redis") ? 0 : 1);' >/dev/null 2>&1
}

mini_forge_ensure_php_redis() {
    local php_bin="${1:-${MF_PHP_BINARY:-}}"
    local version="${2:-${MF_PHP_VERSION:-}}"

    if [[ -z "${php_bin}" ]]; then
        mini_forge_fail "${STEP_KEY}" "missing_php_binary" "The PHP binary path is invalid."
    fi

    if mini_forge_php_has_redis "${php_bin}"; then
        return 0
    fi

    if [[ -z "${version}" && "${php_bin}" =~ php([0-9]+\.[0-9]+)$ ]]; then
        version="${BASH_REMATCH[1]}"
    fi

    if [[ ! "${version}" =~ ^[0-9]+\.[0-9]+$ ]]; then
        mini_forge_fail "${STEP_KEY}" "invalid_php_version" "The PHP version is invalid."
    fi

    mini_forge_enable_php_repo
    mini_forge_apt_update
    mini_forge_apt_install "php${version}-redis"
    sudo -n phpenmod -v "${version}" redis >/dev/null 2>&1 || true

    if ! mini_forge_php_has_redis "${php_bin}"; then
        mini_forge_fail "${STEP_KEY}" "php_redis_missing" "PHP ${version} does not have the Redis extension."
    fi
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

mini_forge_disable_default_nginx_site() {
    sudo -n rm -f /etc/nginx/sites-enabled/default
}

# Restart nginx after writing a vhost. Reload can succeed while the Ubuntu
# default site still answers until the master process is replaced.
mini_forge_reload_nginx() {
    if sudo -n systemctl restart nginx; then
        return 0
    fi

    sudo -n nginx -s reload
}

mini_forge_ensure_parent_traverse() {
    local path="$1"

    while [[ "${path}" != "/" && -n "${path}" ]]; do
        sudo -n chmod a+x "${path}" || true
        path="$(dirname "${path}")"
    done
}

mini_forge_www_data_group_exists() {
    mini_forge_has_cmd getent && getent group www-data >/dev/null
}

# Site types are stored as "Laravel" / "PHP" / "HTML". Bash == is case-sensitive.
mini_forge_is_laravel() {
    local type="${1:-${MF_SITE_TYPE:-}}"

    [[ "${type,,}" == "laravel" ]]
}

# php-fpm (www-data) must own-or-group-write these trees. Laravel's Filesystem::replace()
# calls tempnam() in the target directory; if that dir isn't writable PHP 8.4+ raises
# "tempnam(): file created in the system's temporary directory" as an ErrorException.
mini_forge_make_fpm_writable() {
    local path="$1"
    local ssh_user="${2:-}"

    [[ -e "${path}" ]] || return 0

    if mini_forge_www_data_group_exists; then
        if [[ -n "${ssh_user}" ]]; then
            sudo -n chown -R "${ssh_user}:www-data" "${path}" || sudo -n chgrp -R www-data "${path}" || true
        else
            sudo -n chgrp -R www-data "${path}" || true
        fi
        if mini_forge_has_cmd setfacl; then
            sudo -n setfacl -R -m u:www-data:rwx -m d:u:www-data:rwx "${path}" || true
        fi
    fi

    sudo -n chmod -R ug+rwX "${path}" || true
    sudo -n find "${path}" -type d -exec chmod 2775 {} + 2>/dev/null || true
}

# www-data must read the release and write storage/bootstrap/cache.
# Never chmod -R the site root — that opens .ssh keys.
mini_forge_ensure_www_data_readable() {
    local root="$1"
    local release="${2:-}"
    local ssh_user="${3:-${MF_SSH_USER:-}}"

    sudo -n chmod 755 "${root}" || true
    mini_forge_ensure_parent_traverse "${root}"

    if [[ -n "${release}" && -d "${release}" ]]; then
        sudo -n chmod -R a+rX "${release}"
        sudo -n mkdir -p "${release}/bootstrap/cache"
        mini_forge_make_fpm_writable "${release}/bootstrap/cache" "${ssh_user}"
    fi

    sudo -n mkdir -p \
        "${root}/shared/storage/app/public" \
        "${root}/shared/storage/framework/cache/data" \
        "${root}/shared/storage/framework/sessions" \
        "${root}/shared/storage/framework/views" \
        "${root}/shared/storage/logs"

    mini_forge_make_fpm_writable "${root}/shared/storage" "${ssh_user}"

    if [[ -n "${release}" ]] && { [[ -L "${release}/storage" ]] || [[ -d "${release}/storage" ]]; }; then
        local storage_path
        storage_path="$(readlink -f "${release}/storage" 2>/dev/null || true)"
        if [[ -n "${storage_path}" && -d "${storage_path}" ]]; then
            mini_forge_make_fpm_writable "${storage_path}" "${ssh_user}"
        fi
    fi

    if [[ -f "${root}/shared/.env" ]]; then
        sudo -n chmod 640 "${root}/shared/.env" || true
        if mini_forge_www_data_group_exists; then
            if [[ -n "${ssh_user}" ]]; then
                sudo -n chown "${ssh_user}:www-data" "${root}/shared/.env" || sudo -n chgrp www-data "${root}/shared/.env" || true
            else
                sudo -n chgrp www-data "${root}/shared/.env" || true
            fi
        fi
    fi

    if [[ -f "${root}/shared/database.sqlite" ]]; then
        sudo -n chmod 660 "${root}/shared/database.sqlite" || true
        if mini_forge_www_data_group_exists; then
            if [[ -n "${ssh_user}" ]]; then
                sudo -n chown "${ssh_user}:www-data" "${root}/shared/database.sqlite" || sudo -n chgrp www-data "${root}/shared/database.sqlite" || true
            else
                sudo -n chgrp www-data "${root}/shared/database.sqlite" || true
            fi
        fi
    fi

    if [[ -d "${root}/.ssh" ]]; then
        sudo -n chmod 700 "${root}/.ssh"
        sudo -n chmod 600 "${root}/.ssh/"* 2>/dev/null || true
        if [[ -n "${ssh_user}" ]]; then
            sudo -n chown -R "${ssh_user}:${ssh_user}" "${root}/.ssh" || true
        fi
    fi
}

mini_forge_concat_pem() {
    local dest="$1"
    shift
    : > "${dest}"

    local file
    for file in "$@"; do
        [[ -f "${file}" ]] || continue
        tr -d '\r' < "${file}" >> "${dest}"
        if [[ -s "${dest}" && "$(tail -c1 "${dest}" | wc -l)" -eq 0 ]]; then
            printf '\n' >> "${dest}"
        fi
    done
}

mini_forge_write_nginx_vhost() {
    local domain="$1"
    local config_b64="$2"
    local available="/etc/nginx/sites-available/${domain}"
    local enabled="/etc/nginx/sites-enabled/${domain}"
    local backup=""

    mini_forge_disable_default_nginx_site

    if [[ -f "${available}" ]]; then
        backup="$(mktemp)"
        sudo -n cp "${available}" "${backup}"
    fi

    printf '%s' "${config_b64}" | base64 -d | sudo -n tee "${available}" >/dev/null
    sudo -n ln -sfn "${available}" "${enabled}"

    if ! sudo -n nginx -t; then
        if [[ -n "${backup}" && -f "${backup}" ]]; then
            sudo -n cp "${backup}" "${available}"
            sudo -n nginx -t
            mini_forge_reload_nginx || true
        else
            sudo -n rm -f "${enabled}"
        fi
        rm -f "${backup}"
        mini_forge_fail "${STEP_KEY}" "nginx_test_failed" "Nginx configuration failed validation."
    fi

    mini_forge_reload_nginx
    rm -f "${backup}"
}

mini_forge_ensure_acme_webroot() {
    local webroot="${1:-/var/www/letsencrypt}"

    sudo -n mkdir -p "${webroot}/.well-known/acme-challenge"
    sudo -n chmod -R a+rX "${webroot}"
}

mini_forge_certificate_expires_at() {
    local cert="$1"
    local raw iso

    raw="$(sudo -n openssl x509 -enddate -noout -in "${cert}" 2>/dev/null | cut -d= -f2- || true)"
    [[ -n "${raw}" ]] || return 0
    iso="$(date -u -d "${raw}" +"%Y-%m-%dT%H:%M:%SZ" 2>/dev/null || true)"
    printf '%s' "${iso}"
}

mini_forge_set_app_url_scheme() {
    local scheme="$1"
    local domain="$2"
    local env_file="${3:-}"

    if [[ -z "${env_file}" ]]; then
        env_file="${MF_ROOT_PATH:-}/shared/.env"
    fi

    [[ -f "${env_file}" ]] || return 0

    SCHEME="${scheme}" DOMAIN="${domain}" ENV_FILE="${env_file}" python3 - <<'PY'
import os, re

scheme = os.environ["SCHEME"]
domain = os.environ["DOMAIN"]
path = os.environ["ENV_FILE"]

with open(path, "r", encoding="utf-8") as handle:
    text = handle.read()

pattern = re.compile(
    r'^(APP_URL=)(["\']?)https?://' + re.escape(domain) + r'(["\']?)\s*$',
    re.M,
)
replacement = rf"\1\2{scheme}://{domain}\3"
updated, count = pattern.subn(replacement, text, count=1)

if count:
    with open(path, "w", encoding="utf-8") as handle:
        handle.write(updated)
PY
}

mini_forge_assert_queue_worker_program() {
    local program="$1"

    if [[ ! "${program}" =~ ^stacklab-site-[0-9]+-worker-[0-9]+$ ]]; then
        mini_forge_fail "${STEP_KEY}" "invalid_program" "The Supervisor program name is invalid."
    fi
}

mini_forge_assert_queue_worker_config_path() {
    local program="$1"
    local path="$2"

    mini_forge_assert_queue_worker_program "${program}"

    if [[ "${path}" != "/etc/supervisor/conf.d/${program}.conf" ]]; then
        mini_forge_fail "${STEP_KEY}" "invalid_config_path" "The Supervisor configuration path is invalid."
    fi
}

mini_forge_supervisor_running_count() {
    local program="$1"
    local status_output

    status_output="$(sudo -n supervisorctl status "${program}:*" 2>&1 || true)"
    printf '%s\n' "${status_output}" | grep -cE '[[:space:]]RUNNING[[:space:]]' || true
}

mini_forge_supervisor_wait_running() {
    local program="$1"
    local expected="$2"
    local running=0

    for _ in 1 2 3 4 5 6 7 8; do
        running="$(mini_forge_supervisor_running_count "${program}")"

        if [[ "${running}" -eq "${expected}" ]]; then
            printf '%s' "${running}"
            return 0
        fi

        sleep 1
    done

    printf '%s' "${running}"
    return 1
}

mini_forge_supervisor_program_missing() {
    local program="$1"
    local status_output

    status_output="$(sudo -n supervisorctl status "${program}:*" 2>&1 || true)"
    printf '%s' "${status_output}" | grep -qiE 'no such process|ERROR:'
}
