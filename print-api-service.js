/**
 * Print Server API Service - v1.0
 * Gerenciamento de impressoras via PowerShell local
 * Instalar em cada servidor de impressão Windows
 *
 * Portas padrão:
 *   HTTP:  5002  (PRINT_PORT no .env)
 *   HTTPS: 5444  (PRINT_HTTPS_PORT no .env)
 */

require('dotenv').config();
const express = require('express');
const https = require('https');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const cors = require('cors');

const app = express();
const PORT = process.env.PRINT_PORT || process.env.PORT || 5002;
const HTTPS_PORT = process.env.PRINT_HTTPS_PORT || process.env.HTTPS_PORT || 5444;
const API_KEY = process.env.PRINT_API_KEY || process.env.API_KEY || 'change-this-in-production';

// SSL Configuration
const SSL_CONFIG = {
    key: fs.readFileSync(path.join(__dirname, 'ssl', 'server.key')),
    cert: fs.readFileSync(path.join(__dirname, 'ssl', 'server.crt'))
};

console.log('========================================');
console.log('  Print API Service');
console.log('========================================');
console.log(`HTTP  Port: ${PORT}`);
console.log(`HTTPS Port: ${HTTPS_PORT}`);
console.log(`API Key:    ${API_KEY === 'change-this-in-production' ? '⚠️  DEFAULT (trocar!)' : '****** (Loaded)'}`);
console.log('----------------------------------------');

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

