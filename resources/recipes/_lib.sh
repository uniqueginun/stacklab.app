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

mini_forge_os_id() {
    # shellcheck disable=SC1091
    . /etc/os-release
    printf '%s' "${ID:-unknown}"
}

mini_forge_os_family() {
    case "$(mini_forge_os_id)" in
        ubuntu|debian) printf '%s' debian ;;
        ol|rhel|centos|almalinux|rocky|fedora) printf '%s' rhel ;;
        *) printf '%s' unknown ;;
    esac
}

mini_forge_os_major() {
    # shellcheck disable=SC1091
    . /etc/os-release
    printf '%s' "${VERSION_ID%%.*}"
}

mini_forge_php_short() {
    printf '%s' "${1//./}"
}

mini_forge_pkg_update() {
    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        sudo -n dnf makecache -y >/dev/null
        return
    fi

    sudo -n env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 APT_LISTCHANGES_FRONTEND=none \
        apt-get -o Dpkg::Use-Pty=0 update -y
}

mini_forge_map_pkg_name() {
    local name="$1"

    if [[ "$(mini_forge_os_family)" != "rhel" ]]; then
        printf '%s' "$name"
        return
    fi

    case "$name" in
        redis-server) printf '%s' redis ;;
        gnupg) printf '%s' gnupg2 ;;
        *) printf '%s' "$name" ;;
    esac
}

mini_forge_pkg_install() {
    local args=()
    local mapped=()
    local arg

    for arg in "$@"; do
        if [[ "$arg" == --no-install-recommends ]]; then
            continue
        fi
        args+=("$arg")
    done

    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        for arg in "${args[@]}"; do
            mapped+=("$(mini_forge_map_pkg_name "$arg")")
        done
        sudo -n dnf install -y "${mapped[@]}"
        return
    fi

    sudo -n env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 APT_LISTCHANGES_FRONTEND=none \
        apt-get -o Dpkg::Use-Pty=0 -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
        install -y "${args[@]}"
}

mini_forge_pkg_installed() {
    local name="$1"

    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        rpm -q "$(mini_forge_map_pkg_name "$name")" >/dev/null 2>&1
        return
    fi

    dpkg -s "$name" >/dev/null 2>&1
}

mini_forge_pkg_available() {
    local name="$1"

    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        sudo -n dnf list --available "$(mini_forge_map_pkg_name "$name")" >/dev/null 2>&1 \
            || rpm -q "$(mini_forge_map_pkg_name "$name")" >/dev/null 2>&1
        return
    fi

    apt-cache show "$name" >/dev/null 2>&1
}

mini_forge_apt_update() {
    mini_forge_pkg_update
}

mini_forge_apt_install() {
    mini_forge_pkg_install "$@"
}

mini_forge_apt_has_package() {
    mini_forge_pkg_available "$1"
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
    local short candidate

    if [[ -n "${MF_PHP_VERSION:-}" ]]; then
        if mini_forge_has_cmd "php${MF_PHP_VERSION}"; then
            printf '%s' "php${MF_PHP_VERSION}"
            return
        fi

        short="$(mini_forge_php_short "${MF_PHP_VERSION}")"
        if mini_forge_has_cmd "php${short}"; then
            printf '%s' "php${short}"
            return
        fi
        if [[ -x "/opt/remi/php${short}/root/usr/bin/php" ]]; then
            printf '%s' "/opt/remi/php${short}/root/usr/bin/php"
            return
        fi
    fi

    if mini_forge_has_cmd php; then
        printf '%s' php
        return
    fi

    for candidate in php8.5 php8.4 php8.3 php8.2 php8.1 php8.0 php7.4 php85 php84 php83 php82 php81; do
        if mini_forge_has_cmd "$candidate"; then
            printf '%s' "$candidate"
            return
        fi
    done

    printf '%s' php
}

mini_forge_php_packages() {
    local version="$1"
    local short

    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        short="$(mini_forge_php_short "$version")"
        printf '%s\n' \
            "php${short}-php-cli" \
            "php${short}-php-fpm" \
            "php${short}-php-mbstring" \
            "php${short}-php-xml" \
            "php${short}-php-mysqlnd" \
            "php${short}-php-pdo" \
            "php${short}-php-bcmath" \
            "php${short}-php-sqlite3" \
            "php${short}-php-pecl-zip" \
            "php${short}-php-pecl-redis6" \
            liblzf
        return
    fi

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
    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        mini_forge_apt_install "php$(mini_forge_php_short "${version}")-php-pecl-redis6"
    else
        mini_forge_apt_install "php${version}-redis"
        sudo -n phpenmod -v "${version}" redis >/dev/null 2>&1 || true
    fi

    if ! mini_forge_php_has_redis "${php_bin}"; then
        mini_forge_fail "${STEP_KEY}" "php_redis_missing" "PHP ${version} does not have the Redis extension."
    fi
}

mini_forge_php_cli_package() {
    local version="$1"

    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        printf 'php%s-php-cli' "$(mini_forge_php_short "$version")"
        return
    fi

    printf 'php%s-cli' "$version"
}

