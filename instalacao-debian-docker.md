# AD Manager v2.0 — Guia de Instalação
### Debian 13 (Trixie) + Docker

---

## Pré-requisitos

- Servidor Debian 13 com acesso root ou sudo
- Mínimo **2 GB RAM** e **10 GB** de espaço em disco
- Acesso de rede ao servidor do Active Directory (porta 389 ou 636)
- Arquivo ZIP da aplicação: `ad-manager-v2.0-YYYY-MM-DD.zip`

---

## Etapa 1 — Instalar Docker e Docker Compose

```bash
# Atualizar pacotes
sudo apt update && sudo apt upgrade -y

# Instalar dependências
sudo apt install -y ca-certificates curl gnupg unzip

# Adicionar repositório oficial do Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | \
  sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/debian bookworm stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instalar Docker
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Verificar instalação
docker --version
docker compose version

# Permitir uso sem sudo (opcional, requer logout/login)
sudo usermod -aG docker $USER
```

---

## Etapa 2 — Preparar os arquivos da aplicação

```bash
# Criar diretório de deploy
sudo mkdir -p /opt/ad-manager
sudo chown $USER:$USER /opt/ad-manager

# Copiar o ZIP para o servidor (via scp, de sua máquina Windows)
# scp "C:\tmp\ad-manager-v2.0-2026-03-02.zip" usuario@IP-DO-SERVIDOR:/opt/ad-manager/

# Extrair o ZIP
cd /opt/ad-manager
unzip ad-manager-v2.0-*.zip

# Verificar estrutura
ls -la
# Deve mostrar: src/ views/ public/ database/ docker/ docker-compose.yml Dockerfile .env
```

---

## Etapa 3 — Configurar o arquivo [.env](file:///d:/ad-manager/.env)

```bash
cd /opt/ad-manager

# Editar as configurações
nano .env
```

Configure as seguintes seções:

```env
APP_NAME="AD Manager"

# ─── Banco de Dados ────────────────────────────────────────────
DB_HOST=ad-manager-db
DB_PORT=3306
DB_NAME=ad_manager
DB_USER=ad_manager
DB_USERNAME=ad_manager
DB_PASS=SuaSenhaBancoAqui@2024        # ← ALTERE
DB_PASSWORD=SuaSenhaBancoAqui@2024    # ← ALTERE
DB_ROOT_PASSWORD=SuaSenhaRootAqui@2024 # ← ALTERE

# ─── Conta de Emergência ───────────────────────────────────────
EMERGENCY_ADMIN_USER=admin
EMERGENCY_ADMIN_PASSWORD=SuaSenhaAdminAqui@2024  # ← ALTERE

# ─── Segurança ─────────────────────────────────────────────────
SESSION_LIFETIME=7200
ENCRYPTION_KEY=GerarChaveAleatoria32Chars____  # ← ALTERE (32 chars)

# ─── Active Directory ──────────────────────────────────────────
AD_HOST=192.168.1.10          # ← IP ou hostname do servidor AD
AD_PORT=389
AD_BASE_DN=DC=seudominio,DC=local  # ← ALTERE
AD_USERNAME=svc-admanager@seudominio.local  # ← ALTERE
AD_PASSWORD=SenhaDaContaServico  # ← ALTERE
AD_USE_TLS=false
AD_PROTOCOL=ldap
```

> **Dica:** Para gerar uma `ENCRYPTION_KEY` segura:
> ```bash
> openssl rand -hex 16
> ```

---

## Etapa 4 — Criar as pastas de dados

```bash
cd /opt/ad-manager

mkdir -p storage/reports storage/mpdf_tmp logs
chmod -R 777 storage logs
```

---

## Etapa 5 — Build e iniciar os containers

```bash
cd /opt/ad-manager

# Build da imagem (primeira vez demora ~3-5 min, baixa PowerShell Core)
docker compose build --no-cache

# Iniciar em background
docker compose up -d

# Verificar status
docker compose ps
```

Resultado esperado:
```
NAME              STATUS
ad-manager-app    Up (healthy)
ad-manager-db     Up (healthy)
```

---

## Etapa 6 — Verificar a instalação

```bash
# Ver logs da aplicação
docker compose logs -f app

# Ver logs do banco de dados
docker compose logs db

# Verificar se o PHP está respondendo
curl -sk http://localhost/login | grep -o "<title>[^<]*</title>"
# Esperado: <title>Login - AD Manager</title>
```

---

## Etapa 7 — Configurar o Active Directory na interface

1. Acesse: `http://IP-DO-SERVIDOR` no navegador
2. Faça login com a conta de emergência definida no [.env](file:///d:/ad-manager/.env):
   - **Usuário:** `admin`
   - **Senha:** valor de `EMERGENCY_ADMIN_PASSWORD`
3. Vá em **⚙ Configurações → Active Directory**
4. Clique em **"+ Adicionar Configuração"** e preencha:
   - Servidor AD, porta, Base DN, usuário e senha de serviço
5. Clique em **"Testar Conexão"** — deve exibir ✅ Conectado
6. Salve a configuração

---

## Etapa 8 — Criar pastas necessárias no container (mPDF)

```bash
docker exec -it ad-manager-app bash -c "
  mkdir -p /var/www/html/storage/reports /var/www/html/storage/mpdf_tmp &&
  chmod -R 777 /var/www/html/storage &&
  chown -R www-data:www-data /var/www/html/storage
"
```

---

## Portas utilizadas

| Serviço     | Porta | Descrição              |
|-------------|-------|------------------------|
| HTTP        | 80    | Acesso à aplicação     |
| HTTPS       | 443   | Acesso seguro (SSL)    |
| MariaDB     | 3306  | Banco (interno apenas) |

> O banco de dados **não** é exposto externamente por padrão.

---

## Comandos úteis de manutenção

```bash
# Parar a aplicação
docker compose down

# Reiniciar sem perder dados
docker compose restart

# Atualizar código e rebuild
docker compose down
# (substitua os arquivos .php, .js etc)
docker compose up -d --build

# Backup do banco de dados
docker exec ad-manager-db mysqldump \
  -u ad_manager -pSuaSenhaBancoAqui@2024 ad_manager \
  > backup-$(date +%Y%m%d).sql

# Ver logs em tempo real
docker compose logs -f

# Acessar shell do container
docker exec -it ad-manager-app bash
```

---

## Solução de Problemas

| Problema | Solução |
|---|---|
| Container não inicia | `docker compose logs app` para ver o erro |
| Erro de conexão com AD | Verifique `AD_HOST`, porta 389 e firewall |
| PDF retorna erro 500 | Execute a **Etapa 8** para criar pastas do mPDF |
| Banco não conecta | Verifique se `DB_PASS` = `DB_PASSWORD` no [.env](file:///d:/ad-manager/.env) |
| Tela em branco | `docker exec ad-manager-app cat /var/www/html/public/php_errors.log` |

---

## Atualizando a aplicação

```bash
cd /opt/ad-manager

# 1. Fazer backup do .env atual
cp .env .env.backup

# 2. Extrair arquivos novos (sobrescrever tudo exceto .env)
unzip -o ad-manager-nova-versao.zip -x ".env"

# 3. Rebuild e restart
docker compose up -d --build
```
