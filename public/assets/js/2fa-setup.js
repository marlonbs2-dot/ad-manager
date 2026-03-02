// 2FA Setup JavaScript

let secretKey = '';
let backupCodes = [];

document.addEventListener('DOMContentLoaded', function() {
    // Setup step 1 - Start setup
    const btnStartSetup = document.getElementById('btn-start-setup');
    if (btnStartSetup) {
        btnStartSetup.addEventListener('click', startSetup);
    }

    // Setup step 2 - Next step
    const btnNextStep = document.getElementById('btn-next-step');
    if (btnNextStep) {
        btnNextStep.addEventListener('click', () => showStep(3));
    }

    // Setup step 3 - Verify code
    const verifyForm = document.getElementById('verify-form');
    if (verifyForm) {
        verifyForm.addEventListener('submit', verifyCode);
    }

    // Setup step 4 - Finish
    const btnFinish = document.getElementById('btn-finish');
    if (btnFinish) {
        btnFinish.addEventListener('click', () => window.location.reload());
    }

    // Copy secret key
    const btnCopySecret = document.getElementById('btn-copy-secret');
    if (btnCopySecret) {
        btnCopySecret.addEventListener('click', copySecret);
    }

    // Download backup codes
    const btnDownloadCodes = document.getElementById('btn-download-codes');
    if (btnDownloadCodes) {
        btnDownloadCodes.addEventListener('click', downloadBackupCodes);
    }

    // Print backup codes
    const btnPrintCodes = document.getElementById('btn-print-codes');
    if (btnPrintCodes) {
        btnPrintCodes.addEventListener('click', printBackupCodes);
    }

    // Regenerate backup codes
    const btnRegenerateCodes = document.getElementById('btn-regenerate-codes');
    if (btnRegenerateCodes) {
        btnRegenerateCodes.addEventListener('click', () => App.openModal('regenerate-modal'));
    }

    const regenerateForm = document.getElementById('regenerate-form');
    if (regenerateForm) {
        regenerateForm.addEventListener('submit', regenerateBackupCodes);
    }

    // Disable 2FA
    const btnDisable2FA = document.getElementById('btn-disable-2fa');
    if (btnDisable2FA) {
        btnDisable2FA.addEventListener('click', () => App.openModal('disable-modal'));
    }

    const disableForm = document.getElementById('disable-form');
    if (disableForm) {
        disableForm.addEventListener('submit', disable2FA);
    }

    // Auto-focus and format code inputs
    document.querySelectorAll('.code-input').forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });
});

function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.setup-step').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show current step
    const stepEl = document.getElementById(`setup-step-${step}`);
    if (stepEl) {
        stepEl.style.display = 'block';
    }
}

