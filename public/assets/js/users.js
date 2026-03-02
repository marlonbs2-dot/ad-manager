let currentUserDn = null;

document.addEventListener('DOMContentLoaded', function () {
    // Search functionality
    const searchBtn = document.getElementById('search-btn');
    const searchInput = document.getElementById('user-search');

    searchBtn.addEventListener('click', searchUsers);
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            searchUsers();
        }
    });

    // Create User functionality
    const openCreateBtn = document.getElementById('btn-open-create-modal');
    const createUserForm = document.getElementById('create-user-form');

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', () => {
            clearCopyUserData();
            App.openModal('create-user-modal');
        });
    }
    if (createUserForm) {
        createUserForm.addEventListener('submit', createUser);
    }

    // Copy User functionality
    const btnCopyUser = document.getElementById('btn-copy-user');
    if (btnCopyUser) {
        btnCopyUser.addEventListener('click', () => copyUser(currentUserDn));
    }

    // Reset password modal functionality
    setupResetPasswordModal();

    // Password mode toggle
    document.querySelectorAll('input[name="password-mode"]').forEach(radio => {
        radio.addEventListener('change', function () {
            const manualGroup = document.getElementById('manual-password-group');
            manualGroup.style.display = this.value === 'manual' ? 'block' : 'none';
        });
    });

    // Auto-fill display name and username in create form
    const firstNameInput = document.getElementById('create-first-name');
    const lastNameInput = document.getElementById('create-last-name');
    const displayNameInput = document.getElementById('create-display-name');
    const usernameInput = document.getElementById('create-username');

    function updateCreateFields() {
        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();

        // Display name
        displayNameInput.value = `${firstName} ${lastName}`.trim();

        // Username - só adiciona ponto se houver sobrenome
        if (lastName) {
            usernameInput.value = `${firstName.toLowerCase()}.${lastName.toLowerCase()}`;
        } else {
            usernameInput.value = firstName.toLowerCase();
        }
    }
    firstNameInput?.addEventListener('input', updateCreateFields);
    lastNameInput?.addEventListener('input', updateCreateFields);

    // OU Browser functionality
    const browseOUBtn = document.getElementById('btn-browse-ou');
    if (browseOUBtn) {
        browseOUBtn.addEventListener('click', openOUBrowser);
    }

    const ouSearchInput = document.getElementById('ou-search-input');
    if (ouSearchInput) {
        ouSearchInput.addEventListener('input', filterOUList);
    }

});

