// Theme Management
(function() {
    const THEME_KEY = 'ad-manager-theme';
    
    function getTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved) return saved;
        
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        
        return 'light';
    }
    
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
    }
    
    // Initialize theme immediately
    setTheme(getTheme());
    
    // Setup toggle after DOM loads
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('theme-toggle');
        
        if (toggle) {
            toggle.addEventListener('click', function() {
                const current = getTheme();
                const next = current === 'light' ? 'dark' : 'light';
                setTheme(next);
            });
        }
    });
})();
