STEP_KEY="profile.verify"

: "${MF_EXPECT:=}"

data="$(
    python3 -c 'import json,os,shutil,subprocess

def version(cmd, args):
    if shutil.which(cmd) is None:
        return None
    try:
        completed = subprocess.run([cmd, *args], check=False, capture_output=True, text=True)
        output = (completed.stdout or completed.stderr or "").strip().splitlines()
        return output[0] if output else cmd
    except Exception:
        return cmd

print(json.dumps({
    "php": version("php", ["-v"]),
    "composer": version("composer", ["--version", "--no-ansi"]),
    "nginx": version("nginx", ["-v"]),
    "node": version("node", ["-v"]),
    "supervisor": version("supervisord", ["-v"]) or version("supervisorctl", ["version"]),
    "mysql": version("mysql", ["--version"]) or version("mariadb", ["--version"]),
    "redis": version("redis-server", ["--version"]),
}, separators=(",", ":")))'
)"

IFS=',' read -ra expected <<< "${MF_EXPECT}"

for cmd in "${expected[@]}"; do
    cmd="${cmd#"${cmd%%[![:space:]]*}"}"
    cmd="${cmd%"${cmd##*[![:space:]]}"}"

    if [[ -z "$cmd" ]]; then
        continue
    fi

    mini_forge_require_cmd "$cmd"
done

mini_forge_emit "$STEP_KEY" "true" "false" "$data"
