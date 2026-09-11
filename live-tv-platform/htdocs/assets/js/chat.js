/**
 * HDHome - AI Chat Widget
 * Manages the AI chat interface, conversation history,
 * and message formatting.
 */

class AIChatWidget {
    constructor() {
        this.sessionId = localStorage.getItem('ai_session_id') || this.generateSessionId();
        this.messageContainer = document.getElementById('ai-chat-messages');
        this.input = document.getElementById('ai-message-input');
        this.sendBtn = document.getElementById('ai-send-btn');
        this.widget = document.getElementById('ai-chat-widget');

        this.init();
    }

    init() {
        this.storeSessionId();
        this.setupEventListeners();
    }

    storeSessionId() {
        localStorage.setItem('ai_session_id', this.sessionId);
    }

    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    setupEventListeners() {
        if (this.sendBtn) {
            this.sendBtn.addEventListener('click', () => this.sendMessage());
        }

        if (this.input) {
            this.input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }

        // Close chat
        const closeBtn = document.getElementById('ai-chat-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // Open chat
        const openBtn = document.getElementById('ai-toggle');
        if (openBtn) {
            openBtn.addEventListener('click', () => {
                this.open();
            });
        }
    }

    open() {
        if (this.widget) {
            this.widget.style.display = 'block';
            this.loadHistory();
        }
    }

    close() {
        if (this.widget) {
            this.widget.style.display = 'none';
        }
    }

    async loadHistory() {
        if (!this.messageContainer) return;

        try {
            const response = await fetch('/api/ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'history',
                    session_id: this.sessionId,
                })
            });

            const data = await response.json();
            this.messageContainer.innerHTML = '';

            if (data.history && data.history.length > 0) {
                data.history.forEach(msg => {
                    this.addMessage(msg.message, msg.role === 'user', false);
                });
                this.scrollToBottom();
            } else {
                this.addMessage('Hello! I am the HDHome AI Assistant. How can I help you find channels today?', false, false);
            }
        } catch (err) {
            this.addMessage('Unable to load conversation history. Please try again.', false, false);
        }
    }

    addMessage(content, isUser, animate = true) {
        if (!this.messageContainer) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-message ${isUser ? 'user' : 'bot'}`;
        if (animate) {
            msgDiv.style.opacity = '0';
            msgDiv.style.transform = 'translateY(10px)';
            msgDiv.style.transition = 'all 0.3s ease';
        }

        if (isUser) {
            msgDiv.textContent = content;
        } else {
            msgDiv.innerHTML = this.formatMessage(content);
        }

        this.messageContainer.appendChild(msgDiv);
        this.scrollToBottom();

        if (animate) {
            setTimeout(() => {
                msgDiv.style.opacity = '1';
                msgDiv.style.transform = 'translateY(0)';
            }, 10);
        }
    }

    formatMessage(content) {
        // Escape HTML
        let formatted = content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Format markdown-style bold
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Format markdown-style links
        formatted = formatted.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>');

        // Format bullet points
        formatted = formatted.replace(/^\s*[-•]\s(.+)$/gm, '<li>$1</li>');
        formatted = formatted.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');

        // Format code blocks
        formatted = formatted.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

        // Format line breaks
        formatted = formatted.replace(/\n/g, '<br>');

        return formatted;
    }

    scrollToBottom() {
        if (this.messageContainer) {
            this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
        }
    }

    async sendMessage() {
        const message = this.input?.value?.trim();
        if (!message) return;

        this.input.value = '';
        this.addMessage(message, true);

        const typing = this.createTypingIndicator();
        this.messageContainer.appendChild(typing);
        this.scrollToBottom();

        try {
            const response = await fetch('/api/ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'chat',
                    session_id: this.sessionId,
                    message: message,
                })
            });

            const data = await response.json();
            typing.remove();

            if (data.response) {
                this.addMessage(data.response, false);
            } else if (data.error) {
                this.addMessage('Error: ' + data.error, false);
            }
        } catch (err) {
            typing.remove();
            this.addMessage('Sorry, there was a connection error. Please try again.', false);
        }

        this.scrollToBottom();
    }

    createTypingIndicator() {
        const div = document.createElement('div');
        div.className = 'ai-message bot typing';
        div.innerHTML = '<span>•</span><span>•</span><span>•</span>';
        return div;
    }
}

// Initialize the chat widget
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ai-chat-widget')) {
        window.aiChatWidget = new AIChatWidget();
    }
});
