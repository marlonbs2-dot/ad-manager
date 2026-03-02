# Configuração do Módulo de Logs de Compartilhamentos

## Visão Geral

O módulo de logs de compartilhamentos permite monitorar e auditar acessos aos compartilhamentos de rede do Windows Server, coletando informações detalhadas sobre:

- Acessos a compartilhamentos de rede
- Abertura e fechamento de arquivos
- Tentativas de acesso negadas
- Usuários, IPs de origem e horários
- Caminhos de arquivos e compartilhamentos acessados

## Funcionalidades

### 📊 Dashboard de Estatísticas
- Total de acessos nos últimos 7 dias
- Usuários únicos ativos
- Compartilhamentos mais acessados
- Status da última sincronização

### 🔍 Pesquisa e Filtros
- Filtrar por servidor, usuário, ação, compartilhamento
- Filtros de data e hora personalizáveis
- Pesquisa em tempo real
- Paginação de resultados

### 📋 Visualização Detalhada
- Tabela com informações organizadas
- Modal com detalhes completos de cada log
- Badges coloridos para diferentes tipos de ação
- Formatação otimizada para IPs e caminhos

### 📤 Exportação
- Exportar logs em formato CSV ou JSON
- Aplicar filtros na exportação
- Máximo de 10.000 registros por exportação

### 🔄 Sincronização
- Sincronização manual ou automática
- Coleta logs do Windows Event Log (Security)
- Configuração de intervalo de sincronização
- Detecção e prevenção de duplicatas

## Configuração

### 1. Variáveis de Ambiente

Adicione as seguintes configurações no arquivo `.env`:

```bash
# Share Logs Management
SHARE_SERVER_HOST=192.168.1.10
SHARE_SERVER_USER=svc-admanager
SHARE_SERVER_PASS=SenhaDaContaDeServicoAqui
SHARE_SERVER_DOMAIN=meudominio.local
```

### 2. Permissões no Windows Server

O usuário configurado deve ter as seguintes permissões:

- **Log on as a service** (Fazer logon como serviço)
- **Access this computer from the network** (Acessar este computador pela rede)
- **Read** no Event Log Security
- **PowerShell Remoting** habilitado

### 3. Habilitar PowerShell Remoting

No Windows Server, execute como Administrador:

```powershell
# Habilitar PowerShell Remoting
Enable-PSRemoting -Force

# Configurar TrustedHosts (se necessário)
Set-Item WSMan:\localhost\Client\TrustedHosts -Value "*" -Force

# Verificar configuração
Get-PSSessionConfiguration
```

### 4. Event IDs Coletados

O sistema coleta os seguintes Event IDs do Security Log:

- **5140**: Acesso a compartilhamento de rede
- **5145**: Verificação de acesso a objeto compartilhado  
- **4656**: Handle para objeto solicitado
- **4658**: Handle para objeto fechado
- **4663**: Tentativa de acesso a objeto

## Como Usar

### 1. Acessar o Módulo

Navegue para: `http://localhost:8080/shares`

### 2. Gerenciar Servidores

Antes de sincronizar logs, você precisa configurar os servidores:

1. Clique em **"Gerenciar Servidores"**
2. Clique em **"Adicionar Servidor"** para adicionar um novo servidor
3. Preencha as informações:
   - **Nome**: Identificador único (ex: "servidor-principal")
   - **Hostname/IP**: Endereço do servidor (ex: "192.168.1.100")
   - **Usuário**: Conta com permissões administrativas
   - **Senha**: Senha do usuário (criptografada no banco)
   - **Domínio**: Domínio do Active Directory (opcional)
   - **Ativo**: Marque para habilitar o servidor
4. Clique em **"Testar Conexão"** para verificar a conectividade
5. Clique em **"Salvar"** para adicionar o servidor

### 3. Primeira Sincronização