async function searchUsers() {
    const query = document.getElementById('user-search').value.trim();

    if (!query) {
        alert('Digite um termo de busca');
        return;
    }

    const resultsDiv = document.getElementById('search-results');
    const tbody = document.getElementById('users-table-body');
    const countSpan = document.getElementById('results-count');

    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Buscando...</td></tr>';
    resultsDiv.style.display = 'block';

    try {
        const response = await App.fetch(`/users/search?q=${encodeURIComponent(query)}`);

        if (response.success) {
            const users = response.data;
            countSpan.textContent = users.length;

            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum usuário encontrado</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const statusBadge = user.is_disabled ?
                    '<span class="badge badge-error">Desabilitado</span>' :
                    '<span class="badge badge-success">Ativo</span>';

                const encodedDn = App.base64Encode(user.dn);

                const toggleButton = user.is_disabled ?
                    `<button class="btn-action btn-success" title="Habilitar Usuário" onclick="toggleUserStatusFromTable('${encodedDn}', true)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Habilitar
                    </button>` :
                    `<button class="btn-action btn-danger" title="Desabilitar Usuário" onclick="toggleUserStatusFromTable('${encodedDn}', false)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        Desabilitar
                    </button>`;

                html += `
                    <tr>
                        <td>${user.display_name || user.username}</td>
                        <td>${user.username}</td>
                        <td>${user.email || 'N/A'}</td>
                        <td>${user.department || 'N/A'}</td>
                        <td>${statusBadge}</td>
                        <td class="actions-cell">
                            <button class="btn-action btn-primary" onclick="viewUser('${encodedDn}')" title="Ver detalhes do usuário" style="margin-right: 0.5rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                Ver Detalhes
                            </button>
                            <button class="btn-action btn-warning" title="Resetar Senha" onclick="openResetPasswordModal('${encodedDn}')" style="margin-right: 0.5rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                                </svg>
                                Resetar Senha
                            </button>
                            ${toggleButton}
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-error">${error.message}</td></tr>`;
    }
}

async function viewUser(encodedDn) {
    try {
        const response = await App.fetch(`/users/${encodedDn}`);

        if (response.success) {
            const user = response.data;
            currentUserDn = encodedDn;

            document.getElementById('modal-user-name').textContent = user.display_name || user.username;
            document.getElementById('detail-username').textContent = user.username;
            document.getElementById('detail-email').textContent = user.email || 'N/A';
            document.getElementById('detail-phone').textContent = user.phone || 'N/A';
            document.getElementById('detail-department').textContent = user.department || 'N/A';
            document.getElementById('detail-title').textContent = user.title || 'N/A';

            const statusBadge = user.is_disabled ?
                '<span class="badge badge-error">Desabilitado</span>' :
                '<span class="badge badge-success">Ativo</span>';
            document.getElementById('detail-status').innerHTML = statusBadge;

            document.getElementById('detail-last-logon').textContent = App.formatDate(user.last_logon);
            document.getElementById('detail-created').textContent = App.formatDate(user.created_at);

            // Groups
            const groupsDiv = document.getElementById('detail-groups');
            if (user.groups && user.groups.length > 0) {
                let groupsHtml = '<div class="groups-list">';
                user.groups.forEach(groupDn => {
                    const groupName = App.formatDN(groupDn);
                    const encodedGroupDn = App.base64Encode(groupDn);
                    // Adiciona um botão de remoção para cada grupo
                    groupsHtml += `
                        <span class="badge badge-with-action">
                            ${groupName}
                            <button class="btn-remove-badge btn-remove-badge-error" title="Remover do grupo ${groupName}" onclick="removeUserFromGroup('${encodedGroupDn}', '${encodedDn}')">&times;</button>
                        </span>`;
                });
                groupsHtml += '</div>';
                groupsDiv.innerHTML = groupsHtml;
            } else {
                groupsDiv.innerHTML = '<p>Nenhum grupo</p>';
            }

            // Update button text
            const toggleBtn = document.getElementById('btn-toggle-status');
            toggleBtn.textContent = user.is_disabled ? 'Habilitar Conta' : 'Desabilitar Conta';
            toggleBtn.onclick = () => toggleUserStatus(encodedDn, user.is_disabled);

            App.openModal('user-modal');
        }
    } catch (error) {
        alert('Erro ao carregar usuário: ' + error.message);
    }
}

function openResetPasswordModal(encodedDn) {
    currentUserDn = encodedDn;
    document.getElementById('reset-user-dn').value = currentUserDn;
    document.getElementById('generated-password').style.display = 'none';
    document.getElementById('reset-message').style.display = 'none';
    App.openModal('reset-password-modal');
}

function setupResetPasswordModal() {
    const resetBtn = document.getElementById('btn-reset-password');
    const confirmBtn = document.getElementById('btn-confirm-reset');
    const copyBtn = document.getElementById('copy-password');

    resetBtn.addEventListener('click', function () {
        document.getElementById('reset-user-dn').value = currentUserDn;
        App.closeModal('user-modal');
        openResetPasswordModal(currentUserDn);
    });

    confirmBtn.addEventListener('click', resetPassword);

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const password = document.getElementById('password-display').textContent;
            App.copyToClipboard(password);
        });
    }
}

