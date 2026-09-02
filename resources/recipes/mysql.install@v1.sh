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

configure_local_root() {
    if sudo -n mysql -e "SELECT 1" >/dev/null 2>&1; then
        sudo -n mysql -e "INSTALL PLUGIN auth_socket SONAME 'auth_socket.so';" >/dev/null 2>&1 || true
        sudo -n mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket; FLUSH PRIVILEGES;" >/dev/null 2>&1 \
            || sudo -n mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket; FLUSH PRIVILEGES;" >/dev/null 2>&1 \
            || true
        return
    fi

    if mysql -e "SELECT 1" >/dev/null 2>&1; then
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket; FLUSH PRIVILEGES;" >/dev/null 2>&1 || true
    fi
}

changed="false"

if command -v mysqld >/dev/null 2>&1 || command -v mysql >/dev/null 2>&1 || [[ -x /usr/sbin/mysqld ]] || [[ -x /usr/bin/mysqld ]]; then
    sudo -n systemctl enable --now mysql >/dev/null 2>&1 || sudo -n systemctl enable --now mysqld >/dev/null 2>&1 || sudo -n systemctl enable --now mariadb >/dev/null 2>&1 || true
    configure_local_root
    installed_version="$(detect_mysql_version)"
    mini_forge_emit "$STEP_KEY" "true" "false" "{\"mysql_version\":\"${installed_version}\"}"
    exit 0
fi

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

configure_local_root_el() {
    local tmp_pass

    if sudo -n mysql -e "SELECT 1" >/dev/null 2>&1; then
        configure_local_root
        return
    fi

    tmp_pass="$(sudo -n grep 'temporary password' /var/log/mysqld.log 2>/dev/null | tail -n1 | awk '{print $NF}' || true)"
    [[ -n "${tmp_pass}" ]] || return 0

    mysql --connect-expired-password -uroot -p"${tmp_pass}" -e "INSTALL PLUGIN auth_socket SONAME 'auth_socket.so'; ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket; FLUSH PRIVILEGES;" >/dev/null 2>&1 \
        || mysql --connect-expired-password -uroot -p"${tmp_pass}" -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket; FLUSH PRIVILEGES;" >/dev/null 2>&1 \
        || true
}

if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
    install_mysql_el
    changed="true"
    sudo -n systemctl enable --now mysqld >/dev/null 2>&1 || sudo -n systemctl enable --now mysql >/dev/null 2>&1 || true
    configure_local_root_el

    installed_version="$(detect_mysql_version)"

    if [[ "$installed_version" != "$MF_MYSQL_VERSION" ]]; then
        mini_forge_fail "$STEP_KEY" "mysql_version_mismatch" "Expected MySQL ${MF_MYSQL_VERSION} but found ${installed_version}."
    fi

    mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"mysql_version\":\"${installed_version}\"}"
    exit 0
fi

if ! mini_forge_has_cmd gpg || ! mini_forge_has_cmd curl; then
    mini_forge_apt_update
    mini_forge_apt_install ca-certificates curl gnupg
    changed="true"
fi

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

sudo -n systemctl enable --now mysql >/dev/null 2>&1 || sudo -n systemctl enable --now mysqld >/dev/null 2>&1 || true
configure_local_root

installed_version="$(detect_mysql_version)"

if [[ "$installed_version" != "$MF_MYSQL_VERSION" ]]; then
    mini_forge_fail "$STEP_KEY" "mysql_version_mismatch" "Expected MySQL ${MF_MYSQL_VERSION} but found ${installed_version}."
fi

mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"mysql_version\":\"${installed_version}\"}"
