#!/bin/bash

# AD Manager - Debian 12/13 Automated Installation Script
# This script installs PHP, Nginx, MariaDB, and configures the AD Manager application.

set -e

# Configuration
APP_DIR="/var/www/ad-manager"
REPO_URL="https://github.com/seu-repo/ad-manager.git" # Update this if needed or use local copy
DOMAIN="admanager.local"
DB_NAME="ad_manager"
DB_USER="ad_manager_user"
# Generate a random password for DB
DB_PASS=$(openssl rand -base64 12)
# PHP Version (default for Debian 12 is 8.2)
# Try to detect default PHP version, ensuring we strip epoch (e.g. 2:8.2) and handled +identifier
PHP_VERSION=$(apt-cache show php | grep "Version:" | head -n1 | awk '{print $2}' | sed 's/^[0-9]*://' | cut -d'+' -f1 | cut -d'-' -f1 | cut -d'.' -f1,2)

if [ -z "$PHP_VERSION" ]; then
    PHP_VERSION="8.2" # Fallback
fi

echo -e "Detected PHP Version: ${PHP_VERSION}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Starting AD Manager Installation for Debian...${NC}"

# 1. Check Root
if [ "$EUID" -ne 0 ]; then 
  echo -e "${RED}Please run as root (sudo ./install-debian.sh)${NC}"
  exit 1
fi

# 2. Update System
echo -e "${YELLOW}Updating system packages...${NC}"
apt update && apt upgrade -y

# 3. Install Dependencies
echo -e "${YELLOW}Installing dependencies...${NC}"
apt install -y git curl wget unzip acl openssl
apt install -y nginx mariadb-server
apt install -y php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-common \
               php${PHP_VERSION}-ldap php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring \
               php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-curl \
               php${PHP_VERSION}-gd php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath

# Remove apache2 if verified installed by php deps (nginx conflict)
if systemctl is-active --quiet apache2; then
    echo -e "${YELLOW}Stopping Apache2 to use Nginx...${NC}"
    systemctl stop apache2
    systemctl disable apache2
fi

# 4. Install Composer
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}Installing Composer...${NC}"
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

# 5. Database Setup
echo -e "${YELLOW}Configuring Database...${NC}"

# Ensure MariaDB is running
echo -e "${YELLOW}Starting MariaDB service...${NC}"
if ! systemctl start mariadb; then
    echo -e "${YELLOW}MariaDB failed to start. Attempting LXC/Container fix...${NC}"
    # Fix for LXC/Docker containers (disable sandboxing)
    mkdir -p /etc/systemd/system/mariadb.service.d
    cat > /etc/systemd/system/mariadb.service.d/override.conf <<EOF
[Service]
ProtectSystem=full
PrivateDevices=false
ProtectHome=false
PrivateTmp=false
EOF
    systemctl daemon-reload
    systemctl start mariadb
    systemctl enable mariadb
else
    systemctl enable mariadb
fi

# Wait for MariaDB to be ready
echo -e "${YELLOW}Waiting for MariaDB to be ready...${NC}"
while ! mysqladmin ping --silent; do
    echo -n "."
    sleep 1
done
echo ""

mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# 6. Application Setup
echo -e "${YELLOW}Setting up Application...${NC}"
if [ ! -d "$APP_DIR" ]; then
    mkdir -p "$APP_DIR"
    # Copy current directory files if script is run from inside the folder, otherwise clone
    if [ -f "composer.json" ]; then
        echo "Copying files from current directory..."
        cp -R . "$APP_DIR/"
    else
        echo "Cloning from repository..."
        git clone "$REPO_URL" "$APP_DIR"
    fi
fi

cd "$APP_DIR"

# Install PHP dependencies
echo -e "${YELLOW}Installing PHP dependencies...${NC}"
composer install --no-dev --optimize-autoloader

# 7. Environment Configuration
echo -e "${YELLOW}Configuring .env...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    # Generate keys
    sed -i "s|APP_ENV=local|APP_ENV=production|g" .env
    sed -i "s|APP_DEBUG=true|APP_DEBUG=false|g" .env
    sed -i "s|DB_DATABASE=ad_manager|DB_DATABASE=${DB_NAME}|g" .env
    sed -i "s|DB_USERNAME=root|DB_USERNAME=${DB_USER}|g" .env
    sed -i "s|DB_PASSWORD=|DB_PASSWORD=${DB_PASS}|g" .env
    
    # Generate Encryption Key
    ENC_KEY=$(openssl rand -base64 32)
    sed -i "s|EncryptionKeyShouldBe32CharsLong1234|${ENC_KEY}|g" .env
fi

# 8. Setup Permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/logs"

# 9. Nginx Configuration
echo -e "${YELLOW}Configuring Nginx...${NC}"
cat > /etc/nginx/sites-available/ad-manager <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;

    ssl_certificate /etc/nginx/ssl/admanager.local.crt;
    ssl_certificate_key /etc/nginx/ssl/admanager.local.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    index index.php index.html;
    charset utf-8;

    access_log /var/log/nginx/ad-manager-access.log;
    error_log /var/log/nginx/ad-manager-error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Create SSL directory and Self-Signed Certificate
mkdir -p /etc/nginx/ssl
if [ ! -f /etc/nginx/ssl/admanager.local.crt ]; then
    echo -e "${YELLOW}Generating Self-Signed SSL...${NC}"
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/admanager.local.key \
        -out /etc/nginx/ssl/admanager.local.crt \
        -subj "/C=BR/ST=State/L=City/O=AD Manager/CN=${DOMAIN}"
fi

# Enable Site
ln -sf /etc/nginx/sites-available/ad-manager /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Restart Services
echo -e "${YELLOW}Restarting services...${NC}"
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx
systemctl enable mariadb
systemctl enable nginx
systemctl enable php${PHP_VERSION}-fpm

# 10. Run Migrations (if any DB schema needed)
# Since there isn't a migrations CLI command visible in root, we might need to import a sql file if it exists.
# For now, we assume the app handles it or instructions say so. The INSTALL.md said "Import schema".
if [ -f "database/schema.sql" ]; then
    echo -e "${YELLOW}Importing database schema...${NC}"
    mysql "${DB_NAME}" < database/schema.sql
fi

echo -e "${GREEN}Installation Complete!${NC}"
echo -e "------------------------------------------------"
echo -e "URL: https://${DOMAIN}"
echo -e "Directory: ${APP_DIR}"
echo -e "Database User: ${DB_USER}"
echo -e "Database Pass: ${DB_PASS}"
echo -e "Emergency Admin: Create one running 'php scripts/create-emergency-admin.php' inside app dir"
echo -e "------------------------------------------------"
echo -e "Please configure DNS hosts file or DNS server to point ${DOMAIN} to this server IP."
echo -e " credentials saved to /root/ad-manager-credentials.txt"

# Save Credentials
cat > /root/ad-manager-credentials.txt <<EOF
AD Manager Credentials
======================
Date: $(date)
URL: https://${DOMAIN}
Directory: ${APP_DIR}

Database:
  User: ${DB_USER}
  Pass: ${DB_PASS}
  Name: ${DB_NAME}

Next Steps:
1. Setup AD connection in Settings
2. Configure DNS
EOF
