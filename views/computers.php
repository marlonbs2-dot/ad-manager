<?php
$title = 'Computadores - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Gerenciamento de Computadores</h1>
    <p>Buscar e gerenciar computadores do Active Directory</p>
</div>

<div class="card">
    <div class="card-body">
        <div class="search-bar">
            <input type="text" id="computer-search" placeholder="Buscar computadores por nome ou hostname..." class="search-input">
            <button id="search-btn" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                Buscar
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
                            <th>Hostname</th>
                            <th>Sistema Operacional</th>
                            <th>Grupos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="computers-table-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Computer Details Modal -->
<div id="computer-details-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 id="modal-computer-name">Detalhes do Computador</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="current-computer-dn">
            
            <div class="computer-details">
                <div class="detail-item">
                    <label>Nome:</label>
                    <span id="detail-name"></span>
                </div>
                <div class="detail-item">
                    <label>Hostname:</label>
                    <span id="detail-hostname"></span>
                </div>
                <div class="detail-item">
                    <label>Sistema Operacional:</label>
                    <span id="detail-os"></span>
                </div>
                <div class="detail-item">
                    <label>Versão do SO:</label>
                    <span id="detail-os-version"></span>
                </div>
                <div class="detail-item">
                    <label>Criado em:</label>
                    <span id="detail-created-at"></span>
                </div>
                <div class="detail-item">
                    <label>Total de Grupos:</label>
                    <span id="detail-group-count"></span>
                </div>
            </div>

            <div class="detail-section">
                <div class="section-header">
                    <h3>Membro de Grupos</h3>
                    <button id="btn-add-to-group" class="btn-action btn-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Adicionar a Grupo
                    </button>
                </div>
                <div id="member-of-groups-list" class="groups-list"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="btn-delete-computer" class="btn-action btn-danger">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                Excluir Computador
            </button>
        </div>
    </div>
</div>

<!-- Add Computer to Group Modal -->
<div id="add-to-group-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Adicionar Computador a Grupo</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="add-to-group-csrf" value="<?= \App\Security\CSRF::generateToken() ?>">
            
            <div class="form-group">
                <label for="group-search-input">Buscar Grupo:</label>
                <input type="text" id="group-search-input" class="form-control" placeholder="Digite o nome do grupo...">
            </div>

            <div id="group-search-results" class="ou-browser-list" style="display: none;"></div>

            <div id="add-to-group-message" class="alert" style="display: none;"></div>
        </div>
        <div class="modal-footer">
            
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/computers.js"></script>';
include __DIR__ . '/layout.php';
?>