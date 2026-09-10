/**
 * HDHome Live TV - Frontend app logic.
 *
 * Handles: search debounce, category filtering, dynamic grid loading,
 * card click -> player open, and view count tracking.
 */
(function () {
    'use strict';

    /* ---- State --------------------------------------------------------- */
    const state = {
        page: 1,
        q: '',
        categoryId: null,
        loading: false,
        searchTimeout: null,
    };

    /* ---- DOM refs ------------------------------------------------------ */
    const searchInput   = document.getElementById('searchInput');
    const searchBtn     = document.getElementById('searchBtn');
    const grid          = document.getElementById('channelsGrid');
    const pagination    = document.getElementById('pagination');
    const categoriesBar = document.getElementById('categoriesBar');
    const navToggle     = document.getElementById('navToggle');
    const navLinks      = document.getElementById('navLinks');

    /* ---- Initialisation ------------------------------------------------ */
    function init() {
        bindEvents();
        renderCards(window.HDHOME?.initialData?.channels || []);
        updatePagination(window.HDHOME?.initialData);
    }

    /* ---- Event bindings ------------------------------------------------ */
    function bindEvents() {
        // Search (debounced)
        searchInput?.addEventListener('input', () => {
            clearTimeout(state.searchTimeout);
            state.searchTimeout = setTimeout(() => {
                state.q = searchInput.value.trim();
                state.page = 1;
                loadChannels();
            }, 350);
        });

        searchInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                state.q = searchInput.value.trim();
                state.page = 1;
                loadChannels();
            }
        });

        searchBtn?.addEventListener('click', () => {
            state.q = searchInput?.value?.trim() || '';
            state.page = 1;
            loadChannels();
        });

        // Category chips
        categoriesBar?.addEventListener('click', (e) => {
            const chip = e.target.closest('.chip');
            if (!chip) return;

            // Update active chip
            categoriesBar.querySelectorAll('.chip').forEach(c => c.classList.remove('chip--active'));
            chip.classList.add('chip--active');

            const catId = chip.dataset.category;
            state.categoryId = catId !== '' ? parseInt(catId, 10) : null;
            state.page = 1;
            loadChannels();
        });

        // Card clicks (delegate)
        grid?.addEventListener('click', (e) => {
            const card = e.target.closest('.channel-card');
            if (!card) return;
            const url   = card.dataset.url;
            const name  = card.querySelector('.channel-card-title')?.textContent || '';
            if (url) {
                Player.open(url, name);
            }
        });

        // Pagination clicks (delegate)
        pagination?.addEventListener('click', (e) => {
            const link = e.target.closest('.page-link');
            if (!link) return;
            e.preventDefault();
            const href = link.getAttribute('href');
            const params = new URLSearchParams(href.split('?')[1] || '');
            state.page = parseInt(params.get('page') || '1', 10);
            loadChannels();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Mobile nav toggle
        navToggle?.addEventListener('click', () => {
            navLinks?.classList.toggle('open');
        });

        // Close mobile nav on link click
        navLinks?.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => navLinks.classList.remove('open'));
        });
    }

    /* ---- Data loading -------------------------------------------------- */
    async function loadChannels() {
        if (state.loading) return;
        state.loading = true;
        showSkeleton();

        try {
            const params = {
                page: state.page,
                per: 24,
                sort: 'view_count',
                dir: 'DESC',
            };
            if (state.q) params.q = state.q;
            if (state.categoryId) params.category = state.categoryId;

            const res = await API.get('channels', params);
            renderCards(res.data || []);
            updatePagination(res.meta || {});
        } catch (err) {
            console.error('Failed to load channels:', err);
            renderCards([]);
        } finally {
            state.loading = false;
        }
    }

    /* ---- Render -------------------------------------------------------- */
    function renderCards(channels) {
        if (!grid) return;
        if (channels.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <p class="empty-icon">📺</p>
                    <p>No channels found${state.q ? ' for "' + escapeHtml(state.q) + '"' : ''}.</p>
                </div>`;
            return;
        }

        grid.innerHTML = channels.map(ch => `
            <article class="channel-card" data-id="${ch.id}" data-url="${escapeAttr(ch.stream_url)}">
                <div class="channel-card-img">
                    ${ch.logo_url
                        ? `<img src="${escapeAttr(ch.logo_url)}" alt="${escapeAttr(ch.name)}" loading="lazy" onerror="this.style.display='none'">`
                        : `<div class="channel-card-placeholder">${escapeHtml((ch.name || '??').substring(0, 2).toUpperCase())}</div>`
                    }
                    ${ch.is_featured ? '<span class="badge badge--featured">★ Featured</span>' : ''}
                    <span class="badge badge--live">● LIVE</span>
                </div>
                <div class="channel-card-body">
                    <h3 class="channel-card-title" title="${escapeAttr(ch.name)}">${escapeHtml(ch.name)}</h3>
                    <div class="channel-card-meta">
                        ${ch.category_name ? `<span class="channel-card-tag">${escapeHtml(ch.category_name)}</span>` : ''}
                        ${ch.language ? `<span class="channel-card-tag">${escapeHtml(ch.language.toUpperCase())}</span>` : ''}
                        <span class="channel-card-views">${formatNumber(ch.view_count)} views</span>
                    </div>
                </div>
            </article>
        `).join('');
    }

    function updatePagination(meta) {
        if (!pagination || !meta || (meta.pages || 0) <= 1) {
            if (pagination) pagination.innerHTML = '';
            return;
        }

        let html = '';
        if (meta.page > 1) {
            html += `<a href="?page=${meta.page - 1}" class="page-link">« Prev</a>`;
        }
        html += `<span class="page-info">Page ${meta.page} of ${meta.pages}</span>`;
        if (meta.page < meta.pages) {
            html += `<a href="?page=${meta.page + 1}" class="page-link">Next »</a>`;
        }
        pagination.innerHTML = html;
    }

    function showSkeleton() {
        if (!grid) return;
        grid.innerHTML = Array(8).fill('<div class="skeleton"></div>').join('');
    }

    /* ---- Utilities ----------------------------------------------------- */
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }

    function formatNumber(n) {
        return (n || 0).toLocaleString('en-US');
    }

    /* ---- Boot ---------------------------------------------------------- */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/* ---- PWA service worker registration ------------------------------------ */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/assets/js/sw.js').catch(() => {});
    });
}
