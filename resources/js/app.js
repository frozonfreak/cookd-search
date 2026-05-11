(function initVoiceSearch() {
    const SpeechRecognition =
        window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return;

    const btn          = document.getElementById('voice-btn');
    const input        = document.getElementById('q');
    const form         = document.getElementById('search-form');
    const statusRegion = document.getElementById('voice-status');
    const iconIdle     = document.getElementById('voice-icon-idle');
    const iconStop     = document.getElementById('voice-icon-listening');
    const pulseRing    = document.getElementById('voice-pulse');

    if (!btn || !input || !form) return;

    btn.hidden = false;

    const STATE = { IDLE: 'idle', LISTENING: 'listening', ERROR: 'error' };
    let currentState = STATE.IDLE;
    let recognition  = null;
    let errorTimer   = null;

    function announce(message) {
        statusRegion.textContent = '';
        requestAnimationFrame(() => { statusRegion.textContent = message; });
    }

    function setState(next) {
        currentState      = next;
        btn.dataset.state = next;
        const listening   = next === STATE.LISTENING;
        iconIdle.classList.toggle('hidden', listening);
        iconStop.classList.toggle('hidden', !listening);
        pulseRing.classList.toggle('hidden', !listening);
        btn.setAttribute('aria-pressed', String(listening));
        btn.setAttribute('aria-label', listening ? 'Stop listening' : 'Search by voice');
        if (next === STATE.LISTENING)     announce('Listening…');
        else if (next === STATE.ERROR)    announce('Voice search unavailable. Please type your query.');
        else                              announce('');
    }

    function buildRecognition() {
        const r         = new SpeechRecognition();
        r.lang           = 'en-IN';
        r.continuous     = false;
        r.interimResults = false;
        r.maxAlternatives = 1;

        r.onresult = (e) => {
            const transcript = e.results[0][0].transcript.trim();
            if (transcript) {
                input.value = transcript;
                setTimeout(() => form.submit(), 350);
            } else {
                resetToIdle();
            }
        };

        r.onerror = (e) => {
            if (e.error === 'no-speech' || e.error === 'aborted') { resetToIdle(); return; }
            showError();
        };

        r.onend = () => {
            if (currentState === STATE.LISTENING) resetToIdle();
        };

        return r;
    }

    function startListening() {
        recognition = buildRecognition();
        recognition.start();
        setState(STATE.LISTENING);
    }

    function stopListening() {
        if (recognition) recognition.stop();
    }

    function resetToIdle() {
        setState(STATE.IDLE);
        recognition = null;
    }

    function showError() {
        setState(STATE.ERROR);
        recognition = null;
        clearTimeout(errorTimer);
        errorTimer = setTimeout(resetToIdle, 2500);
    }

    btn.addEventListener('click', () => {
        currentState === STATE.LISTENING ? stopListening() : startListening();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && currentState === STATE.LISTENING) stopListening();
    });
})();