1. Selecione o servidor no dropdown **"Servidor"**
2. Clique em **"Sincronizar Agora"**
3. Selecione o período (recomendado: 24 horas para teste)
4. Aguarde a conclusão da sincronização
5. Verifique as estatísticas atualizadas

### 4. Filtrar Logs

1. Use os filtros na seção **"Filtros de Pesquisa"**
2. Configure datas de início e fim
3. Filtre por usuário, compartilhamento ou tipo de ação
4. Clique em **"Aplicar Filtros"**

### 5. Ver Detalhes

1. Clique no botão **"👁️"** na coluna "Detalhes"
2. Visualize informações completas do log
3. Veja dados como Event ID, máscara de acesso, processo

### 6. Exportar Dados

1. Configure os filtros desejados
2. Clique em **"Exportar"**
3. Escolha o formato (CSV ou JSON)
4. Confirme a exportação

## Gerenciamento de Múltiplos Servidores

### Adicionar Servidores

O sistema suporta múltiplos servidores Windows para coleta de logs:

1. **Servidor Principal**: Servidor de arquivos principal
2. **Servidor Secundário**: Backup ou filial
3. **Servidor de Departamento**: Servidor específico de um setor

### Configurações por Servidor

Cada servidor pode ter:
- Credenciais diferentes
- Domínios diferentes
- Status ativo/inativo independente
- Histórico de sincronização próprio

### Sincronização Seletiva

- Escolha qual servidor sincronizar
- Cada servidor mantém seu próprio histórico
- Logs são identificados por servidor de origem
- Filtros permitem visualizar logs por servidor

## Troubleshooting

### Erro de Conexão PowerShell

**Problema**: "Access is denied" ou "WinRM cannot process the request"

**Solução**:
```powershell
# No servidor Windows
winrm quickconfig
winrm set winrm/config/service/auth '@{Basic="true"}'
winrm set winrm/config/client/auth '@{Basic="true"}'
```

### Nenhum Log Encontrado

**Problema**: Sincronização não retorna logs

**Possíveis causas**:
1. Auditorias não habilitadas no Windows
2. Período muito restrito
3. Usuário sem permissões adequadas

**Solução**:
```powershell
# Verificar se auditoria está habilitada
auditpol /get /category:"Object Access"

# Habilitar auditoria de compartilhamentos
auditpol /set /subcategory:"File Share" /success:enable /failure:enable
```

### Performance Lenta

**Problema**: Sincronização muito lenta

**Soluções**:
1. Reduzir período de sincronização
2. Configurar limpeza automática de logs antigos
3. Otimizar índices do banco de dados

## Manutenção

### Limpeza Automática

O sistema inclui limpeza automática de logs antigos:

- **Padrão**: 90 dias de retenção
- **Execução**: Diariamente às 2h
- **Configurável**: Via tabela `settings`

### Monitoramento

Monitore os seguintes aspectos:

1. **Espaço em disco**: Logs podem crescer rapidamente
2. **Performance**: Índices do banco de dados
3. **Conectividade**: Conexão com Windows Server
4. **Permissões**: Validade das credenciais

## Segurança

### Dados Sensíveis

- Senhas são criptografadas no banco
- Logs contêm caminhos de arquivos (dados sensíveis)
- Acesso restrito por autenticação e autorização

### Auditoria

Todas as operações são auditadas:

- `share_sync_logs`: Sincronizações realizadas
- `share_export_logs`: Exportações de dados
- `share_view_logs`: Visualizações de logs

### Recomendações

1. Use HTTPS em produção
2. Configure retenção adequada de logs
3. Monitore acessos ao módulo
4. Mantenha credenciais seguras
5. Revise permissões regularmente

## Dados de Teste

Para testar o módulo, foram inseridos 3 logs de exemplo:

1. **teste.usuario** - Acesso a compartilhamento "Documentos"
2. **outro.usuario** - Abertura de arquivo "relatorio.pdf"  
3. **admin.teste** - Tentativa de acesso a "config.ini"

Estes dados permitem testar todas as funcionalidades sem necessidade de sincronização real.