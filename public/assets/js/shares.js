// Estado global
let currentPage = 1;
let currentFilters = {};
let isLoading = false;

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    initializeEventListeners();
    loadStatistics();
    loadLogs();
    
    // Definir data padrão (últimas 24 horas)
    const now = new Date();
    const yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
    
    document.getElementById('filterDateFrom').value = formatDateTimeLocal(yesterday);
    document.getElementById('filterDateTo').value = formatDateTimeLocal(now);
});

// Event Listeners
function initializeEventListeners() {
    // Botões principais
    document.getElementById('btnSyncNow').addEventListener('click', syncLogs);
    document.getElementById('btnExportLogs').addEventListener('click', showExportModal);
    document.getElementById('btnManageServers').addEventListener('click', showServersModal);
    document.getElementById('btnApplyFilters').addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters').addEventListener('click', clearFilters);
    
    // Paginação
    document.getElementById('btnPrevPage').addEventListener('click', () => changePage(currentPage - 1));
    document.getElementById('btnNextPage').addEventListener('click', () => changePage(currentPage + 1));
    
    // Filtros em tempo real
    document.getElementById('filterUsername').addEventListener('input', debounce(applyFilters, 500));
    document.getElementById('filterShareName').addEventListener('input', debounce(applyFilters, 500));
    
    // Modal handlers
    window.onclick = function(event) {
        const logModal = document.getElementById('logDetailsModal');
        const exportModal = document.getElementById('exportModal');
        const serversModal = document.getElementById('serversModal');
        const serverFormModal = document.getElementById('serverFormModal');
        
        if (event.target === logModal) {
            closeLogDetailsModal();
        }
        if (event.target === exportModal) {
            closeExportModal();
        }
        if (event.target === serversModal) {
            closeServersModal();
        }
        if (event.target === serverFormModal) {
            closeServerFormModal();
        }
    }
}

// Sincronizar logs do servidor
async function syncLogs() {
    const btnSync = document.getElementById('btnSyncNow');
    const statusDiv = document.getElementById('syncStatus');
    const server = document.getElementById('syncServer').value;
    const hours = document.getElementById('syncHours').value;
    const shareFilter = document.getElementById('syncShareFilter').value;
    
    if (isLoading) return;
    
    isLoading = true;
    btnSync.disabled = true;
    btnSync.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="0">
                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
            </circle>
        </svg>
        Sincronizando...
    `;
    
    try {
        const csrfToken = document.getElementById('share-csrf-token').value;
        
        const response = await fetch('/shares/api/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                server: server,
                hours: parseInt(hours),
                share_filter: shareFilter
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        // Mostrar resultado
        statusDiv.className = 'alert alert-success';
        statusDiv.style.display = 'block';
        let message = `${data.data.imported} novos logs importados, ${data.data.skipped} ignorados (duplicatas)`;
        if (shareFilter) {
            message += `<br>Filtrado por compartilhamento: <strong>${shareFilter}</strong>`;
        }
        statusDiv.innerHTML = `
            <strong>Sincronização concluída!</strong><br>
            ${message}
        `;
        
        // Recarregar dados
        await loadStatistics();
        await loadLogs();
        
    } catch (error) {
        statusDiv.className = 'alert alert-danger';
        statusDiv.style.display = 'block';
        statusDiv.innerHTML = `<strong>Erro na sincronização:</strong> ${error.message}`;
    } finally {
        isLoading = false;
        btnSync.disabled = false;
        btnSync.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                <path d="M9 12l2 2 4-4"/>
            </svg>
            Sincronizar Logs
        `;
        
        // Ocultar status após 5 segundos
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }
}

