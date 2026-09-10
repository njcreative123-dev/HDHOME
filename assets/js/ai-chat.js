/**
 * HDHome Live TV - AI Chat interface.
 * Manages: message send/receive, conversation history, typing indicator,
 * session persistence via localStorage, and tool-call visualization.
 */
const AIAgent = (() => {
    let sessionId = localStorage.getItem('hdhome_ai_session') || 'ai_' + Date.now();
    localStorage.setItem('hdhome_ai_session', sessionId);
    let initialized = false;

    /* ---- DOM refs (lazy init) ------------------------------------------ */
    function getEls() {
        return {
            chat:   document.getElementById('aiChat'),
            form:   document.getElementById('aiAiForm') || document.getElementById('aiForm'),
            input:  document.getElementById('aiInput'),
            btn:    document.getElementById('aiSendBtn'),
            status: document.getElementById('aiStatus'),
        };
    }

    /* ---- Public init (called on view switch) --------------------------- */
    async function init() {
        if (initialized) return;
        initialized = true;
        await checkStatus();
        await loadHistory();
        bindEvents();
    }

    async function checkStatus() {
        const els = getEls();
        try {
            const res = await API.get('ai/status');
            const ok = res?.data?.configured;
            els.status.textContent = ok ? `✅ ${res.data.model || 'Ready'}` : '⚠️ Not configured';
            els.status.className = 'ai-status ' + (ok ? 'ready' : 'error');
        } catch {
            els.status.textContent = '⚠️ Unavailable';
            els.status.className = 'ai-status error';
        }
    }

    /* ---- Load history from server -------------------------------------- */
    async function loadHistory() {
        const els = getEls();
        try {
            const res = await API.get('ai/history', { session_id: sessionId });
            const msgs = res.data || [];
            if (msgs.length === 0) return;

            // Clear the system welcome message, render history
            const systemMsg = els.chat.querySelector('.ai-msg--system');
            els.chat.innerHTML = '';
            if (systemMsg) els.chat.appendChild(systemMsg);

            msgs.forEach(m => appendMsg(m.role, m.content, m.tool_name));
            scrollBottom();
        } catch {
            // ignore — fresh session
        }
    }

    /* ---- Event bindings ------------------------------------------------ */
    function bindEvents() {
        const els = getEls();
        els.form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = els.input.value.trim();
            if (!text) return;

            appendMsg('user', text);
            els.input.value = '';
            scrollBottom();
            await sendMessage(text);
        });
    }

    /* ---- Send message -------------------------------------------------- */
    async function sendMessage(text) {
        const els = getEls();
        els.btn.disabled = true;
        const indicator = appendMsg('assistant', '⏳ Thinking...', '__typing__');

        try {
            const res = await API.post('ai/chat', {
                message: text,
                session_id: sessionId,
            });

            indicator.remove();
            const data = res.data;

            // Show the text reply
            appendMsg('assistant', data.reply || 'No reply.');

            // Show tool calls as collapsible details
            if (data.tool_calls?.length) {
                const toolsHtml = data.tool_calls.map(tc =>
                    `🔧 ${tc.name} → ${truncate(JSON.stringify(tc.result || {}), 120)}`
                ).join('\n');
                appendMsg('tool', toolsHtml, '__tool__');
            }

            // Token usage badge
            if (data.usage) {
                appendMsg('system',
                    `Token usage: ${data.usage.total_tokens || '?'} (prompt: ${data.usage.prompt_tokens || '?'}, completion: ${data.usage.completion_tokens || '?'})`,
                    '__usage__'
                );
            }

            scrollBottom();
        } catch (err) {
            indicator.remove();
            appendMsg('assistant', `❌ Error: ${err.message}`);
            scrollBottom();
        } finally {
            els.btn.disabled = false;
            els.input.focus();
        }
    }

    /* ---- DOM helpers --------------------------------------------------- */
    function appendMsg(role, text, id) {
        const els = getEls();
        const div = document.createElement('div');
        const roleClass = role === 'tool' ? 'ai-msg--tool'
            : role === 'system' ? 'ai-msg--system'
            : role === 'user' ? 'ai-msg--user'
            : 'ai-msg--assistant';

        div.className = `ai-msg ${roleClass}`;
        if (id) div.id = id;

        // Render as pre-formatted if contains newlines
        if (text.includes('\n') && role === 'tool') {
            div.innerHTML = `<pre>${escHtml(text)}</pre>`;
        } else {
            div.textContent = text;
        }

        els.chat.appendChild(div);
        return div;
    }

    function scrollBottom() {
        const els = getEls();
        requestAnimationFrame(() => {
            els.chat.scrollTop = els.chat.scrollHeight;
        });
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function truncate(s, n) {
        return s.length > n ? s.substring(0, n) + '…' : s;
    }

    return { init, checkStatus };
})();
