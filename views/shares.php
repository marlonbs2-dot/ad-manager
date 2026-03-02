<?php
$title = 'Logs de Compartilhamentos - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Logs de Compartilhamentos</h1>
    <p>Monitore e audite acessos aos compartilhamentos de rede do Windows Server</p>
</div>

<!-- Estatísticas -->
<div class="stats-grid" id="shareStats" style="display: none;">
    <div class="stat-card">
        <div class="stat-icon stat-icon-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="totalAccesses">0</div>
            <div class="stat-label">Acessos (7 dias)</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-icon-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="uniqueUsers">0</div>
            <div class="stat-label">Usuários Únicos</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-icon-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="uniqueShares">0</div>
            <div class="stat-label">Compartilhamentos</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-icon-warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="lastSync">Nunca</div>
            <div class="stat-label">Última Sincronização</div>
        </div>
    </div>
</div>

<!-- Controles -->
<div class="card">
    <div class="card-header">
        <h2>Controles de Sincronização</h2>
        <div class="card-actions">
            <button id="btnManageServers" class="btn-action btn-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                    <line x1="6" y1="6" x2="6.01" y2="6"/>
                    <line x1="6" y1="18" x2="6.01" y2="18"/>
                </svg>
                Gerenciar Servidores
            </button>
            <button id="btnSyncNow" class="btn-action btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Sincronizar Agora
            </button>
            <button id="btnExportLogs" class="btn-action btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Exportar
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label for="syncServer">Servidor:</label>
                <select id="syncServer" class="form-control">
                    <option value="">Carregando servidores...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="syncHours">Sincronizar últimas:</label>
                <select id="syncHours" class="form-control">
                    <option value="1">1 hora</option>
                    <option value="6">6 horas</option>
                    <option value="24" selected>24 horas</option>
                    <option value="72">3 dias</option>
                    <option value="168">7 dias</option>
                </select>
            </div>
            <div class="form-group">
                <label for="syncShareFilter">Filtrar por compartilhamento:</label>
                <input type="text" id="syncShareFilter" class="form-control" placeholder="Ex: ShareTest, SYSVOL (deixe vazio para usar configuração padrão)">
                <small class="form-text text-muted">
                    Filtra logs apenas do compartilhamento especificado (busca parcial)<br>
                    <strong>Compartilhamentos monitorados por padrão:</strong> ShareTest, NETLOGON, SYSVOL<br>
                    <strong>Sempre ignorados:</strong> IPC$, ADMIN$, C$, D$, E$, F$, print$
                </small>
            </div>
        </div>
        <div id="syncStatus" class="alert" style="display: none; margin-top: 1rem;"></div>
    </div>
</div>

<!-- Filtros -->
<div class="card">
    <div class="card-header">
        <h2>Filtros de Pesquisa</h2>
        <div class="card-actions">
            <button id="btnApplyFilters" class="btn-action btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                Aplicar Filtros
            </button>
            <button id="btnClearFilters" class="btn-action btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Limpar Filtros
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label for="filterServer">Servidor:</label>
                <select id="filterServer" class="form-control">
                    <option value="">Todos os servidores</option>
                </select>
            </div>
            <div class="form-group">
                <label for="filterUsername">Usuário:</label>
                <input type="text" id="filterUsername" class="form-control" placeholder="Nome do usuário">
            </div>
            <div class="form-group">
                <label for="filterAction">Ação:</label>
                <select id="filterAction" class="form-control">
                    <option value="">Todas as ações</option>
                    <option value="share_access">Acesso a Compartilhamento</option>
                    <option value="share_object_access">Acesso a Objeto</option>
                    <option value="file_access_attempt">Tentativa de Acesso a Arquivo</option>
                    <option value="file_handle_requested">Abertura de Arquivo</option>
                    <option value="file_handle_closed">Fechamento de Arquivo</option>
                </select>
            </div>
            <div class="form-group">
                <label for="filterShareName">Compartilhamento:</label>
                <input type="text" id="filterShareName" class="form-control" placeholder="Nome do compartilhamento">
            </div>
            <div class="form-group">
                <label for="filterDateFrom">Data Inicial:</label>
                <input type="datetime-local" id="filterDateFrom" class="form-control">
            </div>
            <div class="form-group">
                <label for="filterDateTo">Data Final:</label>
                <input type="datetime-local" id="filterDateTo" class="form-control">
            </div>
        </div>
    </div>