mini_forge_resolve_php_version() {
    local requested="$1"
    local candidate

    if mini_forge_apt_has_package "$(mini_forge_php_cli_package "$requested")"; then
        printf '%s' "$requested"
        return 0
    fi

    for candidate in 8.5 8.4 8.3 8.2 8.1 8.0 7.4; do
        if [[ "$candidate" == "$requested" ]]; then
            continue
        fi

        if mini_forge_apt_has_package "$(mini_forge_php_cli_package "$candidate")"; then
            printf '%s' "$candidate"
            return 0
        fi
    done

    return 1
}

mini_forge_enable_repo() {
    local repo="$1"

    sudo -n dnf config-manager --set-enabled "$repo" >/dev/null 2>&1 \
        || sudo -n dnf config-manager --enable "$repo" >/dev/null 2>&1 \
        || true
}

mini_forge_enable_epel_repo_files() {
    local file

    for file in /etc/yum.repos.d/*epel*.repo /etc/yum.repos.d/oracle-epel*.repo; do
        [[ -f "$file" ]] || continue
        sudo -n sed -i 's/^enabled=0/enabled=1/' "$file"
    done
}

mini_forge_enable_rhel_extras() {
    local major
    major="$(mini_forge_os_major)"

    sudo -n dnf install -y dnf-plugins-core >/dev/null 2>&1 || true

    mini_forge_enable_repo "ol${major}_codeready_builder"
    mini_forge_enable_repo crb
    mini_forge_enable_repo powertools

    if ! rpm -q "oracle-epel-release-el${major}" >/dev/null 2>&1 && ! rpm -q epel-release >/dev/null 2>&1; then
        sudo -n dnf install -y "oracle-epel-release-el${major}" \
            || sudo -n dnf install -y epel-release \
            || sudo -n dnf install -y "https://dl.fedoraproject.org/pub/epel/epel-release-latest-${major}.noarch.rpm" \
            || true
    fi

    # OL ships oracle-epel-release with the repo disabled. Installing the RPM
    # is not enough; Remi pecl-redis needs liblzf from EPEL.
    mini_forge_enable_repo "ol${major}_developer_EPEL"
    mini_forge_enable_repo epel
    mini_forge_enable_epel_repo_files
    sudo -n dnf makecache -y >/dev/null 2>&1 || true
}

mini_forge_enable_remi() {
    local major
    major="$(mini_forge_os_major)"

    mini_forge_enable_rhel_extras

    if ! rpm -q remi-release >/dev/null 2>&1; then
        sudo -n dnf install -y "https://rpms.remirepo.net/enterprise/remi-release-${major}.rpm"
    fi

    sudo -n dnf install -y liblzf \
        || sudo -n dnf install -y --enablerepo="ol${major}_developer_EPEL" liblzf \
        || sudo -n dnf install -y --enablerepo=epel liblzf
}

mini_forge_enable_php_repo() {
    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        mini_forge_enable_remi
        return
    fi

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

mini_forge_ensure_git() {
    if mini_forge_has_cmd git; then
        return 0
    fi

    mini_forge_apt_update
    mini_forge_apt_install git
    mini_forge_require_cmd git
}

mini_forge_disable_default_nginx_site() {
    sudo -n rm -f /etc/nginx/sites-enabled/default
    sudo -n rm -f /etc/nginx/conf.d/default.conf
}

mini_forge_ensure_www_data_user() {
    if ! getent group www-data >/dev/null 2>&1; then
        sudo -n groupadd --system www-data
    fi

    if ! getent passwd www-data >/dev/null 2>&1; then
        sudo -n useradd --system --no-create-home --gid www-data --home-dir /var/www --shell /usr/sbin/nologin www-data
    fi
}

mini_forge_selinux_allow_web() {
    mini_forge_has_cmd getenforce || return 0
    [[ "$(getenforce 2>/dev/null || true)" == "Disabled" ]] && return 0

    sudo -n dnf install -y policycoreutils-python-utils >/dev/null 2>&1 || true
    sudo -n setsebool -P httpd_enable_homedirs on || true
    sudo -n setsebool -P httpd_read_user_content on || true
    sudo -n setsebool -P httpd_can_network_connect on || true
    sudo -n setsebool -P httpd_can_network_connect_db on || true

    sudo -n mkdir -p /run/php
    if mini_forge_has_cmd semanage; then
        sudo -n semanage fcontext -a -t httpd_var_run_t '/run/php(/.*)?' >/dev/null 2>&1 \
            || sudo -n semanage fcontext -m -t httpd_var_run_t '/run/php(/.*)?' >/dev/null 2>&1 \
            || true
        sudo -n restorecon -R /run/php >/dev/null 2>&1 || true
    fi
}

mini_forge_open_http_firewall() {
    sudo -n systemctl is-active --quiet firewalld || return 0

    sudo -n firewall-cmd --permanent --add-service=http >/dev/null 2>&1 || true
    sudo -n firewall-cmd --permanent --add-service=https >/dev/null 2>&1 || true
    sudo -n firewall-cmd --reload >/dev/null 2>&1 || true
}

mini_forge_ensure_nginx_layout() {
    sudo -n mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
    mini_forge_disable_default_nginx_site

    [[ "$(mini_forge_os_family)" == "rhel" ]] || return 0

    mini_forge_ensure_www_data_user

    if [[ -f /etc/nginx/nginx.conf ]]; then
        sudo -n sed -i 's/^user[[:space:]].*/user www-data;/' /etc/nginx/nginx.conf
    fi

    if [[ ! -f /etc/nginx/conf.d/zz-sites-enabled.conf ]]; then
        printf 'include /etc/nginx/sites-enabled/*;\n' | sudo -n tee /etc/nginx/conf.d/zz-sites-enabled.conf >/dev/null
    fi

    if [[ ! -e /etc/nginx/sites-enabled/000-placeholder ]]; then
        printf '# Placeholder so the sites-enabled glob is never empty.\n' \
            | sudo -n tee /etc/nginx/sites-available/000-placeholder >/dev/null
        sudo -n ln -sfn /etc/nginx/sites-available/000-placeholder /etc/nginx/sites-enabled/000-placeholder
    fi

    mini_forge_selinux_allow_web
    mini_forge_open_http_firewall
}

