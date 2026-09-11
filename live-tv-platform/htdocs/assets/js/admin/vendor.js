/**
 * HDHome - Admin Panel Vendor Utilities
 * Minimal vanilla JS utilities for admin interactions.
 * Uses no external dependencies to keep the platform lightweight.
 */

class AdminUtils {
    /**
     * AJAX request helper
     */
    static request(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        };

        const opts = Object.assign(defaults, options);
        if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            opts.body = JSON.stringify(opts.body);
        }

        return fetch(url, opts).then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        });
    }

    /**
     * Show a notification toast
     */
    static notify(message, type = 'info', duration = 4000) {
        const container = document.getElementById('notification-container');
        if (!container) {
            const div = document.createElement('div');
            div.id = 'notification-container';
            div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
            document.body.appendChild(div);
        }

        const toast = document.createElement('div');
        toast.className = `notification notification-${type}`;
        toast.style.cssText = `
            background: ${type === 'success' ? 'rgba(46, 204, 113, 0.9)' : type === 'error' ? 'rgba(231, 76, 60, 0.9)' : 'rgba(20, 20, 30, 0.95)'};
            border: 1px solid ${type === 'success' ? 'rgba(46, 204, 113, 0.3)' : type === 'error' ? 'rgba(231, 76, 60, 0.3)' : 'rgba(255, 255, 255, 0.1)'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            backdrop-filter: blur(10px);
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = message;

        document.getElementById('notification-container').appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /**
     * Toggle sidebar on mobile
     */
    static initSidebarToggle() {
        const toggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (toggle && sidebar) {
            toggle.addEventListener('click', () => {
                sidebar.classList.add('active');
                if (overlay) overlay.style.display = 'block';
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
            });
        }

        const close = document.getElementById('sidebar-close');
        if (close) {
            close.addEventListener('click', () => {
                sidebar.classList.remove('active');
                if (overlay) overlay.style.display = 'none';
            });
        }
    }

    /**
     * Format bytes to human readable
     */
    static formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Format number with commas
     */
    static formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Debounce function
     */
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AdminUtils.initSidebarToggle();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminUtils;
}
