// Estado global
let currentScope = null;
const PAGE_SIZE = 15;
let reservationsData = [];
let leasesData = [];
let reservationPage = 1;
let leasePage = 1;
let reservationsFiltered = [];
let leasesFiltered = [];

// Busca global: filtra reservas e leases
function filterDhcp(query) {
    const q = query.trim().toLowerCase();
    reservationsFiltered = q
        ? reservationsData.filter(r =>
            (r.IPAddress || '').toLowerCase().includes(q) ||
            (r.ClientId || '').toLowerCase().includes(q) ||
            (r.Name || '').toLowerCase().includes(q) ||
            (r.Description || '').toLowerCase().includes(q))
        : reservationsData;
    leasesFiltered = q
        ? leasesData.filter(l =>
            (l.IPAddress || '').toLowerCase().includes(q) ||
            (l.ClientId || '').toLowerCase().includes(q) ||
            (l.HostName || '').toLowerCase().includes(q))
        : leasesData;

    reservationPage = 1;
    leasePage = 1;

    // Atualiza contadores
    const rc = document.getElementById('reservationCount');
    if (rc) {
        rc.textContent = `${reservationsFiltered.length} reserva${reservationsFiltered.length !== 1 ? 's' : ''}`;
        rc.style.display = reservationsFiltered.length ? 'inline-block' : 'none';
    }
    const lc = document.getElementById('leaseCount');
    if (lc) {
        lc.textContent = `${leasesFiltered.length} lease${leasesFiltered.length !== 1 ? 's' : ''}`;
        lc.style.display = leasesFiltered.length ? 'inline-block' : 'none';
    }

    renderReservationsPage();
    renderLeasesPage();
}

// Carregar escopos DHCP
async function loadScopes() {
    const loading = document.getElementById('scopesLoading');
    const error = document.getElementById('scopesError');
    const list = document.getElementById('scopesList');

    loading.style.display = 'block';
    error.style.display = 'none';
    list.innerHTML = '';

    try {
        const response = await fetch('/dhcp/api/scopes');
        const data = await response.json();

        loading.style.display = 'none';

        if (!data.success) {
            throw new Error(data.message);
        }

        if (data.data.length === 0) {
            list.innerHTML = '<p class="alert alert-info">Nenhum escopo DHCP encontrado.</p>';
            return;
        }

        data.data.forEach(scope => {
            const card = document.createElement('div');
            card.className = 'scope-card';
            card.onclick = () => selectScope(scope);

            const stateBadge = scope.State === 'Active'
                ? '<span class="badge badge-success">Ativo</span>'
                : '<span class="badge badge-danger">Inativo</span>';

            card.innerHTML = `
                <h3>${scope.Name || scope.ScopeId} ${stateBadge}</h3>
                <p><strong>Escopo:</strong> ${scope.ScopeId}</p>
                <p><strong>Faixa:</strong> ${scope.StartRange} - ${scope.EndRange}</p>
                <p><strong>Máscara:</strong> ${scope.SubnetMask}</p>
            `;

            list.appendChild(card);
        });
    } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = 'Erro ao carregar escopos: ' + err.message;
    }
}

// Selecionar escopo e carregar reservas e leases
async function selectScope(scope) {
    currentScope = scope;

    document.getElementById('scopesCard').style.display = 'none';
    document.getElementById('reservationsCard').style.display = 'block';
    document.getElementById('leasesCard').style.display = 'block';
    document.getElementById('selectedScopeName').textContent = scope.Name || scope.ScopeId;
    document.getElementById('selectedScopeNameLeases').textContent = scope.Name || scope.ScopeId;
    document.getElementById('selectedScopeInfo').textContent =
        `${scope.ScopeId} | ${scope.StartRange} - ${scope.EndRange}`;

    // Carregar reservas e leases em paralelo
    await Promise.all([
        loadReservations(scope.ScopeId),
        loadLeases(scope.ScopeId)
    ]);
}

// Voltar para lista de escopos
function backToScopes() {
    currentScope = null;
    document.getElementById('dhcpSearch').value = '';
    reservationsFiltered = [];
    leasesFiltered = [];
    document.getElementById('scopesCard').style.display = 'block';
    document.getElementById('reservationsCard').style.display = 'none';
    document.getElementById('leasesCard').style.display = 'none';
}

