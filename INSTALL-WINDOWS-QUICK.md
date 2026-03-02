# 🚀 Instalação Rápida no Windows (para usar DHCP)

## Por que instalar no Windows?

O módulo DHCP precisa executar comandos PowerShell remotos via WinRM, o que **não funciona dentro do Docker** devido ao isolamento de rede.

---

## 📋 Pré-requisitos

1. **XAMPP** ou **Laragon** (PHP + Apache + MySQL)
2. **Composer** (gerenciador de dependências PHP)
3. **Git** (opcional, para clonar o repositório)

---

## ⚡ Instalação em 5 Passos

### 1️⃣ Instalar XAMPP

Baixe e instale: https://www.apachefriends.org/download.html

- Marque: Apache, MySQL, PHP
- Instale em: `C:\xampp`

### 2️⃣ Instalar Composer

Baixe e instale: https://getcomposer.org/download/

### 3️⃣ Copiar Aplicação

Copie a pasta `ad-manager` para:
```
C:\xampp\htdocs\ad-manager
```

### 4️⃣ Instalar Dependências

Abra PowerShell na pasta da aplicação:

```powershell
cd C:\xampp\htdocs\ad-manager
composer install
```

### 5️⃣ Configurar Banco de Dados

1. Abra o XAMPP Control Panel
2. Inicie **Apache** e **MySQL**
3. Acesse: http://localhost/phpmyadmin
4. Crie um banco chamado `ad_manager`
5. Importe o arquivo: `database/schema.sql`

### 6️⃣ Configurar Ambiente

Copie `.env.example` para `.env`:

```powershell
copy .env.example .env
```

Edite o `.env` e configure:

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=ad_manager
DB_USERNAME=root
DB_PASSWORD=

# DHCP (AD de Testes)
DHCP_SERVER=srv-ad-01
DHCP_DOMAIN=meudominio.local
DHCP_USERNAME=svc-admanager
DHCP_PASSWORD=SenhaDaContaDeServicoAqui
```

### 7️⃣ Configurar Apache

Edite `C:\xampp\apache\conf\extra\httpd-vhosts.conf` e adicione:

```apache
<VirtualHost *:80>
    ServerName ad-manager.local
    DocumentRoot "C:/xampp/htdocs/ad-manager/public"
    
    <Directory "C:/xampp/htdocs/ad-manager/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Edite `C:\Windows\System32\drivers\etc\hosts` (como Administrador):

```
127.0.0.1 ad-manager.local
```

Reinicie o Apache no XAMPP Control Panel.

### 8️⃣ Configurar AD

Execute o script de configuração:

```powershell
cd C:\xampp\htdocs\ad-manager
php setup-ad-test.php
```

---

## ✅ Acessar Aplicação

Abra o navegador em: **http://ad-manager.local**

**Login:**
- Usuário: `admin`
- Senha: `admin123`

Ou use usuário do AD:
- Usuário: `svc-admanager`
- Senha: `SenhaDaContaDeServicoAqui`

---

## 🧪 Testar DHCP

1. Faça login
2. Clique em **DHCP** no menu
3. Deve listar os escopos do servidor `srv-ad-01`
4. Clique em um escopo para ver reservas
5. Teste criar/remover reservas

---

## 🐛 Troubleshooting

### Erro: "Could not connect to database"
- Verifique se MySQL está rodando no XAMPP
- Verifique credenciais no `.env`

### Erro: "LDAP extension not loaded"
```powershell
# Edite C:\xampp\php\php.ini
# Descomente a linha:
extension=ldap
```
Reinicie Apache.

### Erro: "PowerShell command failed"
- Verifique se WinRM está habilitado no servidor DHCP
- Teste conectividade: `Test-WSMan -ComputerName srv-ad-01`

---

## 📝 Diferenças Docker vs Windows

| Recurso | Docker | Windows Nativo |
|---------|--------|----------------|
| Autenticação AD | ✅ | ✅ |
| Usuários/Grupos | ✅ | ✅ |
| Computadores | ✅ | ✅ |
| **DHCP** | ❌ | ✅ |
| Fácil setup | ✅ | ⚠️ |
| Produção | ❌ | ✅ |

