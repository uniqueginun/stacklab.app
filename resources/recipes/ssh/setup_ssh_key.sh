#!/usr/bin/env bash
#
# setup_ssh_key.sh
#
# Generate an SSH keypair for a given local system user, and prepare that
# user's known_hosts entry for a given remote server, so the user (e.g. a
# service account like www-data) can SSH into that remote host.
#
# Usage:
#   sudo ./setup_ssh_key.sh <user> <remote_host> <key_name> [port]
#
# Example:
#   sudo ./setup_ssh_key.sh www-data 172.22.79.228 id_ed25519_debian_vm
#   sudo ./setup_ssh_key.sh www-data 172.22.79.228 id_ed25519_debian_vm 2222
#
# Output: a single JSON object on stdout describing what happened.
# All progress/step logging goes to stderr, so stdout stays pure JSON
# and can be safely piped into `jq` or any JSON parser.

set -euo pipefail

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

log() {
    # Progress messages go to stderr, keeping stdout JSON-only
    echo "$@" >&2
}

json_escape() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\"/\\\"}"
    s="${s//$'\n'/\\n}"
    s="${s//$'\t'/\\t}"
    printf '%s' "$s"
}

fail_json() {
    # Emit a JSON error object to stdout and exit non-zero
    local message="$1"
    printf '{\n  "success": false,\n  "error": "%s"\n}\n' "$(json_escape "$message")"
    exit 1
}

# ---------------------------------------------------------------------------
# Argument parsing / validation
# ---------------------------------------------------------------------------

