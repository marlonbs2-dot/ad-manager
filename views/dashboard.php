<?php
$title = 'Dashboard - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Visão geral do sistema</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="stat-actions-today">-</div>
            <div class="stat-label">Ações Hoje</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="stat-success-rate">-</div>
            <div class="stat-label">Taxa de Sucesso (30d)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="stat-active-users">-</div>
            <div class="stat-label">Usuários Ativos (7d)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" id="stat-recent-actions">-</div>
            <div class="stat-label">Ações Recentes</div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h2>Ações de Hoje por Tipo</h2>
        </div>
        <div class="card-body">
            <div id="actions-today-by-type" class="chart-container">
                <div class="loading">Carregando...</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Ações por Tipo (7 dias)</h2>
        </div>
        <div class="card-body">
            <div id="actions-by-type" class="chart-container">
                <div class="loading">Carregando...</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Usuários Mais Ativos (7 dias)</h2>
        </div>
        <div class="card-body">
            <div id="most-active-users" class="list-container">
                <div class="loading">Carregando...</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Minhas Atividades Recentes</h2>
        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">Últimas 10 ações realizadas por você</p>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table id="recent-logs" class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Ação</th>
                        <th>Alvo</th>
                        <th>IP</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="text-center">Carregando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/dashboard.js"></script>';
include __DIR__ . '/layout.php';
?>
