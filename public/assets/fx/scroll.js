// Scroll cinema: one passive scroll engine for the landing page (styles in fx/scroll.css).
//  1. [data-fill]    the statement fills from grey to ink, word by word, as it is read
//  2. .shead         section heads: the rule draws, the heading rises out of a mask, the fact follows
//  3. .dates         the dates rows rise in turn while their hairlines draw
//  4. [data-cut]     the IWI diagonal of the green fields draws in with the scroll (--cut-p)
//  5. .agenda        a green progress line runs down the programme; passed rows set their time in ink
//  6. [data-ticker]  the running band runs right to left on its own at a calm pace; scrolling down
//                    adds speed for a moment, scrolling up slows it or briefly turns it back. Its
//                    pause button (WCAG 2.2.2) stops it for the rest of the visit, a resting mouse
//                    pointer holds it, and it rests while off-screen or in a hidden tab
// Progressive enhancement: the page is complete without this file. An element is
// armed only while it is off-screen, so nothing on screen at load changes or
// disappears. Two motion tiers. With motion allowed everything above runs. Under
// prefers-reduced-motion: reduce ("fewer and gentler, not off") nothing travels
// through space, but colour and opacity stay: 1. the statement still fills grey to
// ink, 2./3. heads and dates fade in (opacity only, the rules simply stand, see
// scroll.css), 5. passed programme times still turn ink; the programme line, 4. the
// cut drawing and 6. the band movement are off (static as in site.css, its pause
// button hidden). A change of the preference switches tiers live, in both directions.
// The frame loop runs only while something moves: continuously while the band runs
// on screen, otherwise just until the scroll-linked effects have settled.
(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    root.classList.add('js');

    if (typeof window.IntersectionObserver !== 'function'
        || typeof window.requestAnimationFrame !== 'function'
        || typeof window.matchMedia !== 'function') {
        return;
    }

    // ── Tunables ─────────────────────────────────────────────────────────
    var FILL_FROM = 0.85;    // statement top at 85% of the viewport height: no word filled
    var FILL_TO = 0.45;      // statement bottom at 45%: every word filled
    var CUT_TO = 0.55;       // green field top at 55%: diagonal complete (it starts at 100%)
    var AGENDA_AT = 0.6;     // reading line of the programme, share of the viewport height
    var TICKER_SPEED = 40;   // the band's own pace in px/s, right to left
    var TICKER_MAX = 4;      // scrolling down speeds it up to at most this multiple of its pace
    var TICKER_BACK = 1;     // scrolling up slows it, at most into running backwards at this multiple
    var BOOST_PER_PX = 0.5;  // scroll kick: px/s of extra speed per px scrolled (soft-capped by the two above)
    var TAU_BOOST = 500;     // ms: a scroll kick fades out smoothly with this time constant
    var TAU_SPEED = 160;     // ms: how softly the band takes up a new speed (start, kick, pause, hover)
    var TAU_AGENDA = 90;     // smoothing time constants in ms (more = more glide)
    var TAU_CUT = 70;        // short: the cut settles about 0.3s after a wheel step
    var CUT_EPS = 0.003;     // the cut lands on its target once this close (a last step of at most ~1.5px)
    var HEAD_RATIO = 0.25;   // visible share of a section head before it reveals
    var LIST_RATIO = 0.15;   // visible share of the dates list before it reveals
    var REVEAL_MARGIN = '0px 0px -6% 0px';
    var NEAR_MARGIN = '25% 0px 25% 0px';
    var HEAD_MS = 1400;      // reveal length incl. delays (see scroll.css; the gentle fades end sooner), then the classes go
    var LIST_MS = 1800;

    var BOOST_UP = TICKER_SPEED * (TICKER_MAX - 1);    // most extra speed a scroll down can add (px/s)
    var BOOST_DOWN = TICKER_SPEED * (1 + TICKER_BACK); // most speed a scroll up can take away (px/s)

    var reduce = window.matchMedia('not all') /* motion always on: reduced-motion setting ignored by decision of the project team, 12.10.2026 */;
    var effects = [];        // scroll-linked, updated in the frame loop
    var reveals = [];        // one-shot, played by an IntersectionObserver
    var tickers = [];        // the running bands among the effects
    var tickerPaused = false; // the visitor's pause choice; kept for this page view only, never stored
    var lastY = -1;          // scroll position of the last frame
    var vh = window.innerHeight || root.clientHeight;
    var enabled = false;
    var full = false;        // tier: true = motion allowed, false = gentle (reduce)
    var broken = false;      // set by fail(): the module stays off for good
    var raf = 0;
    var last = 0;
    var needMeasure = true;
    var nearCount = 0;
    var nearIO = null;
    var revealIO = null;
    var seenIO = null;       // is a band actually on screen (no margin)?

    // ── Helpers ──────────────────────────────────────────────────────────
    function clamp01(v) {
        return v < 0 ? 0 : (v > 1 ? 1 : v);
    }

    function clampTo(v, lo, hi) {
        return v < lo ? lo : (v > hi ? hi : v);
    }

    // Does this effect run in the current tier?
    function live(fx) {
        return full || fx.gentle;
    }

    function easeOutCubic(t) {
        var u = 1 - t;
        return 1 - u * u * u;
    }

    // Frame-rate independent glide towards the target; lands exactly once close enough.
    function follow(cur, target, dt, tau, eps) {
        var next = cur + (target - cur) * (1 - Math.exp(-dt / tau));
        return Math.abs(target - next) < eps ? target : next;
    }

    // Layout position in the document, unaffected by transforms (other modules may move things).
    function pageTop(el) {
        var y = el.offsetTop;
        var parent = el.offsetParent;
        while (parent) {
            y += parent.offsetTop + parent.clientTop;
            parent = parent.offsetParent;
        }
        return y;
    }

    function offScreen(fx, y) {
        var top = fx.top - y;
        return top >= vh || top + fx.h <= 0;
    }

    function measureBox(fx) {
        fx.top = pageTop(fx.el);
        fx.h = fx.el.offsetHeight;
    }

    // gentle: the effect also runs under reduced motion (colour only there).
    // retier: called when the tier changes while the effect keeps running.
    function makeEffect(el, measure, update, reset, build, gentle, retier) {
        return {
            el: el, measure: measure, update: update, reset: reset, build: build,
            gentle: gentle, retier: retier,
            near: false, dirty: true, snap: true, armed: false,
            top: 0, h: 0, cur: 0, out: -1,
            words: null, count: -1, rows: null, line: null, mark: null,
            track: null, view: null, toggle: null, bound: false, seen: false, hover: false, ran: false,
            w: 0, x: 0, v: 0, kick: 0, prev: 0
        };
    }

    // ── 1. Statement fill ────────────────────────────────────────────────
    function appendWords(parent, text, words) {
        var parts = text.split(/(\s+)/);
        for (var i = 0; i < parts.length; i++) {
            if (parts[i] === '') {
                continue;
            }
            if (i % 2 === 1) {
                parent.appendChild(doc.createTextNode(parts[i]));
            } else {
                var word = doc.createElement('span');
                word.className = 'fx-w';
                word.textContent = parts[i];
                parent.appendChild(word);
                words.push(word);
            }
        }
    }

    // Every word becomes a plain inline span where it stands: the text, its spaces and any inline
    // structure (links, emphasis) stay as they are, so the sentence exists once for find-in-page,
    // selection and assistive technology (no hidden copy, no aria-hidden twin).
    function splitWords(fx) {
        var words = [];
        var walker = doc.createTreeWalker(fx.el, NodeFilter.SHOW_TEXT, null);
        var nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        for (var i = 0; i < nodes.length; i++) {
            var frag = doc.createDocumentFragment();
            appendWords(frag, nodes[i].nodeValue, words);
            nodes[i].parentNode.replaceChild(frag, nodes[i]);
        }
        fx.words = words;
    }

    function updateFill(fx, y) {
        if (!fx.armed) {
            // On screen in its static state (e.g. visible at load): wait until it has left the viewport.
            if (!offScreen(fx, y)) {
                return false;
            }
            if (!fx.words) {
                splitWords(fx);
            }
            fx.count = -1;
            fx.armed = true;
            fx.el.classList.add('fx-fill');
        }
        var words = fx.words;
        var n = words.length;
        var p = clamp01((vh * FILL_FROM - (fx.top - y)) / (vh * (FILL_FROM - FILL_TO) + fx.h));
        var count = Math.round(p * n);
        if (count !== fx.count) {
            var from = fx.count < 0 ? 0 : Math.min(count, fx.count);
            var to = fx.count < 0 ? n : Math.max(count, fx.count);
            for (var i = from; i < to; i++) {
                words[i].classList.toggle('is-on', i < count);
            }
            fx.count = count;
        }
        return false;
    }

    function resetFill(fx) {
        fx.armed = false;
        fx.count = -1;
        fx.el.classList.remove('fx-fill');
    }

    // ── 4. IWI cut on the green fields ───────────────────────────────────
    // --cut-p (0 flat … 1 full diagonal) only scales a page-coloured triangle on [data-cut]::after
    // (fx/scroll.css); the field itself keeps a static clip, so a step repaints the cut band only.
    function writeCut(fx) {
        var v = Math.round(easeOutCubic(fx.cur) * 1000) / 1000;
        if (v !== fx.out) {
            fx.out = v;
            fx.el.style.setProperty('--cut-p', String(v));
        }
    }

    function updateCut(fx, y, dt) {
        var target = clamp01((vh - (fx.top - y)) / (vh * (1 - CUT_TO)));
        if (!fx.armed) {
            if (!offScreen(fx, y)) {
                return false;
            }
            fx.armed = true;
            fx.cur = target;
            writeCut(fx);
            fx.el.classList.add('fx-cut');
            return false;
        }
        fx.cur = fx.snap ? target : follow(fx.cur, target, dt, TAU_CUT, CUT_EPS);
        writeCut(fx);
        return fx.cur !== target;
    }

    function resetCut(fx) {
        fx.armed = false;
        fx.out = -1;
        fx.el.classList.remove('fx-cut');
        fx.el.style.removeProperty('--cut-p');
    }

    // ── 5. Programme progress line ───────────────────────────────────────
    function buildAgenda(fx) {
        var ol = fx.el;
        var wrap = doc.createElement('div');
        wrap.className = 'fx-agenda';
        ol.parentNode.insertBefore(wrap, ol);
        wrap.appendChild(ol);
        var rail = doc.createElement('div');
        rail.className = 'fx-agenda__rail';
        rail.setAttribute('aria-hidden', 'true');
        fx.line = doc.createElement('div');
        fx.line.className = 'fx-agenda__line';
        fx.mark = doc.createElement('div');
        fx.mark.className = 'fx-agenda__mark';
        rail.appendChild(fx.line);
        rail.appendChild(fx.mark);
        wrap.appendChild(rail);
        fx.rows = [];
        for (var i = 0; i < ol.children.length; i++) {
            var li = ol.children[i];
            fx.rows.push({ el: li, time: li.querySelector('.agenda__time') || li, y: 0, past: false });
        }
        fx.armed = true;
    }

    function measureAgenda(fx) {
        measureBox(fx);
        if (!fx.rows) {
            return;
        }
        for (var i = 0; i < fx.rows.length; i++) {
            var row = fx.rows[i];
            // The middle of the time's first line is where the row counts as passed.
            row.y = pageTop(row.time) - fx.top + Math.min(row.time.offsetHeight, 28) / 2;
        }
    }

    function updateAgenda(fx, y, dt) {
        if (!fx.armed || fx.h <= 0) {
            return false;
        }
        var target = clamp01((vh * AGENDA_AT - (fx.top - y)) / fx.h);
        var tip;
        if (full) {
            fx.cur = fx.snap ? target : follow(fx.cur, target, dt, TAU_AGENDA, 0.0002);
            tip = Math.round(fx.cur * fx.h * 10) / 10;
            if (tip !== fx.out) {
                fx.out = tip;
                fx.line.style.transform = 'scaleY(' + fx.cur.toFixed(4) + ')';
                fx.mark.style.transform = 'translate3d(0,' + tip + 'px,0)';
            }
        } else {
            // Gentle tier: no line (its rail stays display: none), no glide; only the times turn ink.
            fx.cur = target;
            tip = Math.round(target * fx.h * 10) / 10;
        }
        var rows = fx.rows;
        for (var i = 0; i < rows.length; i++) {
            var past = tip > 0 && tip >= rows[i].y;
            if (past !== rows[i].past) {
                rows[i].past = past;
                rows[i].el.classList.toggle('is-past', past);
            }
        }
        return fx.cur !== target;
    }

    // Tier change: the line starts over (drawn again in the full tier, gone in the gentle one);
    // the passed rows keep their ink and are re-checked in the next frame.
    function clearAgendaLine(fx) {
        fx.out = -1;
        if (fx.line) {
            fx.line.style.transform = '';
            fx.mark.style.transform = '';
        }
    }

    function resetAgenda(fx) {
        clearAgendaLine(fx);
        if (fx.rows) {
            for (var i = 0; i < fx.rows.length; i++) {
                fx.rows[i].past = false;
                fx.rows[i].el.classList.remove('is-past');
            }
        }
    }

    // ── 6. Running band ──────────────────────────────────────────────────
    function measureTicker(fx) {
        measureBox(fx);
        var items = fx.track.children;
        var n = items.length / 3;
        var w = 0;
        if (n >= 1 && n === Math.floor(n)) {
            // One round = from the first item to the same item in the second round (sub-pixel exact).
            w = items[n].getBoundingClientRect().left - items[0].getBoundingClientRect().left;
        }
        w = w > 0 ? w : fx.track.scrollWidth / 3;
        if (fx.w > 0 && w > 0 && w !== fx.w) {
            fx.x = fx.x / fx.w * w; // the round changed size (resize, fonts): keep the same text in view
        }
        fx.w = w;
    }

    // A scroll kick in px/s, soft-capped (linear for small kicks, never past the caps, no hard edge).
    function boost(kick) {
        return kick >= 0 ? BOOST_UP * Math.tanh(kick / BOOST_UP) : BOOST_DOWN * Math.tanh(kick / BOOST_DOWN);
    }

    // The band runs at its own pace and takes energy from the scroll: every pixel scrolled adds a
    // kick (down speeds it up, up slows it or briefly turns it back) that fades with TAU_BOOST; the
    // speed itself follows with TAU_SPEED, so starts, kicks, pauses and hovers never jump. Returns
    // true while it moves, which keeps the frame loop running only as long as the band is on screen.
    function updateTicker(fx, y, dt) {
        var dy = y - fx.prev;
        fx.prev = y;
        if (!(fx.w > 0) || !fx.seen || doc.hidden) {
            // Off-screen or in a hidden tab: the loop rests, the band keeps its place and pace.
            fx.kick = 0;
            return false;
        }
        var held = tickerPaused || fx.hover;
        if (fx.snap) {
            // Into view or into this tier: no kick from the way here. Back in view it runs on at its
            // pace at once; its very first start (and the first after a tier change) eases in.
            fx.v = held ? 0 : (fx.ran ? TICKER_SPEED : fx.v);
            fx.kick = 0;
            dy = 0;
        } else if (Math.abs(dy) > vh) {
            dy = 0; // a jump (Home, End, a link) is no scroll gesture: no kick
        }
        var target = 0;
        if (held) {
            fx.kick = 0; // paused or held: the scroll moves nothing either
        } else {
            fx.ran = true;
            fx.kick = clampTo(fx.kick + dy * BOOST_PER_PX, -2 * BOOST_DOWN, 2 * BOOST_UP) * Math.exp(-dt / TAU_BOOST);
            target = TICKER_SPEED + boost(fx.kick);
        }
        fx.v = follow(fx.v, target, dt, TAU_SPEED, 0.5);
        var x = (fx.x + fx.v * dt / 1000) % fx.w;
        if (x < 0) {
            x += fx.w;
        }
        fx.x = x;
        x = Math.round(x * 100) / 100;
        if (x !== fx.out) {
            fx.out = x;
            fx.track.style.transform = 'translate3d(' + (-x) + 'px,0,0)';
        }
        return target !== 0 || fx.v !== 0;
    }

    function syncToggle(fx) {
        fx.toggle.classList.toggle('is-paused', tickerPaused);
        fx.toggle.setAttribute('aria-label', tickerPaused ? fx.labelPlay : fx.labelPause);
    }

    function wakeTicker(fx) {
        fx.dirty = true;
        schedule();
    }

    // The band's button is the page-wide "pause animations" control (WCAG 2.2.2): it also
    // stops the hero data field (fx/field.js listens for fx:pause and html.fx-paused).
    function onToggle() {
        tickerPaused = !tickerPaused;
        for (var i = 0; i < tickers.length; i++) {
            syncToggle(tickers[i]);
            wakeTicker(tickers[i]);
        }
        document.documentElement.classList.toggle('fx-paused', tickerPaused);
        try {
            document.dispatchEvent(new CustomEvent('fx:pause', { detail: { paused: tickerPaused } }));
        } catch (e) { /* very old browsers: the band still pauses */ }
    }

    // Full tier: show the pause button (in the state the visitor left it) and listen once.
    function buildTicker(fx) {
        if (!fx.bound) {
            fx.bound = true;
            fx.labelPause = fx.toggle.getAttribute('aria-label') || 'Pause the running band';
            fx.labelPlay = fx.toggle.getAttribute('data-label-play') || 'Play the running band';
            fx.toggle.addEventListener('click', onToggle);
            // A resting mouse or pen holds the band (touch has no hover; a tap must not stop it for good).
            fx.view.addEventListener('pointerenter', function (event) {
                if (event.pointerType !== 'touch') {
                    fx.hover = true;
                    wakeTicker(fx);
                }
            });
            fx.view.addEventListener('pointerleave', function () {
                if (fx.hover) {
                    fx.hover = false;
                    wakeTicker(fx);
                }
            });
        }
        syncToggle(fx);
        fx.toggle.hidden = false;
    }

    // Static band (gentle tier, off, broken): no transform, no button. The pause choice stays.
    function resetTicker(fx) {
        fx.out = -1;
        fx.x = 0;
        fx.v = 0;
        fx.kick = 0;
        fx.ran = false;
        fx.track.style.transform = '';
        fx.toggle.hidden = true;
    }

    function onSeen(entries) {
        for (var i = 0; i < entries.length; i++) {
            for (var k = 0; k < tickers.length; k++) {
                var fx = tickers[k];
                if (fx.el === entries[i].target && fx.seen !== entries[i].isIntersecting) {
                    fx.seen = entries[i].isIntersecting;
                    fx.dirty = true;
                    if (fx.seen) {
                        fx.snap = true;
                    }
                }
            }
        }
        schedule();
    }

    // ── 2./3. One-shot reveals ───────────────────────────────────────────
    function armReveals() {
        var tops = [];
        var i;
        for (i = 0; i < reveals.length; i++) {
            tops.push(reveals[i].armed || reveals[i].done ? 0 : reveals[i].el.getBoundingClientRect().top);
        }
        for (i = 0; i < reveals.length; i++) {
            var r = reveals[i];
            if (r.armed || r.done) {
                continue;
            }
            if (tops[i] < vh) {
                // On screen or already passed: it stays exactly as it is.
                r.done = true;
                continue;
            }
            if (r.list) {
                for (var k = 0; k < r.el.children.length; k++) {
                    r.el.children[k].style.setProperty('--fx-i', String(k));
                }
            }
            r.armed = true;
            r.el.classList.add('fx-rv');
            revealIO.observe(r.el);
        }
    }

    function settle(r) {
        r.timer = 0;
        r.armed = false;
        r.el.classList.remove('fx-rv', 'is-in');
    }

    function play(r) {
        r.done = true;
        revealIO.unobserve(r.el);
        r.el.classList.add('is-in');
        r.timer = window.setTimeout(function () {
            settle(r);
        }, r.ms);
    }

    function onReveal(entries) {
        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];
            for (var k = 0; k < reveals.length; k++) {
                var r = reveals[k];
                if (r.el === entry.target && r.armed && !r.done
                    && entry.isIntersecting && entry.intersectionRatio >= r.ratio) {
                    play(r);
                }
            }
        }
    }

    // ── Engine ───────────────────────────────────────────────────────────
    function schedule() {
        if (!raf && enabled) {
            raf = window.requestAnimationFrame(frame);
        }
    }

    function measure() {
        needMeasure = false;
        vh = window.innerHeight || root.clientHeight;
        for (var i = 0; i < effects.length; i++) {
            if (live(effects[i])) {
                effects[i].measure(effects[i]);
            }
        }
    }

    // Effects near the viewport that run in this tier; scrolling only wakes the loop for them.
    function countNear() {
        nearCount = 0;
        for (var i = 0; i < effects.length; i++) {
            if (effects[i].near && live(effects[i])) {
                nearCount++;
            }
        }
    }

    function frame(now) {
        raf = 0;
        if (!enabled) {
            return;
        }
        try {
            var dt = last ? Math.min(now - last, 100) : 16.7;
            last = now;
            if (needMeasure) {
                measure(); // all layout reads happen here, before any write below
            }
            var y = window.pageYOffset;
            // Not scrolled since the last frame (the band runs on its own): settled effects have nothing new.
            var still = y === lastY;
            lastY = y;
            var busy = false;
            for (var i = 0; i < effects.length; i++) {
                var fx = effects[i];
                if (!live(fx) || (!fx.dirty && (!fx.near || still))) {
                    continue;
                }
                if (!fx.near) {
                    fx.snap = true; // off-screen: straight to the end state, no work spent on glide
                }
                var moving = fx.update(fx, y, dt);
                fx.snap = false;
                fx.dirty = moving;
                if (moving) {
                    busy = true;
                }
            }
            if (busy) {
                schedule();
            } else {
                last = 0;
            }
        } catch (error) {
            fail();
        }
    }

    function invalidate() {
        needMeasure = true;
        for (var i = 0; i < effects.length; i++) {
            effects[i].dirty = true;
        }
        schedule();
    }

    function onScroll() {
        if (nearCount > 0) {
            schedule();
        }
    }

    function onNear(entries) {
        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];
            for (var k = 0; k < effects.length; k++) {
                var fx = effects[k];
                if (fx.el !== entry.target || fx.near === entry.isIntersecting) {
                    continue;
                }
                fx.near = entry.isIntersecting;
                fx.dirty = true;
                if (fx.near) {
                    fx.snap = true; // enters from outside the margin: start in place, no catch-up
                }
            }
        }
        countNear();
        schedule();
    }

    function enable() {
        if (enabled) {
            return;
        }
        enabled = true;
        full = !reduce.matches;
        vh = window.innerHeight || root.clientHeight;
        for (var i = 0; i < effects.length; i++) {
            if (live(effects[i]) && effects[i].build && !effects[i].line) {
                effects[i].build(effects[i]);
            }
            effects[i].snap = true;
        }
        countNear();
        armReveals(); // one-shot reveals run in both tiers; scroll.css holds each tier's look
        invalidate();
    }

    // Switch tiers live. Effects the new tier does not run go back to their static state;
    // the others start in place. Armed reveals stay armed: they are off-screen, and their
    // pre-reveal state follows the media query in scroll.css by itself.
    function setTier(nextFull) {
        if (nextFull === full) {
            return;
        }
        full = nextFull;
        last = 0;
        for (var i = 0; i < effects.length; i++) {
            var fx = effects[i];
            if (!live(fx)) {
                fx.reset(fx);
                continue;
            }
            if (fx.build && !fx.line) {
                fx.build(fx);
            }
            if (fx.retier) {
                fx.retier(fx);
            }
            fx.snap = true;
        }
        countNear();
        invalidate();
    }

    function disable() {
        if (!enabled) {
            return;
        }
        enabled = false;
        if (raf) {
            window.cancelAnimationFrame(raf);
            raf = 0;
        }
        last = 0;
        var i;
        for (i = 0; i < reveals.length; i++) {
            var r = reveals[i];
            if (r.armed) {
                if (r.timer) {
                    window.clearTimeout(r.timer);
                }
                revealIO.unobserve(r.el);
                settle(r);
                r.done = true;
            }
        }
        for (i = 0; i < effects.length; i++) {
            effects[i].reset(effects[i]);
        }
    }

    // Something broke: drop every effect state so the plain page shows.
    function fail() {
        broken = true;
        try {
            disable();
        } catch (ignore) {
            // nothing left to do
        }
        root.classList.remove('fx-scroll');
    }

    function onMotionChange() {
        if (broken || !enabled) {
            return;
        }
        try {
            setTier(!reduce.matches);
        } catch (error) {
            fail();
        }
    }

    function init() {
        var list;
        var i;

        list = doc.querySelectorAll('[data-fill]');
        for (i = 0; i < list.length; i++) {
            effects.push(makeEffect(list[i], measureBox, updateFill, resetFill, null, true, null));
        }
        list = doc.querySelectorAll('[data-cut]');
        for (i = 0; i < list.length; i++) {
            effects.push(makeEffect(list[i], measureBox, updateCut, resetCut, null, false, null));
        }
        list = doc.querySelectorAll('.agenda');
        for (i = 0; i < list.length; i++) {
            if (list[i].parentNode) {
                effects.push(makeEffect(list[i], measureAgenda, updateAgenda, resetAgenda, buildAgenda, true, clearAgendaLine));
            }
        }
        list = doc.querySelectorAll('[data-ticker]');
        for (i = 0; i < list.length; i++) {
            var track = list[i].querySelector('.ticker__track');
            var toggle = list[i].querySelector('.ticker__toggle');
            // No pause button, no running band (WCAG 2.2.2): it then stays static as in site.css.
            if (track && toggle) {
                var ticker = makeEffect(list[i], measureTicker, updateTicker, resetTicker, buildTicker, false, null);
                ticker.track = track;
                ticker.toggle = toggle;
                ticker.view = list[i].querySelector('.ticker__view') || track;
                effects.push(ticker);
                tickers.push(ticker);
            }
        }
        list = doc.querySelectorAll('.shead');
        for (i = 0; i < list.length; i++) {
            reveals.push({ el: list[i], ratio: HEAD_RATIO, ms: HEAD_MS, list: false, armed: false, done: false, timer: 0 });
        }
        list = doc.querySelectorAll('.dates');
        for (i = 0; i < list.length; i++) {
            reveals.push({ el: list[i], ratio: LIST_RATIO, ms: LIST_MS, list: true, armed: false, done: false, timer: 0 });
        }
        if (!effects.length && !reveals.length) {
            return;
        }

        nearIO = new IntersectionObserver(onNear, { rootMargin: NEAR_MARGIN });
        for (i = 0; i < effects.length; i++) {
            nearIO.observe(effects[i].el);
        }
        revealIO = new IntersectionObserver(onReveal, { rootMargin: REVEAL_MARGIN, threshold: [0, LIST_RATIO, HEAD_RATIO] });
        if (tickers.length) {
            seenIO = new IntersectionObserver(onSeen);
            for (i = 0; i < tickers.length; i++) {
                seenIO.observe(tickers[i].el);
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', invalidate, { passive: true });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                invalidate();
            }
        });
        doc.addEventListener('visibilitychange', function () {
            last = 0;
            if (doc.hidden) {
                // Hidden tab: the loop (and with it the running band) rests until the page is back.
                if (raf) {
                    window.cancelAnimationFrame(raf);
                    raf = 0;
                }
                return;
            }
            for (var k = 0; k < tickers.length; k++) {
                tickers[k].dirty = true;
            }
            schedule();
        });
        if (typeof window.ResizeObserver === 'function' && doc.body) {
            // Fonts, late content and other modules can move things without a window resize.
            new ResizeObserver(invalidate).observe(doc.body);
        }
        if (doc.fonts && doc.fonts.ready) {
            doc.fonts.ready.then(invalidate, function () {});
        }
        if (typeof reduce.addEventListener === 'function') {
            reduce.addEventListener('change', onMotionChange);
        } else if (typeof reduce.addListener === 'function') {
            reduce.addListener(onMotionChange);
        }

        root.classList.add('fx-scroll');
        enable(); // in the tier the preference asks for
    }

    try {
        init();
    } catch (error) {
        fail();
    }
})();
