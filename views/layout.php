<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'AD Manager' ?></title>
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/toast-breadcrumb-badges.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <script src="/assets/js/theme.js"></script>
</head>

<body>
    <?php if (isset($_SESSION['user'])): ?>
        <!-- Botão para abrir/fechar o menu em telas pequenas -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <svg class="sidebar-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <!-- Open Book Icon -->
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                </svg>
                <span class="sidebar-title">AD Manager</span>
            </div>

            <nav class="sidebar-nav">
                <a href="/dashboard" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="/users" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span>Usuários</span>
                </a>
                <a href="/groups" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        <circle cx="18" cy="9" r="2"></circle>
                        <path d="M5 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path>
                    </svg>
                    <span>Grupos</span>
                </a>
                <a href="/computers" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="2" y="3" width="8" height="7" rx="1" />
                        <rect x="14" y="3" width="8" height="7" rx="1" />
                        <rect x="2" y="14" width="8" height="7" rx="1" />
                        <rect x="14" y="14" width="8" height="7" rx="1" />
                        <line x1="6" y1="10" x2="6" y2="14" />
                        <line x1="18" y1="10" x2="18" y2="14" />
                    </svg>
                    <span>Computadores</span>
                </a>
                <a href="/dhcp" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                        <line x1="6" y1="6" x2="6.01" y2="6" />
                        <line x1="6" y1="18" x2="6.01" y2="18" />
                    </svg>
                    <span>DHCP</span>
                </a>
                <a href="/print" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    <span>Impressoras</span>
                </a>
                <a href="/shares" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        <path d="M8 21l8-8-8-8" />
                    </svg>
                    <span>Compartilhamentos</span>
                </a>
                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <a href="/audit" class="sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                            <polyline points="10 9 9 9 8 9" />
                        </svg>
                        <span>Auditoria</span>
                    </a>
                    <a href="/reports" class="sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="18" y1="20" x2="18" y2="10" />
                            <line x1="12" y1="20" x2="12" y2="4" />
                            <line x1="6" y1="20" x2="6" y2="14" />
                        </svg>
                        <span>Relatórios</span>
                    </a>
                    <a href="/settings" class="sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>
                        <span>Configurações</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <a href="/2fa/setup" class="sidebar-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span>Autenticação 2FA</span>
                </a>

                <button id="theme-toggle" class="sidebar-link" title="Alternar tema">
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="5" />
                        <line x1="12" y1="1" x2="12" y2="3" />
                        <line x1="12" y1="21" x2="12" y2="23" />
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                        <line x1="1" y1="12" x2="3" y2="12" />
                        <line x1="21" y1="12" x2="23" y2="12" />
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                    </svg>
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                    </svg>
                    <span>Tema</span>
                </button>

                <div class="sidebar-user">
                    <div class="user-info">
                        <svg class="user-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <div class="user-details">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
                            <div class="user-role"><?= htmlspecialchars($_SESSION['user']['role']) ?></div>
                        </div>
                    </div>
                    <a href="/logout" class="sidebar-link logout-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </aside>
    <?php endif; ?>

    <main class="main-content">
        <?php if (isset($_SESSION['user']) && isset($breadcrumbs) && !empty($breadcrumbs)): ?>
            <!-- Breadcrumbs -->
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <div class="breadcrumb-item <?= $index === count($breadcrumbs) - 1 ? 'active' : '' ?>">
                        <?php if ($index === count($breadcrumbs) - 1): ?>
                            <?= htmlspecialchars($crumb['label']) ?>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                            <svg class="breadcrumb-separator" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="/assets/js/toast.js"></script>
    <script src="/assets/js/components.js"></script>
    <script src="/assets/js/app.js"></script>

    <!-- Script para o menu responsivo -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mainContent = document.querySelector('.main-content');

            if (sidebar && sidebarToggle) {
                // Abre/fecha o menu ao clicar no botão
                sidebarToggle.addEventListener('click', function (e) {
                    e.stopPropagation(); // Impede que o clique feche o menu imediatamente
                    sidebar.classList.toggle('active');
                });

                // Fecha o menu se clicar fora dele (no conteúdo principal)
                mainContent.addEventListener('click', function () {
                    if (sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });
            }
        });
    </script>
    <?= $scripts ?? '' ?>
</body>

</html>