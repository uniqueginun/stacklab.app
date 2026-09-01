STEP_KEY="ssl.deactivate"

: "${MF_DOMAIN:?}"
: "${MF_NGINX_HTTP_CONFIG_B64:?}"

mini_forge_write_nginx_vhost "${MF_DOMAIN}" "${MF_NGINX_HTTP_CONFIG_B64}"
mini_forge_set_app_url_scheme "http" "${MF_DOMAIN}"

mini_forge_emit "$STEP_KEY" "true" "true" "{}"
