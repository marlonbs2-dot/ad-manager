<?php
$title = 'Configurações - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Configurações</h1>
    <p>Configurar conexão com Active Directory e permissões</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Conexão Active Directory</h2>
    </div>
    <div class="card-body">
        <div class="settings-tabs">
            <button type="button" class="tab-button active" data-tab="ad-config">Active Directory</button>
            <button type="button" class="tab-button" data-tab="api-config">APIs</button>
            <button type="button" class="tab-button" data-tab="print-servers-config">Servidores de Impressão</button>
        </div>

        <!-- Configuração Active Directory -->
        <div id="ad-config" class="tab-content active">
            <form id="ad-config-form">
                <input type="hidden" id="csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="host">Host / IP do Controlador de Domínio: *</label>
                        <input type="text" id="host" name="host" class="form-control" required
                            placeholder="dc.empresa.local">
                    </div>

                    <div class="form-group">
                        <label for="port">Porta:</label>
                        <input type="number" id="port" name="port" class="form-control" value="389">
                    </div>

                    <div class="form-group">
                        <label for="protocol">Protocolo:</label>
                        <select id="protocol" name="protocol" class="form-control">
                            <option value="ldap">LDAP</option>
                            <option value="ldaps">LDAPS</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="use-tls" name="use_tls">
                            Usar StartTLS
                        </label>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="base-dn">Base DN: *</label>
                        <input type="text" id="base-dn" name="base_dn" class="form-control" required
                            placeholder="DC=empresa,DC=local">
                    </div>

                    <div class="form-group form-group-full">
                        <label for="bind-dn">Bind DN (Usuário de Serviço): *</label>
                        <input type="text" id="bind-dn" name="bind_dn" class="form-control" required
                            placeholder="CN=svc_ldap,OU=Service Accounts,DC=empresa,DC=local">
                    </div>

                    <div class="form-group form-group-full">
                        <label for="bind-password">Senha do Bind: *</label>
                        <input type="password" id="bind-password" name="bind_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="connection-timeout">Timeout (segundos):</label>
                        <input type="number" id="connection-timeout" name="connection_timeout" class="form-control"
                            value="10">
                    </div>
                </div>

                <div class="form-divider"></div>

                <h3>Permissões por OU</h3>

                <div class="form-group">
                    <label for="admin-ou">OU de Administradores (acesso total): *</label>
                    <input type="text" id="admin-ou" name="admin_ou" class="form-control" required
                        placeholder="OU=Admins,DC=empresa,DC=local">
                    <small>Usuários nesta OU terão acesso completo ao sistema</small>
                </div>

                <div class="form-group">
                    <label for="ou-reset">OUs Autorizadas a Resetar Senhas:</label>
                    <div id="ou-reset-list" class="ou-list"></div>
                    <div class="input-group">
                        <input type="text" id="ou-reset-input" class="form-control"
                            placeholder="OU=Suporte,DC=empresa,DC=local">
                        <button type="button" id="add-ou-reset" class="btn btn-sm btn-secondary">Adicionar</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ou-groups">OUs Autorizadas a Gerenciar Grupos:</label>
                    <div id="ou-groups-list" class="ou-list"></div>
                    <div class="input-group">
                        <input type="text" id="ou-groups-input" class="form-control"
                            placeholder="OU=Operadores,DC=empresa,DC=local">
                        <button type="button" id="add-ou-groups" class="btn btn-sm btn-secondary">Adicionar</button>
                    </div>
                </div>

                <div id="config-message" class="alert" style="display: none;"></div>

                <div class="form-actions">
                    <button type="button" id="test-connection" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                        Testar Conexão
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Salvar Configuração
                    </button>
                </div>
            </form>
        </div>

        <!-- Configuração APIs -->
        <div id="api-config" class="tab-content">
            <form id="api-config-form">
                <input type="hidden" id="api-csrf-token" value="<?= \App\Security\CSRF::generateToken() ?>">

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label for="dhcp-api-url">URL da API DHCP: *</label>
                        <input type="url" id="dhcp-api-url" name="dhcp_api_url" class="form-control" required
                            placeholder="http://10.168.11.80:5001">
                        <small>URL completa da API DHCP incluindo protocolo e porta</small>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="dhcp-api-key" class="form-label">API Key (DHCP & Share Logs)</label>
                        <div class="input-group">
                            <input type="password" id="dhcp-api-key" name="dhcp_api_key" class="form-control"
                                placeholder="Chave da API">
                            <button class="btn btn-outline-secondary" type="button" id="toggle-api-key">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Chave de autenticação configurada no servidor Windows (arquivo
                            .env)</small>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="share-api-url">URL da API Share Logs: *</label>
                        <input type="url" id="share-api-url" name="share_api_url" class="form-control" required
                            placeholder="http://10.168.11.80:5002">
                        <small>URL completa da API Share Logs incluindo protocolo e porta</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="dhcp-api-enabled" name="dhcp_api_enabled" checked>
                            Habilitar API DHCP
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="share-api-enabled" name="share_api_enabled" checked>
                            Habilitar API Share Logs
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="api-timeout">Timeout das APIs (segundos):</label>
                        <input type="number" id="api-timeout" name="api_timeout" class="form-control" value="30" min="5"
                            max="120">
                    </div>

                    <div class="form-group">
                        <label for="api-retry-attempts">Tentativas de Reconexão:</label>
                        <input type="number" id="api-retry-attempts" name="api_retry_attempts" class="form-control"
                            value="3" min="1" max="10">
                    </div>
                </div>

                <div class="form-divider"></div>

                <h3>Teste de Conectividade</h3>

                <div class="api-test-section">
                    <div class="api-test-item">
                        <label>API DHCP:</label>
                        <button type="button" id="test-dhcp-api" class="btn btn-sm btn-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                            </svg>
                            Testar
                        </button>
                        <span id="dhcp-api-status" class="api-status"></span>
                    </div>

                    <div class="api-test-item">
                        <label>API Share Logs:</label>
                        <button type="button" id="test-share-api" class="btn btn-sm btn-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                            </svg>
                            Testar
                        </button>
                        <span id="share-api-status" class="api-status"></span>
                    </div>
                </div>

                <div id="api-config-message" class="alert" style="display: none;"></div>

                <div class="form-actions">
                    <button type="button" id="test-all-apis" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                        Testar Todas as APIs
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Salvar Configuração das APIs
                    </button>
                </div>
            </form>
        </div>

        <!-- Servidores de Impressão -->
        <div id="print-servers-config" class="tab-content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                <h3 style="margin:0">Servidores de Impressão</h3>
                <button type="button" class="btn btn-primary btn-sm" onclick="openPrintServerModal()">+ Adicionar Servidor</button>
            </div>
            <div id="print-servers-table-wrap">
                <div style="text-align:center;padding:2rem;opacity:.5">Clique na aba para carregar</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Servidor de Impressão -->
