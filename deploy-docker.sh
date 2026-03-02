#!/bin/bash

# AD Manager - Docker Deployment Script
# This script prepares the environment, generates keys, and starts the application using Docker Compose.

set -e

# Configuration
APP_DIR=$(pwd)
DB_NAME="ad_manager"
DB_USER="ad_manager"
DB_PASS=$(openssl rand -base64 12)
DB_ROOT_PASS=$(openssl rand -base64 12)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Starting AD Manager Docker Deployment...${NC}"

# 1. Check for Docker and Docker Compose
if ! command -v docker &> /dev/null; then
    echo -e "${YELLOW}Docker is not installed. Attempting to install on Debian 12...${NC}"
    
    if [ "$EUID" -ne 0 ]; then
        echo -e "${RED}Please run as root to install Docker (sudo ./deploy-docker.sh)${NC}"
        exit 1
    fi

    # Install Docker on Debian 12
    echo -e "${YELLOW}Installing Docker...${NC}"
    apt update
    apt install -y ca-certificates curl gnupg
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
    tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt update
    apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    systemctl start docker
    systemctl enable docker
    
    echo -e "${GREEN}Docker installed successfully!${NC}"
fi

if ! docker compose version &> /dev/null; then
    echo -e "${RED}Docker Compose plugin not found even after installation attempt.${NC}"
    exit 1
fi

# 2. Environment Configuration
echo -e "${YELLOW}Configuring .env...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    
    # Generate Environment Variables
    echo -e "${YELLOW}Generating secure credentials...${NC}"
    
    # Generate random Encryption Key (32 chars)
    ENC_KEY=$(openssl rand -base64 32)
    
    # Update .env file
    sed -i "s|APP_ENV=local|APP_ENV=production|g" .env
    sed -i "s|APP_DEBUG=true|APP_DEBUG=false|g" .env
    sed -i "s|DB_DATABASE=ad_manager|DB_DATABASE=${DB_NAME}|g" .env
    sed -i "s|DB_USERNAME=root|DB_USERNAME=${DB_USER}|g" .env
    sed -i "s|DB_PASSWORD=|DB_PASSWORD=${DB_PASS}|g" .env
    sed -i "s|EncryptionKeyShouldBe32CharsLong1234|${ENC_KEY}|g" .env
    
    # Add Docker specific variables if not present
    if ! grep -q "DB_ROOT_PASSWORD" .env; then
        echo "" >> .env
        echo "# Docker Configuration" >> .env
        echo "DB_ROOT_PASSWORD=${DB_ROOT_PASS}" >> .env
    fi
else
    echo -e "${YELLOW}.env file already exists. Skipping generation.${NC}"
    # Read existing DB_PASS for display
    DB_PASS=$(grep DB_PASSWORD .env | cut -d '=' -f2)
fi

# 3. Setup Permissions
echo -e "${YELLOW}Setting up permissions...${NC}"
mkdir -p storage/logs storage/framework/views storage/app/public logs
chmod -R 777 storage logs

# 4. Generate Self-Signed Certificates (if needed)
echo -e "${YELLOW}Checking SSL certificates...${NC}"
mkdir -p docker/ssl
if [ ! -f docker/ssl/server.crt ]; then
    echo -e "${YELLOW}Generating self-signed SSL certificate...${NC}"
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout docker/ssl/server.key \
        -out docker/ssl/server.crt \
        -subj "/C=BR/ST=State/L=City/O=AD Manager/CN=localhost"
    chmod 644 docker/ssl/server.crt
    chmod 600 docker/ssl/server.key
fi

# 5. Build and Start Containers
echo -e "${YELLOW}Building and starting containers...${NC}"
if ! docker compose up -d --build; then
    echo -e "${RED}Docker build failed. Checking for common LXC/Container issues...${NC}"
    
    # Attempt to fix LXC specific storage driver issues
    echo -e "${YELLOW}Attempting to configure Docker for LXC (installing fuse-overlayfs)...${NC}"
    
    if apt-get update && apt-get install -y fuse-overlayfs; then
        echo -e "${YELLOW}Configuring Docker daemon to use fuse-overlayfs...${NC}"
        
        # Backup existing config if any
        if [ -f /etc/docker/daemon.json ]; then
            cp /etc/docker/daemon.json /etc/docker/daemon.json.bak
        fi
        
        # Write new config
        mkdir -p /etc/docker
        cat > /etc/docker/daemon.json <<EOF
{
  "storage-driver": "fuse-overlayfs"
}
EOF
        
        echo -e "${YELLOW}Restarting Docker service...${NC}"
        systemctl restart docker
        
        echo -e "${YELLOW}Retrying build with fuse-overlayfs...${NC}"
        if docker compose up -d --build; then
            echo -e "${GREEN}Build succeeded with fuse-overlayfs fix!${NC}"
        else
            echo -e "${RED}Build failed with fuse-overlayfs. Attempting final fallback to VFS driver...${NC}"
            echo -e "${YELLOW}Note: VFS is slower but works in almost all LXC environments.${NC}"
            
            # Configure Docker to use VFS
            cat > /etc/docker/daemon.json <<EOF
{
  "storage-driver": "vfs"
}
EOF
            echo -e "${YELLOW}Restarting Docker service (VFS)...${NC}"
            systemctl restart docker
            
            if docker compose up -d --build; then
                 echo -e "${GREEN}Build succeeded with VFS driver!${NC}"
            else
                 echo -e "${RED}Build failed even with VFS. Please check your container configuration.${NC}"
                 echo -e "Use a privileged container or check Proxmox/LXC documentation for Docker nesting."
                 exit 1
            fi
        fi
    else
        echo -e "${RED}Failed to install fuse-overlayfs.${NC}"
        exit 1
    fi
fi

# 6. Wait for Database
echo -e "${YELLOW}Waiting for database to be ready...${NC}"
sleep 10 # Give it a moment to start initializing

# 7. Run Database Setup (if schema exists)
# In Docker, the schema.sql mapped to /docker-entrypoint-initdb.d/ runs automatically on first start.
# We just need to wait a bit or check health.

echo -e "${GREEN}Deployment Complete!${NC}"
echo -e "------------------------------------------------"
echo -e "URL: https://localhost (or server IP)"
echo -e "App Port: 80 (HTTP) / 443 (HTTPS)"
echo -e "Database User: ${DB_USER}"
echo -e "Database Pass: ${DB_PASS}"
echo -e "Emergency Admin: Run 'docker compose exec app php scripts/create-emergency-admin.php'"
echo -e "------------------------------------------------"
echo -e "To view logs: docker compose logs -f"
echo -e "To stop: docker compose down"
