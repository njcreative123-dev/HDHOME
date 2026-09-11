/**
 * HDHome - Video Player Controller
 * Manages HLS streaming, quality selection, fullscreen, and error recovery.
 */

class StreamPlayer {
    constructor(playerId = 'main-player') {
        this.player = document.getElementById(playerId);
        this.currentChannel = null;
        this.isPlaying = false;
        this.autoHealEnabled = true;
        this.retryAttempts = 0;
        this.maxRetries = 3;

        if (this.player) {
            this.init();
        }
    }

    init() {
        this.setupEventListeners();
        this.loadChannelFromUrl();
    }

    setupEventListeners() {
        if (!this.player) return;

        // Video events
        this.player.addEventListener('play', () => {
            this.isPlaying = true;
            this.updateChannelTitle();
            this.trackView();
        });

        this.player.addEventListener('pause', () => {
            this.isPlaying = false;
        });

        this.player.addEventListener('error', (e) => {
            this.handleError(e);
        });

        this.player.addEventListener('waiting', () => {
            this.showLoadingIndicator();
        });

        this.player.addEventListener('canplay', () => {
            this.hideLoadingIndicator();
        });

        // Quality selector
        const qualitySelect = document.getElementById('quality-select');
        if (qualitySelect) {
            qualitySelect.addEventListener('change', () => {
                this.changeQuality(qualitySelect.value);
            });
        }

        // Fullscreen button
        const fsBtn = document.getElementById('btn-fullscreen');
        if (fsBtn) {
            fsBtn.addEventListener('click', () => this.toggleFullscreen());
        }
    }

    loadChannelFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const channelSlug = urlParams.get('channel');
        if (channelSlug) {
            this.loadChannel(channelSlug);
        }
    }

    async loadChannel(slug) {
        this.currentChannel = { slug };
        const watchUrl = `/watch.php?channel=${slug}`;
        if (window.location.pathname !== watchUrl) {
            window.history.pushState({ slug }, '', watchUrl);
        }

        this.showLoadingIndicator();
        this.retryAttempts = 0;

        try {
            const response = await fetch(`/api/channels.php?action=stream&id_check=1&slug=${encodeURIComponent(slug)}`);
            // Note: The actual stream URL is rendered server-side in the page.
            // The JS only handles fallback/retry logic and UI updates.
            this.hideLoadingIndicator();
        } catch (err) {
            this.handleError(err);
        }
    }

    handleError(error) {
        console.error('Player error:', error);

        if (this.retryAttempts < this.maxRetries) {
            this.retryAttempts++;
            setTimeout(() => {
                this.retryStream();
            }, 2000 * this.retryAttempts);
        } else {
            this.showErrorScreen('Unable to load stream after multiple attempts. Please try again later.');
        }
    }

    retryStream() {
        if (this.player && this.currentChannel?.slug) {
            const source = this.player.querySelector('source');
            if (source) {
                const currentSrc = source.src;
                source.src = '';
                source.src = currentSrc;
                this.player.load();
                this.player.play().catch(e => console.error('Retry play failed:', e));
            }
        }
    }

    changeQuality(quality) {
        // Quality change is handled by the HLS manifest (master playlist).
        // This method can be extended to switch between different stream variants.
        const qualityLabel = document.getElementById('current-quality');
        if (qualityLabel) {
            qualityLabel.textContent = quality;
        }
    }

    toggleFullscreen() {
        if (!this.player) return;

        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            this.player.requestFullscreen();
        }
    }

    showLoadingIndicator() {
        const wrapper = this.player?.closest('.player-wrapper');
        if (wrapper) {
            wrapper.classList.add('loading');
        }
    }

    hideLoadingIndicator() {
        const wrapper = this.player?.closest('.player-wrapper');
        if (wrapper) {
            wrapper.classList.remove('loading');
        }
    }

    showErrorScreen(message) {
        const wrapper = this.player?.closest('.player-wrapper');
        if (wrapper) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'stream-error-overlay';
            errorDiv.innerHTML = `
                <div class="stream-error-content">
                    <div class="error-icon">⚠️</div>
                    <h3>Stream Unavailable</h3>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="location.reload()">Retry</button>
                </div>
            `;
            wrapper.appendChild(errorDiv);
        }
    }

    updateChannelTitle() {
        const titleEl = document.querySelector('.channel-title');
        if (titleEl && this.currentChannel?.slug) {
            titleEl.textContent = this.currentChannel.slug;
        }
    }

    trackView() {
        if (this.currentChannel?.id && this.retryAttempts === 0) {
            // View tracking is handled server-side via header include.
            // This prevents double-counting from bot/spam requests.
        }
    }

    // Static method to get a player instance
    static getInstance() {
        if (!window.__streamPlayerInstance) {
            window.__streamPlayerInstance = new StreamPlayer();
        }
        return window.__streamPlayerInstance;
    }
}

// Initialize player when page loads
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('main-player')) {
        window.streamPlayer = new StreamPlayer();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    const player = window.streamPlayer;
    if (!player || !player.player) return;

    // Space bar for play/pause
    if (e.code === 'Space' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        if (player.isPlaying) {
            player.player.pause();
        } else {
            player.player.play();
        }
    }

    // F for fullscreen
    if (e.key === 'f' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        player.toggleFullscreen();
    }

    // M for mute
    if (e.key === 'm' && e.target.tagName !== 'INPUT') {
        player.player.muted = !player.player.muted;
    }
});