<div id="print-server-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div class="card" style="width:100%;max-width:480px;margin:1rem">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3 id="ps-modal-title" class="card-title" style="margin:0">Adicionar Servidor</h3>
            <button onclick="closePrintServerModal()" style="background:none;border:none;cursor:pointer;font-size:1.4rem;opacity:.6">&times;</button>
        </div>
        <div class="card-body">
            <input type="hidden" id="ps-id">
            <input type="hidden" id="ps-csrf" value="<?= \App\Security\CSRF::generateToken() ?>">
            <div class="form-group">
                <label for="ps-name">Nome: *</label>
                <input type="text" id="ps-name" class="form-control" placeholder="Ex: Impressoras RH" required>
            </div>
            <div class="form-group">
                <label for="ps-url">URL da Print API: *</label>
                <input type="url" id="ps-url" class="form-control" placeholder="https://10.0.0.5:5444" required>
                <small>URL onde o <code>print-api-service.js</code> está rodando</small>
            </div>
            <div class="form-group">
                <label for="ps-key">API Key: *</label>
                <input type="password" id="ps-key" class="form-control" placeholder="PRINT_API_KEY do .env" required>
            </div>
            <div class="form-group">
                <label for="ps-desc">Descrição:</label>
                <input type="text" id="ps-desc" class="form-control" placeholder="Opcional">
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="ps-enabled" checked> Ativo</label>
            </div>
            <div id="ps-test-result" style="margin-bottom:.75rem"></div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button type="button" class="btn btn-secondary" onclick="testPrintServerConn()">Testar Conexão</button>
                <button type="button" class="btn btn-primary" onclick="savePrintServer()">Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closePrintServerModal()">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ─── Print Servers ────────────────────────────────────────────────────────────
