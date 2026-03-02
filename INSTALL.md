# Guia de Instalação - AD Manager

## Pré-requisitos

### Sistema Operacional
- Windows Server 2016+ ou Linux (Ubuntu 20.04+, Debian 11+, CentOS 8+)

### Software Necessário
- PHP 8.2 ou superior
- MySQL 8.0 ou MariaDB 10.5+
- Nginx ou Apache 2.4+
- Composer (gerenciador de dependências PHP)

### Extensões PHP Obrigatórias
```
php-ldap
php-pdo
php-pdo-mysql
php-mbstring
php-openssl
php-zip
php-xml
php-json
```

## Instalação Passo a Passo

### 1. Preparar o Ambiente

#### Windows

**Instalar PHP:**
1. Baixe PHP 8.2+ de https://windows.php.net/download/
2. Extraia para `C:\php`
3. Adicione `C:\php` ao PATH do sistema
4. Copie `php.ini-production` para `php.ini`
5. Edite `php.ini` e descomente as extensões:
```ini
extension=ldap
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=zip
extension=xml
```

**Instalar MySQL:**
1. Baixe MySQL 8.0 de https://dev.mysql.com/downloads/mysql/
2. Execute o instalador
3. Configure senha root
4. Inicie o serviço MySQL

**Instalar Composer:**
1. Baixe de https://getcomposer.org/download/
2. Execute o instalador
3. Verifique: `composer --version`

#### Linux (Ubuntu/Debian)

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP e extensões
sudo apt install -y php8.2 php8.2-fpm php8.2-ldap php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-zip php8.2-cli

# Instalar MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Instalar Nginx
sudo apt install -y nginx

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Baixar o Projeto

```bash
cd /var/www  # Linux
cd C:\inetpub\wwwroot  # Windows

# Se usando Git
git clone https://github.com/seu-repo/ad-manager.git

# Ou copie os arquivos manualmente
```

### 3. Instalar Dependências

```bash
cd ad-manager
composer install --no-dev --optimize-autoloader
```

### 4. Configurar Ambiente

```bash
# Copiar arquivo de exemplo
cp .env.example .env

# Editar configurações
nano .env  # Linux
notepad .env  # Windows
```

**Configurações essenciais no .env:**

```env
# Application
APP_NAME="AD Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admanager.empresa.local

# Gerar chaves (execute: php -r "echo bin2hex(random_bytes(32));")
APP_KEY=sua_chave_aqui
ENCRYPTION_KEY=sua_chave_aqui

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ad_manager
DB_USERNAME=ad_manager_user
DB_PASSWORD=senha_segura_aqui

# Emergency Admin
EMERGENCY_ADMIN_USER=admin
EMERGENCY_ADMIN_PASSWORD=Senha@Forte123!

# Session
SESSION_LIFETIME=120
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Strict

# Rate Limiting
RATE_LIMIT_LOGIN=5
RATE_LIMIT_WINDOW=300
```

### 5. Criar Banco de Dados

```bash
# Conectar ao MySQL
mysql -u root -p
```

```sql
-- Criar banco de dados
CREATE DATABASE ad_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Criar usuário
CREATE USER 'ad_manager_user'@'localhost' IDENTIFIED BY 'senha_segura_aqui';

-- Conceder permissões
GRANT ALL PRIVILEGES ON ad_manager.* TO 'ad_manager_user'@'localhost';
FLUSH PRIVILEGES;

-- Sair
EXIT;
```

```bash
# Importar schema
mysql -u ad_manager_user -p ad_manager < database/schema.sql
```

### 6. Criar Diretórios e Permissões

#### Linux

```bash
# Criar diretórios
mkdir -p storage/reports storage/temp storage/cache logs

# Definir permissões
sudo chown -R www-data:www-data storage logs
sudo chmod -R 755 storage logs

# Proteger arquivos sensíveis
chmod 600 .env
```

#### Windows

```powershell
# Criar diretórios
New-Item -ItemType Directory -Force -Path storage\reports
New-Item -ItemType Directory -Force -Path storage\temp
New-Item -ItemType Directory -Force -Path storage\cache
New-Item -ItemType Directory -Force -Path logs

# Dar permissões ao usuário do IIS
icacls storage /grant "IIS_IUSRS:(OI)(CI)F" /T
icacls logs /grant "IIS_IUSRS:(OI)(CI)F" /T
```

### 7. Criar Conta de Emergência

```bash
php scripts/create-emergency-admin.php
```

### 8. Configurar Servidor Web

#### Nginx (Linux)

```bash
sudo nano /etc/nginx/sites-available/ad-manager
```

```nginx
server {
    listen 80;
    server_name admanager.empresa.local;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admanager.empresa.local;
    
    root /var/www/ad-manager/public;
    index index.php;

    # SSL
    ssl_certificate /etc/ssl/certs/admanager.crt;
    ssl_certificate_key /etc/ssl/private/admanager.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
# Ativar site
sudo ln -s /etc/nginx/sites-available/ad-manager /etc/nginx/sites-enabled/

# Testar configuração
sudo nginx -t

# Reiniciar Nginx
sudo systemctl restart nginx
```

#### Apache (Linux)

```bash
sudo nano /etc/apache2/sites-available/ad-manager.conf
```