// Carregar estatísticas
async function loadStatistics() {
    try {
        const response = await fetch('/shares/api/statistics?days=7');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        const stats = data.data;
        
        // Atualizar cards de estatísticas
        document.getElementById('totalAccesses').textContent = stats.total_accesses || 0;
        document.getElementById('uniqueUsers').textContent = stats.top_users?.length || 0;
        document.getElementById('uniqueShares').textContent = stats.top_shares?.length || 0;
        document.getElementById('lastSync').textContent = 'Agora';
        
        // Mostrar estatísticas
        document.getElementById('shareStats').style.display = 'grid';
        
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

// Carregar logs
async function loadLogs(page = 1) {
    if (isLoading) return;
    
    const loading = document.getElementById('logsLoading');
    const error = document.getElementById('logsError');
    const table = document.getElementById('logsTable');
    const noLogs = document.getElementById('noLogs');
    const tbody = document.getElementById('logsTableBody');
    const pagination = document.getElementById('pagination');
    
    isLoading = true;
    loading.style.display = 'block';
    error.style.display = 'none';
    table.style.display = 'none';
    noLogs.style.display = 'none';
    pagination.style.display = 'none';
    
    try {
        // Construir query string com filtros
        const params = new URLSearchParams({
            page: page,
            limit: 50,
            ...currentFilters
        });
        
        const response = await fetch(`/shares/api/logs?${params}`);
        const data = await response.json();
        
        loading.style.display = 'none';
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        if (data.data.length === 0) {
            noLogs.style.display = 'block';
            document.getElementById('resultsCount').textContent = '0';
            return;
        }
        
        // Renderizar logs
        tbody.innerHTML = '';
        data.data.forEach(log => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${formatDateTime(log.time_created)}</td>
                <td>
                    <strong>${log.username || 'N/A'}</strong>
                    ${log.domain ? `<br><small>${log.domain}</small>` : ''}
                </td>
                <td>
                    <span class="action-badge action-${log.action.replace(/_/g, '-')}">${formatAction(log.action)}</span>
                </td>
                <td>
                    <strong>${log.share_name || 'N/A'}</strong>
                    ${log.share_path ? `<br><small class="object-path">${log.share_path}</small>` : ''}
                </td>
                <td>
                    <div class="object-info">
                        <div class="object-path">${truncateText(log.object_name || 'N/A', 50)}</div>
                        ${log.object_type && log.object_type !== 'unknown' ? `<small class="object-type">${formatObjectType(log.object_type)}</small>` : ''}
                    </div>
                </td>
                <td>
                    ${log.source_ip ? `<span class="ip-address">${log.source_ip}</span>` : 'N/A'}
                </td>
                <td class="text-center">
                    <button class="btn-action btn-primary" onclick="showLogDetails(${log.id})" title="Ver detalhes">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        // Mostrar tabela
        table.style.display = 'table';
        
        // Atualizar contador
        document.getElementById('resultsCount').textContent = data.pagination.total_records;
        
        // Atualizar paginação
        updatePagination(data.pagination);
        
        currentPage = page;
        
    } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = 'Erro ao carregar logs: ' + err.message;
    } finally {
        isLoading = false;
    }
}

// Aplicar filtros
function applyFilters() {
    currentFilters = {
        server: document.getElementById('filterServer').value,
        username: document.getElementById('filterUsername').value,
        action: document.getElementById('filterAction').value,
        share_name: document.getElementById('filterShareName').value,
        date_from: document.getElementById('filterDateFrom').value,
        date_to: document.getElementById('filterDateTo').value
    };
    
    // Remover filtros vazios
    Object.keys(currentFilters).forEach(key => {
        if (!currentFilters[key]) {
            delete currentFilters[key];
        }
    });
    
    currentPage = 1;
    loadLogs(1);
}

// Limpar filtros
function clearFilters() {
    document.getElementById('filterServer').value = '';
    document.getElementById('filterUsername').value = '';
    document.getElementById('filterAction').value = '';
    document.getElementById('filterShareName').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    
    currentFilters = {};
    currentPage = 1;
    loadLogs(1);
}

// Mudar página
function changePage(page) {
    if (page < 1 || isLoading) return;
    loadLogs(page);
}

// Atualizar paginação
function updatePagination(pagination) {
    const paginationDiv = document.getElementById('pagination');
    const pageInfo = document.getElementById('pageInfo');
    const btnPrev = document.getElementById('btnPrevPage');
    const btnNext = document.getElementById('btnNextPage');
    
    if (pagination.total_pages <= 1) {
        paginationDiv.style.display = 'none';
        return;
    }
    
    pageInfo.textContent = `Página ${pagination.current_page} de ${pagination.total_pages}`;
    btnPrev.disabled = pagination.current_page <= 1;
    btnNext.disabled = pagination.current_page >= pagination.total_pages;
    
    paginationDiv.style.display = 'flex';
}

// Mostrar detalhes do log
async function showLogDetails(logId) {
    try {
        const response = await fetch(`/shares/api/logs?id=${logId}`);
        const data = await response.json();
        
        if (!data.success || !data.data.length) {
            throw new Error('Log não encontrado');
        }
        
        const log = data.data[0];
        const content = document.getElementById('logDetailsContent');
        
        content.innerHTML = `
            <div class="detail-item">
                <label>ID do Evento:</label>
                <span>${log.event_id || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Data/Hora:</label>
                <span>${formatDateTime(log.time_created)}</span>
            </div>
            <div class="detail-item">
                <label>Servidor:</label>
                <span>${log.server_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Usuário:</label>
                <span>${log.username || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Domínio:</label>
                <span>${log.domain || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Ação:</label>
                <span class="action-badge action-${log.action.replace(/_/g, '-')}">${formatAction(log.action)}</span>
            </div>
            <div class="detail-item">
                <label>Compartilhamento:</label>
                <span>${log.share_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Caminho do Compartilhamento:</label>
                <span class="object-path">${log.share_path || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Objeto/Arquivo:</label>
                <span class="object-path">${log.object_name || 'N/A'}</span>
                ${log.object_type && log.object_type !== 'unknown' ? `<br><small class="object-type">Tipo: ${formatObjectType(log.object_type)}</small>` : ''}
            </div>
            <div class="detail-item">
                <label>IP de Origem:</label>
                <span class="ip-address">${log.source_ip || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Máscara de Acesso:</label>
                <span>${log.access_mask || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Processo:</label>
                <span class="object-path">${log.process_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <label>Record ID:</label>
                <span>${log.event_record_id || 'N/A'}</span>
            </div>
        `;
        
        document.getElementById('logDetailsModal').classList.add('active');
        
    } catch (error) {
        alert('Erro ao carregar detalhes do log: ' + error.message);
    }
}

// Fechar modal de detalhes
function closeLogDetailsModal() {
    document.getElementById('logDetailsModal').classList.remove('active');
}

// Mostrar modal de exportação
function showExportModal() {
    document.getElementById('exportModal').classList.add('active');
}

// Fechar modal de exportação
function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
}

// Realizar exportação
async function performExport() {
    const format = document.getElementById('exportFormat').value;
    
    try {
        // Construir query string com filtros atuais
        const params = new URLSearchParams({
            format: format,
            ...currentFilters
        });
        
        const response = await fetch(`/shares/api/export?${params}`);
        
        if (!response.ok) {
            throw new Error('Erro na exportação');
        }
        
        // Fazer download do arquivo
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `share_logs_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.${format}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        closeExportModal();
        
    } catch (error) {
        alert('Erro na exportação: ' + error.message);
    }
}

// Utilitários
function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function formatAction(action) {
    const actions = {
        'share_access': 'Acesso a Compartilhamento',
        'share_object_access': 'Acesso a Objeto',
        'file_handle_requested': 'Abertura de Arquivo',
        'file_handle_closed': 'Fechamento de Arquivo',
        'file_access_attempt': 'Tentativa de Acesso'
    };
    
    return actions[action] || action;
}

function formatObjectType(objectType) {
    const types = {
        'file_or_folder': 'Arquivo/Pasta',
        'share_object': 'Objeto do Compartilhamento',
        'full_path': 'Caminho Completo',
        'process': 'Processo',
        'handle': 'Handle de Arquivo',
        'access_list': 'Lista de Acesso',
        'share_root': 'Raiz do Compartilhamento',
        'resource': 'Recurso do Sistema',
        'unknown': 'Desconhecido'
    };
    
    return types[objectType] || objectType;
}

function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// === GERENCIAMENTO DE SERVIDORES ===

// Carregar lista de servidores
async function loadServers() {
    try {
        const response = await fetch('/shares/api/servers');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        // Atualizar dropdown de sincronização
        const syncSelect = document.getElementById('syncServer');
        const filterSelect = document.getElementById('filterServer');
        
        syncSelect.innerHTML = '';
        filterSelect.innerHTML = '<option value="">Todos os servidores</option>';
        
        if (data.data.length === 0) {
            syncSelect.innerHTML = '<option value="">Nenhum servidor configurado</option>';
            return;
        }
        
        data.data.forEach(server => {
            if (server.enabled) {
                const option = new Option(server.name, server.name);
                syncSelect.appendChild(option);
                
                const filterOption = new Option(server.name, server.name);
                filterSelect.appendChild(filterOption);
            }
        });
        
        return data.data;
        
    } catch (error) {
        console.error('Erro ao carregar servidores:', error);
        const syncSelect = document.getElementById('syncServer');
        syncSelect.innerHTML = '<option value="">Erro ao carregar servidores</option>';
    }
}

// Mostrar modal de gerenciamento de servidores
async function showServersModal() {
    document.getElementById('serversModal').classList.add('active');
    await loadServersTable();
}

// Fechar modal de servidores
function closeServersModal() {
    document.getElementById('serversModal').classList.remove('active');
}

// Carregar tabela de servidores
async function loadServersTable() {
    const loading = document.getElementById('serversLoading');
    const table = document.getElementById('serversTable');
    const noServers = document.getElementById('noServers');
    const tbody = document.getElementById('serversTableBody');
    
    loading.style.display = 'block';
    table.style.display = 'none';
    noServers.style.display = 'none';
    
    try {
        const response = await fetch('/shares/api/servers');
        const data = await response.json();
        
        loading.style.display = 'none';
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        if (data.data.length === 0) {
            noServers.style.display = 'block';
            return;
        }
        
        // Renderizar servidores
        tbody.innerHTML = '';
        data.data.forEach(server => {
            const row = document.createElement('tr');
            
            const statusBadge = server.enabled 
                ? '<span class="badge badge-success">Ativo</span>'
                : '<span class="badge badge-secondary">Inativo</span>';
                
            const syncStatus = server.sync_status === 'success' 
                ? '<span class="badge badge-success">Sucesso</span>'
                : server.sync_status === 'error'
                ? '<span class="badge badge-danger">Erro</span>'
                : '<span class="badge badge-secondary">Nunca</span>';
            
            const lastSync = server.last_sync 
                ? formatDateTime(server.last_sync)
                : 'Nunca';
            
            row.innerHTML = `
                <td><strong>${server.name}</strong></td>
                <td>${server.hostname}</td>
                <td>${server.username}</td>
                <td>${server.domain || 'N/A'}</td>
                <td>${statusBadge}</td>
                <td>${lastSync}</td>
                <td>
                    <button class="btn-action btn-primary" onclick="editServer(${server.id})" title="Editar">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-action btn-danger" onclick="deleteServerConfirm(${server.id}, '${server.name}')" title="Excluir">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        table.style.display = 'table';
        
    } catch (error) {
        loading.style.display = 'none';
        alert('Erro ao carregar servidores: ' + error.message);
    }
}

// Mostrar modal de adicionar servidor
function showAddServerModal() {
    document.getElementById('serverFormTitle').textContent = 'Adicionar Servidor';
    document.getElementById('serverId').value = '';
    document.getElementById('serverForm').reset();
    document.getElementById('serverEnabled').checked = true;
    document.getElementById('serverPassword').required = true;
    document.getElementById('serverFormModal').classList.add('active');
}

// Editar servidor
async function editServer(serverId) {
    try {
        const response = await fetch('/shares/api/servers');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        const server = data.data.find(s => s.id === serverId);
        if (!server) {
            throw new Error('Servidor não encontrado');
        }
        
        document.getElementById('serverFormTitle').textContent = 'Editar Servidor';
        document.getElementById('serverId').value = server.id;
        document.getElementById('serverName').value = server.name;
        document.getElementById('serverHostname').value = server.hostname;
        document.getElementById('serverUsername').value = server.username;
        document.getElementById('serverPassword').value = '';
        document.getElementById('serverPassword').required = false;
        document.getElementById('serverDomain').value = server.domain || '';
        document.getElementById('serverEnabled').checked = server.enabled;
        
        document.getElementById('serverFormModal').classList.add('active');
        
    } catch (error) {
        alert('Erro ao carregar dados do servidor: ' + error.message);
    }
}

// Fechar modal de formulário
function closeServerFormModal() {
    document.getElementById('serverFormModal').classList.remove('active');
    document.getElementById('serverFormStatus').style.display = 'none';
}

// Salvar servidor
async function saveServer() {
    const serverId = document.getElementById('serverId').value;
    const isEdit = serverId !== '';
    
    const data = {
        csrf_token: document.getElementById('share-csrf-token').value,
        name: document.getElementById('serverName').value,
        hostname: document.getElementById('serverHostname').value,
        username: document.getElementById('serverUsername').value,
        domain: document.getElementById('serverDomain').value,
        enabled: document.getElementById('serverEnabled').checked
    };
    
    const password = document.getElementById('serverPassword').value;
    if (password || !isEdit) {
        data.password = password;
    }
    
    // Validação básica
    if (!data.name || !data.hostname || !data.username) {
        showServerFormStatus('error', 'Preencha todos os campos obrigatórios');
        return;
    }
    
    if (!isEdit && !password) {
        showServerFormStatus('error', 'Senha é obrigatória para novos servidores');
        return;
    }
    
    try {
        const url = isEdit ? `/shares/api/servers/${serverId}` : '/shares/api/servers';
        const method = isEdit ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }
        
        showServerFormStatus('success', result.message);
        
        // Recarregar dados
        setTimeout(async () => {
            closeServerFormModal();
            await loadServersTable();
            await loadServers();
        }, 1500);
        
    } catch (error) {
        showServerFormStatus('error', 'Erro ao salvar servidor: ' + error.message);
    }
}

