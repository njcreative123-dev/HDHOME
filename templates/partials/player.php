<div class="player-modal" id="playerModal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Video Player">
    <div class="player-backdrop" id="playerBackdrop"></div>
    <div class="player-container">
        <div class="player-header">
            <h3 class="player-title" id="playerTitle"></h3>
            <div class="player-controls-top">
                <button class="player-btn" id="pipBtn" title="Picture in Picture (P)">📐</button>
                <button class="player-btn" id="fullscreenBtn" title="Fullscreen (F)">⛶</button>
                <button class="player-close" id="playerClose" aria-label="Close player">&times;</button>
            </div>
        </div>
        <div class="player-wrap">
            <video id="videoPlayer" controls playsinline></video>
            <div id="qualitySelector"></div>
            <button class="pip-btn" id="pipBtnOverlay" title="Picture-in-Picture">📐 PiP</button>
            <div class="player-status" id="playerStatus"></div>
        </div>
        <div class="player-epg" id="playerEPG"></div>
    </div>
</div>