// Carregar reservas de um escopo
async function loadReservations(scopeId) {
    const loading = document.getElementById('reservationsLoading');
    const error = document.getElementById('reservationsError');
    const table = document.getElementById('reservationsTable');
    const tbody = document.getElementById('reservationsTableBody');
    const noReservations = document.getElementById('noReservations');

    loading.style.display = 'block';
    error.style.display = 'none';
    table.style.display = 'none';
    noReservations.style.display = 'none';
    tbody.innerHTML = '';

    try {
        const response = await fetch(`/dhcp/api/scopes/${encodeURIComponent(scopeId)}/reservations`);
        const data = await response.json();

        loading.style.display = 'none';

        if (!data.success) {
            throw new Error(data.message);
        }

        if (data.data.length === 0) {
            noReservations.style.display = 'block';
            document.getElementById('reservationCount').style.display = 'none';
            return;
        }

        reservationsData = data.data;
        reservationsFiltered = data.data;
        reservationPage = 1;

        // Mostrar contador de reservas
        const countBadge = document.getElementById('reservationCount');
        countBadge.textContent = `${data.data.length} reserva${data.data.length !== 1 ? 's' : ''}`;
        countBadge.style.display = 'inline-block';

        table.style.display = 'table';
        renderReservationsPage();

    } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = 'Erro ao carregar reservas: ' + err.message;
    }
}

function renderReservationsPage() {
    const tbody = document.getElementById('reservationsTableBody');
    tbody.innerHTML = '';

    const total = reservationsFiltered.length;
    const pages = Math.ceil(total / PAGE_SIZE) || 1;
    const start = (reservationPage - 1) * PAGE_SIZE;
    const slice = reservationsFiltered.slice(start, start + PAGE_SIZE);

    slice.forEach(reservation => {
        const row = document.createElement('tr');
        const ipAddress = `<span class="ip-address">${reservation.IPAddress}</span>`;
        const macAddress = `<span class="mac-address">${formatMAC(reservation.ClientId)}</span>`;
        const deviceName = `<span class="device-name">${reservation.Name || 'Sem nome'}</span>`;
        const description = reservation.Description
            ? `<span class="device-description">${reservation.Description}</span>`
            : `<span class="device-description empty">Sem descrição</span>`;
        row.innerHTML = `
            <td>${ipAddress}</td>
            <td>${macAddress}</td>
            <td>${deviceName}</td>
            <td>${description}</td>
            <td class="text-center">
                <button class="btn-action btn-primary" onclick="showEditReservationModal('${reservation.IPAddress}', '${reservation.ClientId}', '${reservation.Name}', '${reservation.Description || ''}')" title="Editar reserva" style="margin-right: 0.5rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
                <button class="btn-action btn-danger" onclick="deleteReservation('${reservation.IPAddress}')" title="Remover reserva">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Remover
                </button>
            </td>`;
        tbody.appendChild(row);
    });

    renderPagination('reservationsPagination', total, reservationPage, pages, 'changeReservationPage');
}

function changeReservationPage(dir) {
    const pages = Math.ceil(reservationsFiltered.length / PAGE_SIZE) || 1;
    reservationPage = Math.max(1, Math.min(pages, reservationPage + dir));
    renderReservationsPage();
}

// Formatar endereço MAC
function formatMAC(mac) {
    if (!mac) return 'N/A';

    // Remove todos os caracteres não hexadecimais
    const cleanMac = mac.replace(/[^0-9A-Fa-f]/g, '');

    // Se não tem 12 caracteres, retorna como está
    if (cleanMac.length !== 12) return mac;

    // Formata como XX:XX:XX:XX:XX:XX
    return cleanMac.match(/.{2}/g).join(':').toUpperCase();
}

// Mostrar modal de nova reserva
function showAddReservationModal() {
    document.getElementById('addReservationModal').style.display = 'block';
    document.getElementById('addReservationForm').reset();
}

// Fechar modal de nova reserva
function closeAddReservationModal() {
    document.getElementById('addReservationModal').style.display = 'none';
}

