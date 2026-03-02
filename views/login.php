<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AD Manager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/theme.js"></script>
</head>

<body class="login-page">
    <!-- Themed Background Icons -->
    <div class="login-bg-icons">
        <!-- Book icons -->
        <svg class="bg-icon icon-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>

        <!-- Lock icon -->
        <svg class="bg-icon icon-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>

        <!-- Key icon -->
        <svg class="bg-icon icon-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path
                d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4">
            </path>
        </svg>

        <!-- Shield icon -->
        <svg class="bg-icon icon-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>

        <!-- Server/Database icon -->
        <svg class="bg-icon icon-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
        </svg>

        <!-- User group icon -->
        <svg class="bg-icon icon-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
    </div>

    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-icon-wrapper">
                    <svg class="login-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <!-- Book spine -->
                        <path class="book-spine" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path class="book-spine" d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z">
                        </path>
                        <!-- Animated pages -->
                        <path class="book-page page-1" d="M12 2v20" opacity="0.3"></path>
                        <path class="book-page page-2" d="M14 2v20" opacity="0.2"></path>
                        <path class="book-page page-3" d="M16 2v20" opacity="0.15"></path>
                    </svg>
                </div>
                <h1>AD Manager</h1>
                <p>Sistema de Gerenciamento do Active Directory</p>
            </div>

            <form id="login-form" class="login-form">
                <input type="hidden" id="csrf-token-field" name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                <div class="form-group">
                    <label for="username">Usuário</label>
                    <div class="input-with-icon">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input type="text" id="username" name="username" placeholder="Digite seu usuário" required
                            autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <div class="input-with-icon">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
                    </div>
                </div>

                <div id="error-message" class="alert alert-error" style="display: none;"></div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="btn-text">Entrar</span>
                    <span class="btn-loader" style="display: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="0">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12"
                                    dur="1s" repeatCount="indefinite" />
                            </circle>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="login-footer">
                <button id="theme-toggle" class="btn-text">
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="5" />
                        <line x1="12" y1="1" x2="12" y2="3" />
                        <line x1="12" y1="21" x2="12" y2="23" />
                    </svg>
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                    </svg>
                    Alternar tema
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/login.js"></script>
    <script src="/assets/js/login-icons.js"></script>
</body>

</html>