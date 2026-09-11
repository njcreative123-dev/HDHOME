/**
 * HDHome - Main JavaScript
 * Handles mobile menu, user menu, favorites, and general interactions.
 */

class HDHomeApp {
    constructor() {
        this.init();
    }

    init() {
        this.initMobileMenu();
        this.initUserMenu();
        this.initFavoriteButtons();
        this.initSearch();
        this.initAIToggle();
    }

    initMobileMenu() {
        const toggle = document.getElementById('menu-toggle');
        const nav = document.getElementById('main-nav');
        if (toggle && nav) {
            toggle.addEventListener('click', () => {
                nav.classList.toggle('open');
                toggle.classList.toggle('open');
            });
        }
    }

    initUserMenu() {
        const userMenu = document.getElementById('user-menu');
        const toggle = document.getElementById('user-menu-toggle');
        if (userMenu && toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenu.classList.toggle('open');
            });
            document.addEventListener('click', () => {
                userMenu.classList.remove('open');
            });
        }
    }

    initFavoriteButtons() {
        document.querySelectorAll('.toggle-favorite, .btn-favorite').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const channelId = btn.dataset.channelId;
                if (!channelId) return;

                fetch('/api/channels.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=favorite&channel_id=${channelId}&favorite=${btn.classList.contains('active') ? '0' : '1'}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        btn.classList.toggle('active');
                        btn.textContent = data.data?.favorited ? '★ Remove Favorite' : '☆ Add Favorite';
                    } else if (data.status === 'error' && data.error === 'Authentication required.') {
                        window.location.href = '/login.php';
                    }
                })
                .catch(() => {});
            });
        });
    }

    initSearch() {
        const searchInput = document.querySelector('.search-input');
        let debounceTimer;
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    // Could trigger live search
                }, 300);
            });
        }
    }

    initAIToggle() {
        const toggle = document.getElementById('ai-toggle');
        const widget = document.getElementById('ai-chat-widget');
        const close = document.getElementById('ai-chat-close');
        const messages = document.getElementById('ai-chat-messages');
        const input = document.getElementById('ai-message-input');
        const sendBtn = document.getElementById('ai-send-btn');

        let sessionId = localStorage.getItem('ai_session_id') || '';

        const toggleWidget = () => {
            if (widget) {
                widget.style.display = widget.style.display === 'none' ? 'block' : 'none';
                if (widget.style.display === 'block' && !sessionId) {
                    sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem('ai_session_id', sessionId);
                    messages.innerHTML = '<div class="ai-message bot">Hi! I am the HDHome Assistant. How can I help you find channels today?</div>';
                }
            }
        };

        if (toggle) {
            toggle.addEventListener('click', toggleWidget);
        }
        if (close) {
            close.addEventListener('click', () => {
                if (widget) widget.style.display = 'none';
            });
        }

        const sendMessage = async () => {
            if (!input || !input.value.trim()) return;

            const message = input.value.trim();
            input.value = '';

            const userMsg = document.createElement('div');
            userMsg.className = 'ai-message user';
            userMsg.textContent = message;
            messages.appendChild(userMsg);

            const loading = document.createElement('div');
            loading.className = 'ai-message bot loading';
            loading.textContent = 'Thinking...';
            messages.appendChild(loading);

            try {
                const response = await fetch('/api/ai_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'chat',
                        session_id: sessionId,
                        message: message,
                    })
                });

                const data = await response.json();
                loading.remove();

                const botMsg = document.createElement('div');
                botMsg.className = 'ai-message bot';
                botMsg.textContent = data.response || 'Sorry, I could not process that request.';
                messages.appendChild(botMsg);
            } catch (err) {
                loading.remove();
                const botMsg = document.createElement('div');
                botMsg.className = 'ai-message bot error';
                botMsg.textContent = 'Sorry, there was an error connecting to the AI assistant.';
                messages.appendChild(botMsg);
            }

            messages.scrollTop = messages.scrollHeight;
        };

        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.hdhomeApp = new HDHomeApp();
});
