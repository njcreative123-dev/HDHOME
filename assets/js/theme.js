/**
 * HDHome - Theme Toggle (Dark/Light) + Share + PiP
 */
(function() {
    'use strict';

    const THEME_KEY = 'hdhome_theme';

    /* ---- Theme Toggle ---- */
    window HDTheme = {
        get() { return localStorage.getItem(THEME_KEY) || 'dark'; },
        set(theme) {
            localStorage.setItem(THEME_KEY, theme);
            document.documentElement.setAttribute('data-theme', theme);
            const btn = document.getElementById('themeToggle');
            if (btn) btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
        },
        toggle() {
            this.set(this.get() === 'dark' ? 'light' : 'dark');
        },
        init() {
            const saved = this.get();
            document.documentElement.setAttribute('data-theme', saved);
            const btn = document.getElementById('themeToggle');
            if (btn) {
                btn.innerHTML = saved === 'dark' ? '☀️' : '🌙';
                btn.addEventListener('click', () => this.toggle());
            }
        }
    };

    /* ---- Share Channel ---- */
    window.HDShare = {
        async share(name, url) {
            const shareData = {
                title: name + ' - HDHome Live TV',
                text: 'Watch ' + name + ' live on HDHome!',
                url: window.location.origin
            };
            if (navigator.share) {
                try { await navigator.share(shareData); } catch {}
            } else {
                // Fallback: copy to clipboard
                const text = shareData.text + ' ' + shareData.url;
                await navigator.clipboard.writeText(text);
                if (window.HDToast) HDToast.show('📋 Link copied!');
            }
        }
    };

    /* ---- Picture-in-Picture ---- */
    window.HDPiP = {
        async toggle() {
            const vid = document.querySelector('video');
            if (!vid) return;
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled) {
                try { await vid.requestPictureInPicture(); } catch {}
            }
        }
    };

    /* ---- Stream Quality Selector ---- */
    window.HDQuality = {
        levels: [],
        current: -1,
        set(levels) {
            this.levels = levels || [];
            this.render();
        },
        render() {
            const container = document.getElementById('qualitySelector');
            if (!container || this.levels.length < 2) {
                if (container) container.innerHTML = '';
                return;
            }
            container.innerHTML = this.levels.map((l, i) => 
                `<button class="quality-btn ${i === this.current ? 'active' : ''}" data-level="${i}">${l.height ? l.height + 'p' : 'Auto'}</button>`
            ).join('');
        },
        select(level) {
            this.current = level;
            if (window.Player && Player.setQuality) Player.setQuality(level);
            this.render();
        }
    };

    /* ---- Init on DOM ready ---- */
    document.addEventListener('DOMContentLoaded', () => {
        HDTheme.init();
    });
})();
