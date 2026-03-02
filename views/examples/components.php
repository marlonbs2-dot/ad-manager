<?php
/**
 * Example page showing how to use breadcrumbs, badges, and toast notifications
 */

// Set breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Usuários', 'url' => '/users'],
    ['label' => 'Detalhes do Usuário', 'url' => '']  // Last item has no URL
];

$title = 'Exemplo de Componentes Visuais';
ob_start();
?>

<div class="page-header">
    <h1>Componentes Visuais</h1>
    <p>Exemplos de toast notifications, breadcrumbs e badges</p>
</div>

<!-- Toast Examples -->
<div class="card">
    <div class="card-header">
        <h2>Toast Notifications</h2>
    </div>
    <div class="card-body">
        <p>Clique nos botões abaixo para ver exemplos de notificações:</p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
            <button class="btn btn-success" onclick="toast.success('Operação realizada com sucesso!')">
                Success Toast
            </button>
            <button class="btn btn-error" onclick="toast.error('Ocorreu um erro ao processar sua solicitação.')">
                Error Toast
            </button>
            <button class="btn btn-warning" onclick="toast.warning('Atenção: Esta ação não pode ser desfeita.')">
                Warning Toast
            </button>
            <button class="btn btn-primary" onclick="toast.info('Novos dados disponíveis.')">
                Info Toast
            </button>
        </div>
    </div>
</div>

<!-- Badge Examples -->
<div class="card">
    <div class="card-header">
        <h2>Status Badges</h2>
    </div>
    <div class="card-body">
        <h3 style="margin-bottom: 1rem;">Status Badges</h3>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <span class="badge badge-success">Ativo</span>
            <span class="badge badge-error">Bloqueado</span>
            <span class="badge badge-warning">Pendente</span>
            <span class="badge badge-info">Em Análise</span>
            <span class="badge badge-secondary">Inativo</span>
        </div>

        <h3 style="margin-bottom: 1rem;">Role Badges</h3>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <span class="badge badge-admin">Administrador</span>
            <span class="badge badge-user">Usuário</span>
            <span class="badge badge-guest">Convidado</span>
        </div>

        <h3 style="margin-bottom: 1rem;">Badges com Ícone</h3>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <span class="badge badge-online">
                <svg class="badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                Online
            </span>
            <span class="badge badge-offline">
                <svg class="badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
                Offline
            </span>
        </div>

        <h3 style="margin-bottom: 1rem;">Badges com Dot Indicator</h3>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <span class="badge badge-success badge-dot">Ativo</span>
            <span class="badge badge-warning badge-dot">Aguardando</span>
            <span class="badge badge-error badge-dot">Crítico</span>
        </div>
    </div>
</div>

<!-- Table with Badges Example -->
<div class="card">
    <div class="card-header">
        <h2>Tabela com Badges</h2>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Função</th>
                        <th>Último Acesso</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>João Silva</td>
                        <td>joao.silva@empresa.com</td>
                        <td><span class="badge badge-active badge-dot">Ativo</span></td>
                        <td><span class="badge badge-admin">Admin</span></td>
                        <td>Há 5 minutos</td>
                    </tr>
                    <tr>
                        <td>Maria Santos</td>
                        <td>maria.santos@empresa.com</td>
                        <td><span class="badge badge-online badge-dot">Online</span></td>
                        <td><span class="badge badge-user">Usuário</span></td>
                        <td>Há 1 hora</td>
                    </tr>
                    <tr>
                        <td>Pedro Costa</td>
                        <td>pedro.costa@empresa.com</td>
                        <td><span class="badge badge-inactive">Inativo</span></td>
                        <td><span class="badge badge-user">Usuário</span></td>
                        <td>Há 3 dias</td>
                    </tr>
                    <tr>
                        <td>Ana Oliveira</td>
                        <td>ana.oliveira@empresa.com</td>
                        <td><span class="badge badge-blocked">Bloqueado</span></td>
                        <td><span class="badge badge-guest">Convidado</span></td>
                        <td>Há 1 semana</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>