<?php
$title = 'Grupos - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Gerenciamento de Grupos</h1>
    <p>Buscar e gerenciar grupos do Active Directory</p>
</div>

<div class="card">
    <div class="card-body">
        <div class="search-bar">
            <input type="text" id="group-search" placeholder="Buscar grupos..." class="search-input">
            <button id="search-btn" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                Buscar
            </button>
            <button id="btn-open-create-modal" class="btn btn-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Criar Grupo
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
                            <th>Nome do Grupo</th>
                            <th>Descrição</th>
                            <th>Contagem de Membros</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="groups-table-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div id="create-group-modal" class="modal">
    <div class="modal-content modal-lg">
        <form id="create-group-form">
            <div class="modal-header">
                <h2>Criar Novo Grupo</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="create-csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">
                
                <div class="form-group">
                    <label for="create-group-name">Nome do Grupo (sAMAccountName):</label>
                    <input type="text" id="create-group-name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="create-group-description">Descrição <small>(opcional)</small>:</label>
                    <input type="text" id="create-group-description" class="form-control">
                </div>

                <div class="form-group">
                    <label for="create-ou-display">Unidade Organizacional (OU) de Destino:</label>
                    <div class="input-group">
                        <input type="text" id="create-ou-display" class="form-control" placeholder="Selecione uma OU..." readonly required>
                        <button type="button" id="btn-browse-ou" class="btn btn-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            Procurar...
                        </button>
                    </div>
                    <input type="hidden" id="create-ou" name="ou">
                </div>

                <hr>

                <h4>Opções do Grupo</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="create-group-scope">Escopo do Grupo:</label>
                        <select id="create-group-scope" class="form-control">
                            <option value="global">Global</option>
                            <option value="domain_local">Local de Domínio</option>
                            <option value="universal">Universal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="create-group-type">Tipo de Grupo:</label>
                        <select id="create-group-type" class="form-control">
                            <option value="security">Segurança</option>
                            <option value="distribution">Distribuição</option>
                        </select>
                    </div>
                </div>

                <div id="create-message" class="alert" style="display: none;"></div>
            </div>
                <button type="submit" class="btn btn-primary">
                    <span class="btn-text">Criar Grupo</span>
                    <span class="btn-loader" style="display: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="0">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </circle>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- OU Browser Modal (reutilizado de users.php) -->
<div id="ou-browser-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Selecionar Unidade Organizacional</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" id="ou-search-input" class="form-control" placeholder="Filtrar OUs...">
            </div>
            <div id="ou-list" class="ou-browser-list">
                Carregando...
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Group Details Modal -->
<div id="group-details-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 id="modal-group-name">Detalhes do Grupo</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="current-group-dn">
            
            <div class="group-details">
                <div class="detail-item">
                    <label>Nome:</label>
                    <span id="detail-name"></span>
                </div>
                <div class="detail-item">
                    <label>Descrição:</label>
                    <span id="detail-description"></span>
                </div>
                <div class="detail-item">
                    <label>Total de Membros:</label>
                    <span id="detail-member-count"></span>
                </div>
            </div>

            <div class="detail-section">
                <div class="section-header">
                    <h3>Membros</h3>
                    <button id="btn-add-member" class="btn btn-sm btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Adicionar Membro
                    </button>
                </div>
                <div id="members-list" class="members-list"></div>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div id="add-member-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Adicionar Membro ao Grupo</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="add-member-csrf" value="<?= \App\Security\CSRF::generateToken() ?>">
            
            <div class="form-group">
                <label for="member-search">Buscar Usuário:</label>
                <input type="text" id="member-search" class="form-control" placeholder="Digite o nome ou login...">
            </div>
            <div id="member-search-results" class="ou-browser-list" style="display: none;"></div>
            <div id="add-member-message" class="alert" style="display: none;"></div>
        </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/groups.js"></script>';
include __DIR__ . '/layout.php';
?>
