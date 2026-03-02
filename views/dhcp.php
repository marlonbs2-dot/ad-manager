<?php
$title = 'DHCP - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Gerenciamento DHCP</h1>
    <p>Gerencie escopos e reservas DHCP do Windows Server</p>
</div>

<!-- Seleção de Escopo -->
<div class="card" id="scopesCard">
    <div class="card-header">
        <h2>Escopos DHCP</h2>
        <button class="btn btn-primary" onclick="loadScopes()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Atualizar
        </button>
    </div>
    <div class="card-body">
        <div id="scopesLoading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p>Carregando escopos...</p>
        </div>
        <div id="scopesError" class="alert alert-danger" style="display: none;"></div>
        <div id="scopesList" class="scopes-grid"></div>
    </div>
</div>

<!-- Reservas do Escopo Selecionado -->
<div class="card" id="reservationsCard" style="display: none;">
    <div class="card-header">
        <div>
            <h2>Reservas - <span id="selectedScopeName"></span> <span id="reservationCount" class="badge badge-info"
                    style="display: none;"></span></h2>
            <p class="text-muted" id="selectedScopeInfo"></p>
        </div>
        <div>
            <button class="btn btn-secondary" onclick="backToScopes()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Voltar
            </button>
            <button class="btn btn-primary" onclick="showAddReservationModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nova Reserva
            </button>
        </div>
    </div>
    <!-- Campo de busca global -->
    <div style="padding:.75rem 1.5rem;border-bottom:1px solid var(--border-color);background:var(--bg-secondary)">
        <input type="text" id="dhcpSearch" class="form-control"
            placeholder="🔍  Buscar por IP, MAC ou hostname nas reservas e leases..." oninput="filterDhcp(this.value)"
            style="max-width:480px">
    </div>
    <div class="card-body">
        <div id="reservationsLoading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p>Carregando reservas...</p>
        </div>
        <div id="reservationsError" class="alert alert-danger" style="display: none;"></div>
        <div class="table-responsive">
            <table class="table table-striped" id="reservationsTable" style="display: none;">
                <thead class="table-header">
                    <tr>
                        <th width="15%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                            </svg>
                            Endereço IP
                        </th>
                        <th width="20%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            Endereço MAC
                        </th>
                        <th width="15%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Nome
                        </th>
                        <th width="25%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            Descrição
                        </th>
                        <th width="25%" class="text-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V6a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                                </path>
                            </svg>
                            Ações
                        </th>
                    </tr>
                </thead>
                <tbody id="reservationsTableBody"></tbody>
            </table>
        </div>
        <div id="noReservations" class="alert alert-info text-center" style="display: none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                style="margin-bottom: 1rem; color: var(--info);">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
            </svg>
            <h5 style="color: var(--text-primary);">Nenhuma reserva encontrada</h5>
            <p class="mb-0" style="color: var(--text-secondary);">Este escopo DHCP não possui reservas configuradas.
                Clique em "Nova Reserva" para adicionar uma.</p>
        </div>
    </div>
</div>

<!-- Leases (IPs Distribuídos) do Escopo Selecionado -->
<div class="card" id="leasesCard" style="display: none;">
    <div class="card-header">
        <div>
            <h2>IPs Distribuídos (Leases) - <span id="selectedScopeNameLeases"></span> <span id="leaseCount"
                    class="badge badge-info" style="display: none;"></span></h2>
            <p class="text-muted">IPs atribuídos dinamicamente pelo servidor DHCP</p>
        </div>
    </div>
    <div class="card-body">
        <div id="leasesLoading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p>Carregando leases...</p>
        </div>
        <div id="leasesError" class="alert alert-danger" style="display: none;"></div>
        <div class="table-responsive">
            <table class="table table-striped" id="leasesTable" style="display: none;">
                <thead class="table-header">
                    <tr>
                        <th width="15%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                            </svg>
                            Endereço IP
                        </th>
                        <th width="20%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            Endereço MAC
                        </th>
                        <th width="20%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            Hostname
                        </th>
                        <th width="15%" class="text-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Estado
                        </th>
                        <th width="20%">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Expiração
                        </th>
                        <th width="10%" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody id="leasesTableBody"></tbody>
            </table>
        </div>
        <div id="noLeases" class="alert alert-info text-center" style="display: none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                style="margin-bottom: 1rem; color: var(--info);">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
            </svg>
            <h5 style="color: var(--text-primary);">Nenhum lease encontrado</h5>
            <p class="mb-0" style="color: var(--text-secondary);">Este escopo DHCP não possui IPs distribuídos no
                momento.</p>
        </div>
    </div>
</div>

<!-- Hidden CSRF Token -->
<input type="hidden" id="dhcp-csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">

