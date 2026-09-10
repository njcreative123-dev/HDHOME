/**
 * HDHome Live TV - HLS Player manager.
 * Uses hls.js (loaded via CDN) for M3U8 playback with native fallback.
 */
const Player = (() => {
    let hlsInstance = null;
    const modal  = document.getElementById('playerModal');
    const video  = document.getElementById('videoPlayer');
    const title  = document.getElementById('playerTitle');
    const status = document.getElementById('playerStatus');
    const backdrop = document.getElementById('playerBackdrop');
    const closeBtn = document.getElementById('playerClose');

    function showStatus(msg) {
        status.textContent = msg;
        status.classList.add('visible');
    }

    function hideStatus() {
        status.classList.remove('visible');
    }

    function destroy() {
        if (hlsInstance) {
            hlsInstance.destroy();
            hlsInstance = null;
        }
        video.pause();
        video.removeAttribute('src');
        video.load();
    }

    function open(streamUrl, channelName) {
        title.textContent = channelName || 'Live Stream';
        destroy();
        showStatus('Connecting...');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (Hls && Hls.isSupported()) {
            hlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: true,
                maxBufferLength: 30,
                maxMaxBufferLength: 60,
            });

            hlsInstance.loadSource(streamUrl);
            hlsInstance.attachMedia(video);
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                hideStatus();
                video.play().catch(() => {});
            });
            hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            showStatus('Network error — stream might be unavailable.');
                            hlsInstance.startLoad();
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            showStatus('Media error — attempting recovery...');
                            hlsInstance.recoverMediaError();
                            break;
                        default:
                            showStatus('Unable to play this stream.');
                            destroy();
                    }
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Native HLS (Safari, iOS)
            video.src = streamUrl;
            video.play().catch(() => {});
            hideStatus();
        } else {
            showStatus('HLS playback is not supported in this browser.');
        }

        // Increment view count (fire and forget)
        const card = document.querySelector('.channel-card:hover');
        if (card) {
            const id = card.dataset.id;
            if (id) API.post('channels/' + id + '/views').catch(() => {});
        }
    }

    function close() {
        destroy();
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    // Event listeners
    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });

    return { open, close };
})();
