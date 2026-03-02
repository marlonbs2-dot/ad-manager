# Instalação Automatizada - Debian 12 / 13 (Trixie)

Este guia descreve como usar o script de instalação automatizada para Debian 12 (Bookworm) e Debian 13 (Trixie/Testing).

## 🚀 Instalação Rápida (Recomendada)

### 1. Preparar o Sistema

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar git (se necessário)
sudo apt install -y git
```

### 2. Baixar o Projeto

```bash
# Clonar repositório (ou copiar arquivos)
cd /tmp
git clone https://github.com/seu-repo/ad-manager.git
cd ad-manager

# Ou se você já tem os arquivos, navegue até o diretório
```

### 3. Executar o Script de Instalação

```bash
# Dar permissão de execução
chmod +x install-debian.sh

# Executar como root
sudo ./install-debian.sh
```

O script irá:
- ✅ Detectar e instalar a versão do PHP adequada (8.2+) e todas as extensões
- ✅ Instalar e configurar MariaDB
- ✅ Instalar e configurar Nginx
- ✅ Instalar Composer
- ✅ Criar banco de dados e usuário
- ✅ Importar schema do banco (se disponível em `database/schema.sql`)
- ✅ Configurar arquivo .env
- ✅ Gerar certificado SSL auto-assinado
- ✅ Configurar permissões de diretório

### 4. Acessar o Sistema

Após a instalação, acesse:

```
https://admanager.local
```

(Ou o domínio que você configurou no script)

As credenciais serão exibidas no final da instalação e salvas em:
```
/root/ad-manager-credentials.txt
```

## 📋 O que o Script Instala

### Pacotes do Sistema
- PHP (Versão mais recente disponível no repositório, ex: 8.2 ou 8.3)
- Extensões PHP: ldap, mysql, mbstring, xml, zip, curl, gd, intl, bcmath
- MariaDB
- Nginx
- Composer
- Ferramentas: curl, wget, git, unzip, openssl

### Configurações
- Banco de dados: `ad_manager`
- Usuário DB: `ad_manager_user` (senha gerada automaticamente)
- Diretório: `/var/www/ad-manager`
- Certificado SSL auto-assinado

## 🔧 Personalização

### Alterar Domínio

Edite o script antes de executar:

```bash
nano install-debian.sh
```

Altere a linha:
```bash
DOMAIN="admanager.local"
```

Para:
```bash
DOMAIN="seu-dominio.com"
```

### Alterar Diretório de Instalação

```bash
INSTALL_DIR="/var/www/ad-manager"
```

Para:
```bash
INSTALL_DIR="/caminho/desejado"
```

## 🔐 Segurança

### Certificado SSL para Produção

O script gera um certificado auto-assinado. Para produção, use Let's Encrypt:

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obter certificado
sudo certbot --nginx -d seu-dominio.com

# Renovação automática já está configurada
```

### Alterar Senha do Admin

```bash
cd /var/www/ad-manager

# Editar .env
sudo nano .env

# Alterar EMERGENCY_ADMIN_PASSWORD
# Salvar e recriar conta
sudo php scripts/create-emergency-admin.php
```

## 📊 Pós-Instalação

### 1. Configurar Active Directory

1. Acesse o sistema
2. Vá em **Configurações**
3. Preencha:
   - Host do AD
   - Base DN
   - Bind DN (conta de serviço)
   - Senha do Bind
   - OU de Administradores
4. Teste a conexão
5. Salve

### 2. Configurar DNS

Adicione um registro A apontando para o servidor:

```
admanager.empresa.local  A  192.168.1.100
```

### 3. Verificar Serviços

```bash
# Status dos serviços
sudo systemctl status nginx
sudo systemctl status php*-fpm
sudo systemctl status mariadb

# Logs
sudo tail -f /var/log/nginx/ad-manager-error.log
sudo tail -f /var/www/ad-manager/logs/app.log
```

## 🔄 Backup e Restauração

### Backup Manual

```bash
# Exemplo de backup do banco
mysqldump -u ad_manager_user -p ad_manager > backup.sql
```

## 🛠️ Manutenção

### Atualizar Sistema

```bash
cd /var/www/ad-manager
sudo git pull  # Se usando git
sudo composer install --no-dev --optimize-autoloader
# Reiniciar PHP (ajuste a versão conforme instalado)
sudo systemctl restart php*-fpm
```