async function toggleUserStatusFromTable(encodedDn, isDisabled) {
    const action = isDisabled ? 'enable' : 'disable';
    // O token CSRF é pego do modal de reset de senha, que já está na página.
    const csrfToken = document.getElementById('reset-csrf-token').value;

    if (!confirm(`Tem certeza que deseja ${isDisabled ? 'habilitar' : 'desabilitar'} este usuário?`)) {
        return;
    }

    try {
        const response = await App.fetch(`/users/${encodedDn}/${action}`, {
            method: 'POST',
            body: JSON.stringify({ csrf_token: csrfToken })
        });

        if (response.success) {
            searchUsers(); // Recarrega a lista para mostrar o novo status
        }
    } catch (error) {
        alert('Erro: ' + error.message);
    }
}

async function createUser(e) {
    e.preventDefault();

    const form = document.getElementById('create-user-form');
    const messageDiv = document.getElementById('create-message');
    const submitBtn = form.querySelector('button[type="submit"]');

    const password = document.getElementById('create-password').value;
    const confirmPassword = document.getElementById('create-confirm-password').value;

    if (password !== confirmPassword) {
        App.showAlert(messageDiv, 'As senhas não coincidem.', 'error');
        return;
    }

    // Validar complexidade da senha
    if (password.length < 8) {
        App.showAlert(messageDiv, 'A senha deve ter no mínimo 8 caracteres.', 'error');
        return;
    }

    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

    if (!hasUpperCase || !hasLowerCase || !hasNumber || !hasSpecial) {
        App.showAlert(messageDiv, 'A senha deve conter: letras maiúsculas, minúsculas, números e caracteres especiais (!@#$%^&*).', 'error');
        return;
    }

    // Verificar se está copiando de outro usuário
    const copyFromDn = document.getElementById('create-copy-from-dn').value;
    const copyGroupsData = document.getElementById('create-copy-groups-data').value;

    const data = {
        csrf_token: document.getElementById('create-csrf-token').value,
        first_name: document.getElementById('create-first-name').value,
        last_name: document.getElementById('create-last-name').value,
        display_name: document.getElementById('create-display-name').value,
        username: document.getElementById('create-username').value,
        password: password,
        ou: document.getElementById('create-ou').value, // Hidden input
        must_change: document.getElementById('create-must-change').checked,
        is_disabled: document.getElementById('create-is-disabled').checked,
    };

    // Adicionar dados de cópia se existirem
    if (copyFromDn && copyGroupsData) {
        data.copy_from_dn = copyFromDn;
        data.copy_groups = JSON.parse(copyGroupsData);
    }

    App.setLoading(submitBtn, true);

    try {
        const response = await App.fetch('/users/create', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');
            setTimeout(() => {
                App.closeModal('create-user-modal');
                form.reset();
                // Optionally, search for the new user
                document.getElementById('user-search').value = data.username;
                searchUsers();
            }, 2000);
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    } finally {
        App.setLoading(submitBtn, false);
    }
}
async function resetPassword() {
    const dn = document.getElementById('reset-user-dn').value;
    const mode = document.querySelector('input[name="password-mode"]:checked').value;
    const mustChange = document.getElementById('must-change').checked;
    const csrfToken = document.getElementById('reset-csrf-token').value;
    const messageDiv = document.getElementById('reset-message');

    let password = '';
    if (mode === 'manual') {
        password = document.getElementById('new-password').value;
        if (!password) {
            App.showAlert(messageDiv, 'Digite uma senha', 'error');
            return;
        }
    }

    const confirmBtn = document.getElementById('btn-confirm-reset');
    App.setLoading(confirmBtn, true);

    try {
        const response = await App.fetch(`/users/${dn}/reset-password`, {
            method: 'POST',
            body: JSON.stringify({
                csrf_token: csrfToken,
                password: password,
                generate: mode === 'generate' ? 'true' : 'false',
                must_change: mustChange ? 'true' : 'false'
            })
        });

        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');

            if (response.password) {
                document.getElementById('password-display').textContent = response.password;
                document.getElementById('generated-password').style.display = 'block';
            }

            setTimeout(() => {
                App.closeModal('reset-password-modal');
            }, 3000);
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    } finally {
        App.setLoading(confirmBtn, false);
    }
}

async function toggleUserStatus(encodedDn, isDisabled) {
    const action = isDisabled ? 'enable' : 'disable';
    const csrfToken = document.getElementById('reset-csrf-token').value;

    if (!confirm(`Tem certeza que deseja ${isDisabled ? 'habilitar' : 'desabilitar'} este usuário?`)) {
        return;
    }

    try {
        const response = await App.fetch(`/users/${encodedDn}/${action}`, {
            method: 'POST',
            body: JSON.stringify({ csrf_token: csrfToken })
        });

        if (response.success) {
            alert(response.message);
            App.closeModal('user-modal');
            searchUsers(); // Refresh list
        }
    } catch (error) {
        alert('Erro: ' + error.message);
    }
}

async function openOUBrowser() {
    const ouListDiv = document.getElementById('ou-list');
    ouListDiv.innerHTML = 'Carregando...';
    App.openModal('ou-browser-modal');

    try {
        const response = await App.fetch('/users/ous');
        if (response.success) {
            const ous = response.data;
            let html = '';
            if (ous.length > 0) {
                ous.forEach(ou => {
                    html += `<div class="ou-item" data-dn="${ou.dn}">${ou.name} <small>(${ou.dn})</small></div>`;
                });
            } else {
                html = 'Nenhuma OU encontrada.';
            }
            ouListDiv.innerHTML = html;

            // Add click event listeners to new items
            document.querySelectorAll('.ou-item').forEach(item => {
                item.addEventListener('click', function () {
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

/**
 * Remove um usuário de um grupo específico.
 * Chamado a partir do modal de detalhes do usuário.
 * @param {string} encodedGroupDn - O DN do grupo (base64).
 * @param {string} encodedUserDn - O DN do usuário a ser removido (base64).
 */
async function removeUserFromGroup(encodedGroupDn, encodedUserDn) {
    const groupName = App.formatDN(App.base64Decode(encodedGroupDn));
    const csrfToken = document.getElementById('reset-csrf-token').value; // Reutiliza um token CSRF da página

    if (!confirm(`Tem certeza que deseja remover este usuário do grupo "${groupName}"?`)) {
        return;
    }

    try {
        const response = await App.fetch(`/groups/${encodedGroupDn}/members`, {
            method: 'DELETE',
            body: JSON.stringify({
                csrf_token: csrfToken,
                member_dn: App.base64Decode(encodedUserDn) // Envia o DN do membro no corpo
            })
        });

        if (response.success) {
            alert(response.message);
            viewUser(encodedUserDn); // Recarrega os detalhes do usuário para atualizar a lista de grupos
        }
    } catch (error) {
        alert('Erro ao remover do grupo: ' + error.message);
    }
}

/**
 * Copiar usuário - Abre o formulário de criação com dados pré-preenchidos
 * @param {string} encodedDn - DN do usuário a ser copiado (base64)
 */
async function copyUser(encodedDn) {
    try {
        const response = await App.fetch(`/users/${encodedDn}/copy`);

        if (response.success) {
            const data = response.data;

            // Fechar modal de detalhes
            App.closeModal('user-modal');

            // Abrir modal de criação
            App.openModal('create-user-modal');

            // Preencher campos
            document.getElementById('create-ou-display').value = data.ou;
            document.getElementById('create-ou').value = data.ou;

            // Armazenar dados da cópia
            document.getElementById('create-copy-from-dn').value = data.source_dn;
            document.getElementById('create-copy-groups-data').value = JSON.stringify(data.groups);

            // Mostrar banner informativo
            const banner = document.getElementById('copy-info-banner');
            const sourceName = document.getElementById('copy-source-name');
            const groupsInfo = document.getElementById('copy-groups-info');

            sourceName.textContent = data.source_name;
            groupsInfo.textContent = `${data.groups.length} grupo(s) serão copiados para o novo usuário`;
            banner.style.display = 'block';

            // Focar no primeiro campo
            document.getElementById('create-first-name').focus();

        }
    } catch (error) {
        alert('Erro ao copiar usuário: ' + error.message);
    }
}

/**
 * Limpar dados de cópia do usuário
 */
function clearCopyUserData() {
    document.getElementById('create-copy-from-dn').value = '';
    document.getElementById('create-copy-groups-data').value = '';
    document.getElementById('copy-info-banner').style.display = 'none';

    // Limpar formulário
    document.getElementById('create-user-form').reset();
    document.getElementById('create-ou-display').value = '';
    document.getElementById('create-ou').value = '';
}

// ─── Adicionar usuário em grupo a partir do modal de detalhes ───────────────

function openAddToGroupModal() {
    document.getElementById('group-search-input').value = '';
    document.getElementById('group-search-results').innerHTML = '';
    document.getElementById('add-to-group-message').style.display = 'none';
    App.openModal('add-to-group-modal');

    // Permite buscar ao pressionar Enter
    const input = document.getElementById('group-search-input');
    input.onkeydown = (e) => { if (e.key === 'Enter') searchGroupsForUser(); };
    setTimeout(() => input.focus(), 150);
}

async function searchGroupsForUser() {
    const query = document.getElementById('group-search-input').value.trim();
    const resultsDiv = document.getElementById('group-search-results');

    if (!query) {
        resultsDiv.innerHTML = '<p style="color:var(--text-secondary);font-size:.85rem">Digite o nome do grupo para buscar.</p>';
        return;
    }

    resultsDiv.innerHTML = '<p style="color:var(--text-secondary);font-size:.85rem">Buscando...</p>';

    try {
        const response = await App.fetch(`/groups/search?q=${encodeURIComponent(query)}`);
        const groups = response.data || [];

        if (groups.length === 0) {
            resultsDiv.innerHTML = '<p style="color:var(--text-secondary);font-size:.85rem">Nenhum grupo encontrado.</p>';
            return;
        }

        resultsDiv.innerHTML = groups.map(g => {
            const encodedGroupDn = App.base64Encode(g.dn);
            const groupName = g.name || App.formatDN(g.dn);
            return `
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .75rem;border-bottom:1px solid var(--border-color)">
                    <span style="font-size:.9rem">${groupName}</span>
                    <button class="btn btn-success btn-sm" style="font-size:.75rem;padding:.2rem .6rem"
                        onclick="addUserToGroupFromModal('${encodedGroupDn}', '${groupName}')">
                        + Adicionar
                    </button>
                </div>`;
        }).join('');
    } catch (err) {
        resultsDiv.innerHTML = `<p style="color:var(--color-error);font-size:.85rem">Erro: ${err.message}</p>`;
    }
}

async function addUserToGroupFromModal(encodedGroupDn, groupName) {
    const msgDiv = document.getElementById('add-to-group-message');
    const csrfToken = document.getElementById('reset-csrf-token').value;
    const userDn = App.base64Decode(currentUserDn);

    msgDiv.style.display = 'none';

    try {
        const response = await App.fetch(`/groups/${encodedGroupDn}/members`, {
            method: 'POST',
            body: JSON.stringify({ csrf_token: csrfToken, member_dn: userDn })
        });

        if (response.success) {
            msgDiv.className = 'alert alert-success';
            msgDiv.textContent = `✔ Usuário adicionado ao grupo "${groupName}" com sucesso.`;
            msgDiv.style.display = 'block';
            // Recarrega a lista de grupos no modal de detalhes
            viewUser(currentUserDn);
        }
    } catch (err) {
        msgDiv.className = 'alert alert-error';
        msgDiv.textContent = `Erro: ${err.message}`;
        msgDiv.style.display = 'block';
    }
}
