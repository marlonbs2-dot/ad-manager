<?php
ob_start();
?>

<div class="page-header">
    <h1>Autenticação de Dois Fatores (2FA)</h1>
    <p>Proteja sua conta com autenticação de dois fatores usando Google Authenticator ou Microsoft Authenticator</p>
</div>

<?php if (!$is2FAEnabled): ?>
<!-- 2FA Not Enabled -->
<div class="card">
    <div class="card-header">
        <h2>Ativar Autenticação de Dois Fatores</h2>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>📱 O que é 2FA?</strong><br>
            A autenticação de dois fatores adiciona uma camada extra de segurança à sua conta. Após ativar, você precisará:
            <ol style="margin-top: 0.5rem; margin-bottom: 0;">
                <li>Seu usuário e senha (algo que você sabe)</li>
                <li>Um código de 6 dígitos do seu celular (algo que você tem)</li>
            </ol>
        </div>

        <div id="setup-step-1" class="setup-step">
            <h3>Passo 1: Instale um aplicativo autenticador</h3>
            <p>Você precisará de um dos seguintes aplicativos no seu celular:</p>
            <div class="app-list">
                <div class="app-item">
                    <strong>Google Authenticator</strong>
                    <div class="app-links">
                        <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</a>
                        <a href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank">iOS</a>
                    </div>
                </div>
                <div class="app-item">
                    <strong>Microsoft Authenticator</strong>
                    <div class="app-links">
                        <a href="https://play.google.com/store/apps/details?id=com.azure.authenticator" target="_blank">Android</a>
                        <a href="https://apps.apple.com/app/microsoft-authenticator/id983156458" target="_blank">iOS</a>
                    </div>
                </div>
                <div class="app-item">
                    <strong>Authy</strong>
                    <div class="app-links">
                        <a href="https://play.google.com/store/apps/details?id=com.authy.authy" target="_blank">Android</a>
                        <a href="https://apps.apple.com/app/authy/id494168017" target="_blank">iOS</a>
                    </div>
                </div>
            </div>
            <button id="btn-start-setup" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Já tenho o app, continuar
            </button>
        </div>

        <div id="setup-step-2" class="setup-step" style="display: none;">
            <h3>Passo 2: Escaneie o QR Code</h3>
            <p>Abra seu aplicativo autenticador e escaneie este código:</p>
            
            <div id="qr-code-container" class="qr-code-container">
                <div class="loading">Gerando QR Code...</div>
            </div>

            <div class="manual-entry">
                <p><strong>Não consegue escanear?</strong> Digite manualmente:</p>
                <div class="secret-key">
                    <code id="secret-key"></code>
                    <button id="btn-copy-secret" class="btn btn-sm btn-text" title="Copiar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button id="btn-next-step" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                Próximo passo
            </button>
        </div>

        <div id="setup-step-3" class="setup-step" style="display: none;">
            <h3>Passo 3: Verifique o código</h3>
            <p>Digite o código de 6 dígitos exibido no seu aplicativo:</p>
            
            <form id="verify-form">
                <div class="form-group">
                    <input type="text" 
                           id="verification-code" 
                           class="form-control code-input" 
                           placeholder="000000" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           autocomplete="off"
                           required>
                </div>
                <div id="verify-message" class="alert" style="display: none;"></div>
                <button type="submit" class="btn btn-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Ativar 2FA
                </button>
            </form>
        </div>

        <div id="setup-step-4" class="setup-step" style="display: none;">
            <h3>✅ 2FA Ativado com Sucesso!</h3>
            
            <div class="alert alert-success">
                <strong>Parabéns!</strong> Sua conta agora está protegida com autenticação de dois fatores.
            </div>

            <h4>Códigos de Backup</h4>
            <p>Guarde estes códigos em um local seguro. Você pode usá-los para acessar sua conta se perder o celular:</p>
            
            <div class="backup-codes" id="backup-codes-list"></div>
            
            <div class="backup-actions">
                <button id="btn-download-codes" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Baixar Códigos
                </button>
                <button id="btn-print-codes" class="btn btn-text">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    Imprimir
                </button>
            </div>

            <button id="btn-finish" class="btn btn-success" style="margin-top: 1rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Concluir
            </button>
        </div>
    </div>
</div>

<?php else: ?>
<!-- 2FA Already Enabled -->
<div class="card">
    <div class="card-header">
        <h2>2FA Ativo</h2>
        <span class="badge badge-success">✓ Ativado</span>
    </div>
    <div class="card-body">
        <div class="alert alert-success">
            <strong>✅ Sua conta está protegida!</strong><br>
            A autenticação de dois fatores está ativa.
        </div>

        <div class="twofa-info">
            <div class="info-item">
                <strong>Códigos de backup restantes:</strong>
                <span class="badge"><?= $backupCodesCount ?> de 10</span>
            </div>
        </div>

        <div class="twofa-actions">
            <button id="btn-regenerate-codes" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Gerar Novos Códigos de Backup
            </button>

            <button id="btn-disable-2fa" class="btn btn-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                Desativar 2FA
            </button>
        </div>

        <div id="action-message" class="alert" style="display: none; margin-top: 1rem;"></div>
    </div>
</div>

<!-- Regenerate Codes Modal -->
<div id="regenerate-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Gerar Novos Códigos de Backup</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Digite o código atual do seu aplicativo autenticador:</p>
            <form id="regenerate-form">
                <div class="form-group">
                    <input type="text" 
                           id="regenerate-code" 
                           class="form-control code-input" 
                           placeholder="000000" 
                           maxlength="6" 
                           required>
                </div>
                <div id="regenerate-message" class="alert" style="display: none;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-text modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Gerar Códigos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Disable 2FA Modal -->
<div id="disable-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Desativar 2FA</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning">
                <strong>⚠️ Atenção!</strong><br>
                Desativar o 2FA reduzirá a segurança da sua conta.
            </div>
            <p>Digite o código atual do seu aplicativo autenticador para confirmar:</p>
            <form id="disable-form">
                <div class="form-group">
                    <input type="text" 
                           id="disable-code" 
                           class="form-control code-input" 
                           placeholder="000000" 
                           maxlength="6" 
                           required>
                </div>
                <div id="disable-message" class="alert" style="display: none;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-text modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-error">Desativar 2FA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<script src="/assets/js/2fa-setup.js"></script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
