/**
 * HDHome - Favorites & Recently Watched (localStorage-based)
 * No DB needed - works offline, instant, zero server load
 */
(function() {
    'use strict';

    const FAV_KEY = 'hdhome_favorites';
    const RECENT_KEY = 'hdhome_recent';
    const MAX_RECENT = 20;

    /* ---- Storage helpers ---- */
    function getJSON(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key)) || fallback; }
        catch { return fallback; }
    }
    function setJSON(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch {}
    }

    /* ---- Favorites ---- */
    window.HDFavorites = {
        getAll() { return getJSON(FAV_KEY, []); },
        isFav(id) { return this.getAll().includes(id); },
        toggle(id) {
            let favs = this.getAll();
            if (favs.includes(id)) {
                favs = favs.filter(f => f !== id);
            } else {
                favs.unshift(id);
            }
            setJSON(FAV_KEY, favs);
            this.updateUI(id);
            return favs.includes(id);
        },
        count() { return this.getAll().length; },
        updateUI(id) {
            document.querySelectorAll(`.fav-btn[data-id="${id}"]`).forEach(btn => {
                btn.classList.toggle('fav-active', this.isFav(id));
                btn.innerHTML = this.isFav(id) ? '❤️' : '🤍';
            });
        },
        initAll() {
            document.querySelectorAll('.fav-btn').forEach(btn => {
                const id = parseInt(btn.dataset.id);
                btn.innerHTML = this.isFav(id) ? '❤️' : '🤍';
                btn.classList.toggle('fav-active', this.isFav(id));
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isFav = this.toggle(id);
                    // Toast notification
                    if (window.HDToast) {
                        HDToast.show(isFav ? '❤️ Added to favorites' : '🤍 Removed from favorites');
                    }
                });
            });
        }
    };

    /* ---- Recently Watched ---- */
    window HDRecent = {
        getAll() { return getJSON(RECENT_KEY, []); },
        add(id, name, logo, url) {
            let recent = this.getAll().filter(r => r.id !== id);
            recent.unshift({ id, name, logo, url, time: Date.now() });
            if (recent.length > MAX_RECENT) recent = recent.slice(0, MAX_RECENT);
            setJSON(RECENT_KEY, recent);
        },
        clear() { setJSON(RECENT_KEY, []); },
        render(container) {
            if (!container) return;
            const recent = this.getAll().slice(0, 8);
            if (recent.length === 0) {
                container.innerHTML = '<p class="empty-text">No recently watched channels</p>';
                return;
            }
            container.innerHTML = recent.map(r => `
                <div class="recent-card" data-url="${escapeAttr(r.url)}" data-name="${escapeAttr(r.name)}">
                    ${r.logo ? `<img src="${escapeAttr(r.logo)}" alt="" loading="lazy">` : `<div class="recent-placeholder">${(r.name||'?').substring(0,2).toUpperCase()}</div>`}
                    <span class="recent-name">${escapeHtml(r.name)}</span>
                    <span class="recent-time">${timeAgo(r.time)}</span>
                </div>
            `).join('');
        }
    };

    /* ---- Toast utility ---- */
    window.HDToast = {
        show(msg, duration) {
            duration = duration || 2500;
            let t = document.getElementById('hdToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'hdToast';
                t.className = 'hd-toast';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.classList.add('show');
            clearTimeout(t._timer);
            t._timer = setTimeout(() => t.classList.remove('show'), duration);
        }
    };

    /* ---- Keyboard Shortcuts ---- */
    document.addEventListener('keydown', (e) => {
        // Ignore if typing in input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        switch(e.key) {
            case '/':
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
                break;
            case 'f':
                if (!e.ctrlKey && !e.metaKey) {
                    // Toggle fullscreen player
                    const v = document.querySelector('video');
                    if (v) {
                        document.fullscreenElement ? document.exitFullscreen() : v.requestFullscreen?.();
                    }
                }
                break;
            case 'Escape':
                if (window.Player && typeof Player.close === 'function') {
                    Player.close();
                }
                break;
            case 'p':
                // Picture-in-Picture
                const vid = document.querySelector('video');
                if (vid && document.pictureInPictureEnabled) {
                    vid.requestPictureInPicture?.().catch(() => {});
                }
                break;
            case 'm':
                // Mute/unmute
                const video = document.querySelector('video');
                if (video) video.muted = !video.muted;
                break;
        }
    });

    /* ---- Helpers ---- */
    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
    function escapeAttr(s) { return (s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
    function timeAgo(ts) {
        const diff = Date.now() - ts;
        if (diff < 60000) return 'just now';
        if (diff < 3600000) return Math.floor(diff/60000) + 'm ago';
        if (diff < 86400000) return Math.floor(diff/3600000) + 'h ago';
        return Math.floor(diff/86400000) + 'd ago';
    }
})();
