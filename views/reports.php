<?php
$title = 'Relatórios - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Relatórios</h1>
    <p>Gerar e exportar relatórios de auditoria</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Configurar Relatório</h2>
    </div>
    <div class="card-body">
        <form id="report-form" class="filter-grid">
            <div class="form-group">
                <label for="report-username">Usuário:</label>
                <input type="text" id="report-username" class="form-control" placeholder="Nome do usuário">
            </div>

            <div class="form-group">
                <label for="report-action">Ação:</label>
                <select id="report-action" class="form-control">
                    <option value="">Todas</option>
                    <option value="login_success">Login Sucesso</option>
                    <option value="login_failed">Login Falhou</option>
                    <option value="reset_password">Reset de Senha</option>
                    <option value="enable_user">Habilitar Usuário</option>
                    <option value="disable_user">Desabilitar Usuário</option>
                    <option value="add_group_member">Adicionar a Grupo</option>
                    <option value="remove_group_member">Remover de Grupo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="report-result">Resultado:</label>
                <select id="report-result" class="form-control">
                    <option value="">Todos</option>
                    <option value="success">Sucesso</option>
                    <option value="failure">Falha</option>
                    <option value="error">Erro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="report-ou">OU:</label>
                <input type="text" id="report-ou" class="form-control" placeholder="Organizational Unit">
            </div>

            <div class="form-group">
                <label for="report-date-from">Data Inicial:</label>
                <input type="date" id="report-date-from" class="form-control">
            </div>

            <div class="form-group">
                <label for="report-date-to">Data Final:</label>
                <input type="date" id="report-date-to" class="form-control">
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Exportar Relatório</h2>
    </div>
    <div class="card-body">
        <div class="export-options">
            <div class="export-card">
                <div class="export-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <h3>Exportar PDF</h3>
                <p>Relatório formatado em PDF com gráficos e estatísticas</p>
                <button id="export-pdf" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Baixar PDF
                </button>
            </div>

            <div class="export-card">
                <div class="export-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <rect x="8" y="12" width="8" height="2"/>
                        <rect x="8" y="16" width="8" height="2"/>
                    </svg>
                </div>
                <h3>Exportar Excel</h3>
                <p>Planilha Excel com dados detalhados para análise</p>
                <button id="export-excel" class="btn btn-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Baixar Excel
                </button>
            </div>
        </div>

        <div id="export-message" class="alert" style="display: none; margin-top: 20px;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Tipos de Relatórios Disponíveis</h2>
    </div>
    <div class="card-body">
        <div class="report-types">
            <div class="report-type-item">
                <h4>Resumo de Acessos e Logins</h4>
                <p>Histórico completo de tentativas de login, sucessos e falhas</p>
            </div>
            <div class="report-type-item">
                <h4>Mudanças em Grupos</h4>
                <p>Todas as adições e remoções de membros em grupos</p>
            </div>
            <div class="report-type-item">
                <h4>Resets de Senha</h4>
                <p>Registro de todas as redefinições de senha realizadas</p>
            </div>
            <div class="report-type-item">
                <h4>Usuários Desativados</h4>
                <p>Lista de contas desabilitadas e habilitadas</p>
            </div>
            <div class="report-type-item">
                <h4>Logs por OU</h4>
                <p>Atividades filtradas por Organizational Unit</p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/reports.js"></script>';
include __DIR__ . '/layout.php';
?>
