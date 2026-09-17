<?php
/** Shared Play-mode behaviour: camera thumbnails + live timers. */
?>
<script>
(function () {
    // ── Camera thumbnails ──────────────────────────────────────────
    function hideVideo(video) {
        video.style.visibility = 'hidden';
        const ph = video.parentElement?.querySelector('.cam-offline');
        if (ph) ph.style.display = 'flex';
    }

    function attachPlayCameras() {
        document.querySelectorAll('.play-cam').forEach(video => {
            const src = video.dataset.hlsSrc;
            if (!src) return;
            video.addEventListener('error', () => hideVideo(video));
            video.addEventListener('stalled', () => hideVideo(video));
            if (window.Hls && Hls.isSupported()) {
                const hls = new Hls({ lowLatencyMode: true });
                hls.loadSource(src);
                hls.attachMedia(video);
                hls.on(Hls.Events.ERROR, (_, data) => { if (data.fatal) hideVideo(video); });
                video.muted = true;
                video.play().catch(() => hideVideo(video));
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = src;
                video.muted = true;
                video.play().catch(() => hideVideo(video));
            }
        });
    }

    function initPlayCameras() {
        if (!document.querySelector('.play-cam')) return;
        if (window.Hls) { attachPlayCameras(); return; }
        const s = document.createElement('script');
        s.src = '/assets/vendor/hls.min.js';
        s.onload = attachPlayCameras;
        document.head.appendChild(s);
    }

    window.openPlayCam = function (btn) {
        const url = btn?.dataset?.player;
        if (!url) { alert('No camera stream is linked to this table.'); return; }
        window.open(url, '_blank', 'noopener');
    };

    // ── Live timers + running amount ───────────────────────────────
    function tickPlayClocks() {
        document.querySelectorAll('.play-timer').forEach(el => {
            if (el.dataset.status !== 'active') return;
            const start  = parseInt(el.dataset.start || '0', 10);
            const paused = parseInt(el.dataset.paused || '0', 10);
            if (!start) return;

            const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - start - paused);
            el.textContent = formatDuration(elapsed);

            const card = el.closest('[data-play-card]');
            const amt  = card ? card.querySelector('.play-amount') : null;
            if (!amt) return;
            if (parseFloat(amt.dataset.fixed || '0') > 0) return; // fixed charge

            const rate = parseFloat(amt.dataset.rate || '0') || 0;
            const min  = parseFloat(amt.dataset.min || '0') || 0;
            let amount = Math.round((elapsed / 3600) * rate);
            if (elapsed > 0 && amount < min) amount = min;
            amount = Math.ceil(amount / 10) * 10;
            amt.textContent = formatCurrency(amount);
        });
    }

    function boot() {
        initPlayCameras();
        tickPlayClocks();
        setInterval(tickPlayClocks, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
