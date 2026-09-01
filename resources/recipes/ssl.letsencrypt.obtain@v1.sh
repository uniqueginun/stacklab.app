STEP_KEY="ssl.letsencrypt.obtain"

: "${MF_DOMAIN:?}"
: "${MF_NGINX_HTTP_CONFIG_B64:?}"
: "${MF_NGINX_SSL_CONFIG_B64:?}"
: "${MF_LETSENCRYPT_EMAIL:?}"
: "${MF_SSL_DOMAINS:?}"
: "${MF_SSL_CERTIFICATE_PATH:?}"

mini_forge_require_cmd certbot
mini_forge_ensure_acme_webroot
mini_forge_write_nginx_vhost "${MF_DOMAIN}" "${MF_NGINX_HTTP_CONFIG_B64}"

domain_args=()
IFS=',' read -r -a ssl_domains <<< "${MF_SSL_DOMAINS}"
for domain in "${ssl_domains[@]}"; do
    [[ -n "${domain}" ]] || continue
    domain_args+=(-d "${domain}")
done

if [[ "${#domain_args[@]}" -eq 0 ]]; then
    mini_forge_fail "$STEP_KEY" "missing_domains" "No domains were provided for Let's Encrypt."
fi

primary="${ssl_domains[0]}"

set +e
certbot_output="$(sudo -n certbot certonly --webroot \
    -w /var/www/letsencrypt \
    --cert-name "${primary}" \
    --non-interactive --agree-tos --email "${MF_LETSENCRYPT_EMAIL}" \
    --keep-until-expiry \
    "${domain_args[@]}" 2>&1)"
certbot_status=$?
set -e

if [[ "${certbot_status}" -ne 0 ]]; then
    mini_forge_fail "$STEP_KEY" "letsencrypt_failed" "${certbot_output}"
fi

if [[ ! -f "${MF_SSL_CERTIFICATE_PATH}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_certificate" "Let's Encrypt did not write a certificate at ${MF_SSL_CERTIFICATE_PATH}."
fi

mini_forge_write_nginx_vhost "${MF_DOMAIN}" "${MF_NGINX_SSL_CONFIG_B64}"
mini_forge_set_app_url_scheme "https" "${MF_DOMAIN}"

expires_at="$(mini_forge_certificate_expires_at "${MF_SSL_CERTIFICATE_PATH}")"
expires_json="null"
if [[ -n "${expires_at}" ]]; then
    expires_json="\"${expires_at}\""
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"expires_at\":${expires_json}}"
