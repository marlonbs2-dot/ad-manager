# 🚀 Guia Rápido - AD Manager com Docker

## ⚡ Início em 4 Passos

### 1️⃣ Certifique-se que o Docker está rodando

Abra o **Docker Desktop** e aguarde inicializar.

### 2️⃣ Execute o script de inicialização

```bash
.\docker-start.bat
```

Aguarde até ver a mensagem "Containers iniciados!"

### 3️⃣ Configure o Active Directory (PRIMEIRA VEZ)

```bash
.\docker-setup-ad.bat
```

Este passo é **obrigatório na primeira execução** para configurar a conexão com o AD.

### 4️⃣ Acesse a aplicação

Abra o navegador em: **http://localhost:8080**

**Login (Conta Local):**
- Usuário: `admin`
- Senha: `admin123`

**Login (Usuário do AD):**
- Qualquer usuário válido do Active Directory
- Exemplo: `svc-admanager` / `SenhaDaContaDeServicoAqui`

## 🎯 Testar o Módulo DHCP

1. Faça login na aplicação
2. Clique em **"DHCP"** no menu lateral
3. Veja os escopos do servidor `srv-ad-01`
4. Clique em um escopo para ver as reservas
5. Teste criar/remover reservas

## 🛑 Parar a Aplicação

```bash
.\docker-stop.bat
```

## 📊 Ver Logs

```bash
.\docker-logs.bat
```

## 🔧 Acessar Banco de Dados

**phpMyAdmin**: http://localhost:8081

- Servidor: `mysql`
- Usuário: `root`
- Senha: `root_password`

## ❓ Problemas?

### Docker não está rodando
```
Abra o Docker Desktop e aguarde inicializar
```

### Porta 8080 já em uso
```
Edite docker-compose.yml e mude a porta:
  web:
    ports:
      - "9080:80"  # Usar 9080 em vez de 8080
```

### Erro ao conectar no banco
```bash
# Aguarde o MySQL inicializar (pode levar 30 segundos)
docker-compose logs mysql

# Se persistir, reconstrua:
docker-compose down -v
docker-compose up -d
```

## 📚 Documentação Completa

- **Docker Setup**: `DOCKER-SETUP.md`
- **Integração DHCP**: `DHCP-INTEGRATION.md`
- **Instalação**: `INSTALL.md`

## 🎉 Pronto!

Agora você tem:
- ✅ AD Manager rodando localmente
- ✅ Banco MySQL configurado
- ✅ Módulo DHCP integrado
- ✅ phpMyAdmin para gerenciar dados
- ✅ Ambiente isolado e seguro para testes

**Divirta-se testando! 🚀**
