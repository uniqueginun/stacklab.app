STEP_KEY="node.install"

changed="false"
needs_install="false"

if ! mini_forge_has_cmd node || ! mini_forge_has_cmd npm; then
    if ! dpkg -s nodejs >/dev/null 2>&1 || ! dpkg -s npm >/dev/null 2>&1; then
        needs_install="true"
    fi
fi

if [[ "$needs_install" == "true" ]]; then
    mini_forge_apt_update
    mini_forge_apt_install --no-install-recommends nodejs npm
    changed="true"
fi

mini_forge_require_cmd node
node_version="$(node -v)"
mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"node_version\":\"${node_version}\"}"
