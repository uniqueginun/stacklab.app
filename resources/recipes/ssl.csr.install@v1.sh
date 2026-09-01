STEP_KEY="ssl.csr.install"

: "${MF_DOMAIN:?}"
: "${MF_NGINX_SSL_CONFIG_B64:?}"
: "${MF_SSL_CERTIFICATE_B64:?}"
: "${MF_SSL_CERTIFICATE_PATH:?}"
: "${MF_SSL_PRIVATE_KEY_PATH:?}"

mini_forge_require_cmd openssl

if [[ ! -f "${MF_SSL_PRIVATE_KEY_PATH}" ]]; then
    mini_forge_fail "$STEP_KEY" "missing_private_key" "The CSR private key was not found on the server."
fi

ssl_dir="$(dirname "${MF_SSL_CERTIFICATE_PATH}")"
sudo -n mkdir -p "${ssl_dir}"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT

printf '%s' "${MF_SSL_CERTIFICATE_B64}" | base64 -d > "${tmp_dir}/cert.pem"

if [[ -n "${MF_SSL_CHAIN_B64:-}" ]]; then
    printf '%s' "${MF_SSL_CHAIN_B64}" | base64 -d > "${tmp_dir}/chain.pem"
    mini_forge_concat_pem "${tmp_dir}/fullchain.pem" "${tmp_dir}/cert.pem" "${tmp_dir}/chain.pem"
else
    mini_forge_concat_pem "${tmp_dir}/fullchain.pem" "${tmp_dir}/cert.pem"
fi

if ! openssl x509 -in "${tmp_dir}/cert.pem" -noout >/dev/null 2>&1; then
    mini_forge_fail "$STEP_KEY" "invalid_certificate" "The certificate is not valid PEM."
fi

cert_pub="$(openssl x509 -in "${tmp_dir}/cert.pem" -noout -pubkey 2>/dev/null | openssl md5 | awk '{print $NF}')"
key_pub="$(sudo -n openssl pkey -in "${MF_SSL_PRIVATE_KEY_PATH}" -pubout 2>/dev/null | openssl md5 | awk '{print $NF}')"

if [[ -z "${cert_pub}" || -z "${key_pub}" || "${cert_pub}" != "${key_pub}" ]]; then
    mini_forge_fail "$STEP_KEY" "key_mismatch" "The certificate does not match the CSR private key on the server."
fi

sudo -n cp "${tmp_dir}/fullchain.pem" "${MF_SSL_CERTIFICATE_PATH}"
sudo -n chmod 644 "${MF_SSL_CERTIFICATE_PATH}"

mini_forge_write_nginx_vhost "${MF_DOMAIN}" "${MF_NGINX_SSL_CONFIG_B64}"
mini_forge_set_app_url_scheme "https" "${MF_DOMAIN}"

expires_at="$(mini_forge_certificate_expires_at "${MF_SSL_CERTIFICATE_PATH}")"
expires_json="null"
if [[ -n "${expires_at}" ]]; then
    expires_json="\"${expires_at}\""
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"expires_at\":${expires_json}}"
