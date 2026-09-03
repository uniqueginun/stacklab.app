STEP_KEY="mysql.install"

: "${MF_MYSQL_VERSION:=8.4}"

case "${MF_MYSQL_VERSION}" in
    8.0) repo_component="mysql-8.0" ;;
    8.4) repo_component="mysql-8.4-lts" ;;
    *)
        mini_forge_fail "$STEP_KEY" "invalid_mysql_version" "Unsupported MySQL version [${MF_MYSQL_VERSION}]."
        ;;
esac

detect_mysql_version() {
    local raw=""

    if command -v mysqld >/dev/null 2>&1; then
        raw="$(mysqld --version 2>/dev/null || true)"
    elif command -v mysql >/dev/null 2>&1; then
        raw="$(mysql --version 2>/dev/null || true)"
    fi

    if [[ "$raw" =~ Ver\ ([0-9]+\.[0-9]+) ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return
    fi

    if [[ "$raw" =~ Distrib\ ([0-9]+\.[0-9]+) ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return
    fi

    printf '%s' "${MF_MYSQL_VERSION}"
}

finish_mysql_install() {
    local changed="$1"
    local check_version="${2:-true}"

    if ! mini_forge_ensure_mysql_socket_auth; then
        mini_forge_fail "$STEP_KEY" "mysql_socket_auth_failed" "Unable to configure local MySQL root for sudo mysql (unix_socket)."
    fi

    installed_version="$(detect_mysql_version)"

    if [[ "${check_version}" == "true" && "$installed_version" != "$MF_MYSQL_VERSION" ]]; then
        mini_forge_fail "$STEP_KEY" "mysql_version_mismatch" "Expected MySQL ${MF_MYSQL_VERSION} but found ${installed_version}."
    fi

    mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"mysql_version\":\"${installed_version}\"}"
}

install_mysql_el() {
    local el release_rpm
    el="$(mini_forge_os_major)"

    sudo -n dnf module disable -y mysql >/dev/null 2>&1 || true

    if [[ "${MF_MYSQL_VERSION}" == "8.4" ]]; then
        release_rpm="https://dev.mysql.com/get/mysql84-community-release-el${el}-1.noarch.rpm"
    else
        release_rpm="https://dev.mysql.com/get/mysql80-community-release-el${el}-1.noarch.rpm"
    fi

    sudo -n dnf install -y "$release_rpm" \
        || sudo -n dnf install -y "https://repo.mysql.com/mysql$(printf '%s' "${MF_MYSQL_VERSION}" | tr -d .)-community-release-el${el}.rpm"

    if [[ "${MF_MYSQL_VERSION}" == "8.0" ]]; then
        sudo -n dnf config-manager --disable mysql-8.4-lts-community >/dev/null 2>&1 || true
        sudo -n dnf config-manager --enable mysql80-community >/dev/null 2>&1 || true
    fi

    mini_forge_apt_install mysql-community-server mysql-community-client
}

changed="false"

if command -v mysqld >/dev/null 2>&1 || command -v mysql >/dev/null 2>&1 || [[ -x /usr/sbin/mysqld ]] || [[ -x /usr/bin/mysqld ]]; then
    finish_mysql_install "false" "false"
    exit 0
fi

if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
    install_mysql_el
    finish_mysql_install "true"
    exit 0
fi

if ! mini_forge_has_cmd gpg || ! mini_forge_has_cmd curl; then
    mini_forge_apt_update
    mini_forge_apt_install ca-certificates curl gnupg
    changed="true"
fi

# shellcheck disable=SC1091
. /etc/os-release
os_id="${ID:-debian}"
codename="${VERSION_CODENAME:-bookworm}"

if [[ "$os_id" == "ubuntu" ]]; then
    apt_os="ubuntu"
else
    apt_os="debian"
fi

keyring="/usr/share/keyrings/mysql-apt-config.gpg"

# Oracle rotates this key. Always refresh so an expired keyring from a
# previous attempt is not reused (RPM-GPG-KEY-mysql-2023 expired Oct 2025).
curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 \
    | sudo -n gpg --batch --yes --dearmor -o "$keyring"
sudo -n chmod 644 "$keyring"
changed="true"

sudo -n tee /etc/apt/sources.list.d/mysql.list >/dev/null <<EOF
deb [signed-by=${keyring}] http://repo.mysql.com/apt/${apt_os} ${codename} ${repo_component} mysql-tools
EOF

sudo -n debconf-set-selections <<'EOF'
mysql-community-server mysql-community-server/root-pass password
mysql-community-server mysql-community-server/re-root-pass password
mysql-community-server mysql-server/default-auth-override select Use Strong Password Encryption (RECOMMENDED)
EOF

mini_forge_apt_update
mini_forge_apt_install mysql-community-server mysql-community-client
changed="true"

finish_mysql_install "$changed"
