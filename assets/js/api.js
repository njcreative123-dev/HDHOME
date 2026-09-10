/**
 * HDHome Live TV - API client wrapper.
 * Handles CSRF tokens, error normalization, and request formatting.
 */
const API = (() => {
    const base = '/api.php';
    const csrfToken = () => window.HDHOME?.csrfToken || '';

    async function request(method, endpoint, body = null) {
        const url = `${base}?endpoint=${encodeURIComponent(endpoint)}`;
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        };
        if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(url, opts);
        let data;
        try { data = await res.json(); } catch { data = { success: false, error: { message: 'Invalid JSON response' } }; }
        if (!res.ok || !data.success) {
            const msg = data?.error?.message || `HTTP ${res.status}`;
            throw new Error(msg);
        }
        return data;
    }

    return {
        get:    (ep, params) => request('GET', ep + (params ? '?' + new URLSearchParams(params) : '')),
        post:   (ep, body)   => request('POST', ep, body),
        put:    (ep, body)   => request('PUT', ep, body),
        del:    (ep, body)   => request('DELETE', ep, body || {}),
        csrf:   csrfToken,
    };
})();
