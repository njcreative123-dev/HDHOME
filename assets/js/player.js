/**
 * HDHome Live TV - Enhanced HLS Player v2.1
 * Features: PiP, Quality Selector, EPG, Keyboard shortcuts
 */
const Player = (() => {
    let hlsInstance = null;
    let currentChannelId = null;
    let currentChannelName = null;
    const modal  = document.getElementById('playerModal');
    const video  = document.getElementById('videoPlayer');
    const title  = document.getElementById('playerTitle');
    const status = document.getElementById('playerStatus');
    const backdrop = document.getElementById('playerBackdrop');
    const closeBtn = document.getElementById('playerClose');
    const pipBtn = document.getElementById('pipBtn');
    const pipBtnOverlay = document.getElementById('pipBtnOverlay');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const qualitySelector = document.getElementById('qualitySelector');
    const epgContainer = document.getElementById('playerEPG');

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
        currentChannelId = null;
        currentChannelName = null;
        if (qualitySelector) qualitySelector.innerHTML = '';
        if (epgContainer) epgContainer.innerHTML = '';
    }

    function open(streamUrl, channelName, channelId) {
        title.textContent = channelName || 'Live Stream';
        currentChannelName = channelName;
        currentChannelId = channelId || null;
        destroy();
        showStatus('Connecting...');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            hlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: true,
                maxBufferLength: 30,
                maxMaxBufferLength: 60,
                startLevel: -1, // Auto quality
            });

            hlsInstance.loadSource(streamUrl);
            hlsInstance.attachMedia(video);
            
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
                hideStatus();
                video.play().catch(() => {});
                
                // Build quality selector
                if (data.levels && data.levels.length > 1 && qualitySelector) {
                    qualitySelector.innerHTML = data.levels.map((l, i) => 
                        '<button class="quality-btn" data-level="' + i + '">' + (l.height ? l.height + 'p' : 'Auto') + '</button>'
                    ).join('') + '<button class="quality-btn active" data-level="-1">Auto</button>';
                    
                    qualitySelector.querySelectorAll('.quality-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            qualitySelector.querySelectorAll('.quality-btn').forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            hlsInstance.currentLevel = parseInt(btn.dataset.level);
                        });
                    });
                }
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
            video.src = streamUrl;
            video.play().catch(() => {});
            hideStatus();
        } else {
            showStatus('HLS playback is not supported in this browser.');
        }

        // Increment view count
        if (channelId) {
            API.post('channels/' + channelId + '/views').catch(() => {});
        }

        // Load EPG for channel
        if (channelId && epgContainer) {
            loadEPG(channelId);
        }

        // Track recently watched
        if (channelId && window.HDRecent) {
            HDRecent.add(channelId, channelName, '', streamUrl);
        }
    }

    async function loadEPG(channelId) {
        try {
            const res = await API.get('epg', { channel_id: channelId });
            if (res.data && res.data.programs) {
                const current = res.data.programs.find(p => p.is_live);
                if (current) {
                    epgContainer.innerHTML = '<div class="epg-now"><span class="epg-badge">NOW</span> ' + 
                        escapeHtml(current.title) + ' <span class="epg-time">' + current.time + ' - ' + current.end_time + '</span></div>';
                }
            }
        } catch (e) {}
    }

    function close() {
        destroy();
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    /* ---- Picture-in-Picture ---- */
    async function togglePiP() {
        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled && video.src) {
                await video.requestPictureInPicture();
            }
        } catch (e) {
            console.log('PiP not available');
        }
    }

    /* ---- Fullscreen ---- */
    function toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (video) {
            (video.requestFullscreen || video.webkitRequestFullscreen || video.msRequestFullscreen)?.call(video);
        }
    }

    /* ---- Event listeners ---- */
    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);
    pipBtn?.addEventListener('click', togglePiP);
    pipBtnOverlay?.addEventListener('click', togglePiP);
    fullscreenBtn?.addEventListener('click', toggleFullscreen);

    document.addEventListener('keydown', (e) => {
        if (!modal.classList.contains('open')) return;
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        switch(e.key) {
            case 'Escape': close(); break;
            case 'f': case 'F': toggleFullscreen(); break;
            case 'p': case 'P': togglePiP(); break;
            case 'm': case 'M': video.muted = !video.muted; break;
            case ' ':
                e.preventDefault();
                video.paused ? video.play() : video.pause();
                break;
            case 'ArrowUp':
                e.preventDefault();
                video.volume = Math.min(1, video.volume + 0.1);
                break;
            case 'ArrowDown':
                e.preventDefault();
                video.volume = Math.max(0, video.volume - 0.1);
                break;
        }
    });

    return { open, close, togglePiP, toggleFullscreen };
})();
