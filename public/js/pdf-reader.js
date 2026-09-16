/**
 * In-page reader for the secure viewer.
 *
 * Renders the watermarked PDF onto <canvas> with PDF.js, so a student
 * reads inside our shell: the browser's own PDF viewer — and its
 * download, print and share menu — never appears, and pinch-zoom works
 * because the paper is ordinary DOM instead of a plugin.
 *
 * Progressive enhancement. Without JavaScript, or if the library is
 * missing, the server-rendered "open the document" card stays and the
 * student still gets their paper through viewer.open (a fresh signed
 * URL), so this file can only ever improve the page. Every failure lands
 * back on that card rather than on a blank screen.
 *
 * Configuration comes from data-* attributes on #pdf-reader:
 *   data-document  signed URL for this session's document
 *   data-refresh   JSON endpoint that mints a fresh signed URL
 *   data-worker    PDF.js worker
 */
(function () {
    'use strict';

    var viewer = document.getElementById('pdf-reader');
    if (!viewer || !window.pdfjsLib) return;

    var fullEl = document.getElementById('pdf-fullscreen');
    var fallback = document.getElementById('pdf-fallback');
    var loadingEl = document.getElementById('pdf-loading');
    var loadingTextEl = document.getElementById('pdf-loading-text');
    var errorEl = document.getElementById('pdf-error');
    var errorTextEl = document.getElementById('pdf-error-text');
    var retryEl = document.getElementById('pdf-retry');

    var pagesEl = viewer.querySelector('[data-pdf-pages]');
    var statusEl = viewer.querySelector('[data-pdf-status]');
    var prevEl = viewer.querySelector('[data-pdf-prev]');
    var nextEl = viewer.querySelector('[data-pdf-next]');
    var zoomInEl = viewer.querySelector('[data-pdf-zoom-in]');
    var zoomOutEl = viewer.querySelector('[data-pdf-zoom-out]');
    var zoomResetEl = viewer.querySelector('[data-pdf-zoom-reset]');
    var zoomLabelEl = viewer.querySelector('[data-pdf-zoom-label]');

    var documentUrl = viewer.getAttribute('data-document');
    var refreshUrl = viewer.getAttribute('data-refresh');

    var ZOOM_STEPS = [0.75, 1, 1.25, 1.5, 2, 3];

    var doc = null;          // PDFDocumentProxy
    var observer = null;     // IntersectionObserver over the page boxes
    var zoom = 1;            // multiplier on top of fit-width
    var fit = 1;             // container width / page width, remeasured on resize
    var placeholders = [];   // [{ el, base, rendered }]
    var tasks = {};          // page number -> render in flight
    var current = 1;         // page at the top of the viewport
    var refreshed = false;   // one silent URL re-mint per page load
    var scrollQueued = false;

    window.pdfjsLib.GlobalWorkerOptions.workerSrc = viewer.getAttribute('data-worker');

    start();

    function start() {
        // Take the fallback card down while we try: it is the no-JS /
        // failed-load path, and flashing "your browser can't display
        // PDFs" at a student whose reader is about to work is worse than
        // a second of spinner. Every failure restores it.
        hide(fallback);
        hide(errorEl);
        hide(viewer);
        loading(true);
        open();
    }

    function open() {
        window.pdfjsLib.getDocument({ url: documentUrl }).promise
            .then(loaded)
            .catch(failed);
    }

    function loaded(pdf) {
        doc = pdf;

        // Reveal first, measure second: a hidden element has no width, so
        // the fit-width scale would come out as zero and every page box
        // would be sized to nothing.
        hide(fallback);
        show(viewer);
        if (fullEl && canGoFullscreen()) show(fullEl);

        layout().then(function () {
            if ('IntersectionObserver' in window) {
                // Root is the viewport, not the document area: the page is
                // what scrolls, and in full screen the reader fills that
                // same viewport, so one observer covers both.
                observer = new IntersectionObserver(onIntersect, { root: null, rootMargin: '120% 0px' });
                placeholders.forEach(function (entry) { observer.observe(entry.el); });
            } else {
                placeholders.forEach(render);
            }

            loading(false);
            measureBar();
            updateStatus();
        }).catch(showError);
    }

    // ── Pages ────────────────────────────────────────────────────────

    /** A placeholder per page, sized so the scrollbar is honest before pixels arrive. */
    function layout() {
        pagesEl.innerHTML = '';
        placeholders = [];

        var chain = Promise.resolve();
        for (var n = 1; n <= doc.numPages; n++) {
            chain = chain.then(step(n));
        }

        return chain.then(measure);
    }

    function step(n) {
        return function () {
            return doc.getPage(n).then(function (page) {
                var holder = document.createElement('div');
                holder.className = 'pdf-page';
                holder.setAttribute('data-page', String(n));
                holder.setAttribute('role', 'img');
                holder.setAttribute('aria-label', 'Page ' + n);
                holder.appendChild(placeholderLabel(n));
                pagesEl.appendChild(holder);

                var entry = { el: holder, base: page.getViewport({ scale: 1 }), rendered: false };
                placeholders.push(entry);
                size(entry);
            });
        };
    }

    function placeholderLabel(n) {
        var label = document.createElement('span');
        label.className = 'pdf-page-label';
        label.textContent = String(n);

        return label;
    }

    /** Fit-width scale, measured from the scroll container's content box. */
    function measure() {
        if (!placeholders.length) return;

        var styles = window.getComputedStyle(pagesEl);
        var inner = pagesEl.clientWidth
            - parseFloat(styles.paddingLeft || 0)
            - parseFloat(styles.paddingRight || 0);

        if (!(inner > 0)) {
            // Not laid out yet (a hidden ancestor, or a first paint that
            // has not happened). Retry next frame instead of sizing every
            // page to zero — bounded, so a permanently hidden reader can
            // never spin here.
            measure.retries = (measure.retries || 0) + 1;
            if (measure.retries <= 30) window.requestAnimationFrame(measure);

            return;
        }
        measure.retries = 0;

        var next = inner / placeholders[0].base.width;
        if (next === fit) {
            placeholders.forEach(size);

            return;
        }

        fit = next;
        placeholders.forEach(size);

        // Anything already drawn is at the old scale; drop it and let the
        // observer redraw what is on screen.
        reobserve();
    }

    /** Force the observer to re-report every page, so cleared canvases redraw. */
    function reobserve() {
        if (!observer) return;

        placeholders.forEach(function (entry) {
            entry.rendered = false;
            var canvas = entry.el.querySelector('canvas');
            if (canvas) canvas.remove();
            observer.unobserve(entry.el);
            observer.observe(entry.el);
        });
    }

    /** Give a page its CSS box for the current fit + zoom. */
    function size(entry) {
        var width = Math.round(entry.base.width * fit * zoom);
        var height = Math.round(entry.base.height * fit * zoom);
        entry.el.style.width = width + 'px';
        entry.el.style.height = height + 'px';

        var canvas = entry.el.querySelector('canvas');
        if (canvas) {
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
        }
    }

    function onIntersect(entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            var index = Number(entry.target.getAttribute('data-page')) - 1;
            if (placeholders[index]) render(placeholders[index]);
        });
    }

    /** Draw one page at device pixel ratio, so the text is sharp on phones. */
    function render(entry) {
        if (!entry || entry.rendered) return;

        var n = Number(entry.el.getAttribute('data-page'));
        if (tasks[n]) return;

        var ratio = Math.min(window.devicePixelRatio || 1, 2);

        tasks[n] = doc.getPage(n).then(function (page) {
            var viewport = page.getViewport({ scale: fit * zoom * ratio });
            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = Math.round(entry.base.width * fit * zoom) + 'px';
            canvas.style.height = Math.round(entry.base.height * fit * zoom) + 'px';

            var context = canvas.getContext('2d', { alpha: false });
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);

            return page.render({ canvasContext: context, viewport: viewport }).promise
                .then(function () {
                    var old = entry.el.querySelector('canvas');
                    if (old) old.remove();
                    entry.el.appendChild(canvas);
                    entry.rendered = true;
                    var label = entry.el.querySelector('.pdf-page-label');
                    if (label) label.remove();
                })
                .catch(function (error) {
                    // Cancelled renders are a normal consequence of zooming.
                    if (!error || error.name !== 'RenderingCancelledException') throw error;
                });
        }).then(function () {
            delete tasks[n];
        }, function (error) {
            delete tasks[n];
            throw error;
        });

        tasks[n].catch(showError);
    }

    // ── Navigation ───────────────────────────────────────────────────

    /**
     * The vertical scroll belongs to the document. In full screen the
     * browser hands scrolling to the fullscreen element instead, so ask
     * which one is live rather than assuming.
     */
    function scroller() {
        return inFullscreen() ? viewer : window;
    }

    /**
     * Where the readable area starts, in viewport coordinates: the bottom
     * of the sticky chrome. Works in both scroll modes — when the toolbars
     * are stuck it is their lower edge, and before that it is simply where
     * they sit on screen.
     */
    function readingTop() {
        var chrome = document.querySelector('[data-pdf-toolbar]');

        return chrome ? chrome.getBoundingClientRect().bottom : 0;
    }

    function onScroll() {
        if (scrollQueued) return;
        scrollQueued = true;

        window.requestAnimationFrame(function () {
            scrollQueued = false;
            var top = readingTop();
            var found = 1;

            for (var i = 0; i < placeholders.length; i++) {
                if (placeholders[i].el.getBoundingClientRect().top - top <= 8) found = i + 1;
                else break;
            }

            if (found !== current) {
                current = found;
                updateStatus();
            }
        });
    }

    /** Put page n at the top of the readable area. */
    function alignTo(n) {
        var entry = placeholders[n - 1];
        if (!entry) return;

        var delta = entry.el.getBoundingClientRect().top - readingTop();
        scroller().scrollBy(0, delta);
    }

    function goTo(n) {
        if (!placeholders.length) return;
        n = Math.min(Math.max(n, 1), placeholders.length);
        current = n;
        alignTo(n);
        updateStatus();
    }

    function updateStatus() {
        statusEl.textContent = 'Page ' + current + ' of ' + placeholders.length;
        prevEl.disabled = current <= 1;
        nextEl.disabled = current >= placeholders.length;
        zoomLabelEl.textContent = Math.round(zoom * 100) + '%';
    }

    function setZoom(next) {
        if (next === zoom || !placeholders.length) return;

        var page = current;
        zoom = next;
        placeholders.forEach(size);
        reobserve();

        // Stay on the page they were reading, at the new size.
        alignTo(page);
        updateStatus();
    }

    /** Rotation, window resizes and full screen all change the fit scale. */
    function resized() {
        if (!doc || !placeholders.length) return;

        var width = pagesEl.clientWidth;
        var page = current;
        measure();
        measureBar();

        if (width !== pagesEl.clientWidth) alignTo(page);
    }

    /** Keep the toolbar stuck directly under the title bar, whatever it wraps to. */
    function measureBar() {
        var bar = document.getElementById('pdf-bar');
        if (bar) viewer.style.setProperty('--reader-bar-h', bar.offsetHeight + 'px');
    }

    // ── Loading, failure, recovery ───────────────────────────────────

    function loading(on) {
        on ? show(loadingEl) : hide(loadingEl);
        if (on) hide(errorEl);
    }

    /**
     * The signed URL can go stale (it is short-lived for a reason). One
     * silent re-mint keeps the student reading rather than showing an
     * error they cannot act on; after that they get both ways out.
     */
    function failed() {
        if (!refreshed && refreshUrl) {
            refreshed = true;
            loadingTextEl.textContent = 'Refreshing your link…';
            loading(true);

            fetch(refreshUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(response.status); })
                .then(function (data) {
                    if (!data.url) throw new Error('no url');
                    documentUrl = data.url;
                    open();
                })
                .catch(showError);

            return;
        }

        showError();
    }

    /** Both ways out: retry here, or hand the paper to the reader app. */
    function showError() {
        loading(false);
        hide(viewer);
        show(fallback);
        errorTextEl.textContent = 'We could not load the paper in the page. Open it in your reader app, or try again.';
        show(errorEl);
    }

    // ── Full screen ──────────────────────────────────────────────────

    /**
     * Where the browser has real full screen (Chrome on Android, iPadOS,
     * desktop), offer it: the toolbar stays, the browser's own chrome
     * goes, and the paper gets every pixel. iPhone Safari has no such
     * API — the page is already chrome-less there, so the button stays
     * hidden rather than pretending to work.
     */
    function canGoFullscreen() {
        return !!(viewer.requestFullscreen || viewer.webkitRequestFullscreen);
    }

    function inFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function toggleFullscreen() {
        if (inFullscreen()) {
            var exit = document.exitFullscreen || document.webkitExitFullscreen;
            if (exit) exit.call(document);

            return;
        }

        var request = viewer.requestFullscreen || viewer.webkitRequestFullscreen;
        if (!request) return;

        var promise = request.call(viewer);
        if (promise && promise.catch) promise.catch(function () {});
    }

    function onFullscreenChange() {
        var on = inFullscreen();
        var label = on ? 'Exit full screen' : 'Full screen';

        fullEl.setAttribute('aria-label', label);
        fullEl.setAttribute('title', label);
        fullEl.setAttribute('aria-pressed', on ? 'true' : 'false');
        Array.prototype.forEach.call(fullEl.querySelectorAll('.pdf-icon-enter'), function (el) {
            el.classList.toggle('hidden', on);
        });
        Array.prototype.forEach.call(fullEl.querySelectorAll('.pdf-icon-exit'), function (el) {
            el.classList.toggle('hidden', !on);
        });

        // The browser resizes the element as it animates into and out of
        // full screen, so re-fit once it has settled as well as now.
        resized();
        window.setTimeout(resized, 300);
    }

    // ── Wiring ───────────────────────────────────────────────────────

    // The document scrolls; in full screen the shell does. Listen to both.
    window.addEventListener('scroll', onScroll, { passive: true });
    viewer.addEventListener('scroll', onScroll, { passive: true });
    prevEl.addEventListener('click', function () { goTo(current - 1); });
    nextEl.addEventListener('click', function () { goTo(current + 1); });
    zoomInEl.addEventListener('click', function () {
        setZoom(ZOOM_STEPS.filter(function (step) { return step > zoom; })[0] || zoom);
    });
    zoomOutEl.addEventListener('click', function () {
        var lower = ZOOM_STEPS.filter(function (step) { return step < zoom; });

        return setZoom(lower.length ? lower[lower.length - 1] : zoom);
    });
    zoomResetEl.addEventListener('click', function () { setZoom(1); });
    retryEl.addEventListener('click', function () {
        refreshed = true;
        loadingTextEl.textContent = 'Loading your watermarked copy…';
        hide(errorEl);
        hide(viewer);
        loading(true);
        open();
    });

    if (fullEl) {
        fullEl.addEventListener('click', toggleFullscreen);
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    }

    // Printing a single watermarked page is fine; a right-click "save
    // image as" that strips the framing is not, so the canvas keeps its
    // context menu to itself.
    pagesEl.addEventListener('contextmenu', function (event) { event.preventDefault(); });

    window.addEventListener('resize', function () {
        window.clearTimeout(resized._t);
        resized._t = window.setTimeout(resized, 150);
    });

    function show(el) {
        if (el) el.classList.remove('is-hidden');
    }

    function hide(el) {
        if (el) el.classList.add('is-hidden');
    }
})();