async function startSetup() {
    try {
        const response = await fetch('/2fa/enable', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (!data.success) {
            alert('Erro ao iniciar configuração: ' + data.message);
            return;
        }

        secretKey = data.secret;
        backupCodes = data.backup_codes;

        // Generate QR code locally
        const qrContainer = document.getElementById('qr-code-container');
        qrContainer.innerHTML = '<div id="qrcode"></div>';
        
        // Generate QR code
        new QRCode(document.getElementById('qrcode'), {
            text: data.provisioning_uri,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        // Show secret key
        document.getElementById('secret-key').textContent = secretKey;

        // Go to step 2
        showStep(2);

    } catch (error) {
        console.error('Error:', error);
        alert('Erro ao iniciar configuração');
    }
}

async function verifyCode(e) {
    e.preventDefault();

    const code = document.getElementById('verification-code').value;
    const messageEl = document.getElementById('verify-message');

    if (code.length !== 6) {
        messageEl.className = 'alert alert-error';
        messageEl.textContent = 'O código deve ter 6 dígitos';
        messageEl.style.display = 'block';
        return;
    }

    try {
        const response = await fetch('/2fa/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code })
        });

        const data = await response.json();

        if (!data.success) {
            messageEl.className = 'alert alert-error';
            messageEl.textContent = data.message;
            messageEl.style.display = 'block';
            return;
        }

        // Show backup codes
        displayBackupCodes();

        // Go to step 4
        showStep(4);

    } catch (error) {
        console.error('Error:', error);
        messageEl.className = 'alert alert-error';
        messageEl.textContent = 'Erro ao verificar código';
        messageEl.style.display = 'block';
    }
}

function displayBackupCodes() {
    const container = document.getElementById('backup-codes-list');
    let html = '<div class="backup-codes-grid">';
    
    backupCodes.forEach(code => {
        html += `<div class="backup-code">${code}</div>`;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function copySecret() {
    const secret = document.getElementById('secret-key').textContent;
    navigator.clipboard.writeText(secret).then(() => {
        alert('Chave secreta copiada!');
    });
}

function downloadBackupCodes() {
    const text = 'AD Manager - Códigos de Backup 2FA\n\n' +
                 'Guarde estes códigos em um local seguro.\n' +
                 'Cada código pode ser usado apenas uma vez.\n\n' +
                 backupCodes.join('\n');
    
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ad-manager-backup-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
}

function printBackupCodes() {
    const printWindow = window.open('', '', 'width=600,height=400');
    printWindow.document.write(`
        <html>
        <head>
            <title>Códigos de Backup 2FA</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { font-size: 18px; }
                .code { 
                    display: inline-block;
                    padding: 8px 12px;
                    margin: 4px;
                    border: 1px solid #ccc;
                    font-family: monospace;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <h1>AD Manager - Códigos de Backup 2FA</h1>
            <p>Guarde estes códigos em um local seguro. Cada código pode ser usado apenas uma vez.</p>
            <div>
                ${backupCodes.map(code => `<div class="code">${code}</div>`).join('')}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

async function regenerateBackupCodes(e) {
    e.preventDefault();

    const code = document.getElementById('regenerate-code').value;
    const messageEl = document.getElementById('regenerate-message');

    try {
        const response = await fetch('/2fa/regenerate-backup-codes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code })
        });

        const data = await response.json();

        if (!data.success) {
            messageEl.className = 'alert alert-error';
            messageEl.textContent = data.message;
            messageEl.style.display = 'block';
            return;
        }

        backupCodes = data.backup_codes;

        // Show codes in a new window
        const codesWindow = window.open('', '', 'width=600,height=400');
        codesWindow.document.write(`
            <html>
            <head>
                <title>Novos Códigos de Backup</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h1 { font-size: 18px; color: #28a745; }
                    .code { 
                        display: inline-block;
                        padding: 8px 12px;
                        margin: 4px;
                        border: 1px solid #ccc;
                        font-family: monospace;
                        font-size: 14px;
                    }
                    .warning {
                        background: #fff3cd;
                        border: 1px solid #ffc107;
                        padding: 12px;
                        margin: 12px 0;
                        border-radius: 4px;
                    }
                </style>
            </head>
            <body>
                <h1>✅ Novos Códigos de Backup Gerados</h1>
                <div class="warning">
                    <strong>⚠️ Importante:</strong> Os códigos antigos não funcionam mais. 
                    Guarde estes novos códigos em um local seguro.
                </div>
                <div>
                    ${backupCodes.map(code => `<div class="code">${code}</div>`).join('')}
                </div>
                <button onclick="window.print()" style="margin-top: 20px; padding: 8px 16px;">Imprimir</button>
            </body>
            </html>
        `);

        App.closeModal('regenerate-modal');
        
        messageEl.className = 'alert alert-success';
        messageEl.textContent = 'Códigos regenerados com sucesso!';
        messageEl.style.display = 'block';

        setTimeout(() => window.location.reload(), 2000);

    } catch (error) {
        console.error('Error:', error);
        messageEl.className = 'alert alert-error';
        messageEl.textContent = 'Erro ao regenerar códigos';
        messageEl.style.display = 'block';
    }
}

async function disable2FA(e) {
    e.preventDefault();

    const code = document.getElementById('disable-code').value;
    const messageEl = document.getElementById('disable-message');

    if (!confirm('Tem certeza que deseja desativar o 2FA? Isso reduzirá a segurança da sua conta.')) {
        return;
    }

    try {
        const response = await fetch('/2fa/disable', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code })
        });

        const data = await response.json();

        if (!data.success) {
            messageEl.className = 'alert alert-error';
            messageEl.textContent = data.message;
            messageEl.style.display = 'block';
            return;
        }

        alert('2FA desativado com sucesso');
        window.location.reload();

    } catch (error) {
        console.error('Error:', error);
        messageEl.className = 'alert alert-error';
        messageEl.textContent = 'Erro ao desativar 2FA';
        messageEl.style.display = 'block';
    }
}