// Testar conexão do servidor
async function testServerConnection() {
    const hostname = document.getElementById('serverHostname').value;
    const username = document.getElementById('serverUsername').value;
    const password = document.getElementById('serverPassword').value;
    const domain = document.getElementById('serverDomain').value;
    
    if (!hostname || !username || !password) {
        showServerFormStatus('error', 'Preencha hostname, usuário e senha para testar a conexão');
        return;
    }
    
    const btnTest = document.getElementById('btnTestConnection');
    const originalText = btnTest.innerHTML;
    
    btnTest.disabled = true;
    btnTest.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="0">
                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
            </circle>
        </svg>
        Testando...
    `;
    
    try {
        const response = await fetch('/shares/api/servers/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                hostname: hostname,
                username: username,
                password: password,
                domain: domain
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showServerFormStatus('success', result.message);
        } else {
            showServerFormStatus('error', result.message);
        }
        
    } catch (error) {
        showServerFormStatus('error', 'Erro ao testar conexão: ' + error.message);
    } finally {
        btnTest.disabled = false;
        btnTest.innerHTML = originalText;
    }
}

// Confirmar exclusão de servidor
function deleteServerConfirm(serverId, serverName) {
    if (confirm(`Tem certeza que deseja excluir o servidor "${serverName}"?\n\nEsta ação não pode ser desfeita.`)) {
        deleteServerById(serverId);
    }
}

// Excluir servidor
async function deleteServerById(serverId) {
    try {
        const response = await fetch(`/shares/api/servers/${serverId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: document.getElementById('share-csrf-token').value
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }
        
        alert(result.message);
        
        // Recarregar dados
        await loadServersTable();
        await loadServers();
        
    } catch (error) {
        alert('Erro ao excluir servidor: ' + error.message);
    }
}

// Mostrar status no formulário
function showServerFormStatus(type, message) {
    const statusDiv = document.getElementById('serverFormStatus');
    statusDiv.className = `alert alert-${type}`;
    statusDiv.textContent = message;
    statusDiv.style.display = 'block';
    
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 3000);
    }
}

// Adicionar event listener para o botão de adicionar servidor
document.addEventListener('DOMContentLoaded', () => {
    // Event listeners existentes...
    
    // Adicionar listener para botão de adicionar servidor
    setTimeout(() => {
        const btnAddServer = document.getElementById('btnAddServer');
        if (btnAddServer) {
            btnAddServer.addEventListener('click', showAddServerModal);
        }
    }, 100);
    
    // Carregar servidores na inicialização
    loadServers();
});