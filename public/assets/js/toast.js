/**
 * Toast Notification System
 * Elegant visual feedback for user actions
 */

class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.init();
    }

    init() {
        // Create container if it doesn't exist
        if (!document.querySelector('.toast-container')) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.toast-container');
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast (success, error, warning, info)
     * @param {string} title - Optional title
     * @param {number} duration - Duration in milliseconds (0 = no auto-dismiss)
     */
    show(message, type = 'info', title = null, duration = 4000) {
        const toast = this.createToast(message, type, title, duration);
        this.container.appendChild(toast);
        this.toasts.push(toast);

        // Auto dismiss
        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(toast);
            }, duration);
        }

        return toast;
    }

    createToast(message, type, title, duration) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        // Icon
        const icon = this.getIcon(type);

        // Content
        const content = document.createElement('div');
        content.className = 'toast-content';

        if (title) {
            const titleEl = document.createElement('div');
            titleEl.className = 'toast-title';
            titleEl.textContent = title;
            content.appendChild(titleEl);
        }

        const messageEl = document.createElement('div');
        messageEl.className = 'toast-message';
        messageEl.textContent = message;
        content.appendChild(messageEl);

        // Close button
        const closeBtn = document.createElement('button');
        closeBtn.className = 'toast-close';
        closeBtn.innerHTML = '×';
        closeBtn.setAttribute('aria-label', 'Close notification');
        closeBtn.onclick = () => this.dismiss(toast);

        // Progress bar
        let progressBar = null;
        if (duration > 0) {
            progressBar = document.createElement('div');
            progressBar.className = 'toast-progress';
            progressBar.style.animationDuration = `${duration}ms`;
        }

        // Assemble toast
        toast.appendChild(icon);
        toast.appendChild(content);
        toast.appendChild(closeBtn);
        if (progressBar) toast.appendChild(progressBar);

        return toast;
    }

    getIcon(type) {
        const iconContainer = document.createElement('div');
        iconContainer.className = 'toast-icon';

        const icons = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        iconContainer.innerHTML = icons[type] || icons.info;
        return iconContainer;
    }

    dismiss(toast) {
        toast.classList.add('toast-exit');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            this.toasts = this.toasts.filter(t => t !== toast);
        }, 300);
    }

    // Convenience methods
    success(message, title = 'Sucesso', duration = 4000) {
        return this.show(message, 'success', title, duration);
    }

    error(message, title = 'Erro', duration = 5000) {
        return this.show(message, 'error', title, duration);
    }

    warning(message, title = 'Atenção', duration = 4500) {
        return this.show(message, 'warning', title, duration);
    }

    info(message, title = 'Informação', duration = 4000) {
        return this.show(message, 'info', title, duration);
    }

    // Clear all toasts
    clearAll() {
        this.toasts.forEach(toast => this.dismiss(toast));
    }
}

// Create global instance
window.toast = new ToastManager();

// Example usage (can be removed in production):
// toast.success('Operação realizada com sucesso!');
// toast.error('Ocorreu um erro ao processar sua solicitação.');
// toast.warning('Atenção: Esta ação não pode ser desfeita.');
// toast.info('Novos dados disponíveis.');
