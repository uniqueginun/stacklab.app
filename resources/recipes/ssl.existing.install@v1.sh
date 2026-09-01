STEP_KEY="ssl.existing.install"

: "${MF_DOMAIN:?}"
: "${MF_NGINX_SSL_CONFIG_B64:?}"
: "${MF_SSL_CERTIFICATE_B64:?}"
: "${MF_SSL_PRIVATE_KEY_B64:?}"
: "${MF_SSL_CERTIFICATE_PATH:?}"
: "${MF_SSL_PRIVATE_KEY_PATH:?}"

mini_forge_require_cmd openssl

ssl_dir="$(dirname "${MF_SSL_CERTIFICATE_PATH}")"
sudo -n mkdir -p "${ssl_dir}"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT

printf '%s' "${MF_SSL_CERTIFICATE_B64}" | base64 -d > "${tmp_dir}/cert.pem"
printf '%s' "${MF_SSL_PRIVATE_KEY_B64}" | base64 -d > "${tmp_dir}/key.raw.pem"
mini_forge_concat_pem "${tmp_dir}/key.pem" "${tmp_dir}/key.raw.pem"

if [[ -n "${MF_SSL_CHAIN_B64:-}" ]]; then
    printf '%s' "${MF_SSL_CHAIN_B64}" | base64 -d > "${tmp_dir}/chain.pem"
    mini_forge_concat_pem "${tmp_dir}/fullchain.pem" "${tmp_dir}/cert.pem" "${tmp_dir}/chain.pem"
else
    mini_forge_concat_pem "${tmp_dir}/fullchain.pem" "${tmp_dir}/cert.pem"
fi

if ! openssl x509 -in "${tmp_dir}/cert.pem" -noout >/dev/null 2>&1; then
    mini_forge_fail "$STEP_KEY" "invalid_certificate" "The certificate is not valid PEM."
fi

if ! openssl pkey -in "${tmp_dir}/key.pem" -check -noout >/dev/null 2>&1; then
    mini_forge_fail "$STEP_KEY" "invalid_private_key" "The private key is not valid PEM."
fi

cert_pub="$(openssl x509 -in "${tmp_dir}/cert.pem" -noout -pubkey 2>/dev/null | openssl md5 | awk '{print $NF}')"
key_pub="$(openssl pkey -in "${tmp_dir}/key.pem" -pubout 2>/dev/null | openssl md5 | awk '{print $NF}')"

if [[ -z "${cert_pub}" || -z "${key_pub}" || "${cert_pub}" != "${key_pub}" ]]; then
    mini_forge_fail "$STEP_KEY" "key_mismatch" "The private key does not match the certificate."
fi

sudo -n cp "${tmp_dir}/fullchain.pem" "${MF_SSL_CERTIFICATE_PATH}"
sudo -n cp "${tmp_dir}/key.pem" "${MF_SSL_PRIVATE_KEY_PATH}"
sudo -n chmod 644 "${MF_SSL_CERTIFICATE_PATH}"
sudo -n chmod 600 "${MF_SSL_PRIVATE_KEY_PATH}"

mini_forge_write_nginx_vhost "${MF_DOMAIN}" "${MF_NGINX_SSL_CONFIG_B64}"
mini_forge_set_app_url_scheme "https" "${MF_DOMAIN}"

expires_at="$(mini_forge_certificate_expires_at "${MF_SSL_CERTIFICATE_PATH}")"
expires_json="null"
if [[ -n "${expires_at}" ]]; then
    expires_json="\"${expires_at}\""
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"expires_at\":${expires_json}}"
