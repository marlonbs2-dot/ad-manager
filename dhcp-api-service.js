/**
 * DHCP API Service - VERSÃO FINAL 2.1 (Fix: Escape de Credenciais + NetBIOS Domain)
 * Suporta múltiplas convenções de variáveis de ambiente do .env
 *
 * FIXES v2.1:
 *  - Senha com caracteres especiais ($, ", `, \) agora é escapada corretamente
 *    antes de ser injetada no script PowerShell (previne LogonFailure)
 *  - Domínio convertido automaticamente para NetBIOS (exemplo.local → EXEMPLO)
 *    para garantir compatibilidade com autenticação WinRM
 */

require('dotenv').config();
const express = require('express');
const https = require('https');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const cors = require('cors');

const app = express();
const PORT = process.env.PORT || 5001;
const HTTPS_PORT = process.env.HTTPS_PORT || 5443;

// Configuração Robusta (aceita vários nomes de variáveis)
const DHCP_CONFIG = {
    server: process.env.DHCP_SERVER_HOST || process.env.DHCP_SERVER || 'seuad',
    domain: process.env.DHCP_SERVER_DOMAIN || process.env.DHCP_DOMAIN || 'dominio.local',
    username: process.env.DHCP_SERVER_USER || process.env.DHCP_USERNAME || 'ad.manager',
    password: process.env.DHCP_SERVER_PASS || process.env.DHCP_PASSWORD || 'minhasenha'
};

// Modo local: API rodando no mesmo servidor DHCP (evita loopback WinRM)
// Defina DHCP_LOCAL=true no .env quando a API estiver instalada no próprio servidor DHCP
const IS_LOCAL = process.env.DHCP_LOCAL === 'true' ||
    ['localhost', '127.0.0.1', '.'].includes((DHCP_CONFIG.server || '').toLowerCase());

// Deriva nome NetBIOS do domínio (exemplo.local → EXEMPLO)
// WinRM frequentemente exige NetBIOS em vez de FQDN para autenticação
const NETBIOS_DOMAIN = DHCP_CONFIG.domain.split('.')[0].toUpperCase();

