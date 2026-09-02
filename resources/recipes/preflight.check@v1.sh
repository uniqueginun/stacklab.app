STEP_KEY="preflight.check"

os_id="$(. /etc/os-release; printf '%s' "${ID}")"
os_version="$(. /etc/os-release; printf '%s' "${VERSION_ID}")"
os_major="${os_version%%.*}"
arch="$(uname -m)"

supported="false"
case "$os_id-$os_version" in
    ubuntu-22.04|ubuntu-24.04|ubuntu-26.04|debian-12|debian-13)
        supported="true"
        ;;
esac

if [[ "$os_id" == "ol" && "$os_major" == "9" ]]; then
    supported="true"
fi

if [[ "$supported" != "true" ]]; then
    mini_forge_fail "$STEP_KEY" "unsupported_os" "Unsupported ${os_id} ${os_version}. Supported: Ubuntu 22.04, 24.04, 26.04 or Debian 12, 13 or Oracle Linux 9. PHP versions are selected per distro."
fi

case "$arch" in
    x86_64|amd64|aarch64|arm64) ;;
    *)
        mini_forge_fail "$STEP_KEY" "unsupported_arch" "Supported architectures: amd64 or arm64."
        ;;
esac

sudo -n true || mini_forge_fail "$STEP_KEY" "sudo_required" "Passwordless sudo is required."

[[ "$(ps -p 1 -o comm=)" == "systemd" ]] || mini_forge_fail "$STEP_KEY" "systemd_required" "systemd is required."

available_kb="$(df -Pk / | awk 'NR==2 {print $4}')"
[[ "${available_kb}" -ge 1048576 ]] || mini_forge_fail "$STEP_KEY" "disk_low" "At least 1GiB of free disk is required."

mem_kb="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
[[ "${mem_kb}" -ge 262144 ]] || mini_forge_fail "$STEP_KEY" "memory_low" "At least 256MiB of available memory is required."

if [[ "$(mini_forge_os_family)" == "rhel" ]]; then
    if [[ -e /var/lib/rpm/.rpm.lock ]] && ! sudo -n flock -n /var/lib/rpm/.rpm.lock true; then
        mini_forge_fail "$STEP_KEY" "dnf_locked" "Another dnf/rpm process is holding the package lock."
    fi
elif [[ -e /var/lib/dpkg/lock-frontend ]] && ! sudo -n flock -n /var/lib/dpkg/lock-frontend true; then
    mini_forge_fail "$STEP_KEY" "apt_locked" "Another apt/dpkg process is holding the package lock."
fi

getent hosts deb.debian.org >/dev/null 2>&1 \
    || getent hosts archive.ubuntu.com >/dev/null 2>&1 \
    || getent hosts yum.oracle.com >/dev/null 2>&1 \
    || getent hosts rpms.remirepo.net >/dev/null 2>&1 \
    || mini_forge_fail "$STEP_KEY" "dns_failed" "DNS resolution failed."

if command -v curl >/dev/null 2>&1; then
    curl -fsS --max-time 10 https://deb.debian.org >/dev/null 2>&1 \
        || curl -fsS --max-time 10 https://archive.ubuntu.com >/dev/null 2>&1 \
        || curl -fsS --max-time 10 https://yum.oracle.com >/dev/null 2>&1 \
        || curl -fsS --max-time 10 https://rpms.remirepo.net >/dev/null 2>&1 \
        || mini_forge_fail "$STEP_KEY" "https_failed" "Outbound HTTPS failed."
fi

mini_forge_emit "$STEP_KEY" "true" "false" "{\"os\":\"${os_id}\",\"version\":\"${os_version}\",\"arch\":\"${arch}\"}"