</div>

<!-- Resultados -->
<div class="card">
    <div class="card-header">
        <h2>Logs de Compartilhamentos</h2>
        <span id="resultsCount" class="badge">0</span>
    </div>
    <div class="card-body">
        <div id="logsLoading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p>Carregando logs...</p>
        </div>
        
        <div id="logsError" class="alert alert-danger" style="display: none;"></div>
        
        <div class="table-responsive">
            <table class="table table-striped" id="logsTable" style="display: none;">
                <thead class="table-header">
                    <tr>
                        <th width="15%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            Data/Hora
                        </th>
                        <th width="12%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Usuário
                        </th>
                        <th width="15%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                            </svg>
                            Ação
                        </th>
                        <th width="15%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            Compartilhamento
                        </th>
                        <th width="25%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            Objeto/Arquivo
                        </th>
                        <th width="12%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                            </svg>
                            IP Origem
                        </th>
                        <th width="6%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4"/>
                                <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                                <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                            </svg>
                            Detalhes
                        </th>
                    </tr>
                </thead>
                <tbody id="logsTableBody"></tbody>
            </table>
        </div>
        
        <div id="noLogs" class="alert alert-info text-center" style="display: none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem; color: var(--info);">
                <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            <h5>Nenhum log encontrado</h5>
            <p class="mb-0">Não há logs de compartilhamentos para os filtros selecionados. Tente sincronizar os dados ou ajustar os filtros.</p>
        </div>
        
        <!-- Paginação -->
        <div id="pagination" class="pagination" style="display: none;">
            <button id="btnPrevPage" class="btn-action btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Anterior
            </button>
            <span id="pageInfo">Página 1 de 1</span>
            <button id="btnNextPage" class="btn-action btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                Próxima
            </button>
        </div>
    </div>
</div>

<!-- Modal: Detalhes do Log -->
<div class="modal" id="logDetailsModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Detalhes do Log de Compartilhamento</h3>
            <button class="modal-close" onclick="closeLogDetailsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="user-details-grid" id="logDetailsContent">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-action btn-secondary" onclick="closeLogDetailsModal()">Fechar</button>
        </div>
    </div>
</div>

<!-- Modal: Exportar Logs -->
<div class="modal" id="exportModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Exportar Logs de Compartilhamentos</h3>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="exportFormat">Formato:</label>
                <select id="exportFormat" class="form-control">
                    <option value="csv">CSV (Excel)</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <div class="alert alert-info">
                <strong>Nota:</strong> A exportação utilizará os filtros atualmente aplicados. 
                Máximo de 10.000 registros por exportação.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-action btn-secondary" onclick="closeExportModal()">Cancelar</button>
            <button type="button" class="btn-action btn-primary" onclick="performExport()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Exportar
            </button>
        </div>
    </div>
</div>

<!-- Modal: Gerenciar Servidores -->
<div class="modal" id="serversModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Gerenciar Servidores de Compartilhamentos</h3>
            <button class="modal-close" onclick="closeServersModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="card-actions" style="margin-bottom: 1rem;">
                <button id="btnAddServer" class="btn-action btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Adicionar Servidor
                </button>
            </div>
            
            <div id="serversLoading" class="loading" style="display: none;">
                <div class="spinner"></div>
                <p>Carregando servidores...</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped" id="serversTable" style="display: none;">
                    <thead class="table-header">
                        <tr>
                            <th>Nome</th>
                            <th>Hostname</th>
                            <th>Usuário</th>
                            <th>Domínio</th>
                            <th>Status</th>
                            <th>Última Sync</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="serversTableBody"></tbody>
                </table>
            </div>
            
            <div id="noServers" class="alert alert-info text-center" style="display: none;">
                <h5>Nenhum servidor configurado</h5>
                <p class="mb-0">Clique em "Adicionar Servidor" para configurar seu primeiro servidor.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-action btn-secondary" onclick="closeServersModal()">Fechar</button>
        </div>
    </div>
</div>