function escPS(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function loadPrintServers() {
    try {
        const d = await fetch('/settings/print-servers').then(r=>r.json());
        renderPrintServers(d.data || []);
    } catch(e) {
        document.getElementById('print-servers-table-wrap').innerHTML = `<div class="alert alert-danger">Erro: ${escPS(e.message)}</div>`;
    }
}

function renderPrintServers(list) {
    const wrap = document.getElementById('print-servers-table-wrap');
    if (!list.length) { wrap.innerHTML = '<div style="text-align:center;padding:2rem;opacity:.5">Nenhum servidor cadastrado</div>'; return; }
    const rows = list.map(s => `<tr>
        <td>${escPS(s.name)}</td>
        <td style="font-size:.82rem">${escPS(s.url)}</td>
        <td>${escPS(s.description||'—')}</td>
        <td><span class="badge ${s.enabled ? 'badge-success':'badge-secondary'}">${s.enabled?'Ativo':'Inativo'}</span></td>
        <td style="white-space:nowrap">
            <button class="btn btn-sm btn-secondary" onclick='editPrintServer(${JSON.stringify(s)})'>Editar</button>
            <button class="btn btn-sm btn-danger" onclick="deletePrintServer(${s.id},'${escPS(s.name)}')">Remover</button>
        </td>
    </tr>`).join('');
    wrap.innerHTML = `<div style="overflow-x:auto"><table class="table">
        <thead><tr><th>Nome</th><th>URL</th><th>Descrição</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>${rows}</tbody></table></div>`;
}

function openPrintServerModal(s) {
    document.getElementById('ps-id').value   = s?.id   || '';
    document.getElementById('ps-name').value = s?.name || '';
    document.getElementById('ps-url').value  = s?.url  || '';
    document.getElementById('ps-key').value  = s?.api_key || '';
    document.getElementById('ps-desc').value = s?.description || '';
    document.getElementById('ps-enabled').checked = s?.enabled !== false && s?.enabled !== '0';
    document.getElementById('ps-test-result').innerHTML = '';
    document.getElementById('ps-modal-title').textContent = s ? 'Editar Servidor' : 'Adicionar Servidor';
    document.getElementById('print-server-modal').style.display = 'flex';
}
function editPrintServer(s) { openPrintServerModal(s); }
function closePrintServerModal() { document.getElementById('print-server-modal').style.display = 'none'; }

async function savePrintServer() {
    const id  = document.getElementById('ps-id').value;
    const url = id ? `/settings/print-servers/${id}` : '/settings/print-servers';
    const body = new URLSearchParams({
        csrf_token: document.getElementById('ps-csrf').value,
        name:       document.getElementById('ps-name').value,
        url:        document.getElementById('ps-url').value,
        api_key:    document.getElementById('ps-key').value,
        description:document.getElementById('ps-desc').value,
        enabled:    document.getElementById('ps-enabled').checked ? '1' : '0'
    });
    try {
        const d = await fetch(url, {method: id ? 'PUT':'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body}).then(r=>r.json());
        if (!d.success) throw new Error(d.message);
        closePrintServerModal(); loadPrintServers();
        if (window.toast) window.toast.success(d.message);
    } catch(e) { if (window.toast) window.toast.error('Erro: '+e.message); else alert(e.message); }
}

async function deletePrintServer(id, name) {
    if (!confirm(`Remover servidor "${name}"?`)) return;
    const csrf = document.getElementById('ps-csrf').value;
    try {
        const d = await fetch(`/settings/print-servers/${id}`, {method:'DELETE', headers:{'X-CSRF-Token':csrf}}).then(r=>r.json());
        if (!d.success) throw new Error(d.message);
        loadPrintServers();
        if (window.toast) window.toast.success(d.message);
    } catch(e) { if (window.toast) window.toast.error('Erro: '+e.message); }
}

async function testPrintServerConn() {
    const url = document.getElementById('ps-url').value;
    const key = document.getElementById('ps-key').value;
    const el  = document.getElementById('ps-test-result');
    if (!url || !key) { el.innerHTML = '<div class="alert alert-warning">Preencha URL e API Key</div>'; return; }
    el.innerHTML = '<div class="alert">Testando...</div>';
    try {
        const d = await fetch('/settings/print-servers/test', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({url, api_key: key, csrf_token: document.getElementById('ps-csrf').value})
        }).then(r=>r.json());
        el.innerHTML = `<div class="alert ${d.success?'alert-success':'alert-danger'}">${d.success?'✓':'✗'} ${escPS(d.message)}</div>`;
    } catch(e) { el.innerHTML = `<div class="alert alert-danger">Erro: ${escPS(e.message)}</div>`; }
}

// Carregar ao activar a aba
document.querySelectorAll('[data-tab="print-servers-config"]').forEach(btn => {
    btn.addEventListener('click', loadPrintServers);
});
</script>


<div class="card">
    <div class="card-header">
        <h2>Informações do Sistema</h2>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <label>Versão do PHP:</label>
                <span><?= phpversion() ?></span>
            </div>
            <div class="info-item">
                <label>Extensão LDAP:</label>
                <span class="<?= extension_loaded('ldap') ? 'text-success' : 'text-error' ?>">
                    <?= extension_loaded('ldap') ? 'Instalada' : 'Não Instalada' ?>
                </span>
            </div>
            <div class="info-item">
                <label>Extensão OpenSSL:</label>
                <span class="<?= extension_loaded('openssl') ? 'text-success' : 'text-error' ?>">
                    <?= extension_loaded('openssl') ? 'Instalada' : 'Não Instalada' ?>
                </span>
            </div>
            <div class="info-item">
                <label>Versão da Aplicação:</label>
                <span>1.0.0</span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/settings.js"></script>';
include __DIR__ . '/layout.php';
?>