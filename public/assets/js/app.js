// Global App Functions
const App = {
    // API Helper
    async fetch(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin' // IMPORTANTE: Envia cookies com a requisição
        };
        
        const config = { ...defaults, ...options };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    // Show alert message
    showAlert(element, message, type = 'info') {
        element.className = `alert alert-${type}`;
        element.textContent = message;
        element.style.display = 'block';
        
        setTimeout(() => {
            element.style.display = 'none';
        }, 5000);
    },
    
    // Format date
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString('pt-BR');
    },
    
    // Format DN
    formatDN(dn) {
        if (!dn) return 'N/A';
        const parts = dn.split(',');
        return parts[0].replace(/^CN=|^OU=/, '');
    },

    // Escape HTML to prevent XSS
    escapeHtml(str) {
        if (str === null || typeof str === 'undefined') return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },
    
    // Modal helpers
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    },
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    },
    
    // Setup modal close handlers
    setupModals() {
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.classList.remove('active');
                }
            });
        });
        
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    },
    
    // Loading state
    setLoading(button, loading) {
        const text = button.querySelector('.btn-text');
        const loader = button.querySelector('.btn-loader');
        
        if (loading) {
            if (text) text.style.display = 'none';
            if (loader) loader.style.display = 'inline-flex';
            button.disabled = true;
        } else {
            if (text) text.style.display = 'inline';
            if (loader) loader.style.display = 'none';
            button.disabled = false;
        }
    },
    
    // Copy to clipboard
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copiado para a área de transferência!');
            });
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('Copiado para a área de transferência!');
        }
    },
    
    // Base64 encode with UTF-8 support (for DNs with accents and special chars)
    // Returns URL-safe base64 (replaces +, /, = with URL-safe characters)
    base64Encode(str) {
        try {
            // Convert string to UTF-8 bytes, then to base64
            const base64 = btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, (match, p1) => {
                return String.fromCharCode(parseInt(p1, 16));
            }));
            
            // Make it URL-safe by replacing problematic characters
            return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        } catch (e) {
            console.error('Error encoding to base64:', e);
            return btoa(str); // Fallback to regular btoa
        }
    },
    
    // Base64 decode with UTF-8 support
    // Handles URL-safe base64
    base64Decode(str) {
        try {
            // Convert URL-safe base64 back to regular base64
            let base64 = str.replace(/-/g, '+').replace(/_/g, '/');
            
            // Add padding if needed
            while (base64.length % 4) {
                base64 += '=';
            }
            
            // Decode base64 to UTF-8 string
            return decodeURIComponent(Array.prototype.map.call(atob(base64), (c) => {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
        } catch (e) {
            console.error('Error decoding from base64:', e);
            return atob(str); // Fallback to regular atob
        }
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    App.setupModals();
    
    // Mark active sidebar link
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.startsWith(href)) {
            link.classList.add('active');
        }
    });
});