// Escapa caracteres especiais do PowerShell dentro de strings com aspas duplas
// PowerShell usa backtick (`) como caractere de escape dentro de strings "..."
function escapePsString(str) {
    return str
        .replace(/`/g, '``')   // backtick  → ``
        .replace(/"/g, '`"')   // aspas     → `"
        .replace(/\$/g, '`$')   // cifrão    → `$
        .replace(/\0/g, '`0');  // null      → `0
}

// Debug de Configuração (Crucial para descobrir erros de senha)
console.log('--- DHCP CONFIGURATION LOADED ---');
console.log(`Server:        ${DHCP_CONFIG.server}`);
console.log(`Domain (FQDN): ${DHCP_CONFIG.domain}`);
console.log(`Domain (NTLM): ${NETBIOS_DOMAIN}`);
console.log(`User:          ${DHCP_CONFIG.username}`);
console.log(`Password:      ${DHCP_CONFIG.password ? (DHCP_CONFIG.password === 'minhasenha' ? '⚠️  DEFAULT (minhasenha)' : '****** (Loaded)') : '❌ MISSING'}`);
console.log(`Mode:          ${IS_LOCAL ? '🏠 LOCAL (sem WinRM)' : '🌐 REMOTO (via WinRM)'}`);
console.log('---------------------------------');

// SSL Configuration
const SSL_CONFIG = {
    key: fs.readFileSync(path.join(__dirname, 'ssl', 'server.key')),
    cert: fs.readFileSync(path.join(__dirname, 'ssl', 'server.crt'))
};

const API_KEY = process.env.DHCP_API_KEY || process.env.API_KEY || 'change-this-in-production';

// Middleware
app.use(cors());
app.use(express.json());

// Auth Middleware
function authenticate(req, res, next) {
    const apiKey = req.headers['x-api-key'];
    if (!apiKey || apiKey !== API_KEY) {
        return res.status(401).json({ success: false, message: 'Unauthorized: Invalid API Key' });
    }
    next();
}

// Função para executar PowerShell LOCAL ou REMOTO
// - LOCAL (IS_LOCAL=true): roda o comando diretamente, sem WinRM
// - REMOTO: usa Invoke-Command com credenciais (WinRM)
function executePowerShell(command) {
    return new Promise((resolve, reject) => {

        let psScript;

        if (IS_LOCAL) {
            // Execução LOCAL — API instalada no mesmo servidor DHCP
            // O bloco & { } garante que o pipe é válido em qualquer contexto
            psScript = `
$ErrorActionPreference = 'Stop'
& {
    ${command}
} | ConvertTo-Json -Depth 10 -Compress
`;
        } else {
            // Execução REMOTA — API em máquina diferente do servidor DHCP
            const safePassword = escapePsString(DHCP_CONFIG.password);
            const safeUsername = escapePsString(DHCP_CONFIG.username);
            psScript = `
$ErrorActionPreference = 'Stop'
$password = ConvertTo-SecureString "${safePassword}" -AsPlainText -Force
$credential = New-Object System.Management.Automation.PSCredential("${NETBIOS_DOMAIN}\\${safeUsername}", $password)

Invoke-Command -ComputerName "${DHCP_CONFIG.server}" -Credential $credential -ScriptBlock {
    ${command}
} | ConvertTo-Json -Depth 10 -Compress
`;
        }

        const psScriptBase64 = Buffer.from(psScript, 'utf16le').toString('base64');
        const finalCommand = `powershell.exe -NoProfile -NonInteractive -EncodedCommand ${psScriptBase64}`;

        exec(finalCommand, { maxBuffer: 10 * 1024 * 1024, timeout: 30000 }, (error, stdout, stderr) => {
            if (error) {
                console.error('PowerShell Execution Error:', error.message);
                reject(new Error(stderr || stdout || error.message));
                return;
            }

            try {
                const result = stdout.trim();
                if (!result || result === 'null' || result === '') {
                    resolve([]);
                    return;
                }
                const firstBracket = result.indexOf('[');
                const firstBrace = result.indexOf('{');
                let jsonStart = -1;
                if (firstBracket !== -1 && (firstBrace === -1 || firstBracket < firstBrace)) jsonStart = firstBracket;
                else if (firstBrace !== -1) jsonStart = firstBrace;
                const cleanJson = jsonStart !== -1 ? result.substring(jsonStart) : result;
                const data = JSON.parse(cleanJson);
                resolve(Array.isArray(data) ? data : [data]);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError.message);
                reject(new Error('Failed to parse PowerShell output: ' + parseError.message));
            }
        });
    });
}

// Endpoints
app.get('/health', (req, res) => res.json({
    success: true,
    service: 'DHCP API',
    version: '2.1.0',
    target: DHCP_CONFIG.server,
    domain: NETBIOS_DOMAIN
}));

app.get('/api/scopes', authenticate, async (req, res) => {
    try {
        console.log('[GET /api/scopes] Requesting scopes...');
        const command = `Get-DhcpServerv4Scope | Select-Object @{N='ScopeId';E={$_.ScopeId.IPAddressToString}}, Name, @{N='SubnetMask';E={$_.SubnetMask.IPAddressToString}}, @{N='StartRange';E={$_.StartRange.IPAddressToString}}, @{N='EndRange';E={$_.EndRange.IPAddressToString}}, @{N='State';E={$_.State.ToString()}}, @{N='LeaseDuration';E={$_.LeaseDuration.ToString()}}`;
        const scopes = await executePowerShell(command);
        console.log(`[GET /api/scopes] Found: ${scopes.length} scopes`);
        res.json({ success: true, message: `${scopes.length} escopos encontrados`, data: scopes });
    } catch (error) {
        console.error('[GET /api/scopes] Error:', error.message);
        res.status(500).json({ success: false, message: error.message, data: null });
    }
});

