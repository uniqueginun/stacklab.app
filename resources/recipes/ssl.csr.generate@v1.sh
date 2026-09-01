STEP_KEY="ssl.csr.generate"

: "${MF_DOMAIN:?}"
: "${MF_SSL_PRIVATE_KEY_PATH:?}"
: "${MF_CSR_COUNTRY:?}"
: "${MF_CSR_STATE:?}"
: "${MF_CSR_LOCALITY:?}"
: "${MF_CSR_ORGANIZATION:?}"
: "${MF_CSR_COMMON_NAME:?}"

mini_forge_require_cmd openssl

ssl_dir="$(dirname "${MF_SSL_PRIVATE_KEY_PATH}")"
csr_path="${ssl_dir}/request.csr"

sudo -n mkdir -p "${ssl_dir}"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT

COUNTRY="${MF_CSR_COUNTRY}" STATE="${MF_CSR_STATE}" LOCALITY="${MF_CSR_LOCALITY}" \
ORGANIZATION="${MF_CSR_ORGANIZATION}" OU="${MF_CSR_ORGANIZATIONAL_UNIT:-}" \
CN="${MF_CSR_COMMON_NAME}" EMAIL="${MF_CSR_EMAIL:-}" python3 - <<'PY' > "${tmp_dir}/subject.txt"
import os

def clean(value: str) -> str:
    return value.replace("\n", " ").replace("\r", " ").replace("/", " ").strip()

parts = [
    ("C", os.environ["COUNTRY"]),
    ("ST", os.environ["STATE"]),
    ("L", os.environ["LOCALITY"]),
    ("O", os.environ["ORGANIZATION"]),
]
ou = os.environ.get("OU") or ""
if ou.strip():
    parts.append(("OU", ou))
parts.append(("CN", os.environ["CN"]))
email = os.environ.get("EMAIL") or ""
if email.strip():
    parts.append(("emailAddress", email))

print("/" + "/".join(f"{key}={clean(value)}" for key, value in parts))
PY

subject="$(cat "${tmp_dir}/subject.txt")"

sudo -n openssl req -new -newkey rsa:2048 -nodes \
    -keyout "${MF_SSL_PRIVATE_KEY_PATH}" \
    -out "${csr_path}" \
    -subj "${subject}" >/dev/null

sudo -n chmod 600 "${MF_SSL_PRIVATE_KEY_PATH}"
sudo -n chmod 644 "${csr_path}"

csr="$(sudo -n cat "${csr_path}")"
csr_b64="$(printf '%s' "${csr}" | base64 -w0 2>/dev/null || printf '%s' "${csr}" | base64 | tr -d '\n')"

mini_forge_emit "$STEP_KEY" "true" "true" "{\"csr_b64\":\"${csr_b64}\"}"
