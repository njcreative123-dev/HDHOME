/**
 * HDHome Live TV - Admin panel controller.
 *
 * Drives: login flow, dashboard stats, channel CRUD, categories CRUD,
 * M3U import, settings management, maintenance tools, and sidebar navigation.
 */
(function () {
    'use strict';

    /* ---- State --------------------------------------------------------- */
    const S = { chPage: 1, chSearch: '' };

    /* ---- DOM shortcuts ------------------------------------------------- */
    const $ = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => [...(ctx || document).querySelectorAll(sel)];

    /* =================================================================
     * AUTH
     * ================================================================= */
    const loginForm  = $('#loginForm');
    const loginView  = $('#loginView');
    const adminLayout = $('#adminLayout');
    const loginError = $('#loginError');
    const logoutBtn  = $('#logoutBtn');

    async function tryRestoreSession() {
        try {
            const res = await API.get('auth/me');
            if (res?.data?.logged_in) showAdmin();
        } catch {
            showLogin();
        }
    }

    function showLogin() {
        loginView.style.display = '';
        adminLayout.style.display = 'none';
    }

    function showAdmin() {
        loginView.style.display = 'none';
        adminLayout.style.display = '';
        initAdmin();
    }

    loginForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginError.textContent = '';
        const btn = $('#loginBtn');
        btn.disabled = true;
        btn.textContent = 'Signing in...';

        try {
            await API.post('auth/login', {
                username: $('#loginUser').value,
                password: $('#loginPass').value,
            });
            showAdmin();
        } catch (err) {
            loginError.textContent = err.message;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Sign In';
        }
    });

    logoutBtn?.addEventListener('click', async () => {
        await API.post('auth/logout').catch(() => {});
        window.location.reload();
    });

    /* =================================================================
     * SIDEBAR NAVIGATION
     * ================================================================= */
    function switchView(viewName) {
        $$('.sidebar-link').forEach(l => l.classList.toggle('active', l.dataset.view === viewName));
        $$('.admin-view').forEach(v => v.style.display = 'none');
        const target = $(`#view-${viewName}`);
        if (target) target.style.display = '';
        const titles = {
            dashboard: 'Dashboard', channels: 'Channels', categories: 'Categories',
            ai: 'AI Admin Bot', settings: 'Settings', maintenance: 'Maintenance',
        };
        $('#pageTitle').textContent = titles[viewName] || viewName;
    }

    $$('.sidebar-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const v = link.dataset.view;
            switchView(v);
            history.replaceState(null, '', '#' + v);
            // Load data on view switch
            if (v === 'dashboard') loadStats();
            if (v === 'channels') loadChannels();
            if (v === 'categories') loadCategories();
            if (v === 'ai') AIAgent.init();
            if (v === 'settings') loadSettings();
        });
    });

    const sidebarToggle = $('#sidebarToggle');
    sidebarToggle?.addEventListener('click', () => {
        $('#adminSidebar').classList.toggle('open');
    });

    /* =================================================================
     * DASHBOARD
     * ================================================================= */
    async function loadStats() {
        try {
            const res = await API.get('stats/overview');
            const d = res.data;
            $('#statTotal').textContent = d.channels?.total ?? '—';
            $('#statActive').textContent = d.channels?.active ?? '—';
            $('#statViews').textContent = Number(d.channels?.views ?? 0).toLocaleString();
            $('#statDbSize').textContent = (d.database?.size_mb ?? 0) + ' MB';
            $('#statCategories').textContent = d.categories?.total ?? '—';
            $('#statAi').textContent = d.ai?.configured ? '✅ Active' : '⚠️ Off';

            const logsEl = $('#recentLogs');
            if (d.recent_logs?.length) {
                logsEl.innerHTML = '<table class="table"><thead><tr><th>Endpoint</th><th>Method</th><th>Status</th><th>Time</th></tr></thead><tbody>' +
                    d.recent_logs.map(l => `<tr><td>${esc(l.endpoint)}</td><td>${esc(l.method)}</td><td>${l.status_code}</td><td>${l.response_time_ms}ms</td></tr>`).join('') +
                    '</tbody></table>';
            } else {
                logsEl.innerHTML = '<p class="text-muted">No recent API activity.</p>';
            }
        } catch (err) {
            console.error('Stats load failed:', err);
        }
    }

    /* =================================================================
     * CHANNELS
     * ================================================================= */
    async function loadChannels(page) {
        page = page || 1;
        S.chPage = page;
        const tbody = $('#channelTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center">Loading...</td></tr>';

        try {
            const params = { page, per: 15, sort: 'id', dir: 'DESC' };
            if (S.chSearch) params.q = S.chSearch;
            const res = await API.get('channels', params);
            const channels = res.data || [];

            if (channels.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center">No channels found.</td></tr>';
                $('#chPagination').innerHTML = '';
                return;
            }

            tbody.innerHTML = channels.map(ch => `
                <tr>
                    <td>${ch.id}</td>
                    <td title="${esc(ch.stream_url)}">${esc(ch.name)}</td>
                    <td>${esc(ch.category_name || '—')}</td>
                    <td>${esc(ch.language?.toUpperCase() || '—')}</td>
                    <td>${Number(ch.view_count || 0).toLocaleString()}</td>
                    <td><span class="status-badge status-badge--${ch.is_active ? 'active' : 'inactive'}">${ch.is_active ? 'Active' : 'Off'}</span></td>
                    <td class="actions">
                        <button class="btn btn--ghost btn--sm" onclick="Admin.editChannel(${ch.id})">✏️</button>
                        <button class="btn btn--ghost btn--sm" onclick="Admin.toggleChannel(${ch.id},'is_active')">${ch.is_active ? '⏸' : '▶️'}</button>
                        <button class="btn btn--ghost btn--sm" onclick="Admin.toggleChannel(${ch.id},'is_featured')">${ch.is_featured ? '★' : '☆'}</button>
                        <button class="btn btn--ghost btn--sm" onclick="Admin.deleteChannel(${ch.id},'${esc(ch.name)}')" style="color:var(--red)">🗑</button>
                    </td>
                </tr>
            `).join('');

            // Pagination
            const meta = res.meta || {};
            let phtml = '';
            if (meta.page > 1) phtml += `<button class="btn btn--ghost btn--sm" onclick="Admin.loadCh(${meta.page - 1})">« Prev</button>`;
            phtml += `<span class="page-info">Page ${meta.page || 1} / ${meta.pages || 1}</span>`;
            if (meta.page < meta.pages) phtml += `<button class="btn btn--ghost btn--sm" onclick="Admin.loadCh(${meta.page + 1})">Next »</button>`;
            $('#chPagination').innerHTML = phtml;
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-muted">Error: ${esc(err.message)}</td></tr>`;
        }
    }

    $('#chSearch')?.addEventListener('input', debounce(() => {
        S.chSearch = $('#chSearch').value.trim();
        loadChannels(1);
    }, 400));

    function openChannelModal(title, formHtml, editId) {
        $('#modalTitle').textContent = title;
        $('#modalBody').innerHTML = `
            <form id="channelForm">
                ${formHtml}
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="csrf_token" value="${API.csrf()}">
                <div class="btn-group" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn--ghost" onclick="Admin.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn--primary">Save Channel</button>
                </div>
            </form>`;
        $('#adminModal').style.display = '';
        $('#channelForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const body = {
                name: fd.get('name'),
                stream_url: fd.get('stream_url'),
                description: fd.get('description') || '',
                logo_url: fd.get('logo_url') || '',
                category_id: fd.get('category_id') ? parseInt(fd.get('category_id')) : null,
                country: fd.get('country') || '',
                language: fd.get('language') || '',
                is_active: fd.get('is_active') ? 1 : 0,
                is_featured: fd.get('is_featured') ? 1 : 0,
            };
            try {
                if (editId) {
                    await API.put('channels/' + editId, body);
                    toast('Channel updated!', 'success');
                } else {
                    await API.post('channels', body);
                    toast('Channel created!', 'success');
                }
                Admin.closeModal();
                loadChannels(S.chPage);
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }

    async function channelFormHtml(data) {
        let catOpts = '<option value="">— None —</option>';
        try {
            const res = await API.get('categories');
            catOpts = (res.data || []).map(c => `<option value="${c.id}" ${data?.category_id == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
        } catch {}

        return `
            <div class="form-group"><label>Channel Name *</label><input class="form-control" name="name" required value="${esc(data?.name || '')}"></div>
            <div class="form-group"><label>Stream URL (M3U8) *</label><input class="form-control" name="stream_url" required value="${esc(data?.stream_url || '')}" placeholder="https://example.com/stream.m3u8"></div>
            <div class="form-group"><label>Description</label><textarea class="form-control" name="description">${esc(data?.description || '')}</textarea></div>
            <div class="form-group"><label>Logo URL</label><input class="form-control" name="logo_url" value="${esc(data?.logo_url || '')}"></div>
            <div class="form-group"><label>Category</label><select class="form-control" name="category_id"><option value="">— Select —</option>${catOpts}</select></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group"><label>Country</label><input class="form-control" name="country" value="${esc(data?.country || '')}" placeholder="US"></div>
                <div class="form-group"><label>Language</label><input class="form-control" name="language" value="${esc(data?.language || '')}" placeholder="en"></div>
            </div>
            <div style="display:flex;gap:16px">
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="is_active" ${data?.is_active != 0 ? 'checked' : ''}> Active</label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="is_featured" ${data?.is_featured ? 'checked' : ''}> Featured</label>
            </div>`;
    }

    $('#btnAddChannel')?.addEventListener('click', async () => {
        const html = await channelFormHtml();
        openChannelModal('Add New Channel', html);
    });

    // M3U Import
    $('#btnImportM3u')?.addEventListener('click', () => {
        $('#modalTitle').textContent = 'Import M3U / M3U8 Playlist';
        $('#modalBody').innerHTML = `
            <form id="importForm">
                <div class="form-group"><label>Playlist URL</label><input class="form-control" name="url" placeholder="https://example.com/playlist.m3u8"></div>
                <p class="text-muted" style="text-align:center;margin:8px 0">— OR paste content —</p>
                <div class="form-group"><label>Paste M3U content</label><textarea class="form-control" name="content" rows="8" placeholder="#EXTM3U\n#EXTINF:-1 tvg-name=&quot;Channel&quot;..."></textarea></div>
                <div class="btn-group" style="justify-content:flex-end">
                    <button type="button" class="btn btn--ghost" onclick="Admin.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn--primary">Import</button>
                </div>
            </form>`;
        $('#adminModal').style.display = '';
        $('#importForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const body = {};
            if (fd.get('url')) body.url = fd.get('url');
            if (fd.get('content')) body.content = fd.get('content');
            try {
                const res = await API.post('channels/import', body);
                const d = res.data;
                toast(`Imported: ${d.imported} channels, Skipped: ${d.skipped}`, 'success');
                Admin.closeModal();
                loadChannels(1);
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    });

    /* =================================================================
     * CATEGORIES
     * ================================================================= */
    async function loadCategories() {
        try {
            const res = await API.get('categories');
            const cats = res.data || [];
            $('#categoryList').innerHTML = cats.length ? cats.map(c => `
                <div class="category-item">
                    <div>
                        <span class="category-name">${esc(c.name)}</span>
                        <span class="category-count">${c.channel_count || 0} channels</span>
                    </div>
                    <div class="actions">
                        <button class="btn btn--ghost btn--sm" onclick="Admin.deleteCategory(${c.id},'${esc(c.name)}')" style="color:var(--red)">🗑</button>
                    </div>
                </div>
            `).join('') : '<p class="text-muted">No categories yet.</p>';
        } catch (err) {
            $('#categoryList').innerHTML = `<p class="text-muted">Error: ${esc(err.message)}</p>`;
        }
    }

    $('#btnAddCategory')?.addEventListener('click', () => {
        $('#modalTitle').textContent = 'Add Category';
        $('#modalBody').innerHTML = `
            <form id="catForm">
                <div class="form-group"><label>Name</label><input class="form-control" name="name" required placeholder="Sports, News, etc."></div>
                <div class="form-group"><label>Sort Order</label><input class="form-control" name="sort_order" type="number" value="0"></div>
                <div class="btn-group" style="justify-content:flex-end">
                    <button type="button" class="btn btn--ghost" onclick="Admin.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn--primary">Create</button>
                </div>
            </form>`;
        $('#adminModal').style.display = '';
        $('#catForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.post('categories', {
                    name: fd.get('name'),
                    sort_order: parseInt(fd.get('sort_order') || '0'),
                });
                toast('Category created!', 'success');
                Admin.closeModal();
                loadCategories();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    });

    /* =================================================================
     * SETTINGS
     * ================================================================= */
    async function loadSettings() {
        try {
            const res = await API.get('settings');
            const d = res.data || {};
            $('#settAiModel').value  = d.ai_model || '';
            $('#settAiKey').value    = d.ai_api_key || '';
            $('#settRetention').value = d.log_retention_days || 30;
            $('#settSiteName').value = d.site_name || '';
        } catch {}
    }

    $('#settingsForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = {};
        const v = (k) => fd.get(k);
        if (v('ai_model')) body.ai_model = v('ai_model');
        if (v('ai_api_key')) body.ai_api_key = v('ai_api_key');
        if (v('log_retention_days')) body.log_retention_days = v('log_retention_days');
        if (v('site_name')) body.site_name = v('site_name');
        try {
            await API.post('settings', body);
            toast('Settings saved!', 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    /* =================================================================
     * MAINTENANCE
     * ================================================================= */
    $('#btnCleanLogs')?.addEventListener('click', async () => {
        try {
            const res = await API.post('logs/clean', {});
            $('#cleanupOutput').style.display = '';
            $('#cleanupOutput').textContent = JSON.stringify(res.data, null, 2);
            toast('Logs cleaned!', 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    $('#btnGetStats')?.addEventListener('click', async () => {
        try {
            const res = await API.get('stats/overview');
            $('#cleanupOutput').style.display = '';
            $('#cleanupOutput').textContent = JSON.stringify(res.data.logs, null, 2);
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    $('#btnHealthCheck')?.addEventListener('click', async () => {
        try {
            const res = await API.get('system/health');
            $('#healthOutput').style.display = '';
            $('#healthOutput').textContent = JSON.stringify(res.data, null, 2);
        } catch (err) {
            $('#healthOutput').style.display = '';
            $('#healthOutput').textContent = 'Error: ' + err.message;
        }
    });

    /* =================================================================
     * PUBLIC ACTIONS (called from inline onclick)
     * ================================================================= */
    window.Admin = {
        loadCh: loadChannels,

        async editChannel(id) {
            try {
                const res = await API.get('channels/' + id);
                const ch = res.data;
                const html = await channelFormHtml(ch);
                openChannelModal('Edit Channel', html, id);
            } catch (err) {
                toast(err.message, 'error');
            }
        },

        async toggleChannel(id, field) {
            try {
                const method = field === 'is_active' ? 'POST' : 'PUT';
                await API.post('channels/' + id + '/toggle', { field });
                toast('Updated!', 'success');
                loadChannels(S.chPage);
            } catch (err) {
                // Try alternative: PUT update
                try {
                    const res = await API.get('channels/' + id);
                    const ch = res.data;
                    await API.put('channels/' + id, { [field]: ch[field] ? 0 : 1 });
                    toast('Updated!', 'success');
                    loadChannels(S.chPage);
                } catch (e2) {
                    toast(e2.message, 'error');
                }
            }
        },

        async deleteChannel(id, name) {
            if (!confirm(`Delete channel "${name}"? This cannot be undone.`)) return;
            try {
                await API.del('channels/' + id);
                toast('Channel deleted.', 'success');
                loadChannels(S.chPage);
            } catch (err) {
                toast(err.message, 'error');
            }
        },

        async deleteCategory(id, name) {
            if (!confirm(`Delete category "${name}"? Channels under it will become uncategorized.`)) return;
            try {
                await API.del('categories/' + id);
                toast('Category deleted.', 'success');
                loadCategories();
            } catch (err) {
                toast(err.message, 'error');
            }
        },

        closeModal() {
            $('#adminModal').style.display = 'none';
        },
    };

    /* =================================================================
     * UTILITIES
     * ================================================================= */
    function esc(s) { return (s ?? '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    window.toast = function (msg, type = 'info') {
        const c = document.getElementById('toastContainer');
        if (!c) return;
        const el = document.createElement('div');
        el.className = `toast toast--${type}`;
        el.textContent = msg;
        c.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    };

    // Close modal on backdrop / close button clicks
    $('#modalClose')?.addEventListener('click', window.Admin.closeModal);
    $('.modal-backdrop')?.addEventListener('click', window.Admin.closeModal);

    /* =================================================================
     * INIT
     * ================================================================= */
    function initAdmin() {
        // Determine initial view from URL hash
        const hash = location.hash.replace('#', '') || 'dashboard';
        switchView(hash);
        if (hash === 'dashboard') loadStats();
        if (hash === 'channels') loadChannels();
        if (hash === 'categories') loadCategories();
        if (hash === 'settings') loadSettings();
    }

    // Boot
    tryRestoreSession();
})();
