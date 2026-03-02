# Deploy com Docker (Recomendado)

Este guia descreve como fazer o deploy da aplicação usando Docker e Docker Compose, a maneira mais fácil e isolada de rodar o sistema.

## 🚀 Requisitos

- **Sistema Operacional**: Debian 12 (Recomendado) ou compatível
- **Privilégios**: Acesso root (sudo)

**Nota:** O script verifica e **instala automaticamente** o Docker e Docker Compose no Debian 12 se não estiverem presentes.

## 📦 Instalação Rápida

O projeto inclui um script automatizado que prepara o ambiente, gera credenciais seguras e inicia os containers.

### 1. Execute o Script de Deploy

```bash
# Dar permissão de execução
chmod +x deploy-docker.sh

# Executar
./deploy-docker.sh
```

O script irá:
- ✅ Criar o arquivo `.env` com senhas seguras geradas automaticamente
- ✅ Gerar certificados SSL auto-assinados (para HTTPS local)
- ✅ Ajustar permissões de diretórios
- ✅ Construir a imagem Docker otimizada (PHP 8.2 + Nginx + Supervisord)
- ✅ Iniciar o banco de dados MariaDB
- ✅ Iniciar a aplicação

### 2. Acesse o Sistema

Após o script finalizar, acesse:

```
https://localhost
```
(Aceite o aviso de segurança do certificado auto-assinado)

**Credenciais:**
As credenciais do banco de dados geradas serão exibidas no terminal.
Para criar o primeiro usuário admin, veja a seção de pós-instalação abaixo.

## 🔧 Gerenciamento

### Parar a Aplicação
```bash
docker compose down
```

### Reiniciar a Aplicação
```bash
docker compose restart
```

### Ver Logs em Tempo Real
```bash
docker compose logs -f
```

### Atualizar a Aplicação (após git pull)
```bash
docker compose up -d --build
```

## 🔐 Pós-Instalação

### Criar Administrador de Emergência

Como o banco de dados inicia vazio, crie seu primeiro usuário administrador executando este comando **dentro do container**:

```bash
# Executa o script PHP dentro do container 'app'
docker compose exec app php scripts/create-emergency-admin.php
```

Isso criará o usuário `admin` com uma senha aleatória que será exibida na tela.

---

## 📂 Visão Técnica

O deploy utiliza 2 serviços principais definidos no `docker-compose.yml`:

1.  **db (MariaDB 10.11)**
    - Persistência de dados no volume `db_data`
    - Healthcheck configurado para garantir disponibilidade

2.  **app (Imagem Customizada)**
    - Base: `php:8.2-fpm-alpine`
    - Inclui Nginx (Web Server) e Supervisord (Gerenciador de Processos) no mesmo container
    - Configurado para produção (`APP_ENV=production`)
    - Volumes mapeados para logs e storage

### Portas

- **80 (HTTP)**: Redireciona para HTTPS
- **443 (HTTPS)**: Aplicação segura

## 🐛 Troubleshooting - Proxmox / LXC

Se você encontrar erros como `permission denied` ao montar overlayfs ou `net.ipv4.ip_unprivileged_port_start`, é necessário ajustar as configurações do container LXC no Proxmox.

### Passo a Passo no Proxmox:

1.  Acesse a interface web do Proxmox.
2.  Selecione o container LXC onde você está tentando instalar.
3.  Vá em **Options** (Opções) -> **Features** (Recursos).
4.  Clique em **Edit** (Editar).
5.  Marque as caixas:
    *   ✅ **Nesting** (Essencial para Docker funcionar)
    *   ✅ **keyctl** (Recomendado)
6.  Clique em **OK**.
7.  **Reinicie o container** LXC (Shutdown > Start).

Após reiniciar, execute o `./deploy-docker.sh` novamente. O script deve funcionar imediatamente.