if [[ $# -lt 3 || $# -gt 4 ]]; then
    fail_json "Usage: $0 <user> <remote_host> <key_name> [port]"
fi

TARGET_USER="$1"
REMOTE_HOST="$2"
KEY_NAME="$3"
PORT="${4:-22}"

if [[ $EUID -ne 0 ]]; then
    fail_json "This script must be run with sudo/root privileges."
fi

if ! getent passwd "$TARGET_USER" >/dev/null; then
    fail_json "User '$TARGET_USER' does not exist on this system."
fi

USER_HOME=$(getent passwd "$TARGET_USER" | cut -d: -f6)
if [[ -z "$USER_HOME" ]]; then
    fail_json "Could not determine home directory for '$TARGET_USER'."
fi

SSH_DIR="${USER_HOME}/.ssh"
KEY_PATH="${SSH_DIR}/${KEY_NAME}"
PUB_KEY_PATH="${KEY_PATH}.pub"
KNOWN_HOSTS_PATH="${SSH_DIR}/known_hosts"

log "== Config =="
log "User:          $TARGET_USER"
log "Home:          $USER_HOME"
log "Remote host:   $REMOTE_HOST"
log "Port:          $PORT"
log "Key name:      $KEY_NAME"
log "Key path:      $KEY_PATH"
log "============"

# ---------------------------------------------------------------------------
# 1. Create .ssh directory
# ---------------------------------------------------------------------------
log "[1/6] Ensuring ${SSH_DIR} exists with correct permissions..."
install -d -m 700 -o "$TARGET_USER" -g "$TARGET_USER" "$SSH_DIR"

# ---------------------------------------------------------------------------
# 2. Generate the keypair (skip if it already exists)
# ---------------------------------------------------------------------------
KEY_GENERATED=false
KEY_ALREADY_EXISTED=false
if [[ -f "$KEY_PATH" ]]; then
    log "[2/6] Key already exists at ${KEY_PATH}, skipping generation."
    KEY_ALREADY_EXISTED=true
else
    log "[2/6] Generating ed25519 keypair..."
    # Discard ssh-keygen's own stdout (banner, fingerprint, randomart) —
    # we already derive fingerprint/type ourselves below, and we want
    # stdout to contain *only* our final JSON object.
    sudo -u "$TARGET_USER" ssh-keygen -t ed25519 -f "$KEY_PATH" -N "" >/dev/null
    KEY_GENERATED=true
fi

# ---------------------------------------------------------------------------
# 3. Fix ownership & permissions on the key files
# ---------------------------------------------------------------------------
log "[3/6] Setting ownership/permissions on key files..."
chown "$TARGET_USER":"$TARGET_USER" "$KEY_PATH" "$PUB_KEY_PATH"
chmod 600 "$KEY_PATH"
chmod 644 "$PUB_KEY_PATH"

# ---------------------------------------------------------------------------
# 4. Scan the remote host's key and add it to known_hosts
# ---------------------------------------------------------------------------
log "[4/6] Scanning ${REMOTE_HOST}:${PORT} for its host key..."
SCANNED_KEY=$(ssh-keyscan -p "$PORT" -H -t ed25519 "$REMOTE_HOST" 2>/dev/null || true)

HOST_KEY_SCANNED=false
KNOWN_HOSTS_UPDATED=false
KNOWN_HOSTS_ALREADY_PRESENT=false
WARNING=""

if [[ -z "$SCANNED_KEY" ]]; then
    WARNING="ssh-keyscan returned nothing for ${REMOTE_HOST}:${PORT}. The remote host may be unreachable, or the port/firewall may be blocking it."
    log "Warning: $WARNING"
else
    HOST_KEY_SCANNED=true
    log "[5/6] Adding host key to ${KNOWN_HOSTS_PATH}..."
    touch "$KNOWN_HOSTS_PATH"
    if grep -qF "$SCANNED_KEY" "$KNOWN_HOSTS_PATH" 2>/dev/null; then
        KNOWN_HOSTS_ALREADY_PRESENT=true
    else
        echo "$SCANNED_KEY" >> "$KNOWN_HOSTS_PATH"
        KNOWN_HOSTS_UPDATED=true
    fi
    chown "$TARGET_USER":"$TARGET_USER" "$KNOWN_HOSTS_PATH"
    chmod 600 "$KNOWN_HOSTS_PATH"
fi

# ---------------------------------------------------------------------------
# 6. Build JSON output
# ---------------------------------------------------------------------------
log "[6/6] Done."

PUBLIC_KEY_CONTENT=$(cat "$PUB_KEY_PATH")
KEY_FINGERPRINT=$(ssh-keygen -lf "$PUB_KEY_PATH" 2>/dev/null | awk '{print $2}')
KEY_TYPE=$(ssh-keygen -lf "$PUB_KEY_PATH" 2>/dev/null | awk '{print $4}' | tr -d '()')

read -r -d '' JSON_OUTPUT <<EOF || true
{
  "success": true,
  "user": "$(json_escape "$TARGET_USER")",
  "user_home": "$(json_escape "$USER_HOME")",
  "remote_host": "$(json_escape "$REMOTE_HOST")",
  "port": ${PORT},
  "key": {
    "name": "$(json_escape "$KEY_NAME")",
    "type": "$(json_escape "${KEY_TYPE:-ed25519}")",
    "private_key_path": "$(json_escape "$KEY_PATH")",
    "public_key_path": "$(json_escape "$PUB_KEY_PATH")",
    "public_key": "$(json_escape "$PUBLIC_KEY_CONTENT")",
    "fingerprint": "$(json_escape "${KEY_FINGERPRINT:-}")",
    "generated": ${KEY_GENERATED},
    "already_existed": ${KEY_ALREADY_EXISTED}
  },
  "known_hosts": {
    "path": "$(json_escape "$KNOWN_HOSTS_PATH")",
    "host_key_scanned": ${HOST_KEY_SCANNED},
    "updated": ${KNOWN_HOSTS_UPDATED},
    "already_present": ${KNOWN_HOSTS_ALREADY_PRESENT}
  },
  "warning": "$(json_escape "$WARNING")"
}
EOF

if command -v jq >/dev/null 2>&1; then
    echo "$JSON_OUTPUT" | jq .
else
    echo "$JSON_OUTPUT" | tr -d '\n' | sed 's/  */ /g'
    echo
fi
