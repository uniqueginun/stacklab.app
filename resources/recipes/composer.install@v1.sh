STEP_KEY="composer.install"

changed="false"

if ! mini_forge_has_cmd composer; then
    mini_forge_ensure_composer
    changed="true"
fi

mini_forge_require_cmd composer
composer_version="$(composer --version --no-ansi 2>/dev/null | head -n1 | tr -d '"')"

mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"composer_version\":\"${composer_version}\"}"
