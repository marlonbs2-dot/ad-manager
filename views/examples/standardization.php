<?php
/**
 * Complete Standardization Examples
 * Demonstrates all new components and features
 */

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Exemplos', 'url' => '/examples'],
    ['label' => 'Padronização Completa', 'url' => '']
];

$title = 'Padronização Completa - Exemplos';
ob_start();
?>

<div class="page-header">
    <h1>Padronização Completa</h1>
    <p>Demonstração de todos os componentes e estados padronizados</p>
</div>

<!-- Typography -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Tipografia</h2>
    </div>
    <div class="card-body">
        <h1>Heading 1 - 32px</h1>
        <h2>Heading 2 - 24px</h2>
        <h3>Heading 3 - 20px</h3>
        <h4>Heading 4 - 18px</h4>
        <p>Parágrafo normal - 16px. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
        <p class="text-small">Texto pequeno - 14px. Usado para texto secundário.</p>
        <p class="text-tiny">Texto minúsculo - 12px. Usado para labels e badges.</p>
    </div>
</div>

<!-- Loading States -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Estados de Carregamento</h2>
    </div>
    <div class="card-body">
        <h3 class="mb-md">Spinners</h3>
        <div class="d-flex align-items-center gap-lg mb-lg">
            <div class="spinner-sm"></div>
            <div class="spinner"></div>
            <div class="spinner-lg"></div>
        </div>

        <h3 class="mb-md">Skeleton Screens</h3>
        <div class="skeleton skeleton-text"></div>
        <div class="skeleton skeleton-text"></div>
        <div class="skeleton skeleton-text"></div>

        <h3 class="mb-md mt-lg">Button Loading</h3>
        <button class="btn btn-primary loading">Carregando...</button>
    </div>
</div>

<!-- Empty States -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Empty States</h2>
    </div>
    <div class="card-body">
        <div class="empty-state">
            <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            <h3 class="empty-state-title">Nenhum resultado encontrado</h3>
            <p class="empty-state-message">Não encontramos nenhum item correspondente à sua busca. Tente usar termos
                diferentes.</p>
            <button class="btn btn-primary">Nova Busca</button>
        </div>
    </div>
</div>

<!-- Pagination -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Paginação</h2>
    </div>
    <div class="card-body">
        <div class="pagination">
            <a href="#" class="pagination-item disabled">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </a>
            <a href="#" class="pagination-item active">1</a>
            <a href="#" class="pagination-item">2</a>
            <a href="#" class="pagination-item">3</a>
            <span class="pagination-ellipsis">...</span>
            <a href="#" class="pagination-item">10</a>
            <a href="#" class="pagination-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </a>
        </div>
    </div>
</div>

<!-- Dropdown Menus -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Dropdown Menus</h2>
    </div>
    <div class="card-body">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-trigger">
                Ações
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="6 9 12 15 18 9" />
                </svg>
            </button>
            <div class="dropdown-menu">
                <a href="#" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Editar
                </a>
                <a href="#" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                    Excluir
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="16" x2="12" y2="12" />
                        <line x1="12" y1="8" x2="12.01" y2="8" />
                    </svg>
                    Mais informações
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Search Input -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Search Input</h2>
    </div>
    <div class="card-body">
        <div class="search-input-wrapper">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            <input type="text" class="search-input" placeholder="Buscar...">
            <svg class="search-clear" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </div>
    </div>
</div>

<!-- Validation States -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Estados de Validação</h2>
    </div>
    <div class="card-body">
        <div class="form-group mb-md">
            <label>Campo Válido</label>
            <input type="text" class="form-control is-valid" value="usuario@exemplo.com">
            <div class="form-feedback valid">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Email válido!
            </div>
        </div>

        <div class="form-group mb-md">
            <label>Campo Inválido</label>
            <input type="text" class="form-control is-invalid" value="email-invalido">
            <div class="form-feedback invalid">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
                Por favor, insira um email válido.
            </div>
        </div>

        <div class="form-group">
            <label>Campo com Aviso</label>
            <input type="text" class="form-control is-warning" value="senha123">
            <div class="form-feedback warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path
                        d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                Senha fraca. Considere usar uma senha mais forte.
            </div>
        </div>
    </div>
</div>

<!-- Input Icons -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Input com Ícones</h2>
    </div>
    <div class="card-body">
        <div class="form-group mb-md">
            <label>Email</label>
            <div class="input-group-icon input-icon-left">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                    <polyline points="22,6 12,13 2,6" />
                </svg>
                <input type="email" class="form-control" placeholder="seu@email.com">
            </div>
        </div>

        <div class="form-group">
            <label>Senha</label>
            <div class="input-group-icon input-icon-right">
                <input type="password" class="form-control" placeholder="Digite sua senha">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Table with Sticky Headers and Sorting -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Tabela com Sticky Headers e Ordenação</h2>
    </div>
    <div class="card-body">
        <div class="table-container" style="max-height: 400px; overflow-y: auto;">
            <table class="data-table table-sticky">
                <thead>
                    <tr>
                        <th class="sortable">Nome</th>
                        <th class="sortable">Email</th>
                        <th class="sortable">Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 15; $i++): ?>
                        <tr>
                            <td>Usuário
                                <?= $i ?>
                            </td>
                            <td>usuario
                                <?= $i ?>@exemplo.com
                            </td>
                            <td>
                                <?php if ($i % 3 == 0): ?>
                                    <span class="badge badge-active badge-dot">Ativo</span>
                                <?php elseif ($i % 3 == 1): ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-secondary dropdown-trigger">•••</button>
                                    <div class="dropdown-menu">
                                        <a href="#" class="dropdown-item">Editar</a>
                                        <a href="#" class="dropdown-item">Excluir</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Card Variants -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Variantes de Cards</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <div class="card">
                <div class="card-body">
                    <h3>Card Padrão</h3>
                    <p>Com glassmorphism</p>
                </div>
            </div>

            <div class="card card-outlined">
                <div class="card-body">
                    <h3>Card Outlined</h3>
                    <p>Apenas borda</p>
                </div>
            </div>

            <div class="card card-elevated">
                <div class="card-body">
                    <h3>Card Elevated</h3>
                    <p>Sombra maior</p>
                </div>
            </div>

            <div class="card card-flat">
                <div class="card-body">
                    <h3>Card Flat</h3>
                    <p>Sem sombra</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Disabled States -->
<div class="card mb-lg">
    <div class="card-header">
        <h2>Estados Desabilitados</h2>
    </div>
    <div class="card-body">
        <div class="d-flex gap-md mb-md">
            <button class="btn btn-primary">Normal</button>
            <button class="btn btn-primary" disabled>Desabilitado</button>
        </div>
        <div class="form-group">
            <input type="text" class="form-control" placeholder="Normal">
            <input type="text" class="form-control mt-sm" placeholder="Desabilitado" disabled>
        </div>
    </div>
</div>

<!-- Icon Sizes -->
<div class="card">
    <div class="card-header">
        <h2>Tamanhos de Ícones</h2>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-lg">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <svg class="icon-xl" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
        </div>
        <p class="mt-md text-small">16px, 20px, 24px, 32px</p>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout.php';
?>