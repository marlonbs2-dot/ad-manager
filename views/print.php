<?php
/**
 * View: Gerenciamento de Impressoras
 */

$csrfToken = \App\Security\CSRF::generateToken();
$servers = $data['servers'] ?? [];

ob_start();
?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                style="width:28px;height:28px;margin-right:10px">
                <polyline points="6 9 6 2 18 2 18 9" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <rect x="6" y="14" width="12" height="8" />
            </svg>
            Gerenciamento de Impressoras
        </h1>
        <p class="page-subtitle">Monitore e gerencie filas de impressão nos servidores</p>
    </div>
</div>

<?php if (empty($servers)): ?>
    <div class="card" style="text-align:center;padding:3rem">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
            style="width:64px;height:64px;margin:0 auto 1rem;opacity:.4">
            <polyline points="6 9 6 2 18 2 18 9" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect x="6" y="14" width="12" height="8" />
        </svg>
        <h3>Nenhum servidor configurado</h3>
        <p style="opacity:.6;margin-bottom:1.5rem">Configure os servidores de impressão em Configurações → Servidores de
            Impressão</p>
        <a href="/settings" class="btn btn-primary">Ir para Configurações</a>
    </div>
<?php else: ?>

    <!-- Seletor de Servidor -->
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-body" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <label for="serverSelect" style="font-weight:600;white-space:nowrap">Servidor de impressão:</label>
            <select id="serverSelect" class="form-control" style="max-width:320px">
                <option value="">— Selecione um servidor —</option>
                <?php foreach ($servers as $s): ?>
                    <?php if ($s['enabled']): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button id="refreshBtn" class="btn btn-secondary" onclick="loadPrinters()" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="16" height="16">
                    <polyline points="23 4 23 10 17 10" />
                    <polyline points="1 20 1 14 7 14" />
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                </svg>
                Atualizar
            </button>
            <span id="printerCount" class="badge badge-secondary" style="opacity:.6;display:none"></span>
        </div>
    </div>

    <!-- Grid de Impressoras -->
    <div class="card">
        <div class="card-header"
            style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
            <h3 class="card-title" style="margin:0">Impressoras</h3>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                <input id="printerSearch" type="text" class="form-control" placeholder="Buscar impressora..."
                    style="max-width:200px" oninput="filterPrinters()" />
                <button id="addPrinterBtn" class="btn btn-primary btn-sm" onclick="openAddPrinterModal()" disabled>+
                    Adicionar Impressora</button>
            </div>
        </div>
        <div class="card-body">
            <div id="printersList">
                <div style="text-align:center;padding:2rem;opacity:.5">Selecione um servidor para listar as impressoras
                </div>
            </div>
            <div id="printerPagination"
                style="display:none;margin-top:.75rem;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                <span id="paginationInfo" style="font-size:.82rem;opacity:.6"></span>
                <div style="display:flex;gap:.35rem">
                    <button class="btn btn-sm btn-secondary" id="prevPageBtn" onclick="changePage(-1)">‹ Ant</button>
                    <button class="btn btn-sm btn-secondary" id="nextPageBtn" onclick="changePage(1)">Próx ›</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Fila de Impressão ═══ -->
    <div id="jobsModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center;padding:1rem">
        <div
            style="background:var(--bg-primary);border-radius:var(--radius-xl);box-shadow:var(--shadow-2xl);width:100%;max-width:820px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden">
            <div
                style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1rem 1.5rem;border-bottom:1px solid var(--border-color);flex-shrink:0">
                <div style="display:flex;align-items:center;gap:.75rem;min-width:0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="width:20px;height:20px;flex-shrink:0;color:var(--primary)">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    <div>
                        <h3 style="margin:0;font-size:1rem;font-weight:700">Fila de Impressão</h3>
                        <div id="jobsModalPrinterName" style="font-size:.8rem;opacity:.6;margin-top:.1rem"></div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0">
                    <button class="btn btn-sm btn-secondary" onclick="refreshJobs()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14">
                            <polyline points="23 4 23 10 17 10" />
                            <polyline points="1 20 1 14 7 14" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        Atualizar
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="clearQueue()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg>
                        Limpar Fila
                    </button>
                    <button onclick="closeJobsModal()"
                        style="background:none;border:none;cursor:pointer;font-size:1.5rem;opacity:.5;line-height:1;padding:.2rem .4rem">&times;</button>
                </div>
            </div>
            <div id="jobsList" style="overflow-y:auto;flex:1;padding:1rem 1.5rem">
                <div style="text-align:center;padding:2rem;opacity:.5">Carregando...</div>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Adicionar Impressora ═══ -->
    <div id="addPrinterModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1002;align-items:center;justify-content:center;padding:1rem">
        <div class="card" style="width:100%;max-width:540px;margin:0;max-height:92vh;overflow-y:auto">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <h3 class="card-title" style="margin:0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="width:18px;height:18px;margin-right:6px;vertical-align:middle">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    Adicionar Impressora
                </h3>
                <button onclick="closeAddPrinterModal()"
                    style="background:none;border:none;cursor:pointer;font-size:1.4rem;opacity:.6">&times;</button>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="addPrinterName">Nome da Impressora: *</label>
                    <input type="text" id="addPrinterName" class="form-control" placeholder="Ex: HP LaserJet 1020 - RH"
                        oninput="document.getElementById('addPrinterShareName').value=this.value">
                </div>
                <div class="form-group">
                    <label for="addPrinterDriver">Driver de Impressão: *</label>
                    <select id="addPrinterDriver" class="form-control">
                        <option value="">— Carregando drivers instalados... —</option>
                    </select>
                    <small>Drivers já instalados no servidor de impressão</small>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem">
                    <div class="form-group">
                        <label for="addPrinterIP">IP da Impressora: *</label>
                        <input type="text" id="addPrinterIP" class="form-control" placeholder="192.168.1.100"
                            oninput="document.getElementById('addPrinterPortName').value='IP_'+this.value">
                    </div>
                    <div class="form-group">
                        <label for="addPrinterPortName">Nome da Porta:</label>
                        <input type="text" id="addPrinterPortName" class="form-control" readonly
                            style="opacity:.5;cursor:not-allowed;background:var(--bg-secondary)"
                            placeholder="IP_192.168.1.100">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem">
                    <div class="form-group">
                        <label for="addPrinterShareName">Nome de Compartilhamento:</label>
                        <input type="text" id="addPrinterShareName" class="form-control" readonly
                            style="opacity:.5;cursor:not-allowed;background:var(--bg-secondary)"
                            placeholder="Igual ao nome">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:.25rem">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:500">
                            <input type="checkbox" id="addPrinterShared" checked> Compartilhar na rede
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="addPrinterComment">Comentário / Localização:</label>
                    <input type="text" id="addPrinterComment" class="form-control" placeholder="Ex: Sala 203 - 2º Andar">
                </div>
                <div id="addPrinterMsg" style="margin:.5rem 0"></div>
                <div style="display:flex;gap:.75rem;margin-top:.25rem">
                    <button type="button" class="btn btn-primary" id="addPrinterSubmitBtn"
                        onclick="submitAddPrinter()">Instalar Impressora</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddPrinterModal()">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Editar Impressora ═══ -->
    <div id="editPrinterModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1001;align-items:center;justify-content:center;padding:1rem">
        <div class="card" style="width:100%;max-width:480px;margin:0">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <h3 class="card-title" style="margin:0">Editar Impressora</h3>
                <button onclick="closeEditModal()"
                    style="background:none;border:none;cursor:pointer;font-size:1.4rem;opacity:.6">&times;</button>
            </div>
            <div class="card-body">
                <input type="hidden" id="editOriginalName">
                <div class="form-group">
                    <label for="editPrinterName">Novo nome:</label>
                    <input type="text" id="editPrinterName" class="form-control">
                </div>
                <div class="form-group">
                    <label for="editPrinterPort">Porta:</label>
                    <select id="editPrinterPort" class="form-control">
                        <option value="">Carregando...</option>
                    </select>
                    <small>Portas disponíveis no servidor</small>
                </div>
                <div id="editModalMsg" style="margin-bottom:.75rem"></div>
                <div style="display:flex;gap:.75rem">
                    <button type="button" class="btn btn-primary" onclick="saveEditPrinter()">Salvar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    const PAGE_SIZE = 20;
    let currentServerId = null;
    let currentPrinter = null;
    let allPrinters = [];
    let filteredPrinters = [];
    let currentPage = 1;

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeJobsModal(); closeEditModal(); closeAddPrinterModal(); }
    });

    document.getElementById('serverSelect')?.addEventListener('change', function () {
        currentServerId = this.value || null;
        currentPage = 1;
        allPrinters = [];
        filteredPrinters = [];
        const addBtn = document.getElementById('addPrinterBtn');
        document.getElementById('refreshBtn').disabled = !currentServerId;
        if (addBtn) addBtn.disabled = !currentServerId;
        if (currentServerId) loadPrinters();
        else {
            document.getElementById('printersList').innerHTML = '<div style="text-align:center;padding:2rem;opacity:.5">Selecione um servidor</div>';
            document.getElementById('printerCount').style.display = 'none';
        }
    });

    async function loadPrinters() {
        if (!currentServerId) return;
        document.getElementById('printersList').innerHTML = '<div style="text-align:center;padding:2rem;opacity:.5">Carregando...</div>';
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers`);
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            allPrinters = d.data || [];
            document.getElementById('printerSearch').value = '';
            const ct = document.getElementById('printerCount');
            ct.textContent = allPrinters.length + ' impressoras';
            ct.style.display = '';
            filterPrinters();
        } catch (e) {
            document.getElementById('printersList').innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    }

    function filterPrinters() {
        const q = (document.getElementById('printerSearch')?.value || '').toLowerCase();
        filteredPrinters = q ? allPrinters.filter(p => p.Name.toLowerCase().includes(q)) : allPrinters;
        currentPage = 1;
        renderPrinters();
    }

    function renderPrinters() {
        const total = filteredPrinters.length;
        const pages = Math.ceil(total / PAGE_SIZE) || 1;
        const start = (currentPage - 1) * PAGE_SIZE;
        const slice = filteredPrinters.slice(start, start + PAGE_SIZE);
        const pag = document.getElementById('printerPagination');

        if (total > PAGE_SIZE) {
            pag.style.display = 'flex';
            document.getElementById('paginationInfo').textContent = `${start + 1}–${Math.min(start + PAGE_SIZE, total)} de ${total}`;
            document.getElementById('prevPageBtn').disabled = currentPage <= 1;
            document.getElementById('nextPageBtn').disabled = currentPage >= pages;
        } else { pag.style.display = 'none'; }

        if (!slice.length) {
            document.getElementById('printersList').innerHTML = '<div style="text-align:center;padding:2rem;opacity:.5">Nenhuma impressora encontrada</div>';
            return;
        }

        const cards = slice.map(p => {
            const status = p.PrinterStatus || 'Unknown';
            const isNormal = status === 'Normal';
            const isPaused = status.includes('Paused') || status.includes('Stopped');
            const statusCls = isNormal ? 'badge-success' : (isPaused ? 'badge-warning' : 'badge-secondary');
            return `<div onclick="openJobsModal('${escapeHtml(p.Name)}')" style="border:1px solid var(--border-color);border-radius:var(--radius-md);padding:.9rem 1rem;cursor:pointer;transition:all .15s;background:var(--bg-primary)" onmouseenter="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--primary)'" onmouseleave="this.style.boxShadow='';this.style.borderColor='var(--border-color)'">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                <div style="min-width:0;flex:1">
                    <div style="font-weight:600;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escapeHtml(p.Name)}">${escapeHtml(p.Name)}</div>
                    <div style="font-size:.75rem;opacity:.55;margin-top:.15rem">${escapeHtml(p.PortName || '')}${p.Shared ? ' · Compartilhada' : ''}</div>
                </div>
                <span class="badge ${statusCls}" style="font-size:.7rem;flex-shrink:0">${escapeHtml(status)}</span>
            </div>
            <div style="display:flex;gap:.35rem;margin-top:.6rem;flex-wrap:wrap">
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation();openEditModal('${escapeHtml(p.Name)}','${escapeHtml(p.PortName || '')}')" style="font-size:.75rem;padding:.2rem .5rem">✎ Editar</button>
                ${isNormal
                    ? `<button class="btn btn-sm btn-secondary" onclick="event.stopPropagation();printerAction('${escapeHtml(p.Name)}','pause')" style="font-size:.75rem;padding:.2rem .5rem">⏸ Pausar</button>`
                    : `<button class="btn btn-sm btn-success" onclick="event.stopPropagation();printerAction('${escapeHtml(p.Name)}','resume')" style="font-size:.75rem;padding:.2rem .5rem">▶ Retomar</button>`}
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation();removePrinter('${escapeHtml(p.Name)}')" style="font-size:.75rem;padding:.2rem .5rem">✕ Remover</button>
            </div>
        </div>`;
        }).join('');
        document.getElementById('printersList').innerHTML = `<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem">${cards}</div>`;
    }

    function changePage(dir) {
        const pages = Math.ceil(filteredPrinters.length / PAGE_SIZE);
        currentPage = Math.max(1, Math.min(pages, currentPage + dir));
        renderPrinters();
    }

    // ─── Modal de Fila ────────────────────────────────────────────────────────────
    async function openJobsModal(name) {
        currentPrinter = name;
        document.getElementById('jobsModalPrinterName').textContent = name;
        document.getElementById('jobsList').innerHTML = '<div style="text-align:center;padding:2rem;opacity:.5">Carregando...</div>';
        document.getElementById('jobsModal').style.display = 'flex';
        await loadJobs();
    }
    function closeJobsModal() { document.getElementById('jobsModal').style.display = 'none'; currentPrinter = null; }
    async function refreshJobs() { if (currentPrinter) await loadJobs(); }

    async function loadJobs() {
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers/${encodeURIComponent(currentPrinter)}/jobs`);
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            renderJobs(d.data || []);
        } catch (e) {
            document.getElementById('jobsList').innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
        }
    }

    function renderJobs(jobs) {
        if (!jobs.length) {
            document.getElementById('jobsList').innerHTML = '<div style="text-align:center;padding:3rem;opacity:.5;font-size:1.1rem">✓ Fila vazia</div>';
            return;
        }
        const rows = jobs.map(j => {
            const status = j.JobStatus || '';
            const isPaused = status.includes('Paused');
            const kb = j.Size ? Math.round(j.Size / 1024) + ' KB' : '—';
            return `<tr>
            <td>${j.Id}</td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escapeHtml(j.DocumentName || '')}">${escapeHtml(j.DocumentName || '—')}</td>
            <td>${escapeHtml(j.UserName || '—')}</td>
            <td>${j.TotalPages ?? '—'}</td>
            <td>${kb}</td>
            <td><span class="badge ${status.includes('Printing') ? 'badge-success' : (isPaused ? 'badge-warning' : 'badge-secondary')}" style="font-size:.72rem">${escapeHtml(status || 'Aguardando')}</span></td>
            <td style="white-space:nowrap">
                ${isPaused
                    ? `<button class="btn btn-sm btn-success" onclick="jobAction(${j.Id},'resume')">▶</button>`
                    : `<button class="btn btn-sm btn-secondary" onclick="jobAction(${j.Id},'pause')">⏸</button>`}
                <button class="btn btn-sm btn-danger" onclick="jobAction(${j.Id},'cancel')">✕</button>
            </td>
        </tr>`;
        }).join('');
        document.getElementById('jobsList').innerHTML = `<div style="overflow-x:auto"><table class="table">
        <thead><tr><th>#</th><th>Documento</th><th>Usuário</th><th>Págs</th><th>Tam</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>${rows}</tbody></table></div>`;
    }

    async function printerAction(name, action) {
        if (action === 'pause' && !confirm(`Pausar impressora "${name}"?`)) return;
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers/${encodeURIComponent(name)}/${action}`, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showToast(d.message, 'success'); loadPrinters();
        } catch (e) { showToast('Erro: ' + e.message, 'error'); }
    }

    async function removePrinter(name) {
        if (!confirm(`Remover a impressora "${name}" do servidor?\n\nEsta ação não pode ser desfeita.`)) return;
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers/${encodeURIComponent(name)}?csrf_token=${encodeURIComponent(CSRF_TOKEN)}`, { method: 'DELETE' });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showToast(d.message, 'success'); loadPrinters();
        } catch (e) { showToast('Erro: ' + e.message, 'error'); }
    }

    async function jobAction(jobId, action) {
        if (action === 'cancel' && !confirm(`Cancelar job ${jobId}?`)) return;
        try {
            const method = action === 'cancel' ? 'DELETE' : 'POST';
            const url = action === 'cancel'
                ? `/print/api/servers/${currentServerId}/printers/${encodeURIComponent(currentPrinter)}/jobs/${jobId}?csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
                : `/print/api/servers/${currentServerId}/printers/${encodeURIComponent(currentPrinter)}/jobs/${jobId}/${action}`;
            const opts = { method };
            if (method === 'POST') { opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' }; opts.body = `csrf_token=${encodeURIComponent(CSRF_TOKEN)}`; }
            const r = await fetch(url, opts);
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showToast(d.message, 'success'); loadJobs();
        } catch (e) { showToast('Erro: ' + e.message, 'error'); }
    }

    async function clearQueue() {
        if (!currentPrinter || !confirm(`Limpar TODA a fila de "${currentPrinter}"?`)) return;
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers/${encodeURIComponent(currentPrinter)}/jobs?csrf_token=${encodeURIComponent(CSRF_TOKEN)}`, { method: 'DELETE' });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showToast(d.message, 'success'); loadJobs();
        } catch (e) { showToast('Erro: ' + e.message, 'error'); }
    }

    // ─── Modal Adicionar Impressora ───────────────────────────────────────────────
    async function openAddPrinterModal() {
        document.getElementById('addPrinterName').value = '';
        document.getElementById('addPrinterIP').value = '';
        document.getElementById('addPrinterPortName').value = '';
        document.getElementById('addPrinterShareName').value = '';
        document.getElementById('addPrinterComment').value = '';
        document.getElementById('addPrinterShared').checked = true;
        document.getElementById('addPrinterMsg').innerHTML = '';
        document.getElementById('addPrinterDriver').innerHTML = '<option value="">Carregando drivers...</option>';
        document.getElementById('addPrinterModal').style.display = 'flex';
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/drivers`);
            const d = await r.json();
            const drivers = d.data || [];
            if (!drivers.length) {
                document.getElementById('addPrinterDriver').innerHTML = '<option value="">Nenhum driver encontrado</option>';
            } else {
                document.getElementById('addPrinterDriver').innerHTML = '<option value="">— Selecione o driver —</option>' +
                    drivers.map(dr => `<option value="${escapeHtml(dr.Name)}">${escapeHtml(dr.Name)}${dr.Manufacturer ? ' (' + escapeHtml(dr.Manufacturer) + ')' : ''}</option>`).join('');
            }
        } catch (e) {
            document.getElementById('addPrinterDriver').innerHTML = `<option value="">Erro ao carregar drivers</option>`;
        }
    }
    function closeAddPrinterModal() { document.getElementById('addPrinterModal').style.display = 'none'; }

    async function submitAddPrinter() {
        const name = document.getElementById('addPrinterName').value.trim();
        const driver = document.getElementById('addPrinterDriver').value;
        const ip = document.getElementById('addPrinterIP').value.trim();
        const portName = document.getElementById('addPrinterPortName').value.trim();
        const shareName = document.getElementById('addPrinterShareName').value.trim();
        const shared = document.getElementById('addPrinterShared').checked ? '1' : '0';
        const comment = document.getElementById('addPrinterComment').value.trim();
        const msgEl = document.getElementById('addPrinterMsg');
        const btn = document.getElementById('addPrinterSubmitBtn');

        if (!name || !driver || !ip) {
            msgEl.innerHTML = '<div class="alert alert-warning">Preencha: Nome, Driver e IP da Impressora</div>';
            return;
        }
        msgEl.innerHTML = '<div class="alert">Instalando impressora, aguarde...</div>';
        btn.disabled = true;
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: CSRF_TOKEN, name, driverName: driver, printerIP: ip, portName, shareName, shared, comment })
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showToast(d.message, 'success');
            closeAddPrinterModal();
            loadPrinters();
        } catch (e) {
            msgEl.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml(e.message)}</div>`;
        } finally { btn.disabled = false; }
    }

    // ─── Modal Editar Impressora ──────────────────────────────────────────────────
    async function openEditModal(name, currentPort) {
        document.getElementById('editOriginalName').value = name;
        document.getElementById('editPrinterName').value = name;
        document.getElementById('editPrinterPort').innerHTML = '<option value="">Carregando...</option>';
        document.getElementById('editModalMsg').innerHTML = '';
        document.getElementById('editPrinterModal').style.display = 'flex';
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/ports`);
            const d = await r.json();
            document.getElementById('editPrinterPort').innerHTML = '<option value="">— Manter porta atual —</option>' +
                (d.data || []).map(p => `<option value="${escapeHtml(p.Name)}" ${p.Name === currentPort ? 'selected' : ''}>${escapeHtml(p.Name)}</option>`).join('');
        } catch (e) { document.getElementById('editPrinterPort').innerHTML = '<option value="">Erro ao carregar</option>'; }
    }
    function closeEditModal() { document.getElementById('editPrinterModal').style.display = 'none'; }

    async function saveEditPrinter() {
        const originalName = document.getElementById('editOriginalName').value;
        const newName = document.getElementById('editPrinterName').value.trim();
        const portName = document.getElementById('editPrinterPort').value;
        const msgEl = document.getElementById('editModalMsg');
        if (!newName) { msgEl.innerHTML = '<div class="alert alert-warning">Nome não pode ser vazio</div>'; return; }
        msgEl.innerHTML = '<div class="alert">Salvando...</div>';
        try {
            const r = await fetch(`/print/api/servers/${currentServerId}/printers/${encodeURIComponent(originalName)}`, {
                method: 'PUT', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: CSRF_TOKEN, newName, portName })
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            showToast(d.message, 'success'); closeEditModal(); loadPrinters();
        } catch (e) { msgEl.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml(e.message)}</div>`; }
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function showToast(msg, type) {
        if (window.toast) window.toast[type === 'success' ? 'success' : 'error'](msg);
        else alert(msg);
    }
</script>

<?php
$content = ob_get_clean();
$title = $data['title'] ?? 'Impressoras';
$user = $data['user'] ?? null;
require __DIR__ . '/layout.php';
