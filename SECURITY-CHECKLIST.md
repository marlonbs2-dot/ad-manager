# Checklist de Segurança - AD Manager

## ⚠️ IMPORTANTE: Não use em produção sem revisar esta lista

Esta aplicação foi desenvolvida para ambiente de testes. Antes de usar em produção, você **DEVE** implementar as seguintes melhorias de segurança:

---

## 🔒 Segurança Crítica

### 1. HTTPS Obrigatório
- [ ] **Configurar certificado SSL/TLS válido**
- [ ] **Forçar redirecionamento HTTP → HTTPS**
- [ ] **Desabilitar acesso HTTP completamente**
- [ ] Configurar HSTS (HTTP Strict Transport Security)

**Por quê?** Senhas e tokens CSRF trafegam pela rede. Sem HTTPS, podem ser interceptados.

### 2. Credenciais do Servidor DHCP
- [ ] **Remover credenciais hardcoded do código**
- [ ] **Armazenar credenciais em variáveis de ambiente (.env)**
- [ ] **Usar conta de serviço dedicada com permissões mínimas**
- [ ] **Implementar rotação de senhas**
- [ ] Considerar usar Kerberos ao invés de credenciais básicas

**Arquivo atual:** `ad-manager/src/Services/DhcpService.php` contém credenciais hardcoded

### 3. Validação de Entrada
- [ ] **Validar formato de endereços IP** (regex + validação de range)
- [ ] **Validar formato de endereços MAC**
- [ ] **Sanitizar todos os inputs antes de passar para PowerShell**
- [ ] **Implementar whitelist de caracteres permitidos**
- [ ] Prevenir command injection em comandos PowerShell


### 4. Controle de Acesso
- [ ] **Implementar sistema de permissões granulares**
- [ ] **Separar permissões: visualizar vs modificar DHCP**
- [ ] **Registrar todas as ações em audit log**
- [ ] **Implementar aprovação para ações críticas**
- [ ] Limitar quais escopos cada usuário pode gerenciar

### 5. Rate Limiting
- [ ] **Implementar limite de requisições por IP/usuário**
- [ ] **Proteger contra brute force**
- [ ] **Adicionar CAPTCHA após múltiplas tentativas falhas**
- [ ] Implementar bloqueio temporário de conta

### 6. Logs e Auditoria
- [ ] **Registrar TODAS as operações DHCP (criar/deletar reservas)**
- [ ] **Incluir: usuário, timestamp, IP origem, ação, resultado**
- [ ] **Armazenar logs em local seguro e imutável**
- [ ] **Implementar alertas para ações suspeitas**
- [ ] Manter logs por período adequado (compliance)

---

## 🛡️ Segurança Importante

### 7. Sessões
- [ ] Configurar timeout de sessão adequado (15-30 min)
- [ ] Implementar logout automático por inatividade
- [ ] Regenerar session ID após login
- [ ] Usar cookies com flags: HttpOnly, Secure, SameSite


### 8. Banco de Dados
- [ ] Usar prepared statements (já implementado ✓)
- [ ] Criptografar dados sensíveis em repouso
- [ ] Backup regular e testado
- [ ] Restringir acesso ao banco apenas da aplicação

### 9. Servidor Web
- [ ] Desabilitar listagem de diretórios
- [ ] Remover headers que expõem versões (X-Powered-By)
- [ ] Configurar Content Security Policy (CSP)
- [ ] Implementar X-Frame-Options, X-Content-Type-Options

### 10. Dependências
- [ ] Manter Composer packages atualizados
- [ ] Executar `composer audit` regularmente
- [ ] Monitorar vulnerabilidades conhecidas
- [ ] Usar versões LTS de PHP

---

## 📋 Configurações Específicas do Módulo DHCP

### Conexão PowerShell Remota
**Arquivo:** `ad-manager/src/Services/DhcpService.php`

