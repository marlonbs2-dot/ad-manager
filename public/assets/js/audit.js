let currentPage = 1;
const pageSize = 50;

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filter-form');
    const clearBtn = document.getElementById('clear-filters');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadLogs();
    });
    
    clearBtn.addEventListener('click', function() {
        filterForm.reset();
        currentPage = 1;
        loadLogs();
    });
    
    prevBtn.addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadLogs();
        }
    });
    
    nextBtn.addEventListener('click', function() {
        currentPage++;
        loadLogs();
    });
    
    // Load initial data
    loadLogs();
});

async function loadLogs() {
    const filters = getFilters();
    const offset = (currentPage - 1) * pageSize;
    
    const params = new URLSearchParams({
        ...filters,
        limit: pageSize,
        offset: offset
    });
    
    const tbody = document.getElementById('audit-table-body');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center">Carregando...</td></tr>';
    
    try {
        const response = await App.fetch(`/audit/logs?${params}`);
        
        if (response.success) {
            const logs = response.data;
            const total = response.total;
            
            document.getElementById('total-records').textContent = `${total} registros`;
            document.getElementById('page-info').textContent = `Página ${currentPage}`;
            
            // Update pagination buttons
            document.getElementById('prev-page').disabled = currentPage === 1;
            document.getElementById('next-page').disabled = offset + pageSize >= total;
            
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nenhum registro encontrado</td></tr>';
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const resultClass = log.result === 'success' ? 'badge-success' : 
                                   log.result === 'failure' ? 'badge-error' : 'badge-warning';
                
                html += `
                    <tr>
                        <td>${log.id}</td>
                        <td>${App.formatDate(log.created_at)}</td>
                        <td>${log.username}</td>
                        <td>${formatActionName(log.action)}</td>
                        <td>${App.formatDN(log.target_dn)}</td>
                        <td>${log.target_ou || 'N/A'}</td>
                        <td>${log.ip_address}</td>
                        <td><span class="badge ${resultClass}">${log.result}</span></td>
                        <td>
                            <button class="btn-action btn-primary" onclick="viewDetails(${log.id})" title="Ver detalhes do log">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                Ver Detalhes
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-error">${error.message}</td></tr>`;
    }
}

function getFilters() {
    return {
        username: document.getElementById('filter-username').value,
        action: document.getElementById('filter-action').value,
        result: document.getElementById('filter-result').value,
        target_ou: document.getElementById('filter-ou').value,
        date_from: document.getElementById('filter-date-from').value,
        date_to: document.getElementById('filter-date-to').value
    };
}

async function viewDetails(logId) {
    App.openModal('details-modal');
    const detailsContent = document.getElementById('details-content');
    detailsContent.textContent = 'Carregando detalhes...';
    
    try {
        const response = await App.fetch(`/audit/logs/${logId}`);
        
        if (response.success) {
            const log = response.data;
            detailsContent.textContent = formatLogDetails(log);
        } else {
            detailsContent.textContent = `Erro ao carregar detalhes: ${response.message}`;
        }
    } catch (error) {
        detailsContent.textContent = `Erro ao carregar detalhes: ${error.message}`;
    }
}

function formatLogDetails(log) {
    let details = '';
    
    details += `═══════════════════════════════════════════════════════════\n`;
    details += `  DETALHES DA AÇÃO - LOG #${log.id}\n`;
    details += `═══════════════════════════════════════════════════════════\n\n`;
    
    details += `📅 Data/Hora:\n`;
    details += `   ${App.formatDate(log.created_at)}\n\n`;
    
    details += `👤 Usuário:\n`;
    details += `   ${log.username}\n`;
    if (log.user_id) {
        details += `   ID: ${log.user_id}\n`;
    }
    details += `\n`;
    
    details += `⚡ Ação:\n`;
    details += `   ${formatActionName(log.action)}\n`;
    details += `   Código: ${log.action}\n\n`;
    
    if (log.target_dn) {
        details += `🎯 Alvo:\n`;
        details += `   DN: ${log.target_dn}\n`;
        if (log.target_ou) {
            details += `   OU: ${log.target_ou}\n`;
        }
        details += `\n`;
    }
    
    details += `🌐 Origem:\n`;
    details += `   IP: ${log.ip_address}\n`;
    if (log.user_agent) {
        details += `   User-Agent: ${log.user_agent}\n`;
    }
    details += `\n`;
    
    details += `📊 Resultado:\n`;
    details += `   ${log.result.toUpperCase()}\n\n`;
    
    if (log.details && Object.keys(log.details).length > 0) {
        details += `📋 Informações Adicionais:\n`;
        details += `───────────────────────────────────────────────────────────\n`;
        details += formatDetailsObject(log.details, '   ');
    } else {
        details += `📋 Informações Adicionais:\n`;
        details += `   Nenhuma informação adicional disponível.\n`;
    }
    
    details += `\n═══════════════════════════════════════════════════════════\n`;
    
    return details;
}

function formatDetailsObject(obj, indent = '') {
    let result = '';
    
    for (const [key, value] of Object.entries(obj)) {
        const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        
        if (value === null || value === undefined) {
            result += `${indent}${formattedKey}: N/A\n`;
        } else if (typeof value === 'object' && !Array.isArray(value)) {
            result += `${indent}${formattedKey}:\n`;
            result += formatDetailsObject(value, indent + '   ');
        } else if (Array.isArray(value)) {
            result += `${indent}${formattedKey}:\n`;
            value.forEach((item, index) => {
                if (typeof item === 'object') {
                    result += `${indent}   [${index + 1}]\n`;
                    result += formatDetailsObject(item, indent + '      ');
                } else {
                    result += `${indent}   - ${item}\n`;
                }
            });
        } else if (typeof value === 'boolean') {
            result += `${indent}${formattedKey}: ${value ? 'Sim' : 'Não'}\n`;
        } else {
            result += `${indent}${formattedKey}: ${value}\n`;
        }
    }
    
    return result;
}

function formatActionName(action) {
    const names = {
        'login_success': 'Login Sucesso',
        'login_failed': 'Login Falhou',
        'reset_password': 'Reset de Senha',
        'enable_user': 'Habilitar Usuário',
        'disable_user': 'Desabilitar Usuário',
        'add_group_member': 'Adicionar a Grupo',
        'remove_group_member': 'Remover de Grupo'
    };
    
    return names[action] || action;
}
