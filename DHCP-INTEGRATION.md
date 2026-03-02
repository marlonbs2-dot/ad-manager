# Integração DHCP no AD Manager

## 📋 Visão Geral

O módulo DHCP foi integrado ao AD Manager, permitindo gerenciar escopos e reservas DHCP do Windows Server diretamente pela interface web.

## ✅ Funcionalidades Implementadas

- ✅ Listar escopos DHCP
- ✅ Visualizar reservas por escopo
- ✅ Criar novas reservas
- ✅ Remover reservas existentes
- ✅ Interface integrada ao design do AD Manager

## 🔧 Configuração

### 1. Adicionar Variáveis de Ambiente

Edite o arquivo `.env` e adicione:

```env
# DHCP Management
DHCP_SERVER=srv-ad-01
DHCP_DOMAIN=meudominio.local
DHCP_USERNAME=svc-admanager
DHCP_PASSWORD=sua_senha_aqui
```

### 2. Configurar WinRM no Servidor DHCP

Execute no servidor Windows Server como Administrador:

```powershell
Enable-PSRemoting -Force
winrm quickconfig -q
winrm set winrm/config/service/auth '@{Basic="true"}'
winrm set winrm/config/service/auth '@{Kerberos="true"}'
```

### 3. Testar Conectividade

Execute no cliente:

```powershell
Test-WSMan -ComputerName srv-ad-01
```

## 📁 Arquivos Criados

```
ad-manager/
├── src/
│   ├── Controllers/
│   │   └── DhcpController.php          # Controller DHCP
│   ├── Services/
│   │   └── DhcpService.php             # Lógica de negócio DHCP
│   └── routes.php                       # Rotas atualizadas
├── views/
│   ├── dhcp.php                         # View DHCP
│   └── layout.php                       # Menu atualizado
└── public/
    └── assets/
        └── js/
            └── dhcp.js                  # JavaScript DHCP
```

## 🔐 Segurança

### ✅ Implementado

- ✓ **Proteção CSRF**: Todas as operações POST/DELETE validam token CSRF
- ✓ **Autenticação**: Requer login no AD Manager
- ✓ **Prepared Statements**: Proteção contra SQL Injection

### ⚠️ Importante para Produção

**ATENÇÃO:** Esta aplicação NÃO está pronta para produção. Consulte `SECURITY-CHECKLIST.md` para lista completa.

Antes de usar em produção, você **DEVE** implementar:

1. **HTTPS Obrigatório**
   - Certificado SSL/TLS válido
   - Forçar redirecionamento HTTP → HTTPS

2. **Credenciais Seguras**
   - Mover credenciais do código para variáveis de ambiente
   - Usar conta de serviço com permissões mínimas
   - Implementar rotação de senhas

3. **Validação de Entrada**
   - Validar formato de IPs (regex + range)
   - Validar formato de MACs
   - Sanitizar inputs antes de passar para PowerShell
   - Prevenir command injection

4. **Logs de Auditoria Completos**
   - Registrar TODAS as operações DHCP
   - Incluir: usuário, timestamp, IP origem, ação, resultado
   - Armazenar logs em local seguro

5. **Rate Limiting**
   - Limitar requisições por IP/usuário
   - Proteger contra brute force

6. **Controle de Acesso Granular**
   - Separar permissões: visualizar vs modificar
   - Limitar quais escopos cada usuário pode gerenciar

**📄 Leia o arquivo `SECURITY-CHECKLIST.md` para detalhes completos.**

## 🚀 Uso

### Acessar o Módulo DHCP

1. Faça login no AD Manager
2. Clique em "DHCP" no menu lateral
3. Selecione um escopo para ver as reservas
4. Use os botões para criar ou remover reservas

### API Endpoints

```
GET    /dhcp                                    # Página DHCP
GET    /dhcp/api/scopes                         # Listar escopos
GET    /dhcp/api/scopes/{scopeId}/reservations  # Listar reservas
POST   /dhcp/api/reservations                   # Criar reserva
DELETE /dhcp/api/scopes/{scopeId}/reservations/{ipAddress}  # Remover reserva
```

## 🐛 Troubleshooting

### Erro: "Falha ao executar comando PowerShell"

**Causa:** WinRM não configurado ou credenciais inválidas

**Solução:**
1. Verificar se WinRM está habilitado no servidor
2. Testar credenciais manualmente
3. Verificar variáveis de ambiente no `.env`

### Erro: "Parameter set cannot be resolved"

**Causa:** Conflito de parâmetros no cmdlet PowerShell

**Solução:** Já corrigido - usa apenas `-IPAddress` para remover reservas

### Erro: "Nenhum escopo encontrado"

**Causa:** Servidor DHCP sem escopos configurados ou sem permissões

**Solução:**
1. Verificar se há escopos no servidor DHCP
2. Confirmar que o usuário tem permissões administrativas

## 📝 Próximos Passos

Para melhorar o módulo DHCP:

- [ ] Adicionar edição de reservas
- [ ] Implementar filtros e busca
- [ ] Adicionar visualização de leases ativos
- [ ] Criar relatórios de utilização de escopos
- [ ] Implementar backup/restore de configurações
- [ ] Adicionar suporte a IPv6
- [ ] Integrar com sistema de auditoria do AD Manager

## 📞 Suporte

Para problemas ou dúvidas sobre a integração DHCP, consulte:
- Documentação do AD Manager
- Logs em `logs/`
- Issues no repositório


---

## ⚠️ Limitação no Docker

O módulo DHCP **NÃO funciona completamente no Docker** porque:

1. **PowerShell Remoting (WinRM)** requer acesso direto à rede Windows
2. O container Docker está isolado e não consegue fazer conexões WinRM
3. Comandos PowerShell remotos falham com timeout ou erro de conexão

### Soluções:

#### Opção 1: Executar Fora do Docker (Recomendado)
Para usar o módulo DHCP, execute a aplicação **diretamente no Windows**:
- Instale PHP, Apache e MySQL no Windows
- Siga o guia `INSTALL.md`
- Configure WinRM conforme `SETUP-REMOTE.md`

#### Opção 2: Docker com Rede Host (Linux apenas)
No Linux, você pode usar `network_mode: host` no docker-compose.yml, mas isso **não funciona no Windows/Mac**.

#### Opção 3: API Intermediária
Crie uma API Node.js rodando no Windows host que execute os comandos PowerShell, e o Docker se comunica com essa API via HTTP.

### Para Testes no Docker:

O Docker é ideal para testar:
- ✅ Autenticação AD (LDAP funciona)
- ✅ Gerenciamento de usuários
- ✅ Gerenciamento de grupos
- ✅ Gerenciamento de computadores
- ✅ Auditoria
- ✅ Relatórios
- ❌ **Módulo DHCP** (requer execução fora do Docker)