<!-- Modal: Nova Reserva -->
<div class="modal" id="addReservationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nova Reserva DHCP</h3>
            <button class="modal-close" onclick="closeAddReservationModal()">&times;</button>
        </div>
        <form id="addReservationForm" onsubmit="addReservation(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label for="reservationIP">Endereço IP *</label>
                    <input type="text" id="reservationIP" class="form-control" required placeholder="192.168.1.100"
                        pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                </div>
                <div class="form-group">
                    <label for="reservationMAC">Endereço MAC *</label>
                    <input type="text" id="reservationMAC" class="form-control" required
                        placeholder="00-11-22-33-44-55">
                </div>
                <div class="form-group">
                    <label for="reservationName">Nome *</label>
                    <input type="text" id="reservationName" class="form-control" required placeholder="Servidor Web">
                </div>
                <div class="form-group">
                    <label for="reservationDescription">Descrição</label>
                    <input type="text" id="reservationDescription" class="form-control"
                        placeholder="Descrição opcional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddReservationModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar Reserva</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Reserva -->
<div class="modal" id="editReservationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Reserva DHCP</h3>
            <button class="modal-close" onclick="closeEditReservationModal()">&times;</button>
        </div>
        <form id="editReservationForm" onsubmit="updateReservation(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label for="editReservationIP">Endereço IP *</label>
                    <input type="text" id="editReservationIP" class="form-control" required placeholder="192.168.1.100"
                        pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                </div>
                <div class="form-group">
                    <label for="editReservationMAC">Endereço MAC *</label>
                    <input type="text" id="editReservationMAC" class="form-control" required
                        placeholder="00-11-22-33-44-55">
                </div>
                <div class="form-group">
                    <label for="editReservationName">Nome *</label>
                    <input type="text" id="editReservationName" class="form-control" required
                        placeholder="Servidor Web">
                </div>
                <div class="form-group">
                    <label for="editReservationDescription">Descrição</label>
                    <input type="text" id="editReservationDescription" class="form-control"
                        placeholder="Descrição opcional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditReservationModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<style>
    .scopes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }

    .scope-card {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .scope-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px var(--shadow);
    }

    .scope-card h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.1rem;
        color: var(--text-primary);
    }

    .scope-card .badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .badge-success {
        background: var(--success);
        color: white;
    }

    .badge-danger {
        background: var(--error);
        color: white;
    }

    .badge-warning {
        background: #ffc107;
        color: #212529;
    }

    .badge-info {
        background: var(--info);
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.875rem;
    }

    /* Melhorias na tabela de reservas */
    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px var(--shadow);
    }

    .table {
        margin-bottom: 0;
        background: var(--bg-primary);
        color: var(--text-primary);
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

    .table tbody tr:hover .ip-address,
    .table tbody tr:hover .mac-address,
    .table tbody tr:hover .device-name,
    .table tbody tr:hover .device-description {
        color: white !important;
        background-color: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    .table tbody td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        border-top: 1px solid var(--border-color);
    }

    /* Estilização específica para células */
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

    .mac-address {
        font-family: 'Courier New', monospace;
        color: var(--info);
        background: var(--bg-secondary);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        display: inline-block;
        font-size: 0.9rem;
    }

    .device-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .device-description {
        color: var(--text-secondary);
        font-style: italic;
    }

    .device-description.empty {
        color: var(--text-tertiary);
        font-size: 0.9rem;
    }

    /* Botão de ação melhorado */
    .btn-action {
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px var(--shadow);
    }

    .btn-action.btn-danger {
        background: linear-gradient(135deg, var(--error), var(--error-hover));
        color: white;
    }

    .btn-action.btn-danger:hover {
        background: linear-gradient(135deg, var(--error-hover), #b02a37);
    }

    .btn-action.btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        color: white;
    }

    .btn-action.btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-hover), #2c4a7a);
    }

    .btn-action.btn-reserve {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
    }

    .btn-action.btn-reserve:hover {
        background: linear-gradient(135deg, #218838, #1a6e2e);
    }

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
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        margin: 5% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px var(--shadow-lg);
    }

    .modal-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-secondary);
    }

    .modal-close:hover {
        color: var(--text-primary);
    }

    /* Responsividade para dispositivos móveis */
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }

        .table-header th {
            padding: 0.75rem 0.5rem;
            font-size: 0.8rem;
        }

        .table tbody td {
            padding: 0.75rem 0.5rem;
        }

        .ip-address,
        .mac-address {
            font-size: 0.8rem;
            padding: 0.2rem 0.4rem;
        }
    }

    /* Melhorias específicas para tema escuro */
    [data-theme="dark"] .table-header {
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    }

    [data-theme="dark"] .table tbody tr:hover {
        background-color: var(--primary) !important;
    }

    [data-theme="dark"] .ip-address {
        background: var(--bg-tertiary);
        border-color: var(--border-color);
    }

    [data-theme="dark"] .mac-address {
        background: var(--bg-tertiary);
        border-color: var(--border-color);
        color: #81c784;
    }

    /* Ajustes para alertas no tema escuro */
    [data-theme="dark"] .alert {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }

    [data-theme="dark"] .alert-info {
        background: rgba(23, 162, 184, 0.1);
        border-color: var(--info);
    }

    [data-theme="dark"] .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border-color: var(--error);
    }
</style>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/dhcp.js"></script>';
include __DIR__ . '/layout.php';
?>