<!-- Modal: Adicionar/Editar Servidor -->
<div class="modal" id="serverFormModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="serverFormTitle">Adicionar Servidor</h3>
            <button class="modal-close" onclick="closeServerFormModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="serverForm">
                <input type="hidden" id="serverId" value="">
                
                <div class="form-group">
                    <label for="serverName">Nome do Servidor:</label>
                    <input type="text" id="serverName" class="form-control" required 
                           placeholder="Ex: servidor-principal">
                    <small class="form-text">Nome único para identificar o servidor</small>
                </div>
                
                <div class="form-group">
                    <label for="serverHostname">Hostname/IP:</label>
                    <input type="text" id="serverHostname" class="form-control" required 
                           placeholder="Ex: 192.168.1.100 ou servidor.dominio.local">
                </div>
                
                <div class="form-group">
                    <label for="serverUsername">Usuário:</label>
                    <input type="text" id="serverUsername" class="form-control" required 
                           placeholder="Ex: administrador">
                </div>
                
                <div class="form-group">
                    <label for="serverPassword">Senha:</label>
                    <input type="password" id="serverPassword" class="form-control" 
                           placeholder="Digite a senha do usuário">
                    <small class="form-text">Deixe em branco para manter a senha atual (apenas edição)</small>
                </div>
                
                <div class="form-group">
                    <label for="serverDomain">Domínio (opcional):</label>
                    <input type="text" id="serverDomain" class="form-control" 
                           placeholder="Ex: empresa.local">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="serverEnabled" checked> 
                        Servidor ativo
                    </label>
                </div>
                
                <div id="serverFormStatus" class="alert" style="display: none;"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-action btn-secondary" onclick="closeServerFormModal()">Cancelar</button>
            <button type="button" id="btnTestConnection" class="btn-action btn-info" onclick="testServerConnection()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Testar Conexão
            </button>
            <button type="button" id="btnSaveServer" class="btn-action btn-primary" onclick="saveServer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Hidden CSRF Token -->
<input type="hidden" id="share-csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">

<style>
/* Estilos específicos para logs de compartilhamentos */
.loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.spinner {
    border: 3px solid var(--border-color);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.table-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.table-header th {
    border: none;
    padding: 1rem 0.75rem;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    vertical-align: middle;
}

.table-header th svg {
    margin-right: 0.5rem;
    vertical-align: middle;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: var(--bg-secondary);
}

.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: var(--primary) !important;
    color: white !important;
}

.table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-top: 1px solid var(--border-color);
}

.action-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.action-share-access { background: #e3f2fd; color: #1976d2; }
.action-share-object-access { background: #f3e5f5; color: #7b1fa2; }
.action-file-handle-requested { background: #e8f5e8; color: #388e3c; }
.action-file-handle-closed { background: #fff3e0; color: #f57c00; }
.action-file-access-attempt { background: #ffebee; color: #d32f2f; }

.object-path {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: var(--text-secondary);
    word-break: break-all;
}

.ip-address {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: var(--text-primary);
    background: var(--bg-tertiary);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
    border: 1px solid var(--border-color);
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
}

/* Melhorias específicas para tema escuro */
[data-theme="dark"] .table-header {
    background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
}

[data-theme="dark"] .action-share-access { background: rgba(25, 118, 210, 0.2); color: #64b5f6; }
[data-theme="dark"] .action-share-object-access { background: rgba(123, 31, 162, 0.2); color: #ba68c8; }
[data-theme="dark"] .action-file-handle-requested { background: rgba(56, 142, 60, 0.2); color: #81c784; }
[data-theme="dark"] .action-file-handle-closed { background: rgba(245, 124, 0, 0.2); color: #ffb74d; }
[data-theme="dark"] .action-file-access-attempt { background: rgba(211, 47, 47, 0.2); color: #e57373; }

/* Estilos para modal grande */
.modal-lg {
    max-width: 900px;
}

/* Estilos para formulários */
.form-text {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* Melhorias para botões de ação em tabelas */
.table tbody td .btn-action {
    margin-right: 0.25rem;
}

.table tbody td .btn-action:last-child {
    margin-right: 0;
}
</style>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/shares.js"></script>';
include __DIR__ . '/layout.php';
?>