// Adicionar nova reserva
async function addReservation(event) {
    event.preventDefault();

    if (!currentScope) {
        alert('Nenhum escopo selecionado');
        return;
    }

    const csrfToken = document.getElementById('dhcp-csrf-token').value;

    const formData = {
        csrf_token: csrfToken,
        scopeId: currentScope.ScopeId,
        ipAddress: document.getElementById('reservationIP').value,
        macAddress: document.getElementById('reservationMAC').value,
        name: document.getElementById('reservationName').value,
        description: document.getElementById('reservationDescription').value
    };

    try {
        const response = await fetch('/dhcp/api/reservations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        alert('Reserva criada com sucesso!');
        closeAddReservationModal();
        await Promise.all([
            loadReservations(currentScope.ScopeId),
            loadLeases(currentScope.ScopeId)
        ]);
    } catch (err) {
        alert('Erro ao criar reserva: ' + err.message);
    }
}

// Remover reserva
async function deleteReservation(ipAddress) {
    if (!currentScope) {
        alert('Nenhum escopo selecionado');
        return;
    }

    if (!confirm(`Tem certeza que deseja remover a reserva para o IP ${ipAddress}?`)) {
        return;
    }

    const csrfToken = document.getElementById('dhcp-csrf-token').value;

    try {
        const response = await fetch('/dhcp/api/reservations/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                scopeId: currentScope.ScopeId,
                ipAddress: ipAddress
            })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        alert('Reserva removida com sucesso!');
        await Promise.all([
            loadReservations(currentScope.ScopeId),
            loadLeases(currentScope.ScopeId)
        ]);
    } catch (err) {
        alert('Erro ao remover reserva: ' + err.message);
    }
}

// Carregar leases (IPs distribuídos) de um escopo
async function loadLeases(scopeId) {
    const loading = document.getElementById('leasesLoading');
    const error = document.getElementById('leasesError');
    const table = document.getElementById('leasesTable');
    const tbody = document.getElementById('leasesTableBody');
    const noLeases = document.getElementById('noLeases');

    loading.style.display = 'block';
    error.style.display = 'none';
    table.style.display = 'none';
    noLeases.style.display = 'none';
    tbody.innerHTML = '';

    try {
        const response = await fetch(`/dhcp/api/scopes/${encodeURIComponent(scopeId)}/leases`);
        const data = await response.json();

        loading.style.display = 'none';

        if (!data.success) {
            throw new Error(data.message);
        }

        if (data.data.length === 0) {
            noLeases.style.display = 'block';
            document.getElementById('leaseCount').style.display = 'none';
            return;
        }

        leasesData = data.data;
        leasesFiltered = data.data;
        leasePage = 1;

        // Mostrar contador de leases
        const countBadge = document.getElementById('leaseCount');
        countBadge.textContent = `${data.data.length} lease${data.data.length !== 1 ? 's' : ''}`;
        countBadge.style.display = 'inline-block';

        table.style.display = 'table';
        renderLeasesPage();

    } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = 'Erro ao carregar leases: ' + err.message;
    }
}

function renderLeasesPage() {
    const tbody = document.getElementById('leasesTableBody');
    tbody.innerHTML = '';

    const total = leasesFiltered.length;
    const pages = Math.ceil(total / PAGE_SIZE) || 1;
    const start = (leasePage - 1) * PAGE_SIZE;
    const slice = leasesFiltered.slice(start, start + PAGE_SIZE);

    slice.forEach(lease => {
        const row = document.createElement('tr');
        const ipAddress = `<span class="ip-address">${lease.IPAddress}</span>`;
        const macAddress = `<span class="mac-address">${formatMAC(lease.ClientId)}</span>`;
        const hostName = lease.HostName
            ? `<span class="device-name">${lease.HostName}</span>`
            : `<span class="device-name empty">Sem hostname</span>`;

        let stateClass = 'badge-success';
        let stateText = lease.AddressState || 'Active';
        if (stateText === 'Inactive') stateClass = 'badge-danger';
        if (stateText === 'Declined') stateClass = 'badge-warning';
        const state = `<span class="badge ${stateClass}">${stateText}</span>`;

        let expiryText = 'N/A';
        if (lease.LeaseExpiryTime) {
            expiryText = new Date(lease.LeaseExpiryTime).toLocaleString('pt-BR');
        }

        row.innerHTML = `
            <td>${ipAddress}</td>
            <td>${macAddress}</td>
            <td>${hostName}</td>
            <td class="text-center">${state}</td>
            <td>${expiryText}</td>
            <td class="text-center">
                <button class="btn-action btn-reserve"
                    onclick="reserveFromLease('${lease.IPAddress}','${(lease.ClientId || '').replace(/'/g, '')}','${(lease.HostName || '').replace(/'/g, '')}')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    Reservar
                </button>
            </td>`;
        tbody.appendChild(row);
    });

    renderPagination('leasesPagination', total, leasePage, pages, 'changeLeasePage');
}

