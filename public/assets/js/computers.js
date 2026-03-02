let currentComputerDn = null;

document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchBtn = document.getElementById('search-btn');
    const searchInput = document.getElementById('computer-search');
    
    if (searchBtn) {
        searchBtn.addEventListener('click', searchComputers);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchComputers();
            }
        });
    }
    
    // Delete computer functionality
    const btnDeleteComputer = document.getElementById('btn-delete-computer');
    if (btnDeleteComputer) {
        btnDeleteComputer.addEventListener('click', () => deleteComputer(currentComputerDn));
    }

    // Add to Group functionality
    const btnAddToGroup = document.getElementById('btn-add-to-group');
    if (btnAddToGroup) {
        btnAddToGroup.addEventListener('click', function() {
            App.closeModal('computer-details-modal');
            document.getElementById('group-search-input').value = '';
            document.getElementById('group-search-results').style.display = 'none';
            document.getElementById('add-to-group-message').style.display = 'none';
            App.openModal('add-to-group-modal');
        });
    }

    const groupSearchInput = document.getElementById('group-search-input');
    let searchTimeout;
    if (groupSearchInput) {
        groupSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => searchGroupsForComputer(this.value), 500);
        });
    }
});

async function searchComputers() {
    const query = document.getElementById('computer-search').value.trim();
    
    if (!query) {
        App.showAlert(document.querySelector('.page-header'), 'Digite um termo de busca', 'info');
        return;
    }
    
    const resultsDiv = document.getElementById('search-results');
    const tbody = document.getElementById('computers-table-body');
    const countSpan = document.getElementById('results-count');
    
    tbody.innerHTML = '<tr><td colspan="5" class="text-center">Buscando...</td></tr>';
    resultsDiv.style.display = 'block';
    
    try {
        const response = await App.fetch(`/computers/search?q=${encodeURIComponent(query)}`);
        
        if (response.success) {
            const computers = response.data;
            countSpan.textContent = computers.length;
            
            if (computers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum computador encontrado</td></tr>';
                return;
            }
            
            let html = '';
            computers.forEach(computer => {
                const encodedDn = App.base64Encode(computer.dn);
                html += `
                    <tr>
                        <td>${App.escapeHtml(computer.name)}</td>
                        <td>${App.escapeHtml(computer.hostname || 'N/A')}</td>
                        <td>${App.escapeHtml(computer.os || 'N/A')}</td>
                        <td><span class="badge">${computer.group_count}</span></td>
                        <td class="actions-cell">
                            <button class="btn-action btn-primary" onclick="viewComputer('${encodedDn}')" title="Ver detalhes do computador">
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
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-error">${error.message}</td></tr>`;
    }
}

async function viewComputer(encodedDn) {
    try {
        const response = await App.fetch(`/computers/${encodedDn}`);
        
        if (response.success) {
            const computer = response.data;
            currentComputerDn = encodedDn;
            
            document.getElementById('current-computer-dn').value = encodedDn;
            document.getElementById('modal-computer-name').textContent = App.escapeHtml(computer.name);
            document.getElementById('detail-name').textContent = App.escapeHtml(computer.name);
            document.getElementById('detail-hostname').textContent = App.escapeHtml(computer.hostname || 'N/A');
            document.getElementById('detail-os').textContent = App.escapeHtml(computer.os || 'N/A');
            document.getElementById('detail-os-version').textContent = App.escapeHtml(computer.os_version || 'N/A');
            document.getElementById('detail-created-at').textContent = App.formatDate(computer.created_at);
            document.getElementById('detail-group-count').textContent = computer.group_count;
            
            renderMemberOfGroups(computer.member_of_groups);
            
            App.openModal('computer-details-modal');
        }
    } catch (error) {
        alert('Erro ao carregar detalhes do computador: ' + error.message);
    }
}

function renderMemberOfGroups(groups) {
    const container = document.getElementById('member-of-groups-list');
    
    if (!groups || groups.length === 0) {
        container.innerHTML = '<p class="text-center">Não é membro de nenhum grupo.</p>';
        return;
    }
    
    let html = '<div class="groups-list">';
    groups.forEach(groupDn => {
        const groupName = App.formatDN(groupDn);
        const encodedGroupDn = App.base64Encode(groupDn);
        html += `
            <span class="badge badge-with-action">
                ${App.escapeHtml(groupName)}
                <button class="btn-remove-badge btn-remove-badge-error" title="Remover do grupo ${App.escapeHtml(groupName)}" onclick="removeComputerFromGroup('${encodedGroupDn}', '${currentComputerDn}')">&times;</button>
            </span>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

async function searchGroupsForComputer(query) {
    const resultsDiv = document.getElementById('group-search-results');
    if (!query || query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }

    resultsDiv.innerHTML = '<p class="text-center">Buscando...</p>';
    resultsDiv.style.display = 'block';
    
    try {
        const response = await App.fetch(`/groups/search?q=${encodeURIComponent(query)}`);
        if (response.success) {
            const groups = response.data;
            if (groups.length === 0) {
                resultsDiv.innerHTML = '<p class="text-center">Nenhum grupo encontrado.</p>';
                return;
            }
            
            let html = '';
            groups.forEach(group => {
                const encodedGroupDn = App.base64Encode(group.dn);
                html += `
                    <div class="ou-item" onclick="addComputerToGroup('${encodedGroupDn}', '${App.escapeHtml(group.name)}')">
                        <strong>${App.escapeHtml(group.name)}</strong>
                        <small>${App.escapeHtml(group.description || 'N/A')}</small>
                    </div>
                `;
            });
            resultsDiv.innerHTML = html;
        }
    } catch (error) {
        resultsDiv.innerHTML = `<p class="text-center text-error">${error.message}</p>`;
    }
}

async function addComputerToGroup(encodedGroupDn, groupName) {
    const computerDn = currentComputerDn;
    const csrfToken = document.getElementById('add-to-group-csrf').value;
    const messageDiv = document.getElementById('add-to-group-message');
    
    if (!confirm(`Adicionar este computador ao grupo "${groupName}"?`)) return;
    
    try {
        const response = await App.fetch(`/groups/${encodedGroupDn}/add-member`, {
            method: 'POST',
            body: JSON.stringify({
                csrf_token: csrfToken,
                member_dn: App.base64Decode(computerDn) // Use 'member_dn' para consistência
            })
        });
        
        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');
            setTimeout(() => {
                App.closeModal('add-to-group-modal');
                viewComputer(computerDn); // Recarrega os detalhes do computador
            }, 1500);
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    }
}

async function removeComputerFromGroup(encodedGroupDn, encodedComputerDn) {
    const groupName = App.formatDN(App.base64Decode(encodedGroupDn));
    // Reutiliza um token CSRF da página, por exemplo, do modal de adicionar a grupo
    const csrfToken = document.getElementById('add-to-group-csrf').value; 

    if (!confirm(`Tem certeza que deseja remover este computador do grupo "${groupName}"?`)) {
        return;
    }

    try {
        // O endpoint para remover membro de grupo é genérico e está no GroupController
        const response = await App.fetch(`/groups/${encodedGroupDn}/members`, {
            method: 'DELETE',
            body: JSON.stringify({
                csrf_token: csrfToken,
                member_dn: App.base64Decode(encodedComputerDn) // Envia o DN do computador no corpo
            })
        });

        if (response.success) {
            alert(response.message);
            viewComputer(encodedComputerDn); // Recarrega os detalhes do computador para atualizar a lista de grupos
        }
    } catch (error) {
        alert('Erro ao remover do grupo: ' + error.message);
    }
}

/**
 * Excluir computador do Active Directory
 * @param {string} encodedDn - DN do computador (base64)
 */
async function deleteComputer(encodedDn) {
    const csrfToken = document.getElementById('add-to-group-csrf').value;
    
    // Obter nome do computador para confirmação
    const computerName = document.getElementById('modal-computer-name').textContent;
    
    if (!confirm(`⚠️ ATENÇÃO: Tem certeza que deseja EXCLUIR o computador "${computerName}"?\n\nEsta ação NÃO pode ser desfeita!\n\nO computador será removido permanentemente do Active Directory.`)) {
        return;
    }
    
    // Segunda confirmação para ações destrutivas
    if (!confirm(`Confirme novamente: Excluir "${computerName}"?`)) {
        return;
    }
    
    try {
        const response = await App.fetch(`/computers/${encodedDn}`, {
            method: 'DELETE',
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        });
        
        if (response.success) {
            alert('✅ ' + response.message);
            App.closeModal('computer-details-modal');
            
            // Limpar resultados de busca
            const tbody = document.getElementById('computers-table-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">Busque por um computador para ver os resultados</td></tr>';
            }
        }
    } catch (error) {
        alert('❌ Erro ao excluir computador: ' + error.message);
    }
}