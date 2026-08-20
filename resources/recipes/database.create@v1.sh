STEP_KEY="database.create"

: "${MF_DB_NAME:?}"
: "${MF_DB_USERNAME:?}"
: "${MF_DB_PASSWORD:?}"

mini_forge_require_cmd mysql

if [[ "${MF_DB_USERNAME}" == "root" || "${MF_DB_USERNAME}" == "mysql" || "${MF_DB_USERNAME}" == "mariadb" ]]; then
    mini_forge_fail "$STEP_KEY" "reserved_username" "Refusing to manage reserved MySQL username [${MF_DB_USERNAME}]."
fi

# Prefer sudo mysql (unix_socket). Never require a root password for administration.
mysql_cmd() {
    if sudo -n mysql -e "SELECT 1" >/dev/null 2>&1; then
        sudo -n mysql "$@"
        return
    fi

    if [[ -f /etc/mini-forge/mysql.cnf ]] && mysql --defaults-extra-file=/etc/mini-forge/mysql.cnf -e "SELECT 1" >/dev/null 2>&1; then
        mysql --defaults-extra-file=/etc/mini-forge/mysql.cnf "$@"
        return
    fi

    mini_forge_fail "$STEP_KEY" "mysql_unavailable" "MySQL/MariaDB admin access failed. Local root should use unix_socket so sudo mysql works."
}

NAME_B64="$(printf '%s' "${MF_DB_NAME}" | base64 -w0 2>/dev/null || printf '%s' "${MF_DB_NAME}" | base64 | tr -d '\n')"
USER_B64="$(printf '%s' "${MF_DB_USERNAME}" | base64 -w0 2>/dev/null || printf '%s' "${MF_DB_USERNAME}" | base64 | tr -d '\n')"
PASS_B64="$(printf '%s' "${MF_DB_PASSWORD}" | base64 -w0 2>/dev/null || printf '%s' "${MF_DB_PASSWORD}" | base64 | tr -d '\n')"

SQL="$(DB_NAME_B64="${NAME_B64}" DB_USER_B64="${USER_B64}" DB_PASS_B64="${PASS_B64}" python3 - <<'PY'
import base64, os
name = base64.standard_b64decode(os.environ["DB_NAME_B64"]).decode()
user = base64.standard_b64decode(os.environ["DB_USER_B64"]).decode()
password = base64.standard_b64decode(os.environ["DB_PASS_B64"]).decode()

def q_ident(value: str) -> str:
    return "`" + value.replace("`", "``") + "`"

def q_str(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"

print(
    f"CREATE DATABASE IF NOT EXISTS {q_ident(name)} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    f"CREATE USER IF NOT EXISTS {q_str(user)}@'%' IDENTIFIED BY {q_str(password)};"
    f"CREATE USER IF NOT EXISTS {q_str(user)}@'localhost' IDENTIFIED BY {q_str(password)};"
    f"ALTER USER {q_str(user)}@'%' IDENTIFIED BY {q_str(password)};"
    f"ALTER USER {q_str(user)}@'localhost' IDENTIFIED BY {q_str(password)};"
    f"GRANT ALL PRIVILEGES ON {q_ident(name)}.* TO {q_str(user)}@'%';"
    f"GRANT ALL PRIVILEGES ON {q_ident(name)}.* TO {q_str(user)}@'localhost';"
    f"FLUSH PRIVILEGES;"
)
PY
)"

mysql_cmd -e "${SQL}"

mini_forge_emit "$STEP_KEY" "true" "true" "{\"name\":\"${MF_DB_NAME}\",\"username\":\"${MF_DB_USERNAME}\"}"
