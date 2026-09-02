STEP_KEY="node.install"

changed="false"
needs_install="false"

if ! mini_forge_has_cmd node || ! mini_forge_has_cmd npm; then
    if ! mini_forge_pkg_installed nodejs || { ! mini_forge_has_cmd npm && ! mini_forge_pkg_installed npm; }; then
        needs_install="true"
    fi
fi

if [[ "$needs_install" == "true" ]]; then
    if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
        sudo -n dnf module enable -y nodejs:22 >/dev/null 2>&1 \
            || sudo -n dnf module enable -y nodejs:20 >/dev/null 2>&1 \
            || true
        mini_forge_apt_update
        mini_forge_apt_install nodejs
        if ! mini_forge_has_cmd npm; then
            mini_forge_apt_install npm
        fi
    else
        mini_forge_apt_update
        mini_forge_apt_install --no-install-recommends nodejs npm
    fi
    changed="true"
fi

mini_forge_require_cmd node
node_version="$(node -v)"
mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"node_version\":\"${node_version}\"}"