function changeLeasePage(dir) {
    const pages = Math.ceil(leasesFiltered.length / PAGE_SIZE) || 1;
    leasePage = Math.max(1, Math.min(pages, leasePage + dir));
    renderLeasesPage();
}

// Helper: renderiza controles de paginação
function renderPagination(containerId, total, page, pages, changeFn) {
    let el = document.getElementById(containerId);
    if (!el) {
        // cria o container se não existir
        const table = containerId === 'leasesPagination'
            ? document.getElementById('leasesTable')
            : document.getElementById('reservationsTable');
        el = document.createElement('div');
        el.id = containerId;
        el.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:.5rem .75rem;border-top:1px solid var(--border-color);font-size:.85rem;color:var(--text-secondary);background:var(--bg-primary)';
        table.parentNode.insertBefore(el, table.nextSibling);
    }

    if (total <= PAGE_SIZE) { el.style.display = 'none'; return; }

    const start = (page - 1) * PAGE_SIZE + 1;
    const end = Math.min(page * PAGE_SIZE, total);
    el.style.display = 'flex';
    el.innerHTML = `
        <span>${start}–${end} de ${total}</span>
        <div style="display:flex;gap:.35rem">
            <button class="btn btn-sm btn-secondary" onclick="${changeFn}(-1)" ${page <= 1 ? 'disabled' : ''} style="padding:.25rem .6rem;font-size:.8rem">‹ Ant</button>
            <span style="padding:.25rem .5rem;font-weight:600">${page} / ${pages}</span>
            <button class="btn btn-sm btn-secondary" onclick="${changeFn}(1)" ${page >= pages ? 'disabled' : ''} style="padding:.25rem .6rem;font-size:.8rem">Próx ›</button>
        </div>`;
}

// Fechar modal ao clicar fora
window.onclick = function (event) {
    const addModal = document.getElementById('addReservationModal');
    const editModal = document.getElementById('editReservationModal');

    if (event.target === addModal) {
        closeAddReservationModal();
    }
    if (event.target === editModal) {
        closeEditReservationModal();
    }
}

// Pré-preencher modal de reserva a partir de um lease
function reserveFromLease(ip, mac, hostname) {
    document.getElementById('addReservationModal').style.display = 'block';
    document.getElementById('reservationIP').value = ip;
    document.getElementById('reservationMAC').value = formatMAC(mac);
    document.getElementById('reservationName').value = hostname || ip;
    document.getElementById('reservationDescription').value = hostname ? `Reservado a partir do lease: ${hostname}` : '';
}

// Variável global para armazenar dados da reserva sendo editada
let currentEditingReservation = null;

// Mostrar modal de editar reserva
function showEditReservationModal(ipAddress, macAddress, name, description) {
    currentEditingReservation = {
        originalIpAddress: ipAddress,
        macAddress: macAddress,
        name: name,
        description: description
    };

    // Preencher campos do modal
    document.getElementById('editReservationIP').value = ipAddress;
    document.getElementById('editReservationMAC').value = formatMAC(macAddress);
    document.getElementById('editReservationName').value = name;
    document.getElementById('editReservationDescription').value = description;

    // Mostrar modal
    document.getElementById('editReservationModal').style.display = 'block';
}

// Fechar modal de editar reserva
function closeEditReservationModal() {
    document.getElementById('editReservationModal').style.display = 'none';
    currentEditingReservation = null;
}

// Atualizar reserva
async function updateReservation(event) {
    event.preventDefault();

    if (!currentScope || !currentEditingReservation) {
        alert('Erro: dados da reserva não encontrados');
        return;
    }

    const csrfToken = document.getElementById('dhcp-csrf-token').value;

    const formData = {
        csrf_token: csrfToken,
        ipAddress: document.getElementById('editReservationIP').value,
        macAddress: document.getElementById('editReservationMAC').value.replace(/:/g, ''),
        name: document.getElementById('editReservationName').value,
        description: document.getElementById('editReservationDescription').value
    };

    try {
        const response = await fetch(
            `/dhcp/api/scopes/${encodeURIComponent(currentScope.ScopeId)}/reservations/${encodeURIComponent(currentEditingReservation.originalIpAddress)}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            }
        );

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        alert('Reserva atualizada com sucesso!');
        closeEditReservationModal();
        await Promise.all([
            loadReservations(currentScope.ScopeId),
            loadLeases(currentScope.ScopeId)
        ]);
    } catch (err) {
        alert('Erro ao atualizar reserva: ' + err.message);
    }
}

// Carregar escopos ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
    loadScopes();
});