```php
// ❌ ATUAL (INSEGURO):
private string $server = 'srv-ad-01';
private string $domain = 'meudominio.local';
private string $username = 'svc-admanager';
private string $password = 'SenhaDaContaDeServicoAqui';

// ✅ RECOMENDADO:
private string $server;
private string $domain;
private string $username;
private string $password;

public function __construct() {
    $this->server = $_ENV['DHCP_SERVER'];
    $this->domain = $_ENV['DHCP_DOMAIN'];
    $this->username = $_ENV['DHCP_USERNAME'];
    $this->password = $_ENV['DHCP_PASSWORD'];
}
```


### Validação de Inputs
**Adicionar em:** `ad-manager/src/Services/DhcpService.php`

```php
private function validateIPAddress(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new \InvalidArgumentException("Endereço IP inválido: $ip");
    }
    return true;
}

private function validateMACAddress(string $mac): bool {
    // Aceita formatos: 00-11-22-33-44-55 ou 00:11:22:33:44:55
    $pattern = '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/';
    if (!preg_match($pattern, $mac)) {
        throw new \InvalidArgumentException("Endereço MAC inválido: $mac");
    }
    return true;
}

private function sanitizeForPowerShell(string $input): string {
    // Remove caracteres perigosos
    return preg_replace('/[^a-zA-Z0-9\.\-\:\_]/', '', $input);
}
```

### Permissões no Windows Server
**Conta de serviço deve ter APENAS:**
- Permissão de leitura em escopos DHCP
- Permissão de criar/deletar reservas
- **NÃO** deve ser Domain Admin
- **NÃO** deve poder modificar escopos ou configurações globais

---

## 🔍 Testes de Segurança Recomendados

Antes de produção, execute:

1. **Teste de Penetração**
   - Contratar profissional ou usar ferramentas: OWASP ZAP, Burp Suite
   - Testar SQL Injection, XSS, CSRF, Command Injection

2. **Code Review de Segurança**
   - Revisar todo código que executa comandos PowerShell
   - Verificar escape de caracteres especiais
   - Validar todos os pontos de entrada de dados


3. **Scan de Vulnerabilidades**
   - Executar `composer audit`
   - Verificar CVEs conhecidas
   - Testar configurações do servidor web

4. **Teste de Carga**
   - Verificar comportamento sob alta carga
   - Testar rate limiting
   - Validar timeouts adequados

---

## 📝 Compliance e Regulamentações

Se sua organização está sujeita a:
- **LGPD**: Implementar controles de acesso e logs de auditoria
- **ISO 27001**: Seguir políticas de segurança da informação
- **PCI-DSS**: Criptografia, controle de acesso, logs

---

## ✅ O que JÁ está implementado

- ✓ Proteção CSRF em todas as operações POST/DELETE
- ✓ Autenticação de usuários
- ✓ Prepared statements no banco de dados
- ✓ Estrutura básica de auditoria
- ✓ Separação de camadas (MVC)

---

## 🚨 RESPOSTA DIRETA: Posso usar em produção?

### ❌ **NÃO** - Não está pronto para produção

**Motivos principais:**
1. Credenciais hardcoded no código
2. Sem HTTPS configurado
3. Sem validação robusta de inputs
4. Sem rate limiting
5. Logs de auditoria incompletos
6. Sem testes de segurança

### ✅ **Pode usar em produção SE:**
1. Implementar TODOS os itens marcados como "Críticos"
2. Configurar HTTPS com certificado válido
3. Mover credenciais para variáveis de ambiente
4. Adicionar validação de inputs
5. Implementar logs de auditoria completos
6. Realizar testes de segurança

### 🧪 **Ambiente de Testes/Desenvolvimento**
Pode usar SEM problemas, mas:
- Isolar da rede de produção
- Usar dados de teste
- Não expor para internet

---

## 📞 Próximos Passos

1. Revisar este checklist com equipe de segurança
2. Priorizar itens críticos
3. Implementar melhorias gradualmente
4. Testar cada mudança
5. Documentar configurações de segurança
6. Treinar usuários sobre uso seguro

