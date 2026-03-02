let currentGroupDn = null;

document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchBtn = document.getElementById('search-btn');
    const searchInput = document.getElementById('group-search');
    
    if (searchBtn) {
        searchBtn.addEventListener('click', searchGroups);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchGroups();
            }
        });
    }

    // Create Group functionality
    const openCreateBtn = document.getElementById('btn-open-create-modal');
    const createGroupForm = document.getElementById('create-group-form');
    
    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', () => App.openModal('create-group-modal'));
    }
    if (createGroupForm) {
        createGroupForm.addEventListener('submit', createGroup);
    }

    // OU Browser functionality
    const browseOUBtn = document.getElementById('btn-browse-ou');
    if (browseOUBtn) {
        browseOUBtn.addEventListener('click', openOUBrowser);
    }

    const ouSearchInput = document.getElementById('ou-search-input');
    if (ouSearchInput) {
        ouSearchInput.addEventListener('input', filterOUList);
    }

    // Group Details / Add Member functionality
    const btnAddMember = document.getElementById('btn-add-member');
    if (btnAddMember) {
        btnAddMember.addEventListener('click', function() {
            App.closeModal('group-details-modal');
            document.getElementById('member-search').value = '';
            document.getElementById('member-search-results').style.display = 'none';
            document.getElementById('add-member-message').style.display = 'none';
            App.openModal('add-member-modal');
        });
    }

    const memberSearchInput = document.getElementById('member-search');
    let searchTimeout;
    memberSearchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchMembersForGroup(this.value), 500);
    });
});

