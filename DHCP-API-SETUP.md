# 🚀 Setup DHCP API - Integração Completa

## 📋 Arquitetura

```
┌─────────────────────────┐
│   AD Manager (PHP)      │
│   Docker/Linux          │
│   localhost:8080        │
└──────────┬──────────────┘
           │ HTTP
           ▼
┌─────────────────────────┐
│   DHCP API (Node.js)    │
│   Windows Host          │
│   localhost:5001        │
└──────────┬──────────────┘
           │ PowerShell/WinRM
           ▼
┌─────────────────────────┐
│  Windows Server DHCP    │
│  srv-ad-01                │
│  192.168.1.10           │
└─────────────────────────┘
```

---

## ⚡ Setup em 3 Passos

### 1️⃣ Configurar DHCP API (Windows Host)

**Na máquina Windows (pode ser seu PC de desenvolvimento):**

```powershell
cd D:\dhcp-viewer\DhcpManager.NodeApi

# Copiar arquivo de configuração
copy .env.example .env

# Editar .env e configurar:
# - API_KEY (chave de segurança)
# - Credenciais do servidor DHCP
notepad .env
```

**Exemplo de `.env`:**
```env
PORT=5001
DHCP_SERVER=srv-ad-01
DHCP_DOMAIN=meudominio.local
DHCP_USERNAME=svc-admanager
DHCP_PASSWORD=SenhaDaContaDeServicoAqui
API_KEY=minha-chave-super-secreta-123
```

**Iniciar a API:**
```powershell
.\start-dhcp-api.bat
```

Deve aparecer:
```
========================================
  DHCP API Service
========================================
Servidor: srv-ad-01
Porta: 5001
API Key: minha-chave...

Rodando em: http://localhost:5001
========================================
```

**Testar:**
```powershell
# Health check
curl http://localhost:5001/health
```

---

### 2️⃣ Configurar AD Manager (Docker)

**Edite `ad-manager/.env.docker`:**

```env
# DHCP Management (API HTTP)
DHCP_API_URL=http://host.docker.internal:5001
DHCP_API_KEY=minha-chave-super-secreta-123
```

**IMPORTANTE:** 
- `host.docker.internal` é um DNS especial do Docker que aponta para o host Windows
- A `API_KEY` deve ser a mesma configurada na API

**Reiniciar container:**
```powershell
cd D:\dhcp-viewer\ad-manager
docker restart ad-manager-web
```

---

### 3️⃣ Testar Integração

1. **Acesse:** http://localhost:8080
2. **Faça login** (admin/admin123 ou svc-admanager/SenhaDaContaDeServicoAqui)
3. **Clique em DHCP** no menu
4. **Deve listar os escopos** do servidor srv-ad-01
5. **Teste criar/remover reservas**

---

## 🌐 Deploy em Produção (Linux)

### Cenário: AD Manager no Linux, API no Windows

**1. AD Manager (Servidor Linux - 192.168.1.10)**

Edite `.env`:
```env
DHCP_API_URL=http://192.168.1.20:5001
DHCP_API_KEY=sua-chave-producao-aqui
```

**2. DHCP API (Servidor Windows - 192.168.1.20)**

Instale como serviço Windows:

```powershell
# Instalar PM2 (gerenciador de processos Node.js)
npm install -g pm2
npm install -g pm2-windows-service

# Instalar como serviço
pm2 start dhcp-api-service.js --name dhcp-api
pm2 save
pm2-service-install
```

**3. Firewall**

No Windows Server (192.168.1.20):
```powershell
New-NetFirewallRule -DisplayName "DHCP API" -Direction Inbound -LocalPort 5001 -Protocol TCP -Action Allow
```

**4. HTTPS (Recomendado)**

Use um reverse proxy (nginx/Apache) com certificado SSL:

```nginx
server {
    listen 443 ssl;
    server_name dhcp-api.seudominio.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        proxy_pass http://localhost:5001;
        proxy_set_header X-API-Key $http_x_api_key;
    }
}
```

Então no AD Manager:
```env
DHCP_API_URL=https://dhcp-api.seudominio.com
```

---

## 🔒 Segurança

### API Key

**Gerar chave segura:**
```powershell
# PowerShell
-join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | % {[char]$_})
```

### Restrições de Rede

**Permitir apenas IPs específicos:**

Edite `dhcp-api-service.js`:
```javascript
const ALLOWED_IPS = ['192.168.1.10', '127.0.0.1'];

app.use((req, res, next) => {
    const clientIp = req.ip || req.connection.remoteAddress;
    if (!ALLOWED_IPS.includes(clientIp)) {
        return res.status(403).json({ success: false, message: 'Forbidden' });
    }
    next();
});
```

### Logs de Auditoria

A API registra todas as operações no console. Em produção, redirecione para arquivo:

```powershell
pm2 start dhcp-api-service.js --name dhcp-api --log dhcp-api.log
```

---

## 🐛 Troubleshooting

### Erro: "Erro de conexão com API DHCP"

**Causa:** AD Manager não consegue conectar na API

**Solução:**
1. Verifique se a API está rodando: `curl http://localhost:5001/health`
2. Verifique a URL no `.env`: `DHCP_API_URL`
3. No Docker, use `host.docker.internal` ao invés de `localhost`

### Erro: "Unauthorized: Invalid API Key"

**Causa:** API Key incorreta

**Solução:**
1. Verifique se a `API_KEY` é a mesma nos dois lados
2. Não use espaços ou caracteres especiais na chave

### Erro: "PowerShell command failed"

**Causa:** API não consegue conectar no servidor DHCP

**Solução:**
1. Verifique credenciais no `.env` da API
2. Teste WinRM: `Test-WSMan -ComputerName srv-ad-01`
3. Verifique se o usuário tem permissões no DHCP

### API não inicia

**Causa:** Porta 5001 já está em uso

**Solução:**
```powershell
# Verificar o que está usando a porta
netstat -ano | findstr :5001

# Matar o processo (substitua PID)
taskkill /PID <PID> /F

# Ou use outra porta no .env
PORT=5002
```

---

## 📊 Monitoramento

### Health Check

```bash
curl http://localhost:5001/health
```

Resposta esperada:
```json
{
  "success": true,
  "service": "DHCP API",
  "version": "1.0.0",
  "server": "srv-ad-01",
  "timestamp": "2026-01-20T12:00:00.000Z"
}
```

### Logs

```powershell
# Ver logs em tempo real
pm2 logs dhcp-api

# Ver últimas 100 linhas
pm2 logs dhcp-api --lines 100
```

---

## ✅ Checklist de Produção

- [ ] API Key forte e única
- [ ] HTTPS configurado
- [ ] Firewall configurado (apenas IPs necessários)
- [ ] API rodando como serviço Windows
- [ ] Logs configurados e monitorados
- [ ] Backup das configurações
- [ ] Documentação de recovery
- [ ] Testes de failover

---

## 🎯 Vantagens dessa Arquitetura

✅ **Flexibilidade:** AD Manager pode rodar em qualquer lugar (Linux, Docker, Kubernetes)
✅ **Segurança:** API isolada, autenticação via API Key
✅ **Escalabilidade:** Múltiplas instâncias do AD Manager, uma API
✅ **Manutenção:** Atualiza componentes independentemente
✅ **Performance:** API Node.js é rápida e leve
✅ **Logs:** Auditoria separada por componente