// Escapa caracteres especiais para strings PowerShell com aspas duplas
function escapePsString(str) {
    if (!str) return '';
    return String(str)
        .replace(/`/g, '``')
        .replace(/"/g, '`"')
        .replace(/\$/g, '`$')
        .replace(/\0/g, '`0');
}

// Executa PowerShell LOCAL (sem WinRM)
function executePowerShell(command) {
    return new Promise((resolve, reject) => {
        const psScript = `
$ErrorActionPreference = 'Stop'
& {
    ${command}
} | ConvertTo-Json -Depth 10 -Compress
`;
        const psScriptBase64 = Buffer.from(psScript, 'utf16le').toString('base64');
        const finalCommand = `powershell.exe -NoProfile -NonInteractive -EncodedCommand ${psScriptBase64}`;

        exec(finalCommand, { maxBuffer: 10 * 1024 * 1024, timeout: 30000 }, (error, stdout, stderr) => {
            if (error) {
                console.error('PowerShell Error:', error.message);
                reject(new Error(stderr || stdout || error.message));
                return;
            }

            try {
                const result = stdout.trim();
                if (!result || result === 'null' || result === '') {
                    resolve([]);
                    return;
                }
                // Encontrar início do JSON (ignorar warnings/progress do PS)
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

// Executa PowerShell para operações sem retorno (void)
function executePowerShellVoid(command) {
    return new Promise((resolve, reject) => {
        const psScript = `
$ErrorActionPreference = 'Stop'
${command}
`;
        const psScriptBase64 = Buffer.from(psScript, 'utf16le').toString('base64');
        const finalCommand = `powershell.exe -NoProfile -NonInteractive -EncodedCommand ${psScriptBase64}`;

        exec(finalCommand, { maxBuffer: 4 * 1024 * 1024, timeout: 60000 }, (error, stdout, stderr) => {
            if (error) {
                // stderr pode conter CLIXML de progresso (ex: "Preparing modules for first use")
                // Só é erro real se o exit code for não-zero E o stderr tiver conteúdo de erro
                const stderrClean = (stderr || '').replace(/#<\s*CLIXML[\s\S]*?<\/Objs>/g, '').trim();
                const hasRealError = stderrClean.length > 0;
                const errorMsg = hasRealError ? stderrClean : (stdout.trim() || error.message);
                console.error('PowerShell Void Error:', errorMsg);
                reject(new Error(errorMsg));
                return;
            }
            // Sucesso: stderr pode ter CLIXML de progresso — ignorar
            resolve();
        });
    });
}

// ─── ENDPOINTS ────────────────────────────────────────────────────────────────

// Health Check
app.get('/health', (req, res) => res.json({
    success: true,
    service: 'Print API',
    version: '1.0.0',
    hostname: require('os').hostname(),
    timestamp: new Date().toISOString()
}));

// GET /api/printers — Lista todas as impressoras
app.get('/api/printers', authenticate, async (req, res) => {
    try {
        console.log('[GET /api/printers] Listing printers...');
        const command = `Get-Printer | Select-Object Name, DriverName, PortName, @{N='PrinterStatus';E={$_.PrinterStatus.ToString()}}, Shared, ShareName, Published`;
        const printers = await executePowerShell(command);
        console.log(`[GET /api/printers] Found: ${printers.length}`);
        res.json({ success: true, message: `${printers.length} impressoras encontradas`, data: printers });
    } catch (error) {
        console.error('[GET /api/printers] Error:', error.message);
        res.status(500).json({ success: false, message: error.message, data: null });
    }
});

// GET /api/printers/:name/jobs — Lista jobs de uma impressora
app.get('/api/printers/:name/jobs', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const safeName = escapePsString(name);
        console.log(`[GET /api/printers/${name}/jobs] Listing jobs...`);
        const command = `Get-PrintJob -PrinterName "${safeName}" | Select-Object Id, DocumentName, UserName, @{N='JobStatus';E={$_.JobStatus.ToString()}}, @{N='SubmittedTime';E={if($_.SubmittedTime){$_.SubmittedTime.ToString('o')}else{$null}}}, TotalPages, PagesPrinted, Size`;
        const jobs = await executePowerShell(command);
        console.log(`[GET /api/printers/${name}/jobs] Found: ${jobs.length}`);
        res.json({ success: true, message: `${jobs.length} jobs encontrados`, data: jobs });
    } catch (error) {
        console.error(`[GET /api/printers/jobs] Error:`, error.message);
        res.status(500).json({ success: false, message: error.message, data: null });
    }
});

// POST /api/printers/:name/pause — Pausa impressora
app.post('/api/printers/:name/pause', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const safeName = escapePsString(name);
        await executePowerShellVoid(`Suspend-Printer -Name "${safeName}"`);
        res.json({ success: true, message: `Impressora "${name}" pausada` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// POST /api/printers/:name/resume — Retoma impressora
app.post('/api/printers/:name/resume', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const safeName = escapePsString(name);
        await executePowerShellVoid(`Resume-Printer -Name "${safeName}"`);
        res.json({ success: true, message: `Impressora "${name}" retomada` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// DELETE /api/printers/:name/jobs — Limpa fila (todos os jobs)
app.delete('/api/printers/:name/jobs', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const safeName = escapePsString(name);
        await executePowerShellVoid(`Get-PrintJob -PrinterName "${safeName}" | Remove-PrintJob`);
        res.json({ success: true, message: `Fila de "${name}" limpa` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// DELETE /api/printers/:name/jobs/:id — Cancela job específico
app.delete('/api/printers/:name/jobs/:id', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const jobId = parseInt(req.params.id, 10);
        const safeName = escapePsString(name);
        if (isNaN(jobId)) return res.status(400).json({ success: false, message: 'ID de job inválido' });
        await executePowerShellVoid(`Remove-PrintJob -PrinterName "${safeName}" -ID ${jobId}`);
        res.json({ success: true, message: `Job ${jobId} cancelado` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// POST /api/printers/:name/jobs/:id/pause — Pausa job
app.post('/api/printers/:name/jobs/:id/pause', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const jobId = parseInt(req.params.id, 10);
        const safeName = escapePsString(name);
        if (isNaN(jobId)) return res.status(400).json({ success: false, message: 'ID de job inválido' });
        await executePowerShellVoid(`Suspend-PrintJob -PrinterName "${safeName}" -ID ${jobId}`);
        res.json({ success: true, message: `Job ${jobId} pausado` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// POST /api/printers/:name/jobs/:id/resume — Retoma job
app.post('/api/printers/:name/jobs/:id/resume', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const jobId = parseInt(req.params.id, 10);
        const safeName = escapePsString(name);
        if (isNaN(jobId)) return res.status(400).json({ success: false, message: 'ID de job inválido' });
        await executePowerShellVoid(`Resume-PrintJob -PrinterName "${safeName}" -ID ${jobId}`);
        res.json({ success: true, message: `Job ${jobId} retomado` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// GET /api/drivers — Lista drivers de impressora instalados no servidor
app.get('/api/drivers', authenticate, async (req, res) => {
    try {
        const command = `Get-PrinterDriver | Select-Object Name, Manufacturer | Sort-Object Name`;
        const drivers = await executePowerShell(command);
        res.json({ success: true, data: Array.isArray(drivers) ? drivers : [drivers] });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message, data: [] });
    }
});

// POST /api/printers — Cria nova impressora (porta IP + instalação)
app.post('/api/printers', authenticate, async (req, res) => {
    try {
        const { name, driverName, printerIP, portName, shareName, shared, comment } = req.body;

        if (!name || !driverName || !printerIP) {
            return res.status(400).json({ success: false, message: 'name, driverName e printerIP são obrigatórios' });
        }

        const safeName = escapePsString(name);
        const safeDriver = escapePsString(driverName);
        const safeIP = escapePsString(printerIP);
        const safePortName = escapePsString(portName || `IP_${printerIP}`);
        const safeShareName = escapePsString(shareName || name);
        const safeComment = escapePsString(comment || '');
        const isShared = shared === true || shared === 'true' || shared === '1';

        // 1. Criar porta TCP/IP (ignora erro se já existir)
        await executePowerShellVoid(
            `$existing = Get-PrinterPort -Name "${safePortName}" -ErrorAction SilentlyContinue; ` +
            `if (-not $existing) { Add-PrinterPort -Name "${safePortName}" -PrinterHostAddress "${safeIP}" }`
        );

        // 2. Instalar impressora
        const shareParam = isShared
            ? `-ShareName "${safeShareName}" -Shared`
            : '';
        const commentParam = safeComment ? `-Comment "${safeComment}"` : '';

        await executePowerShellVoid(
            `Add-Printer -Name "${safeName}" -DriverName "${safeDriver}" -PortName "${safePortName}" ${shareParam} ${commentParam}`
        );

        res.json({ success: true, message: `Impressora "${name}" instalada com sucesso` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// DELETE /api/printers/:name — Remove impressora do servidor
app.delete('/api/printers/:name', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const safeName = escapePsString(name);
        await executePowerShellVoid(`Remove-Printer -Name "${safeName}" -Confirm:$false`);
        res.json({ success: true, message: `Impressora "${name}" removida` });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// GET /api/ports — Lista portas de impressora disponíveis no servidor
app.get('/api/ports', authenticate, async (req, res) => {
    try {
        const command = `Get-PrinterPort | Select-Object Name, @{N='PortType';E={$_.GetType().Name}} | Sort-Object Name`;
        const ports = await executePowerShell(command);
        res.json({ success: true, data: ports });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message, data: [] });
    }
});

// PUT /api/printers/:name — Renomear e/ou alterar porta da impressora
app.put('/api/printers/:name', authenticate, async (req, res) => {
    try {
        const name = decodeURIComponent(req.params.name);
        const safeName = escapePsString(name);
        const { newName, portName } = req.body;

        if (!newName && !portName) {
            return res.status(400).json({ success: false, message: 'Informe newName e/ou portName' });
        }

        // Alterar porta (Set-Printer)
        if (portName) {
            const safePort = escapePsString(portName);
            await executePowerShellVoid(`Set-Printer -Name "${safeName}" -PortName "${safePort}"`);
        }

        // Renomear (Rename-Printer) — deve ser o último pois muda o nome
        if (newName && newName !== name) {
            const safeNewName = escapePsString(newName);
            await executePowerShellVoid(`Rename-Printer -Name "${safeName}" -NewName "${safeNewName}"`);
        }

        res.json({ success: true, message: 'Impressora atualizada com sucesso' });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// ─── INICIALIZAÇÃO ─────────────────────────────────────────────────────────────
app.listen(PORT, '0.0.0.0', () => console.log(`HTTP  Server running on port ${PORT}`));
https.createServer(SSL_CONFIG, app).listen(HTTPS_PORT, '0.0.0.0', () => console.log(`HTTPS Server running on port ${HTTPS_PORT}`));
