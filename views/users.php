<?php
$title = 'Usuários - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Gerenciamento de Usuários</h1>
    <p>Buscar e gerenciar usuários do Active Directory</p>
</div>

<div class="card">
    <div class="card-body">
        <div class="search-bar">
            <input type="text" id="user-search" placeholder="Buscar por nome, email ou login..." class="search-input">
            <button id="search-btn" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                Buscar
            </button>
            <button id="btn-open-create-modal" class="btn btn-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Criar Usuário
            </button>
        </div>
    </div>
</div>

<div id="search-results" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h2>Resultados da Busca</h2>
            <span id="results-count" class="badge">0</span>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Login</th>
                            <th>Email</th>
                            <th>Departamento</th>
                            <th>Status</th>
                            <th style="width: 300px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div id="user-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 id="modal-user-name">Detalhes do Usuário</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="user-details-grid">
                <div class="detail-item">
                    <label>Login:</label>
                    <span id="detail-username"></span>
                </div>
                <div class="detail-item">
                    <label>Email:</label>
                    <span id="detail-email"></span>
                </div>
                <div class="detail-item">
                    <label>Telefone:</label>
                    <span id="detail-phone"></span>
                </div>
                <div class="detail-item">
                    <label>Departamento:</label>
                    <span id="detail-department"></span>
                </div>
                <div class="detail-item">
                    <label>Cargo:</label>
                    <span id="detail-title"></span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span id="detail-status"></span>
                </div>
                <div class="detail-item">
                    <label>Último Logon:</label>
                    <span id="detail-last-logon"></span>
                </div>
                <div class="detail-item">
                    <label>Criado em:</label>
                    <span id="detail-created"></span>
                </div>
            </div>

            <div class="detail-section">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                    <h3 style="margin:0">Grupos</h3>
                    <button type="button" class="btn btn-success btn-sm" onclick="openAddToGroupModal()"
                        style="font-size:.8rem;padding:.25rem .75rem">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" style="vertical-align:middle;margin-right:3px">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Adicionar em Grupo
                    </button>
                </div>
                <div id="detail-groups" class="groups-list"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="btn-copy-user" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
                Copiar Usuário
            </button>
            <button id="btn-reset-password" class="btn btn-warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path
                        d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
                </svg>
                Resetar Senha
            </button>
            <button id="btn-toggle-status" class="btn btn-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="1" y="5" width="22" height="14" rx="7" ry="7" />
                    <circle cx="16" cy="12" r="3" />
                </svg>
                Habilitar/Desabilitar
            </button>
        </div>
    </div>
    <!-- Add User to Group Modal -->
    <div id="add-to-group-modal" class="modal">
        <div class="modal-content" style="max-width:500px">
            <div class="modal-header">
                <h2>Adicionar em Grupo</h2>
                <button class="modal-close" onclick="App.closeModal('add-to-group-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="group-search-input">Buscar grupo:</label>
                    <div class="input-group">
                        <input type="text" id="group-search-input" class="form-control" placeholder="Nome do grupo...">
                        <button type="button" class="btn btn-primary" onclick="searchGroupsForUser()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.35-4.35" />
                            </svg>
                            Buscar
                        </button>
                    </div>
                </div>
                <div id="group-search-results" style="max-height:300px;overflow-y:auto;margin-top:.5rem"></div>
                <div id="add-to-group-message" class="alert" style="display:none;margin-top:.5rem"></div>
            </div>
        </div>
    </div>

</div>

<!-- Reset Password Modal -->
<div id="reset-password-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Resetar Senha</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reset-password-form">
                <input type="hidden" id="reset-user-dn">
                <input type="hidden" id="reset-csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">

                <div class="form-group">
                    <label>
                        <input type="radio" name="password-mode" value="generate" checked>
                        Gerar senha automaticamente
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="radio" name="password-mode" value="manual">
                        Definir senha manualmente
                    </label>
                </div>

                <div id="manual-password-group" class="form-group" style="display: none;">
                    <label for="new-password">Nova Senha:</label>
                    <input type="password" id="new-password" class="form-control" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="must-change" checked>
                        Usuário deve alterar a senha no próximo logon
                    </label>
                </div>
            </form>

            <div id="generated-password" class="alert alert-info" style="display: none;">
                <strong>Senha gerada:</strong>
                <code id="password-display"></code>
                <button id="copy-password" class="btn btn-sm btn-text">Copiar</button>
            </div>

            <div id="reset-message" class="alert" style="display: none;"></div>
        </div>
        <div class="modal-footer">
            <button id="btn-confirm-reset" class="btn btn-primary">
                <span class="btn-text">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Confirmar Reset
                </span>
                <span class="btn-loader" style="display: none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="0">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12"
                                dur="1s" repeatCount="indefinite" />
                        </circle>
                    </svg>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="create-user-modal" class="modal">
    <div class="modal-content modal-lg">
        <form id="create-user-form">
            <div class="modal-header">
                <h2>Criar Novo Usuário</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="create-csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">
                <input type="hidden" id="create-copy-from-dn" name="copy_from_dn">
                <input type="hidden" id="create-copy-groups-data" name="copy_groups_data">

                <div id="copy-info-banner"
                    style="display: none; background: #e3f2fd; padding: 12px; border-radius: 4px; margin-bottom: 16px; border-left: 4px solid #2196f3;">
                    <strong>📋 Copiando de:</strong> <span id="copy-source-name"></span>
                    <br>
                    <small id="copy-groups-info"></small>
                </div>

                <h4>Informações da Conta</h4>
                <div class="user-details-grid">
                    <div class="form-group">
                        <label for="create-first-name">Primeiro Nome:</label>
                        <input type="text" id="create-first-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="create-last-name">Sobrenome <small>(opcional)</small>:</label>
                        <input type="text" id="create-last-name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="create-display-name">Nome de Exibição:</label>
                        <input type="text" id="create-display-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="create-username">Login de Usuário (sAMAccountName):</label>
                        <input type="text" id="create-username" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="create-ou-display">Unidade Organizacional (OU) de Destino:</label>
                    <div class="input-group">
                        <input type="text" id="create-ou-display" class="form-control" placeholder="Selecione uma OU..."
                            readonly required>
                        <button type="button" id="btn-browse-ou" class="btn btn-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            Procurar...
                        </button>
                    </div>
                    <input type="hidden" id="create-ou" name="ou">
                </div>

                <hr>

                <h4>Opções de Senha</h4>
                <div class="user-details-grid">
                    <div class="form-group">
                        <label for="create-password">Senha:</label>
                        <input type="password" id="create-password" class="form-control" required
                            autocomplete="new-password">
                        <small
                            style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                            Mínimo 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="create-confirm-password">Confirmar Senha:</label>
                        <input type="password" id="create-confirm-password" class="form-control" required
                            autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="create-must-change" checked>
                        Usuário deve alterar a senha no próximo logon
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="create-is-disabled">
                        A conta está desabilitada
                    </label>
                </div>
                <div id="create-message" class="alert" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-text">Criar Usuário</span>
                    <span class="btn-loader" style="display: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="0">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12"
                                    dur="1s" repeatCount="indefinite" />
                            </circle>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- OU Browser Modal -->
<div id="ou-browser-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Selecionar Unidade Organizacional</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" id="ou-search-input" class="form-control" placeholder="Filtrar OUs...">
            </div>
            <div id="ou-list" class="ou-browser-list">
                Carregando...
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-text modal-close">Cancelar</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/users.js"></script>';
include __DIR__ . '/layout.php';
?>