<?php
$title = 'Auditoria - AD Manager';
ob_start();
?>

<div class="page-header">
    <h1>Auditoria de Ações</h1>
    <p>Visualizar e filtrar logs de atividades do sistema</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Filtros</h2>
    </div>
    <div class="card-body">
        <form id="filter-form" class="filter-grid">
            <div class="form-group">
                <label for="filter-username">Usuário:</label>
                <input type="text" id="filter-username" class="form-control" placeholder="Nome do usuário">
            </div>

            <div class="form-group">
                <label for="filter-action">Ação:</label>
                <select id="filter-action" class="form-control">
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
                <label for="filter-result">Resultado:</label>
                <select id="filter-result" class="form-control">
                    <option value="">Todos</option>
                    <option value="success">Sucesso</option>
                    <option value="failure">Falha</option>
                    <option value="error">Erro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="filter-ou">OU:</label>
                <input type="text" id="filter-ou" class="form-control" placeholder="Organizational Unit">
            </div>

            <div class="form-group">
                <label for="filter-date-from">Data Inicial:</label>
                <input type="date" id="filter-date-from" class="form-control">
            </div>

            <div class="form-group">
                <label for="filter-date-to">Data Final:</label>
                <input type="date" id="filter-date-to" class="form-control">
            </div>

            <div class="form-group form-actions">
                <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                <button type="button" id="clear-filters" class="btn btn-text">Limpar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Logs de Auditoria</h2>
        <div class="card-actions">
            <span id="total-records" class="badge">0 registros</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Alvo</th>
                        <th>OU</th>
                        <th>IP</th>
                        <th>Resultado</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody id="audit-table-body">
                    <tr>
                        <td colspan="9" class="text-center">Carregando...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button id="prev-page" class="btn btn-sm btn-text" disabled>Anterior</button>
            <span id="page-info">Página 1</span>
            <button id="next-page" class="btn btn-sm btn-text">Próxima</button>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detalhes da Ação</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="details-content" class="code-block"></pre>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$scripts = '<script src="/assets/js/audit.js"></script>';
include __DIR__ . '/layout.php';
?>
