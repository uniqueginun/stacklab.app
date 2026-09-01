<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteCertificate;

class SiteNginxConfig
{
    public const ACME_WEBROOT = '/var/www/letsencrypt';

    public function documentRoot(Site $site, bool $useCurrentSymlink = false): string
    {
        $webDirectory = $site->web_directory === '.' ? '' : '/'.trim($site->web_directory, '/');

        if ($useCurrentSymlink) {
            return $site->root_path.'/current'.$webDirectory;
        }

        return $site->root_path.$webDirectory;
    }

    public function phpFpmSocket(Site $site): string
    {
        $version = $site->php_version;

        if (! is_string($version) || preg_match('/^\d+\.\d+$/', $version) !== 1) {
            $server = $site->relationLoaded('server') ? $site->server : null;

            if ($server === null && $site->exists) {
                $site->loadMissing('server');
                $server = $site->server;
            }

            $version = $server?->provisionedPhpVersion()
                ?? SupportedPlatforms::defaultPhpVersionFor($server);
        }

        return '/run/php/php'.$version.'-fpm.sock';
    }

    public function serverBlock(
        Site $site,
        ?string $publicRoot = null,
        ?SiteCertificate $certificate = null,
        bool $forceHttp = false,
    ): string {
        $publicRoot ??= $this->documentRoot($site);

        if ($forceHttp) {
            return $this->httpOnlyBlock($site, $publicRoot);
        }

        if ($certificate === null && $site->exists) {
            $certificate = $site->activeCertificate();
        }

        if ($certificate === null) {
            return $this->httpOnlyBlock($site, $publicRoot);
        }

        return $this->httpRedirectBlock($site, $certificate)
            ."\n"
            .$this->httpsBlock($site, $publicRoot, $certificate);
    }

    public function installScript(Site $site): string
    {
        $server = $site->server;
        $domain = $site->domain;
        $rootPath = $site->root_path;
        $publicRoot = $this->documentRoot($site);
        $marker = 'mini-forge-site:'.$site->domain;
        $nginxConfig = $this->serverBlock($site, $publicRoot);

        $escapedConfig = base64_encode($nginxConfig);
        $escapedMarker = escapeshellarg($marker);
        $escapedDomain = escapeshellarg($domain);
        $escapedRoot = escapeshellarg($rootPath);
        $escapedPublic = escapeshellarg($publicRoot);
        $escapedUser = escapeshellarg($server->ssh_user);

        return <<<BASH
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:\${PATH:-}"
umask 022

DOMAIN={$escapedDomain}
ROOT={$escapedRoot}
PUBLIC_ROOT={$escapedPublic}
SSH_USER={$escapedUser}
MARKER={$escapedMarker}
CONFIG_B64={$escapedConfig}
AVAILABLE="/etc/nginx/sites-available/\${DOMAIN}"
ENABLED="/etc/nginx/sites-enabled/\${DOMAIN}"
BACKUP=""

sudo -n mkdir -p "\${PUBLIC_ROOT}"
sudo -n chown -R "\${SSH_USER}:\${SSH_USER}" "\${ROOT}"
sudo -n chmod 755 "\${ROOT}"

# www-data must traverse every parent of the site root. Home dirs are often 700.
path="\${ROOT}"
while [[ "\${path}" != "/" && -n "\${path}" ]]; do
  sudo -n chmod a+x "\${path}"
  path="\$(dirname "\${path}")"
done

if [[ -d "\${ROOT}/.ssh" ]]; then
  sudo -n chmod 700 "\${ROOT}/.ssh"
  sudo -n chmod 600 "\${ROOT}/.ssh/"* 2>/dev/null || true
fi

# Write/refresh the placeholder unless a real app index.php exists. Stale
# mini-forge-site markers from earlier deploy attempts must be overwritten.
if [[ ! -f "\${PUBLIC_ROOT}/index.php" ]]; then
  if [[ ! -f "\${PUBLIC_ROOT}/index.html" ]] || grep -q 'mini-forge-site:' "\${PUBLIC_ROOT}/index.html" 2>/dev/null; then
    printf '<!doctype html><html><body><p>%s</p></body></html>\n' "\${MARKER}" > "\${PUBLIC_ROOT}/index.html"
  fi
fi

sudo -n rm -f /etc/nginx/sites-enabled/default

if [[ -f "\${AVAILABLE}" ]]; then
  BACKUP="\$(mktemp)"
  sudo -n cp "\${AVAILABLE}" "\${BACKUP}"
fi

printf '%s' "\${CONFIG_B64}" | base64 -d | sudo -n tee "\${AVAILABLE}" >/dev/null
sudo -n ln -sfn "\${AVAILABLE}" "\${ENABLED}"

if ! sudo -n nginx -t; then
  if [[ -n "\${BACKUP}" && -f "\${BACKUP}" ]]; then
    sudo -n cp "\${BACKUP}" "\${AVAILABLE}"
    sudo -n nginx -t
    sudo -n systemctl restart nginx || true
  else
    sudo -n rm -f "\${ENABLED}"
  fi
  rm -f "\${BACKUP}"
  echo "NGINX_TEST_FAILED"
  exit 1
fi

sudo -n systemctl restart nginx || sudo -n nginx -s reload
rm -f "\${BACKUP}"
echo "SITE_DEPLOYED:\${DOMAIN}"
BASH;
    }

    private function httpOnlyBlock(Site $site, string $publicRoot): string
    {
        $domain = $site->domain;
        $acme = self::ACME_WEBROOT;

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};
    root {$publicRoot};

{$this->acmeLocation($acme)}
{$this->securityHeaders(includeHsts: false)}
{$this->appLocations($site)}
}
NGINX;
    }

    private function httpRedirectBlock(Site $site, SiteCertificate $certificate): string
    {
        $serverName = $this->serverName($site, $certificate);
        $acme = self::ACME_WEBROOT;

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$serverName};

{$this->acmeLocation($acme)}
    location / {
        return 301 https://\$host\$request_uri;
    }
}
NGINX;
    }

    private function httpsBlock(Site $site, string $publicRoot, SiteCertificate $certificate): string
    {
        $serverName = $this->serverName($site, $certificate);
        $certificatePath = $certificate->certificatePath();
        $privateKeyPath = $certificate->privateKeyPath();

        return <<<NGINX
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$serverName};
    root {$publicRoot};

    ssl_certificate {$certificatePath};
    ssl_certificate_key {$privateKeyPath};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;

{$this->securityHeaders(includeHsts: true)}
{$this->appLocations($site)}
}
NGINX;
    }

    private function serverName(Site $site, SiteCertificate $certificate): string
    {
        $domains = $certificate->domains;

        if (! is_array($domains) || $domains === []) {
            return $site->domain;
        }

        $names = array_values(array_filter(
            $domains,
            fn (mixed $domain): bool => is_string($domain) && $domain !== '',
        ));

        return $names === [] ? $site->domain : implode(' ', $names);
    }

    private function acmeLocation(string $webroot): string
    {
        return <<<NGINX
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root {$webroot};
        allow all;
    }
NGINX;
    }

    private function securityHeaders(bool $includeHsts): string
    {
        $hsts = $includeHsts
            ? "    add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;\n"
            : '';

        return <<<NGINX
{$hsts}    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
NGINX;
    }

    private function appLocations(Site $site): string
    {
        $phpSocket = $this->phpFpmSocket($site);

        return <<<NGINX
    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \\.php\$ {
        fastcgi_pass unix:{$phpSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
        fastcgi_busy_buffers_size 32k;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
NGINX;
    }
}
