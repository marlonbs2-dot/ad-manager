# AD Manager - Sistema Web de Gerenciamento do Active Directory

Sistema completo em PHP 8+ para gerenciamento de usuários e grupos do Active Directory com interface moderna, tema claro/escuro, relatórios PDF/Excel e controle granular de permissões por OU.

## 🎯 Características

- ✅ **Autenticação AD** - Login direto via Active Directory
- ✅ **Controle de Permissões por OU** - Delegação granular de acesso
- ✅ **Reset de Senha** - Com geração automática ou manual
- ✅ **Gerenciamento de Grupos** - Adicionar/remover membros
- ✅ **Auditoria Completa** - Registro de todas as ações
- ✅ **Relatórios PDF/Excel** - Exportação de logs e estatísticas
- ✅ **Tema Claro/Escuro** - Interface moderna e responsiva
- ✅ **Segurança** - CSRF, criptografia, rate limiting

## 📋 Requisitos

### Servidor
- PHP 8.2 ou superior
- MySQL 8.0 ou MariaDB 10.5+
- Nginx ou Apache
- Extensões PHP:
  - ldap
  - pdo_mysql
  - mbstring
  - openssl
  - zip
  - xml

### Active Directory
- Acesso LDAP/LDAPS ao controlador de domínio
- Conta de serviço com permissões de leitura/escrita
- Porta 389 (LDAP) ou 636 (LDAPS) acessível

## 🚀 Instalação

### 1. Clonar/Copiar o Projeto

```bash
cd C:\Users\marlon.borges\CascadeProjects\ad-manager
```

### 2. Instalar Dependências

```bash
composer install
```

### 3. Configurar Ambiente

Copie o arquivo `.env.example` para `.env`:

```bash
copy .env.example .env
```

Edite o arquivo `.env` e configure:

```env
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ad_manager
DB_USERNAME=root
DB_PASSWORD=sua_senha

# Application
APP_KEY=gere_uma_chave_aleatoria_aqui
ENCRYPTION_KEY=gere_outra_chave_aleatoria_aqui

# Emergency Admin
EMERGENCY_ADMIN_USER=admin
EMERGENCY_ADMIN_PASSWORD=senha_segura_aqui
```

**Gerar chaves de criptografia:**

```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 4. Criar Banco de Dados

```bash
mysql -u root -p
```

```sql
CREATE DATABASE ad_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Importe o schema:

```bash
mysql -u root -p ad_manager < database/schema.sql
```

### 5. Criar Conta de Emergência

Execute o script para criar a conta de emergência local:

```bash
php scripts/create-emergency-admin.php
```

### 6. Configurar Servidor Web

#### Nginx

```nginx
server {
    listen 80;
    server_name admanager.local;
    root /caminho/para/ad-manager/public;
    index index.php;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admanager.local;
    root /caminho/para/ad-manager/public;
    index index.php;

    ssl_certificate /caminho/para/certificado.crt;
    ssl_certificate_key /caminho/para/chave.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache

O arquivo `.htaccess` já está configurado em `public/.htaccess`.

Certifique-se de que o módulo `mod_rewrite` está ativado:

```bash
a2enmod rewrite
systemctl restart apache2
```

### 7. Configurar Permissões

```bash
# Linux
chmod -R 755 storage
chmod -R 755 logs
chown -R www-data:www-data storage logs

# Windows
# Dar permissões de escrita para o usuário do IIS/Apache nas pastas storage e logs
```

### 8. Acessar o Sistema

Acesse: `https://admanager.local`

**Primeiro Login:**
- Use a conta de emergência configurada no `.env`
- Vá em **Configurações** e configure a conexão com o AD
- Teste a conexão antes de salvar

## 📖 Uso

### Configuração Inicial

1. **Login com conta de emergência**
2. **Configurar AD** (Menu: Configurações)
   - Host do controlador de domínio
   - Base DN (ex: `DC=empresa,DC=local`)
   - Bind DN (conta de serviço)
   - Senha do Bind
   - OU de Administradores
   - OUs com permissão para reset de senha
   - OUs com permissão para gerenciar grupos
3. **Testar Conexão**
4. **Salvar Configuração**

### Gerenciar Usuários

1. Acesse **Usuários** no menu
2. Busque por nome, email ou login
3. Clique em **Ver Detalhes** para:
   - Visualizar informações completas
   - Resetar senha (manual ou automática)
   - Habilitar/Desabilitar conta
   - Ver grupos do usuário

### Gerenciar Grupos

1. Acesse **Grupos** no menu
2. Busque grupos por nome
3. Clique em **Ver Detalhes** para:
   - Ver membros do grupo
   - Adicionar novos membros
   - Remover membros

### Auditoria

1. Acesse **Auditoria** no menu
2. Aplique filtros:
   - Por usuário
   - Por tipo de ação
   - Por resultado (sucesso/falha)
   - Por período
3. Visualize logs detalhados

### Relatórios

1. Acesse **Relatórios** no menu
2. Configure filtros desejados
3. Escolha formato:
   - **PDF**: Relatório formatado com estatísticas
   - **Excel**: Planilha com dados detalhados

## 🔒 Segurança

### Implementações de Segurança

