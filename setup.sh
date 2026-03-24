#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────
#  smallmd setup.sh
#  Tested on: Ubuntu 22.04/24.04, Debian 12
#  Usage: sudo bash setup.sh
# ─────────────────────────────────────────────

INSTALL_DIR="/var/www/smallmd"
NGINX_CONF="/etc/nginx/sites-available/smallmd"
PHP_VER=""

print_step() { echo -e "\n\033[1;34m──\033[0m $1"; }
print_ok()   { echo -e "  \033[0;32m✓\033[0m $1"; }
print_err()  { echo -e "  \033[0;31m✗\033[0m $1"; exit 1; }

# ── 0. Must be root ──────────────────────────
if [[ $EUID -ne 0 ]]; then
    print_err "Please run as root: sudo bash setup.sh"
fi

# ── 1. Detect distro ─────────────────────────
print_step "Detecting system"
if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    OS=$ID
    print_ok "Detected: $PRETTY_NAME"
else
    print_err "Cannot detect OS"
fi

# ── 2. Install deps ──────────────────────────
print_step "Installing nginx, PHP, composer"

apt-get update -qq

# Pick the right PHP version
if apt-cache show php8.3 &>/dev/null; then
    PHP_VER="8.3"
elif apt-cache show php8.2 &>/dev/null; then
    PHP_VER="8.2"
else
    # Try Ondřej's PPA on Ubuntu
    if [[ $OS == "ubuntu" ]]; then
        apt-get install -y -qq software-properties-common
        add-apt-repository -y ppa:ondrej/php
        apt-get update -qq
        PHP_VER="8.3"
    else
        # Debian: use sury.org
        apt-get install -y -qq apt-transport-https lsb-release ca-certificates curl
        curl -sSo /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/php.list
        apt-get update -qq
        PHP_VER="8.3"
    fi
fi

apt-get install -y -qq \
    nginx \
    php${PHP_VER}-fpm \
    php${PHP_VER}-cli \
    php${PHP_VER}-mbstring \
    php${PHP_VER}-xml \
    php${PHP_VER}-zip \
    unzip \
    curl

print_ok "nginx + PHP ${PHP_VER} installed"

# Install composer if not present
if ! command -v composer &>/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    print_ok "composer installed"
else
    print_ok "composer already present"
fi

# ── 3. Copy files ────────────────────────────
print_step "Installing smallmd to $INSTALL_DIR"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ ! -f "$SCRIPT_DIR/composer.json" ]]; then
    print_err "Run setup.sh from the smallmd repo directory"
fi

mkdir -p "$INSTALL_DIR"
rsync -a --exclude='.git' --exclude='vendor' "$SCRIPT_DIR/" "$INSTALL_DIR/"
print_ok "Files copied"

# ── 4. Composer install ──────────────────────
print_step "Installing PHP dependencies"
cd "$INSTALL_DIR"
composer install --no-dev --optimize-autoloader --quiet
print_ok "Dependencies installed"

# ── 5. Permissions ───────────────────────────
print_step "Setting permissions"
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/var"
print_ok "Permissions set"

# ── 6. nginx config ──────────────────────────
print_step "Configuring nginx"

PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"

cat > "$NGINX_CONF" <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root ${INSTALL_DIR}/public;
    index index.php;

    location /assets/ {
        alias ${INSTALL_DIR}/themes/default/assets/;
        expires 7d;
        add_header Cache-Control "public";
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_pass   unix:${PHP_FPM_SOCK};
        fastcgi_param  SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param  DOCUMENT_ROOT   \$document_root;
    }

    location ~ \.(md|yaml|json)$ { deny all; }
    location ~ /\. { deny all; }
}
NGINX

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/smallmd

# Disable default site if it exists
if [[ -f /etc/nginx/sites-enabled/default ]]; then
    rm /etc/nginx/sites-enabled/default
    print_ok "Disabled nginx default site"
fi

nginx -t -q && print_ok "nginx config valid"

# ── 7. Start / restart services ──────────────
print_step "Starting services"
systemctl enable --now php${PHP_VER}-fpm nginx
systemctl restart php${PHP_VER}-fpm nginx
print_ok "nginx and PHP-FPM running"

# ── 8. Done ──────────────────────────────────
echo ""
echo -e "\033[1;32m✓ smallmd is live!\033[0m"
echo ""
echo "  Content folder : $INSTALL_DIR/content/"
echo "  Config         : $INSTALL_DIR/config/site.yaml"
echo "  Theme          : $INSTALL_DIR/themes/default/"
echo ""
echo "  To add a page  : create a .md file in content/"
echo "  To edit config : nano $INSTALL_DIR/config/site.yaml"
echo ""
echo "  Visit http://$(hostname -I | awk '{print $1}')"
echo ""