async function searchGroups() {
    const query = document.getElementById('group-search').value.trim();
    
    if (!query) {
        App.showAlert(document.querySelector('.page-header'), 'Digite um termo de busca', 'info');
        return;
    }
    
    const resultsDiv = document.getElementById('search-results');
    const tbody = document.getElementById('groups-table-body');
    const countSpan = document.getElementById('results-count');
    
    tbody.innerHTML = '<tr><td colspan="4" class="text-center">Buscando...</td></tr>';
    resultsDiv.style.display = 'block';
    
    try {
        const response = await App.fetch(`/groups/search?q=${encodeURIComponent(query)}`);
        
        if (response.success) {
            const groups = response.data;
            countSpan.textContent = groups.length;
            
            if (groups.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">Nenhum grupo encontrado</td></tr>';
                return;
            }
            
            let html = '';
            groups.forEach(group => {
                const encodedDn = App.base64Encode(group.dn);
                html += `
                    <tr>
                        <td>${App.escapeHtml(group.name)}</td>
                        <td>${App.escapeHtml(group.description || 'N/A')}</td>
                        <td><span class="badge">${group.member_count}</span></td>
                        <td class="actions-cell">
                            <button class="btn-action btn-primary" onclick="viewGroup('${encodedDn}')" title="Ver detalhes do grupo">
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
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-error">${error.message}</td></tr>`;
    }
}

async function createGroup(e) {
    e.preventDefault();
    
    const form = document.getElementById('create-group-form');
    const messageDiv = document.getElementById('create-message');
    const submitBtn = form.querySelector('button[type="submit"]');

    const data = {
        csrf_token: document.getElementById('create-csrf-token').value,
        name: document.getElementById('create-group-name').value,
        description: document.getElementById('create-group-description').value,
        ou: document.getElementById('create-ou').value,
        scope: document.getElementById('create-group-scope').value,
        type: document.getElementById('create-group-type').value,
    };

    App.setLoading(submitBtn, true);

    try {
        const response = await App.fetch('/groups/create', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');
            setTimeout(() => {
                App.closeModal('create-group-modal');
                form.reset();
                document.getElementById('group-search').value = data.name;
                searchGroups();
            }, 2000);
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    } finally {
        App.setLoading(submitBtn, false);
    }
}

async function openOUBrowser() {
    const ouListDiv = document.getElementById('ou-list');
    ouListDiv.innerHTML = 'Carregando...';
    App.openModal('ou-browser-modal');

    try {
        // Reutiliza o endpoint de OUs dos usuários
        const response = await App.fetch('/users/ous');
        if (response.success) {
            const ous = response.data;
            let html = '';
            if (ous.length > 0) {
                ous.forEach(ou => {
                    html += `<div class="ou-item" data-dn="${App.escapeHtml(ou.dn)}">${App.escapeHtml(ou.name)} <small>(${App.escapeHtml(ou.dn)})</small></div>`;
                });
            } else {
                html = 'Nenhuma OU encontrada.';
            }
            ouListDiv.innerHTML = html;

            document.querySelectorAll('.ou-item').forEach(item => {
                item.addEventListener('click', function() {
                    const selectedDn = this.getAttribute('data-dn');
                    document.getElementById('create-ou-display').value = selectedDn;
                    document.getElementById('create-ou').value = selectedDn;
                    App.closeModal('ou-browser-modal');
                });
            });
        }
    } catch (error) {
        ouListDiv.innerHTML = `<div class="text-error">${error.message}</div>`;
    }
}

function filterOUList() {
    const filter = document.getElementById('ou-search-input').value.toLowerCase();
    const ouItems = document.querySelectorAll('#ou-list .ou-item');
    ouItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(filter)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

async function viewGroup(encodedDn) {
    try {
        const response = await App.fetch(`/groups/${encodedDn}`);
        
        if (response.success) {
            const group = response.data;
            currentGroupDn = encodedDn;
            
            document.getElementById('current-group-dn').value = encodedDn;
            document.getElementById('modal-group-name').textContent = App.escapeHtml(group.name);
            document.getElementById('detail-name').textContent = App.escapeHtml(group.name);
            document.getElementById('detail-description').textContent = App.escapeHtml(group.description || 'N/A');
            document.getElementById('detail-member-count').textContent = group.member_count;
            
            renderMembers(group.members);
            
            App.openModal('group-details-modal');
        }
    } catch (error) {
        alert('Erro ao carregar detalhes do grupo: ' + error.message);
    }
}

function renderMembers(members) {
    const container = document.getElementById('members-list');
    
    if (!members || members.length === 0) {
        container.innerHTML = '<p class="text-center">Nenhum membro neste grupo.</p>';
        return;
    }
    
    let html = '<div class="members-grid">';
    members.forEach(memberDn => {
        const name = App.formatDN(memberDn);
        const encodedMemberDn = App.base64Encode(memberDn);
        html += `
            <div class="member-item">
                <span class="member-name" title="${App.escapeHtml(memberDn)}">${App.escapeHtml(name)}</span>
                <button class="btn-action btn-danger" title="Remover membro" onclick="removeMember('${encodedMemberDn}')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    Remover
                </button>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

async function searchMembersForGroup(query) {
    const resultsDiv = document.getElementById('member-search-results');
    if (!query || query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }

    resultsDiv.innerHTML = '<p class="text-center">Buscando...</p>';
    resultsDiv.style.display = 'block';
    
    try {
        // Reutiliza o endpoint de busca de usuários
        const response = await App.fetch(`/users/search?q=${encodeURIComponent(query)}`);
        if (response.success) {
            const users = response.data;
            if (users.length === 0) {
                resultsDiv.innerHTML = '<p class="text-center">Nenhum usuário encontrado.</p>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                const encodedUserDn = App.base64Encode(user.dn);
                html += `
                    <div class="ou-item" onclick="addMember('${encodedUserDn}', '${App.escapeHtml(user.display_name || user.username)}')">
                        <strong>${App.escapeHtml(user.display_name || user.username)}</strong>
                        <small>${App.escapeHtml(user.username)}</small>
                    </div>
                `;
            });
            resultsDiv.innerHTML = html;
        }
    } catch (error) {
        resultsDiv.innerHTML = `<p class="text-center text-error">${error.message}</p>`;
    }
}

async function addMember(encodedMemberDn, memberName) {
    const groupDn = currentGroupDn;
    const csrfToken = document.getElementById('add-member-csrf').value;
    const messageDiv = document.getElementById('add-member-message');
    
    if (!confirm(`Adicionar "${memberName}" ao grupo?`)) return;
    
    try {
        const response = await App.fetch(`/groups/${groupDn}/add-member`, {
            method: 'POST',
            body: JSON.stringify({
                csrf_token: csrfToken,
                member_dn: App.base64Decode(encodedMemberDn)
            })
        });
        
        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');
            setTimeout(() => {
                App.closeModal('add-member-modal');
                viewGroup(groupDn); // Recarrega os detalhes do grupo
            }, 1500);
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    }
}

async function removeMember(encodedMemberDn) {
    const groupDn = currentGroupDn;
    const csrfToken = document.getElementById('add-member-csrf').value;
    
    if (!confirm('Tem certeza que deseja remover este membro do grupo?')) return;
    
    try {
        const response = await App.fetch(`/groups/${groupDn}/members`, {
            method: 'DELETE',
            body: JSON.stringify({
                csrf_token: csrfToken,
                member_dn: App.base64Decode(encodedMemberDn)
            })
        });
        
        if (response.success) {
            alert(response.message);
            viewGroup(groupDn); // Recarrega os detalhes do grupo
        }
    } catch (error) {
        alert('Erro ao remover membro: ' + error.message);
    }
}
