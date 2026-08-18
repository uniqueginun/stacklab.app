STEP_KEY="server.info"

hostname="$(hostname -f 2>/dev/null || hostname)"
os_id="$(. /etc/os-release; printf '%s' "${ID}")"
os_version="$(. /etc/os-release; printf '%s' "${VERSION_ID}")"
os_pretty="$(. /etc/os-release; printf '%s' "${PRETTY_NAME}")"
arch="$(uname -m)"
kernel="$(uname -r)"
uptime_seconds="$(awk '{print int($1)}' /proc/uptime)"
cpu_cores="$(nproc 2>/dev/null || getconf _NPROCESSORS_ONLN 2>/dev/null || printf '1')"
mem_total_kb="$(awk '/MemTotal:/ {print $2}' /proc/meminfo)"
mem_available_kb="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
disk_total_kb="$(df -Pk / | awk 'NR==2 {print $2}')"
disk_available_kb="$(df -Pk / | awk 'NR==2 {print $4}')"
load_avg="$(awk '{print $1","$2","$3}' /proc/loadavg)"

data="$(
    HOSTNAME_B64="$(printf '%s' "${hostname}" | base64 -w0 2>/dev/null || printf '%s' "${hostname}" | base64 | tr -d '\n')"
    OS_ID_B64="$(printf '%s' "${os_id}" | base64 -w0 2>/dev/null || printf '%s' "${os_id}" | base64 | tr -d '\n')"
    OS_VERSION_B64="$(printf '%s' "${os_version}" | base64 -w0 2>/dev/null || printf '%s' "${os_version}" | base64 | tr -d '\n')"
    OS_PRETTY_B64="$(printf '%s' "${os_pretty}" | base64 -w0 2>/dev/null || printf '%s' "${os_pretty}" | base64 | tr -d '\n')"
    ARCH_B64="$(printf '%s' "${arch}" | base64 -w0 2>/dev/null || printf '%s' "${arch}" | base64 | tr -d '\n')"
    KERNEL_B64="$(printf '%s' "${kernel}" | base64 -w0 2>/dev/null || printf '%s' "${kernel}" | base64 | tr -d '\n')"
    LOAD_B64="$(printf '%s' "${load_avg}" | base64 -w0 2>/dev/null || printf '%s' "${load_avg}" | base64 | tr -d '\n')"

    HOSTNAME_B64="$HOSTNAME_B64" \
    OS_ID_B64="$OS_ID_B64" \
    OS_VERSION_B64="$OS_VERSION_B64" \
    OS_PRETTY_B64="$OS_PRETTY_B64" \
    ARCH_B64="$ARCH_B64" \
    KERNEL_B64="$KERNEL_B64" \
    LOAD_B64="$LOAD_B64" \
    UPTIME_SECONDS="$uptime_seconds" \
    CPU_CORES="$cpu_cores" \
    MEM_TOTAL_KB="$mem_total_kb" \
    MEM_AVAILABLE_KB="$mem_available_kb" \
    DISK_TOTAL_KB="$disk_total_kb" \
    DISK_AVAILABLE_KB="$disk_available_kb" \
    python3 -c 'import base64,json,os

def d(key):
    return base64.standard_b64decode(os.environ.get(key) or "").decode()

print(json.dumps({
    "hostname": d("HOSTNAME_B64"),
    "os": d("OS_ID_B64"),
    "os_version": d("OS_VERSION_B64"),
    "os_pretty": d("OS_PRETTY_B64"),
    "arch": d("ARCH_B64"),
    "kernel": d("KERNEL_B64"),
    "uptime_seconds": int(os.environ.get("UPTIME_SECONDS") or 0),
    "cpu_cores": int(os.environ.get("CPU_CORES") or 0),
    "memory_total_kb": int(os.environ.get("MEM_TOTAL_KB") or 0),
    "memory_available_kb": int(os.environ.get("MEM_AVAILABLE_KB") or 0),
    "disk_total_kb": int(os.environ.get("DISK_TOTAL_KB") or 0),
    "disk_available_kb": int(os.environ.get("DISK_AVAILABLE_KB") or 0),
    "load_average": d("LOAD_B64"),
}, separators=(",", ":")))'
)"

mini_forge_emit "$STEP_KEY" "true" "false" "$data"