mini_forge_link_php_cli() {
    local version="$1"
    local short src
    short="$(mini_forge_php_short "$version")"

    if [[ -x "/usr/bin/php${short}" ]]; then
        src="/usr/bin/php${short}"
    elif [[ -x "/opt/remi/php${short}/root/usr/bin/php" ]]; then
        src="/opt/remi/php${short}/root/usr/bin/php"
    else
        return 0
    fi

    sudo -n ln -sfn "$src" "/usr/local/bin/php${version}"
    sudo -n ln -sfn "$src" /usr/local/bin/php
}

mini_forge_php_fpm_unit() {
    local version="$1"

    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        printf 'php%s-php-fpm' "$(mini_forge_php_short "$version")"
        return
    fi

    printf 'php%s-fpm' "$version"
}

mini_forge_php_fpm_pool_conf() {
    local version="$1"
    local short
    short="$(mini_forge_php_short "$version")"

    if [[ -f "/etc/opt/remi/php${short}/php-fpm.d/www.conf" ]]; then
        printf '%s' "/etc/opt/remi/php${short}/php-fpm.d/www.conf"
        return
    fi

    if [[ -f /etc/php-fpm.d/www.conf ]]; then
        printf '%s' /etc/php-fpm.d/www.conf
        return
    fi

    printf '%s' "/etc/php/${version}/fpm/pool.d/www.conf"
}

mini_forge_configure_php_fpm() {
    local version="$1"
    local socket="/run/php/php${version}-fpm.sock"
    local conf

    [[ "$(mini_forge_os_family)" == "rhel" ]] || return 0

    mini_forge_ensure_www_data_user
    sudo -n mkdir -p /run/php
    sudo -n chown www-data:www-data /run/php || true

    conf="$(mini_forge_php_fpm_pool_conf "$version")"
    [[ -f "$conf" ]] || return 0

    sudo -n sed -i \
        -e 's/^user = .*/user = www-data/' \
        -e 's/^group = .*/group = www-data/' \
        -e "s|^listen = .*|listen = ${socket}|" \
        -e 's/^;*listen.owner = .*/listen.owner = www-data/' \
        -e 's/^;*listen.group = .*/listen.group = www-data/' \
        "$conf"
}

mini_forge_reload_php_fpm() {
    local version="${1:-${MF_PHP_VERSION:-}}"
    local unit

    [[ -n "$version" ]] || return 0

    unit="$(mini_forge_php_fpm_unit "$version")"
    sudo -n systemctl reload "$unit" 2>/dev/null \
        || sudo -n systemctl restart "$unit" 2>/dev/null \
        || sudo -n systemctl reload "php${version}-fpm" 2>/dev/null \
        || sudo -n systemctl reload php-fpm 2>/dev/null \
        || true
}

mini_forge_ensure_supervisor_layout() {
    local conf=""

    sudo -n mkdir -p /etc/supervisor/conf.d

    [[ "$(mini_forge_os_family)" == "rhel" ]] || return 0

    if [[ -f /etc/supervisord.conf ]]; then
        conf=/etc/supervisord.conf
    elif [[ -f /etc/supervisor/supervisord.conf ]]; then
        conf=/etc/supervisor/supervisord.conf
    else
        return 0
    fi

    if grep -q '/etc/supervisor/conf.d' "$conf"; then
        return 0
    fi

    if grep -qE '^files[[:space:]]*=' "$conf"; then
        sudo -n sed -i 's|^files[[:space:]]*=.*|files = supervisord.d/*.ini /etc/supervisor/conf.d/*.conf|' "$conf"
        return 0
    fi

    printf '\n[include]\nfiles = /etc/supervisor/conf.d/*.conf\n' | sudo -n tee -a "$conf" >/dev/null
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

    mini_forge_ensure_nginx_layout

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