- **HTTPS Obrigatório**: Todas as requisições devem usar HTTPS
- **CSRF Protection**: Tokens CSRF em todos os formulários
- **Rate Limiting**: Limite de tentativas de login
- **Criptografia**: Senhas e credenciais criptografadas (AES-256-GCM)
- **Sessões Seguras**: HttpOnly, Secure, SameSite
- **LDAP Injection Prevention**: Validação de filtros LDAP
- **XSS Protection**: Sanitização de saídas
- **Auditoria**: Todas as ações são registradas

### Permissões por OU

O sistema permite controle granular:

1. **OU de Administradores**: Acesso total ao sistema
2. **OUs de Reset de Senha**: Podem resetar senhas apenas dentro de suas OUs
3. **OUs de Gerenciamento de Grupos**: Podem adicionar/remover membros apenas em suas OUs

**Exemplo:**
- Usuário em `OU=Suporte,DC=empresa,DC=local` pode resetar senhas apenas de usuários na mesma OU
- Não pode acessar usuários de `OU=Financeiro,DC=empresa,DC=local`

## 🎨 Tema Claro/Escuro

O sistema detecta automaticamente a preferência do sistema operacional e permite alternar manualmente.

A preferência é salva no navegador do usuário.

## 📊 Estrutura do Projeto

```
ad-manager/
├── database/           # Schema SQL
├── logs/              # Logs da aplicação
├── public/            # Pasta pública (DocumentRoot)
│   ├── assets/
│   │   ├── css/      # Estilos
│   │   └── js/       # JavaScript
│   └── index.php     # Entry point
├── src/               # Código-fonte PHP
│   ├── Controllers/  # Controllers
│   ├── Database/     # Conexão DB
│   ├── LDAP/         # Operações LDAP
│   ├── Security/     # Segurança (CSRF, Encryption)
│   ├── Services/     # Lógica de negócio
│   └── routes.php    # Definição de rotas
├── storage/           # Arquivos gerados
│   ├── reports/      # Relatórios PDF/Excel
│   └── temp/         # Arquivos temporários
├── views/             # Templates HTML
├── .env.example       # Exemplo de configuração
├── composer.json      # Dependências PHP
└── README.md          # Este arquivo
```

## 🔧 Manutenção

### Limpar Relatórios Antigos

Os relatórios são salvos em `storage/reports/`. Para limpar automaticamente:

```php
// Via código
$reportService = new \App\Services\ReportService();
$reportService->cleanOldReports(7); // Remove relatórios com mais de 7 dias
```

### Limpar Logs de Login Antigos

Os logs de tentativas de login são limpos automaticamente após 24 horas.

### Backup

**Banco de Dados:**
```bash
mysqldump -u root -p ad_manager > backup_$(date +%Y%m%d).sql
```

**Arquivos:**
```bash
tar -czf ad-manager-backup.tar.gz /caminho/para/ad-manager
```

## 🐛 Troubleshooting

### Erro: "LDAP extension not loaded"

Instale a extensão LDAP do PHP:

```bash
# Ubuntu/Debian
sudo apt-get install php8.2-ldap
sudo systemctl restart php8.2-fpm

# Windows
# Descomente extension=ldap no php.ini
```

### Erro: "Connection refused" ao conectar no AD

Verifique:
1. Firewall permite conexão na porta 389/636
2. Host do AD está correto
3. Rede permite acesso ao controlador de domínio

### Erro: "Invalid credentials" no login

Verifique:
1. Usuário existe no AD
2. Usuário está na OU de administradores configurada
3. Senha está correta

### Permissões negadas ao resetar senha

Verifique:
1. Usuário logado tem permissão na OU do usuário alvo
2. Configuração de OUs está correta
3. Conta de serviço (Bind DN) tem permissões no AD

## 📝 API Endpoints

### Autenticação
- `POST /login` - Fazer login
- `GET /logout` - Fazer logout

### Usuários
- `GET /users/search?q={query}` - Buscar usuários
- `GET /users/{dn}` - Detalhes do usuário
- `POST /users/{dn}/reset-password` - Resetar senha
- `POST /users/{dn}/enable` - Habilitar usuário
- `POST /users/{dn}/disable` - Desabilitar usuário

### Grupos
- `GET /groups/search?q={query}` - Buscar grupos
- `GET /groups/{dn}` - Detalhes do grupo
- `POST /groups/{dn}/add-member` - Adicionar membro
- `DELETE /groups/{dn}/remove-member/{member_dn}` - Remover membro

### Auditoria
- `GET /audit/logs` - Listar logs (com filtros)
- `GET /audit/statistics` - Estatísticas

### Relatórios
- `GET /reports/export?type={pdf|excel}` - Exportar relatório

### Configurações
- `GET /settings/ad` - Obter configuração AD
- `POST /settings/ad` - Salvar configuração AD
- `POST /settings/ad/test` - Testar conexão AD

## 📄 Licença

MIT License

## 👥 Suporte

Para suporte, abra uma issue no repositório ou entre em contato com o administrador do sistema.

## 🔄 Atualizações Futuras

- [ ] Cache de usuários/grupos com sincronização agendada
- [ ] API REST externa para integração
- [ ] Dashboard customizável
- [ ] Webhooks para integração SIEM
- [ ] Autenticação 2FA (TOTP)
- [ ] Suporte a múltiplos domínios AD
- [ ] Interface mobile otimizada