app.get('/api/scopes/:scopeId/reservations', authenticate, async (req, res) => {
    try {
        const { scopeId } = req.params;
        const command = `Get-DhcpServerv4Reservation -ScopeId '${scopeId}' | Select-Object @{N='IPAddress';E={$_.IPAddress.IPAddressToString}}, ClientId, Name, Description`;
        const reservations = await executePowerShell(command);
        console.log(`[GET /reservations] Scope: ${scopeId}, Found: ${reservations.length}`);
        res.json({ success: true, message: `${reservations.length} reservas encontradas`, data: reservations });
    } catch (error) {
        console.error(`[GET /reservations] Error (${req.params.scopeId}):`, error.message);
        res.status(500).json({ success: false, message: error.message, data: null });
    }
});

app.get('/api/scopes/:scopeId/leases', authenticate, async (req, res) => {
    try {
        const { scopeId } = req.params;
        const command = `Get-DhcpServerv4Lease -ScopeId '${scopeId}' | Select-Object @{N='IPAddress';E={$_.IPAddress.IPAddressToString}}, ClientId, HostName, @{N='AddressState';E={$_.AddressState.ToString()}}, LeaseExpiryTime`;
        const leases = await executePowerShell(command);
        console.log(`[GET /leases] Scope: ${scopeId}, Found: ${leases.length}`);
        res.json({ success: true, message: `${leases.length} leases encontrados`, data: leases });
    } catch (error) {
        console.error(`[GET /leases] Error (${req.params.scopeId}):`, error.message);
        res.status(500).json({ success: false, message: error.message, data: null });
    }
});

app.post('/api/reservations', authenticate, async (req, res) => {
    try {
        const { scopeId, ipAddress, macAddress, name, description } = req.body;
        if (!scopeId || !ipAddress || !macAddress || !name) throw new Error('Missing required fields');
        const command = `Add-DhcpServerv4Reservation -ScopeId '${scopeId}' -IPAddress '${ipAddress}' -ClientId '${macAddress}' -Name '${name}' -Description '${description || ''}'`;
        await executePowerShell(command);
        res.json({ success: true, message: 'Reserva criada com sucesso' });
    } catch (error) {
        console.error('[POST /reservations] Error:', error.message);
        res.status(500).json({ success: false, message: error.message });
    }
});

app.delete('/api/scopes/:scopeId/reservations/:ipAddress', authenticate, async (req, res) => {
    try {
        const { ipAddress } = req.params;
        const command = `Remove-DhcpServerv4Reservation -IPAddress '${ipAddress}'`;
        await executePowerShell(command);
        res.json({ success: true, message: 'Reserva removida com sucesso' });
    } catch (error) {
        console.error('[DELETE /reservations] Error:', error.message);
        res.status(500).json({ success: false, message: error.message });
    }
});

app.put('/api/scopes/:scopeId/reservations/:ipAddress', authenticate, async (req, res) => {
    try {
        const { scopeId, ipAddress } = req.params;
        const { newIpAddress, macAddress, name, description } = req.body;
        const desc = description || '';
        if (newIpAddress && newIpAddress !== ipAddress) {
            await executePowerShell(`Remove-DhcpServerv4Reservation -IPAddress '${ipAddress}'`);
            await executePowerShell(`Add-DhcpServerv4Reservation -ScopeId '${scopeId}' -IPAddress '${newIpAddress}' -ClientId '${macAddress}' -Name '${name}' -Description '${desc}'`);
        } else {
            await executePowerShell(`Set-DhcpServerv4Reservation -IPAddress '${ipAddress}' -ClientId '${macAddress}' -Name '${name}' -Description '${desc}'`);
        }
        res.json({ success: true, message: 'Reserva atualizada com sucesso' });
    } catch (error) {
        console.error('[PUT /reservations] Error:', error.message);
        res.status(500).json({ success: false, message: error.message });
    }
});

const httpServer = app.listen(PORT, '0.0.0.0', () => console.log(`HTTP  Server running on port ${PORT}`));
const httpsServer = https.createServer(SSL_CONFIG, app).listen(HTTPS_PORT, '0.0.0.0', () => console.log(`HTTPS Server running on port ${HTTPS_PORT}`));
