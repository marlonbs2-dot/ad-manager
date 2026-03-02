# 🐳 AD Manager - Setup Docker para Testes

## 📋 Pré-requisitos

- **Docker Desktop** instalado e rodando
- **Docker Compose** (já vem com Docker Desktop)
- Portas **8080** e **8081** livres

## 🚀 Início Rápido

### Windows

```bash
# Iniciar tudo
.\docker-start.bat

# Parar
.\docker-stop.bat

# Ver logs
.\docker-logs.bat
```

### Linux/Mac

```bash
# Iniciar
docker-compose up -d

# Parar
docker-compose down

# Ver logs
docker-compose logs -f web
```

## 🌐 Acessar Aplicação

Após iniciar os containers:

- **Aplicação**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081

### ⚙️ Configuração Inicial (IMPORTANTE!)

**Na primeira execução, você DEVE configurar o Active Directory:**

```bash
.\docker-setup-ad.bat
```

Ou manualmente:
```bash
docker exec -it ad-manager-web php /var/www/html/setup-ad-docker.php
```

Este script irá:
- Configurar conexão com o AD (srv-ad-01 / meudominio.local)
- Criar conta de emergência local (admin/admin123)
- Criptografar credenciais no banco de dados

### Credenciais Padrão

**Aplicação (Conta Local de Emergência):**
- Usuário: `admin`
- Senha: `admin123`

**Aplicação (Usuário do AD):**
- Qualquer usuário válido do Active Directory
- Exemplo: `svc-admanager` / `SenhaDaContaDeServicoAqui`

**phpMyAdmin:**
- Servidor: `mysql`
- Usuário: `root`
- Senha: `root_password`

**Banco de Dados:**
- Host: `localhost:3306`
- Database: `ad_manager`
- Usuário: `ad_user`
- Senha: `ad_password`

## 📦 Serviços Incluídos

### 1. MySQL 8.0
- Banco de dados da aplicação
- Porta: `3306`
- Volume persistente para dados

### 2. PHP 8.2 + Apache
- Aplicação AD Manager
- Porta: `8080`
- Extensões: PDO, MySQL, LDAP, GD, ZIP

### 3. phpMyAdmin
- Interface web para gerenciar MySQL
- Porta: `8081`

## 🔧 Comandos Úteis

### Gerenciar Containers

```bash
# Ver status
docker-compose ps

# Parar tudo
docker-compose down

# Parar e remover volumes (limpar dados)
docker-compose down -v

# Reiniciar um serviço específico
docker-compose restart web

# Reconstruir imagens
docker-compose build --no-cache
```

### Logs

```bash
# Ver logs de todos os serviços
docker-compose logs -f

# Ver logs apenas do web
docker-compose logs -f web

# Ver logs apenas do MySQL
docker-compose logs -f mysql
```

### Acessar Container

```bash
# Entrar no container web
docker-compose exec web bash

# Entrar no container MySQL
docker-compose exec mysql mysql -u root -p
```

### Banco de Dados

```bash
# Executar SQL no MySQL
docker-compose exec mysql mysql -u ad_user -pad_password ad_manager

# Backup do banco
docker-compose exec mysql mysqldump -u root -proot_password ad_manager > backup.sql

# Restaurar backup
docker-compose exec -T mysql mysql -u root -proot_password ad_manager < backup.sql
```

## 🔍 Troubleshooting

### Porta já em uso

Se as portas 8080 ou 8081 já estiverem em uso, edite `docker-compose.yml`:

```yaml
services:
  web:
    ports:
      - "9080:80"  # Mudar 8080 para 9080
```

### Container não inicia

```bash
# Ver logs de erro
docker-compose logs web

# Reconstruir do zero
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

### Erro de permissão

```bash
# Ajustar permissões (dentro do container)
docker-compose exec web chown -R www-data:www-data /var/www/html
docker-compose exec web chmod -R 755 /var/www/html
docker-compose exec web chmod -R 777 logs storage
```

### Banco não conecta

```bash
# Verificar se MySQL está pronto
docker-compose exec mysql mysqladmin ping -h localhost

# Verificar variáveis de ambiente
docker-compose exec web env | grep DB_
```

## 🧪 Testar DHCP

O módulo DHCP está configurado para conectar ao servidor real:

- **Servidor**: srv-ad-01 (192.168.1.10)
- **Domínio**: meudominio.local
- **Usuário**: svc-admanager

Para testar:

1. Acesse http://localhost:8080
2. Faça login com `admin` / `admin123`
3. Clique em "DHCP" no menu
4. Veja os escopos reais do servidor

### Modo Mock (sem servidor real)

Se quiser testar sem conectar ao servidor real, edite `.env`:

```env
DHCP_SERVER=mock
```

E modifique `DhcpService.php` para retornar dados fictícios.

## 📁 Estrutura de Volumes

```
ad-manager/
├── logs/              # Logs da aplicação (persistente)
├── storage/           # Arquivos temporários (persistente)
└── mysql_data/        # Dados do MySQL (volume Docker)
```

## 🔄 Atualizar Código

Após modificar o código:

```bash
# Reiniciar apenas o web (sem rebuild)
docker-compose restart web

# Se mudou dependências do Composer
docker-compose exec web composer install

# Se mudou Dockerfile
docker-compose build web
docker-compose up -d web
```

## 🧹 Limpar Tudo

```bash
# Parar e remover containers, volumes e imagens
docker-compose down -v --rmi all

# Limpar cache do Docker
docker system prune -a
```

## 📊 Monitoramento

### Ver uso de recursos

```bash
# CPU e memória dos containers
docker stats

# Espaço em disco
docker system df
```

## 🔐 Segurança

⚠️ **Este setup é apenas para DESENVOLVIMENTO/TESTES!**

**NÃO use em produção sem:**
- Mudar todas as senhas padrão
- Configurar HTTPS
- Restringir acesso ao phpMyAdmin
- Configurar firewall
- Usar secrets do Docker para senhas
- Implementar backup automático

## 📝 Notas

- Os dados do MySQL são persistentes (volume Docker)
- Os logs e storage são mapeados para o host
- O código é montado como volume (mudanças refletem automaticamente)
- Composer install roda automaticamente no build

## 🆘 Suporte

Se encontrar problemas:

1. Verifique os logs: `docker-compose logs -f`
2. Verifique o status: `docker-compose ps`
3. Reconstrua do zero: `docker-compose down -v && docker-compose up -d --build`
4. Consulte a documentação do Docker: https://docs.docker.com/


---

## 🔧 Reconstruir Containers

Se você fez alterações no Dockerfile, código ou configurações, reconstrua os containers:

### Windows
```bash
.\docker-rebuild.bat
```

### Linux/Mac
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

**Quando reconstruir:**
- Após atualizar o Dockerfile
- Após mudanças nas dependências do Composer
- Se houver problemas com sessões ou cache
- Após atualizar configurações do Apache

---

## 🐛 Troubleshooting

### Erro: "Invalid CSRF token" no login

**Causa:** Sessões PHP não estão sendo persistidas corretamente

**Solução:**
1. Pare os containers: `docker-compose down`
2. Crie o diretório de sessões: `mkdir storage\sessions` (Windows) ou `mkdir -p storage/sessions` (Linux)
3. Reconstrua: `.\docker-rebuild.bat` ou `docker-compose build --no-cache`
4. Inicie novamente: `docker-compose up -d`

### Testar Sessões

Acesse: http://localhost:8080/test-session.php

Deve retornar JSON com informações da sessão. Se `session_id` estiver vazio, há problema com sessões.