```apache
<VirtualHost *:80>
    ServerName admanager.empresa.local
    Redirect permanent / https://admanager.empresa.local/
</VirtualHost>

<VirtualHost *:443>
    ServerName admanager.empresa.local
    DocumentRoot /var/www/ad-manager/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/admanager.crt
    SSLCertificateKeyFile /etc/ssl/private/admanager.key

    <Directory /var/www/ad-manager/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ad-manager-error.log
    CustomLog ${APACHE_LOG_DIR}/ad-manager-access.log combined
</VirtualHost>
```

```bash
# Ativar módulos
sudo a2enmod rewrite ssl headers

# Ativar site
sudo a2ensite ad-manager

# Testar configuração
sudo apache2ctl configtest

# Reiniciar Apache
sudo systemctl restart apache2
```

#### IIS (Windows)

1. Abra o IIS Manager
2. Adicione novo site:
   - Nome: AD Manager
   - Caminho físico: `C:\inetpub\wwwroot\ad-manager\public`
   - Binding: HTTPS, porta 443
3. Instale URL Rewrite Module
4. Configure SSL:
   - Importe certificado
   - Vincule ao site
5. Configure permissões:
   - IIS_IUSRS precisa de acesso de leitura/execução

### 9. Configurar SSL/TLS

#### Gerar Certificado Auto-assinado (Desenvolvimento)

```bash
# Linux
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/admanager.key \
    -out /etc/ssl/certs/admanager.crt
```

```powershell
# Windows
New-SelfSignedCertificate -DnsName "admanager.empresa.local" `
    -CertStoreLocation "cert:\LocalMachine\My"
```

#### Certificado de Produção

Use Let's Encrypt ou certificado da sua CA corporativa.

### 10. Configurar DNS

Adicione entrada DNS apontando para o servidor:

```
admanager.empresa.local  A  192.168.1.100
```

Ou adicione ao arquivo hosts para teste:

```bash
# Linux: /etc/hosts
# Windows: C:\Windows\System32\drivers\etc\hosts
192.168.1.100  admanager.empresa.local
```

### 11. Testar Instalação

1. Acesse: `https://admanager.empresa.local`
2. Faça login com a conta de emergência
3. Verifique se a página de login carrega corretamente

### 12. Configurar Active Directory

1. Faça login no sistema
2. Vá em **Configurações**
3. Preencha os dados do AD:
   - Host: `dc.empresa.local`
   - Porta: `389` (LDAP) ou `636` (LDAPS)
   - Base DN: `DC=empresa,DC=local`
   - Bind DN: `CN=svc_admanager,OU=Service Accounts,DC=empresa,DC=local`
   - Senha do Bind
   - OU de Administradores: `OU=IT,DC=empresa,DC=local`
4. Clique em **Testar Conexão**
5. Se bem-sucedido, clique em **Salvar**

### 13. Configurar Firewall

#### Linux (UFW)

```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

#### Windows Firewall

```powershell
New-NetFirewallRule -DisplayName "HTTP" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "HTTPS" -Direction Inbound -LocalPort 443 -Protocol TCP -Action Allow
```

### 14. Configurar Backup Automático

#### Script de Backup (Linux)

```bash
sudo nano /usr/local/bin/backup-admanager.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/backup/ad-manager"
DATE=$(date +%Y%m%d_%H%M%S)

# Criar diretório
mkdir -p $BACKUP_DIR

# Backup banco de dados
mysqldump -u ad_manager_user -p'senha' ad_manager > $BACKUP_DIR/db_$DATE.sql

# Backup arquivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/ad-manager

# Manter apenas últimos 7 dias
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup concluído: $DATE"
```

```bash
chmod +x /usr/local/bin/backup-admanager.sh

# Adicionar ao cron (diariamente às 2h)
sudo crontab -e
0 2 * * * /usr/local/bin/backup-admanager.sh
```

## Verificação Pós-Instalação

### Checklist

- [ ] Sistema acessível via HTTPS
- [ ] Login com conta de emergência funciona
- [ ] Conexão com AD testada e funcionando
- [ ] Busca de usuários retorna resultados
- [ ] Reset de senha funciona
- [ ] Gerenciamento de grupos funciona
- [ ] Logs de auditoria são registrados
- [ ] Relatórios PDF/Excel são gerados
- [ ] Tema claro/escuro funciona
- [ ] Backup configurado

### Logs para Monitorar

```bash
# Logs da aplicação
tail -f logs/app.log

# Logs do Nginx
tail -f /var/log/nginx/error.log

# Logs do PHP-FPM
tail -f /var/log/php8.2-fpm.log

# Logs do MySQL
tail -f /var/log/mysql/error.log
```

## Troubleshooting

### Problema: Página em branco

**Solução:**
```bash
# Verificar logs de erro
tail -f logs/app.log
tail -f /var/log/nginx/error.log

# Verificar permissões
ls -la storage logs
```

### Problema: Erro 500

**Solução:**
```bash
# Ativar debug temporariamente
# No .env: APP_DEBUG=true

# Verificar logs
tail -f logs/app.log
```

### Problema: Não conecta no AD

**Solução:**
1. Verificar firewall permite porta 389/636
2. Testar conectividade: `telnet dc.empresa.local 389`
3. Verificar credenciais do Bind DN
4. Verificar extensão LDAP: `php -m | grep ldap`

## Suporte

Para problemas, consulte:
- README.md
- Logs do sistema
- Documentação do PHP LDAP: https://www.php.net/manual/en/book.ldap.php
