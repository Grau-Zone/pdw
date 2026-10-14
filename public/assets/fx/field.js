/* ==========================================================================
   fx/field.js – the data field in the hero.

   Small square data points stream in from the left and settle into a ribbon
   along the IWI cut of the deadline tile, extended across the whole hero, so
   the green tile reads as the solid continuation of the data. The ribbon is
   built from lanes parallel to the cut: dense and tight on the tile edge,
   thinning and loosening with distance (a halftone dissolving out of the
   tile). The lanes flow slowly along the cut and breathe only while the
   entrance plays; right after it the flow eases to a standstill (about 1 s),
   so nothing moves on its own after roughly 3 s (WCAG 2.2.2), and the loop
   parks once everything is at rest. A fine pointer over the hero wakes it:
   it pushes the points aside and draws faint links to the nearest ones,
   then the field springs back and the loop parks again.

   Reduced motion (gentle tier: fewer and gentler, not off): the settled
   ribbon is drawn in place with no entrance, flow or push; nothing moves
   through space. A fine pointer only changes light: points near it brighten
   toward HSG green and the faint links appear, then fade back when it
   leaves. The loop runs only while that response changes, then parks. The
   media-query listener switches tiers live in both directions.

   Points never sit behind text: the hero copy is measured line by line; the
   ribbon passes behind it as a whole (soft fade), and a hard clear on top
   guarantees nothing is ever drawn under a line of text or a link.

   Decorative only: the canvas is aria-hidden and the page is complete without
   this file. Classic script, no libraries, CSP-safe (writes only canvas
   attributes and one class, never inline styles).
   ========================================================================== */
(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    if (!root.classList.contains('js')) {
        root.classList.add('js');
    }

    var hero = doc.querySelector('[data-hero]');
    var canvas = hero ? hero.querySelector('canvas[data-field]') : null;
    if (!canvas || !canvas.getContext || !window.requestAnimationFrame || !window.matchMedia) {
        return;
    }
    var ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    /* ── Tunables ─────────────────────────────────────────────────────── */
    var MAXP = 720;                 // hard cap of particles
    var BAND_K = 0.045;             // ribbon thickness above the cut = hero width * BAND_K …
    var BAND_MIN = 30;              // … clamped to [BAND_MIN, BAND_MAX] px
    var BAND_MAX = 72;
    var GAP_MIN = 5.5;              // lane pitch, px (about band / 8)
    var GAP_MAX = 9;
    var LANE_DECAY = 3;             // lane k gets weight exp(-k / LANE_DECAY): halftone falloff
    var DUST = 0.1;                 // share of free points between the lanes
    var LOOSEN = 0.3;               // lanes spread by up to this share away from the tile
    var SCATTER = 1.3;              // perpendicular scatter (in lane pitches) away from the tile
    var OVER = 40;                  // px beyond the left/right edge where points wrap
    var DRIFT_MIN = 5;              // idle drift along the ribbon, px/s
    var DRIFT_RANGE = 9;
    var TILE_SLOW = 0.6;            // drift slows by this share over the tile (denser band there)
    var HIDDEN_FAST = 3;            // extra drift speed while behind the copy (fewer wasted points)
    var WOB_MIN = 6;                // flow-field wobble amplitude, px (inner lanes) …
    var WOB_RANGE = 8;              // … up to WOB_MIN + WOB_RANGE (outer lanes)
    var WOB_EDGE = 0.25;            // the lane on the cut moves only this share (boundary layer)
    var BREATH = 0.16;              // slow travelling thickening/thinning of the band
    var MARGIN = 12;                // clear zone around every text line and link, px
    var FEATHER = 14;               // fade distance outside that zone, px
    var FOOT_FEATHER = 36;          // fade of the whole ribbon where it passes behind the copy
    var ZONE = 22;                  // deflection zone outside the clear zone, px
    var DEFLECT = 9;                // max deflection away from text, px
    var REPEL_R = 120;              // pointer radius, px
    var REPEL_PUSH = 46;            // max push at the pointer, px
    var LINKS = 8;                  // data links to the nearest points
    var LINK_R = 170;               // max link length, px
    var LINK_ALPHA = 0.25;          // max link opacity
    var GLOW_R = 120;               // reduced motion: pointer light radius, px …
    var GLOW_A = 0.85;              // … and max opacity of the HSG green over a point (size unchanged)
    var SPRING_K = 90;              // spring stiffness (spring-back after the pointer)
    var SPRING_C = 13;              // spring damping (about 0.7 of critical)
    // Entrance timing: calm, but every self-started movement still ends within about 5 s
    // (latest landing 0.05 + 1.2 + 0.3 + 2.0 = 3.55 s, then 0.7 s settle), WCAG 2.2.2.
    var ENTER_SPREAD = 1.2;         // s: left-to-right stagger across the hero
    var ENTER_JITTER = 0.3;         // s: random extra delay
    var ENTER_DUR = 1.6;            // s: flight time (cubic ease-out) …
    var ENTER_DUR_RANGE = 0.4;      // … plus up to this
    var SETTLE_DUR = 0.7;           // s: drift, wobble and breathing ease to a stop after the entrance
    var REST_V = 0.01;              // at rest: every spring slower than this (px/s, |vx| + |vy|) …
    var REST_D = 0.02;              // … and closer than this to its target (px) …
    var REST_P = 0.003;             // … and the pointer strength this close to its goal
    var MAX_PIXELS = 6e6;          // canvas backing-store budget (DPR is lowered below 2 beyond it)
    var RELAYOUT_MS = 120;          // resize throttle
    var MEASURE_UNTIL = 2600;       // ms after navigation: re-measure text every frame (hero entrance)
    var REMEASURE_EVERY = 30;       // frames: safety re-measure of the text
    var STATIC_T = 7.3;             // wobble phase of the settled frame (reduced motion, and where the flow stops)
    var COLORS = ['#00802f', '#0a5e2d', '#c2c2c2'];
    var LINK_COLOR = '#0a5e2d';
    var EXPO_NORM = 1 / (1 - Math.pow(2, -10));

    var mqReduce = window.matchMedia('not all') /* motion always on: reduced-motion setting ignored by decision of the project team, 12.10.2026 */;
    var mqFine = window.matchMedia('(hover: hover) and (pointer: fine)');
    var mqForced = window.matchMedia('(forced-colors: active)');

    /* ── Particle state (preallocated, no allocations per frame) ──────── */
    var hx = new Float32Array(MAXP);    // position along the cut (x of the foot point)
    var ln = new Float32Array(MAXP);    // offset from the cut in bands (+ above, - below)
    var jit = new Float32Array(MAXP);   // perpendicular scatter, -1 … 1
    var amp = new Float32Array(MAXP);   // wobble amplitude across the ribbon
    var ampA = new Float32Array(MAXP);  // wobble amplitude along the ribbon
    var phs = new Float32Array(MAXP);   // wobble phase
    var spd = new Float32Array(MAXP);   // drift speed
    var al = new Float32Array(MAXP);    // base alpha
    var sz = new Float32Array(MAXP);    // size in CSS px
    var dl = new Float32Array(MAXP);    // entrance start (clock seconds)
    var du = new Float32Array(MAXP);    // entrance duration
    var sx0 = new Float32Array(MAXP);   // entrance start offset
    var sy0 = new Float32Array(MAXP);
    var ox = new Float32Array(MAXP);    // spring offset (pointer, text, relayout)
    var oy = new Float32Array(MAXP);
    var vx = new Float32Array(MAXP);
    var vy = new Float32Array(MAXP);
    var px = new Float32Array(MAXP);    // last drawn position
    var py = new Float32Array(MAXP);
    var mk = new Float32Array(MAXP);    // last visibility (1 = free, 0 = behind text)
    var af = new Float32Array(MAXP);    // final alpha of this frame
    var gl = new Float32Array(MAXP);    // reduced motion: pointer light of this frame, 0 … 1
    var col = new Uint8Array(MAXP);
    var sd = new Uint8Array(MAXP);      // size in device px
    var seen = new Uint8Array(MAXP);
    var dust = new Uint8Array(MAXP);
    var n = 0;

    var laneSpd = new Float32Array(64); // drift speed per lane (index k + 32), 0 = unset

    var MAXR = 64;
    var R = new Float32Array(MAXR * 4); // text rects (l, t, r, b), canvas px, incl. MARGIN
    var rc = 0;                         // used length of R
    var lD = new Float32Array(LINKS);
    var lI = new Uint16Array(LINKS);
    var linkN = 0;
    var glowN = 0;                      // points lit by the pointer this frame (reduced motion)

    var TAB_STEP = 4, TAB_MAX = 2048;   // tables along the cut, one entry per 4 px
    var footTab = new Float32Array(TAB_MAX);
    var cdfTab = new Float32Array(TAB_MAX);
    var tabN = 0;

    var W = 0, H = 0, span = 0, dpr = 1, dprRaw = 1, devW = 0, devH = 0;
    var m = -0.114, lx0 = 0, ly0 = 0, ux = 1, uy = 0, nx = 0, ny = -1;
    var tL = 0, tR = 0, cFall = 240, band = 40, gap = 8, K = 8, KB = 4;
    var cLeft = 0, cTop = 0, scX = 1, scY = 1, measW = 0, measH = 0;
    var clock = 0, lastTs = 0, frameNo = 0, raf = 0, running = false;
    var layoutDirty = true, measureDirty = true, lastLayoutAt = -1e9, lastScrollY = -1, absorb = false;
    var visible = !('IntersectionObserver' in window);
    var reduced = mqReduce.matches;
    var forced = mqForced.matches;
    var ptrCX = -1e4, ptrCY = -1e4, ptrIn = false, pX = -1e4, pY = -1e4, pStr = 0;
    var defX = 0, defY = 0;
    var enterEnd = -1e9;                // clock time the last entrance flight lands
    var settleAt = 1e9, phase0 = 0;     // the idle flow eases out from settleAt (see flowAt)
    var flowT = 0, flowD = 0;           // eased flow time of this frame and its step
    var motV = 0, motD = 0, ptrGoal = 0; // rest metrics of the last step
    var ready = false;
    var range = doc.createRange ? doc.createRange() : null;
    var tile = null, textEls = [], boxEls = [];
    // Copy zone (canvas px): from the left edge to the right end of the copy. No point
    // shows in it, so no stray squares sit beside the title or in the left margin.
    var zR = -1, zT = 0, zB = 0;

    // Seeded PRNG: the composition is the same on every visit.
    var seed = 0x2f80a0;
    function rnd() {
        seed = (seed + 0x6d2b79f5) | 0;
        var t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    }

    function clamp(v, lo, hi) {
        return v < lo ? lo : (v > hi ? hi : v);
    }

    /* ── Flow time ────────────────────────────────────────────────────── */

    // Time that drives drift, wobble and breathing. It runs with the clock until
    // settleAt, then its rate eases from 1 to 0 over SETTLE_DUR (rate 1 - smoothstep,
    // integrated in closed form) and stays put: the self-started motion ends there.
    function flowAt(c) {
        var u = (c - settleAt) / SETTLE_DUR;
        if (u <= 0) {
            return phase0 + c;
        }
        if (u > 1) {
            u = 1;
        }
        return phase0 + settleAt + SETTLE_DUR * (u - u * u * u + 0.5 * u * u * u * u);
    }

    // Global pause (html.fx-paused, toggled by the band's pause button): the flow eases
    // to a stop from where it is; on play it continues from the same phase, no jump.
    var paused = doc.documentElement.classList.contains('fx-paused');
    function pauseFlow() {
        if (settleAt > clock) {
            settleAt = clock;
        }
    }
    function resumeFlow() {
        var f = flowAt(clock);
        settleAt = 1e9;
        phase0 = f - clock;
    }

    // Let the flow come to rest from `at` on, exactly at the phase of the static frame.
    function setSettle(at) {
        settleAt = at;
        phase0 = STATIC_T - at - 0.5 * SETTLE_DUR;
        flowT = flowAt(clock);
        flowD = 0;
    }

    /* ── Geometry helpers ─────────────────────────────────────────────── */

    // 1 over the tile, easing to 0 with distance from it.
    function coh(x) {
        var dist = x < tL ? tL - x : (x > tR ? x - tR : 0);
        if (dist === 0) {
            return 1;
        }
        if (dist >= cFall) {
            return 0;
        }
        var s = 1 - dist / cFall;
        return s * s * (3 - 2 * s);
    }

    // 0 inside a text rect (incl. margin), smooth to 1 at `feather` px outside.
    function maskAt(x, y, feather) {
        var mm = 1, k, l, t, r, b, dx, dy, dist, v;
        for (k = 0; k < rc; k += 4) {
            l = R[k]; t = R[k + 1]; r = R[k + 2]; b = R[k + 3];
            if (x < l - feather || x > r + feather || y < t - feather || y > b + feather) {
                continue;
            }
            dx = x < l ? l - x : (x > r ? x - r : 0);
            dy = y < t ? t - y : (y > b ? y - b : 0);
            if (dx === 0 && dy === 0) {
                return 0;
            }
            dist = dx === 0 ? dy : (dy === 0 ? dx : Math.sqrt(dx * dx + dy * dy));
            v = dist / feather;
            if (v < mm) {
                mm = v;
            }
        }
        return mm >= 1 ? 1 : mm * mm * (3 - 2 * mm);
    }

    // Visibility of the ribbon's cross-section at x (0 where the cut runs behind the copy).
    function footAt(x) {
        var f = (x + OVER) / TAB_STEP;
        var j = Math.floor(f);
        if (j <= 0) {
            return footTab[0];
        }
        if (j >= tabN - 1) {
            return footTab[tabN - 1];
        }
        return footTab[j] + (footTab[j + 1] - footTab[j]) * (f - j);
    }

    // Relative drift speed at x; the stationary density of points is its inverse.
    function speedAt(x) {
        return (1 - TILE_SLOW * coh(x)) * (1 + HIDDEN_FAST * (1 - footAt(x)));
    }

    function buildFoot() {
        var j, x;
        if (span <= 0) {
            return;
        }
        tabN = Math.min(TAB_MAX, Math.ceil(span / TAB_STEP) + 2);
        for (j = 0; j < tabN; j++) {
            x = -OVER + j * TAB_STEP;
            footTab[j] = maskAt(x, ly0 + m * (x - lx0), FOOT_FEATHER);
        }
    }

    function buildCdf() {
        var j, total;
        cdfTab[0] = 0;
        for (j = 1; j < tabN; j++) {
            cdfTab[j] = cdfTab[j - 1] + 1 / speedAt(-OVER + (j - 0.5) * TAB_STEP);
        }
        total = cdfTab[tabN - 1] || 1;
        for (j = 1; j < tabN; j++) {
            cdfTab[j] /= total;
        }
    }

    // Position along the cut for a quantile u of the stationary density.
    function xFromU(u) {
        var lo = 0, hi = tabN - 1, mid, a, b;
        while (hi - lo > 1) {
            mid = (lo + hi) >> 1;
            if (cdfTab[mid] <= u) {
                lo = mid;
            } else {
                hi = mid;
            }
        }
        a = cdfTab[lo];
        b = cdfTab[hi];
        return clamp(-OVER + (lo + (b > a ? (u - a) / (b - a) : 0)) * TAB_STEP, -OVER, W + OVER);
    }

    // Push away from text that a home point is close to (writes defX/defY).
    function deflect(x, y) {
        var k, l, t, r, b, dx, dy, dist, s, p;
        defX = 0;
        defY = 0;
        for (k = 0; k < rc; k += 4) {
            l = R[k]; t = R[k + 1]; r = R[k + 2]; b = R[k + 3];
            if (x < l - ZONE || x > r + ZONE || y < t - ZONE || y > b + ZONE) {
                continue;
            }
            dx = x < l ? x - l : (x > r ? x - r : 0);
            dy = y < t ? y - t : (y > b ? y - b : 0);
            if (dx === 0 && dy === 0) {
                continue; // inside: hidden by the mask, drifts out faster
            }
            dist = Math.sqrt(dx * dx + dy * dy);
            if (dist >= ZONE) {
                continue;
            }
            s = 1 - dist / ZONE;
            p = DEFLECT * s * s / dist;
            defX += dx * p;
            defY += dy * p;
        }
    }

    /* ── Measuring (reads only) ───────────────────────────────────────── */

    function query() {
        tile = hero.querySelector('.hero__tile .tile') || hero.querySelector('.tile');
        textEls = hero.querySelectorAll('.hero__copy h1, .hero__copy h2, .hero__copy p, .hero__copy li');
        boxEls = hero.querySelectorAll('.hero__copy a, .hero__copy button');
    }

    // Adds a client rect in canvas px; merges with a rect of the same element on the same line.
    function addRect(r, from) {
        var l = (r.left - cLeft) * scX;
        var t = (r.top - cTop) * scY;
        var rr = (r.right - cLeft) * scX;
        var b = (r.bottom - cTop) * scY;
        var k, ot, ob, ov;
        for (k = from; k < rc; k += 4) {
            ot = R[k + 1];
            ob = R[k + 3];
            ov = Math.min(b, ob) - Math.max(t, ot);
            if (ov > 0.5 * Math.min(b - t, ob - ot)) {
                if (l < R[k]) { R[k] = l; }
                if (t < ot) { R[k + 1] = t; }
                if (rr > R[k + 2]) { R[k + 2] = rr; }
                if (b > ob) { R[k + 3] = b; }
                return;
            }
        }
        if (rc < R.length) {
            R[rc] = l; R[rc + 1] = t; R[rc + 2] = rr; R[rc + 3] = b;
            rc += 4;
        }
    }

    // Canvas position and the text lines of the hero copy (visual boxes, transforms included).
    function measure() {
        var cr = canvas.getBoundingClientRect();
        var i, j, k, list, r, from;
        measW = canvas.clientWidth;
        measH = canvas.clientHeight;
        scX = cr.width > 0 ? measW / cr.width : 1;
        scY = cr.height > 0 ? measH / cr.height : 1;
        cLeft = cr.left;
        cTop = cr.top;
        rc = 0;
        for (i = 0; i < textEls.length; i++) {
            from = rc;
            list = null;
            if (range) {
                range.selectNodeContents(textEls[i]);
                list = range.getClientRects();
            }
            if (list && list.length) {
                for (j = 0; j < list.length; j++) {
                    r = list[j];
                    if (r.width >= 0.5 && r.height >= 0.5) {
                        addRect(r, from);
                    }
                }
            } else {
                r = textEls[i].getBoundingClientRect();
                if (r.width && r.height) {
                    addRect(r, from);
                }
            }
        }
        for (i = 0; i < boxEls.length; i++) {
            r = boxEls[i].getBoundingClientRect();
            if (r.width && r.height) {
                addRect(r, rc);
            }
        }
        zR = -1;
        for (k = 0; k < rc; k += 4) {
            R[k] -= MARGIN;
            R[k + 1] -= MARGIN;
            R[k + 2] += MARGIN;
            R[k + 3] += MARGIN;
            if (zR < 0 || R[k + 1] < zT) { zT = R[k + 1]; }
            if (zR < 0 || R[k + 3] > zB) { zB = R[k + 3]; }
            if (R[k + 2] > zR) { zR = R[k + 2]; }
        }
        lastScrollY = window.pageYOffset;
        measureDirty = false;
        buildFoot();
    }

    function countFor(w) {
        var c;
        if (w < 720) {
            c = 240 * clamp(w / 390, 0.8, 1.25);
        } else if (w < 1024) {
            c = 400 * clamp(w / 834, 0.9, 1.15);
        } else {
            c = 520 * clamp(w / 1440, 0.85, 1.3);
        }
        if (navigator.deviceMemory && navigator.deviceMemory <= 2) {
            c *= 0.7;
        }
        return Math.min(MAXP, Math.round(c));
    }

    /* ── Spawning ─────────────────────────────────────────────────────── */

    function laneWeight(k) {
        return k >= 0 ? Math.exp(-k / LANE_DECAY) : 0.5 * Math.exp(k / 2);
    }

    function pickLane() {
        var k, total = 0, r;
        for (k = -KB; k < K; k++) {
            total += laneWeight(k);
        }
        r = rnd() * total;
        for (k = -KB; k < K; k++) {
            r -= laneWeight(k);
            if (r <= 0) {
                return k;
            }
        }
        return 0;
    }

    function speedOfLane(k) {
        var idx = clamp(k + 32, 0, 63);
        if (!laneSpd[idx]) {
            laneSpd[idx] = DRIFT_MIN + DRIFT_RANGE * rnd();
        }
        return laneSpd[idx];
    }

    // Entrance parameters: a flight from the left, staggered left to right.
    function enter(i, late) {
        var a = -(140 + 380 * rnd());
        var p = (rnd() * 2 - 1) * (30 + 110 * rnd());
        if (late) {
            a *= 0.3;
            p *= 0.3;
        }
        sx0[i] = ux * a + nx * p;
        sy0[i] = uy * a + ny * p;
        du[i] = ENTER_DUR + ENTER_DUR_RANGE * rnd();
        if (reduced) {
            dl[i] = -10;
        } else if (late) {
            dl[i] = clock + 0.4 * rnd();
        } else {
            dl[i] = clock + 0.05 + ENTER_SPREAD * clamp((hx[i] + OVER) / span, 0, 1) + ENTER_JITTER * rnd();
        }
        if (dl[i] + du[i] > enterEnd) {
            enterEnd = dl[i] + du[i];
        }
        ox[i] = 0; oy[i] = 0; vx[i] = 0; vy[i] = 0;
        mk[i] = 1; af[i] = 0; seen[i] = 0;
    }

    function spawnLane(i, k, u, late) {
        var a = Math.min(1, Math.abs(k) / Math.max(1, K - 1)); // 0 on the cut … 1 outermost
        var q = rnd();
        dust[i] = 0;
        ln[i] = k >= 0 ? (k + 0.35) / K : k / K;
        jit[i] = rnd() * 2 - 1;
        amp[i] = (WOB_MIN + WOB_RANGE * a) * (WOB_EDGE + (1 - WOB_EDGE) * a) * (0.92 + 0.16 * rnd());
        ampA[i] = 0.45 * (WOB_MIN + WOB_RANGE * a);
        phs[i] = 0.12 * k + 0.08 * rnd();
        spd[i] = speedOfLane(k);
        // Larger, denser, greener on the cut; smaller, fainter, greyer outwards.
        sz[i] = rnd() < 0.05 ? 4 + rnd() : clamp(2.9 - 1.3 * a + (rnd() - 0.5) * 0.6, 1.5, 3);
        al[i] = clamp(0.9 - 0.4 * a + (rnd() - 0.5) * 0.1, 0.45, 0.9);
        if (k === 0) {
            col[i] = q < 0.7 ? 0 : 1;
        } else if (k > 0 && a < 0.4) {
            col[i] = q < 0.62 ? 0 : (q < 0.9 ? 1 : 2);
        } else {
            col[i] = q < 0.55 ? 0 : (q < 0.8 ? 1 : 2);
        }
        hx[i] = xFromU(u);
        enter(i, late);
    }

    function spawnDust(i, u, late) {
        var q = rnd();
        dust[i] = 1;
        ln[i] = -0.6 + 2.2 * rnd();
        jit[i] = 0;
        amp[i] = WOB_MIN + WOB_RANGE * rnd();
        ampA[i] = 0.45 * amp[i];
        phs[i] = 6.283 * rnd();
        spd[i] = DRIFT_MIN + DRIFT_RANGE * rnd();
        sz[i] = 1.5 + 0.7 * rnd();
        al[i] = 0.45 + 0.2 * rnd();
        col[i] = q < 0.4 ? 0 : (q < 0.55 ? 1 : 2);
        hx[i] = xFromU(u);
        enter(i, late);
    }

    // First fill: each lane gets its share, evenly spaced in time (stratified), like packets.
    function populate(target) {
        var lanesTotal = Math.round(target * (1 - DUST));
        var total = 0, rest = lanesTotal, k, j, cnt, off, i = 0;
        for (k = -KB; k < K; k++) {
            total += laneWeight(k);
        }
        // The lane on the cut takes the rounding remainder.
        for (k = -KB; k < K; k++) {
            if (k !== 0) {
                rest -= Math.round(lanesTotal * laneWeight(k) / total);
            }
        }
        for (k = -KB; k < K; k++) {
            cnt = k === 0 ? Math.max(0, rest) : Math.round(lanesTotal * laneWeight(k) / total);
            off = rnd();
            for (j = 0; j < cnt && i < target; j++) {
                spawnLane(i++, k, (off + (j + 0.5 + 0.6 * (rnd() - 0.5)) / cnt) % 1, false);
            }
        }
        while (i < target) {
            spawnDust(i++, rnd(), false);
        }
    }

    // Measure everything, then size the canvas and (re)place the ribbon.
    function layout(now) {
        var w, h, r, tlx = 0, tty = 0, tw = 0, cut = 0, dw, dh, oldSpan, target, i, len, f;
        lastLayoutAt = now;
        layoutDirty = false;
        query();
        measure();
        w = measW;
        h = measH;
        if (w < 2 || h < 2) {
            layoutDirty = true;
            return false;
        }
        if (tile) {
            r = tile.getBoundingClientRect();
            tlx = (r.left - cLeft) * scX;
            tty = (r.top - cTop) * scY;
            tw = r.width * scX;
            cut = parseFloat(window.getComputedStyle(tile).getPropertyValue('--cut'));
        }
        if (!(cut > 0)) {
            cut = w < 720 ? 40 : 64;
        }

        // Writes from here on.
        dprRaw = Math.min(2, window.devicePixelRatio || 1);
        dpr = Math.min(dprRaw, Math.sqrt(MAX_PIXELS / (w * h)));
        dw = Math.max(1, Math.round(w * dpr));
        dh = Math.max(1, Math.round(h * dpr));
        if (canvas.width !== dw) { canvas.width = dw; }
        if (canvas.height !== dh) { canvas.height = dh; }
        devW = dw;
        devH = dh;

        oldSpan = span;
        W = w;
        H = h;
        span = w + 2 * OVER;
        if (tw > 40) {
            // The tile's top edge: from (left, top + cut) rising to (right, top).
            m = -cut / tw;
            lx0 = tlx;
            ly0 = tty + cut;
            tL = tlx;
            tR = tlx + tw;
        } else {
            m = -0.114; // about 6.5 degrees
            lx0 = w * 0.6;
            ly0 = h * 0.6;
            tL = tR = w * 0.75;
        }
        len = Math.sqrt(1 + m * m);
        ux = 1 / len;
        uy = m / len;
        nx = m / len;   // normal pointing up (and slightly left)
        ny = -1 / len;
        // Stacked layouts (below 1024px) give the ribbon its own gap above the tile, so it may be
        // thicker there than the width-based value; the CSS reserves that room (.hero__grid row-gap).
        band = w < 1024 ? clamp(w * 0.16, 56, 96) : clamp(w * BAND_K, BAND_MIN, BAND_MAX);
        K = Math.max(3, Math.round(band / clamp(band / 8, GAP_MIN, GAP_MAX)));
        KB = Math.max(2, Math.round(K * 0.5));
        gap = band / K;
        cFall = Math.max(220, w * 0.3);
        buildFoot();
        buildCdf();

        target = countFor(w);
        if (n === 0) {
            populate(target);
            // The idle flow keeps running (the page offers a visible "pause animations"
            // control, WCAG 2.2.2). It only eases out when paused; with reduced motion
            // the field is settled at once.
            if (reduced || paused) {
                setSettle(enterEnd);
            }
        } else {
            if (oldSpan > 0 && oldSpan !== span) {
                f = span / oldSpan;
                for (i = 0; i < n; i++) {
                    hx[i] = (hx[i] + OVER) * f - OVER;
                }
            }
            absorb = true; // points glide from where they are to their new place
            for (i = n; i < target; i++) {
                if (rnd() < DUST) {
                    spawnDust(i, rnd(), true);
                } else {
                    spawnLane(i, pickLane(), rnd(), true);
                }
            }
        }
        n = target;
        for (i = 0; i < n; i++) {
            sd[i] = Math.max(1, Math.round(sz[i] * dpr));
        }
        return true;
    }

    /* ── Simulation ───────────────────────────────────────────────────── */

    function considerLink(i, d2) {
        var k;
        if (linkN === LINKS && d2 >= lD[LINKS - 1]) {
            return;
        }
        k = linkN < LINKS ? linkN : LINKS - 1;
        while (k > 0 && lD[k - 1] > d2) {
            lD[k] = lD[k - 1];
            lI[k] = lI[k - 1];
            k--;
        }
        lD[k] = d2;
        lI[k] = i;
        if (linkN < LINKS) {
            linkN++;
        }
    }

    // dt: physics step (springs, pointer); the drift advances by flowD, the eased flow step.
    // still (reduced motion): the settled frame in place, no flow, entrance, springs or
    // push; the pointer strength still eases, but only lights points up (gl) and links.
    function step(dt, still) {
        var t = still ? STATIC_T : flowT;
        var fdt = still ? 0 : flowD;
        var tw1 = t * 0.62, tw2 = t * 0.41, tw3 = t * 0.29, tb = t * 0.45;
        var r2 = REPEL_R * REPEL_R, l2 = LINK_R * LINK_R, g2 = GLOW_R * GLOW_R;
        var kdt = SPRING_K * dt, cdt = SPRING_C * dt;
        var i, k, x0, c, foot, lnv, d, ph, wp, wa, hX, hY, te, e, ex, ey, fade;
        var tx, ty, qx, qy, q2, r, s, push, X, Y, mm, ptrOn, usePtr, ev, g;

        pX = (ptrCX - cLeft) * scX;
        pY = (ptrCY - cTop) * scY;
        ptrOn = ptrIn && mqFine.matches &&
            pX > -24 && pX < W + 24 && pY > -24 && pY < H + 24;
        ptrGoal = ptrOn ? 1 : 0;
        pStr += (ptrGoal - pStr) * (1 - Math.exp(-dt * 7));
        usePtr = pStr > 0.003;
        linkN = 0;
        glowN = 0;
        motV = 0;
        motD = 0;

        for (i = 0; i < n; i++) {
            x0 = hx[i];
            c = coh(x0);
            foot = footAt(x0);
            if (fdt > 0) {
                x0 += spd[i] * (1 - TILE_SLOW * c) * (1 + HIDDEN_FAST * (1 - foot)) * fdt;
                if (x0 > W + OVER) {
                    x0 -= span;
                }
                hx[i] = x0;
            }

            // Home on the ribbon: lane offset, scatter away from the tile, breathing, wobble.
            lnv = ln[i];
            if (lnv >= 0) {
                d = lnv * band * (1 + LOOSEN * (1 - c));
            } else {
                d = lnv * band * (1 - c); // lanes below the cut merge into it over the tile
            }
            if (!dust[i]) {
                d += jit[i] * gap * (0.12 + SCATTER * (1 - c));
            }
            d *= 1 + BREATH * Math.sin(tb + x0 * 0.0025);
            ph = phs[i];
            wp = amp[i] * (0.62 * Math.sin(x0 * 0.0062 + tw1 + ph) + 0.38 * Math.sin(x0 * 0.0141 - tw2 + ph * 1.93));
            if (lnv < 0) {
                wp *= 1 - c;
            }
            wa = ampA[i] * Math.sin(x0 * 0.0037 + tw3 + ph * 2.71);
            d += wp;
            hX = x0 + ux * wa + nx * d;
            hY = ly0 + m * (x0 - lx0) + uy * wa + ny * d;

            // Entrance: gentle (cubic) ease-out flight from the left; expo felt too abrupt.
            ex = 0;
            ey = 0;
            fade = 1;
            if (!still) {
                te = (clock - dl[i]) / du[i];
                if (te < 1) {
                    if (te <= 0) {
                        af[i] = 0;
                        continue;
                    }
                    e = 1 - (1 - te) * (1 - te) * (1 - te);
                    ex = sx0[i] * (1 - e);
                    ey = sy0[i] * (1 - e);
                    fade = te * 4;
                    if (fade > 1) {
                        fade = 1;
                    }
                }
            }

            // Target offset: away from text, away from the pointer.
            deflect(hX, hY);
            tx = defX;
            ty = defY;
            if (usePtr && !still) {
                qx = hX - pX;
                qy = hY - pY;
                q2 = qx * qx + qy * qy;
                if (q2 < r2) {
                    r = Math.sqrt(q2);
                    s = 1 - r / REPEL_R;
                    push = REPEL_PUSH * s * s * pStr;
                    if (r > 0.5) {
                        tx += qx / r * push;
                        ty += qy / r * push;
                    } else {
                        tx += nx * push;
                        ty += ny * push;
                    }
                }
            }
            if (still) {
                ox[i] = tx;
                oy[i] = ty;
                vx[i] = 0;
                vy[i] = 0;
            } else {
                vx[i] += (tx - ox[i]) * kdt - vx[i] * cdt;
                vy[i] += (ty - oy[i]) * kdt - vy[i] * cdt;
                ox[i] += vx[i] * dt;
                oy[i] += vy[i] * dt;
            }

            X = hX + ex + ox[i];
            Y = hY + ey + oy[i];
            if (absorb && seen[i] && !still) {
                ox[i] += px[i] - X;
                oy[i] += py[i] - Y;
                X = px[i];
                Y = py[i];
            }
            // Rest metrics (after the absorb, which can move a point off its target).
            ev = Math.abs(vx[i]) + Math.abs(vy[i]);
            if (ev > motV) {
                motV = ev;
            }
            ev = Math.abs(tx - ox[i]) + Math.abs(ty - oy[i]);
            if (ev > motD) {
                motD = ev;
            }
            px[i] = X;
            py[i] = Y;
            seen[i] = 1;

            mm = foot > 0 ? maskAt(X, Y, FEATHER) * foot : 0;
            mk[i] = mm;
            af[i] = al[i] * fade * mm;

            // Reduced motion: light instead of a push (smooth falloff, never near text).
            if (still) {
                g = 0;
                if (usePtr && mm > 0) {
                    qx = X - pX;
                    qy = Y - pY;
                    q2 = qx * qx + qy * qy;
                    if (q2 < g2) {
                        s = 1 - Math.sqrt(q2) / GLOW_R;
                        g = s * s * (3 - 2 * s) * pStr * mm;
                    }
                }
                gl[i] = g;
                if (g > 0.01) {
                    glowN++;
                }
            }

            if (usePtr && mm > 0.98 && af[i] > 0.12) {
                qx = X - pX;
                qy = Y - pY;
                q2 = qx * qx + qy * qy;
                if (q2 < l2) {
                    considerLink(i, q2);
                }
            }
        }
        absorb = false;
        for (k = 0; k < linkN; k++) {
            i = lI[k];
            af[i] = Math.min(1, af[i] + 0.35 * pStr);
        }
    }

    /* ── Drawing (canvas writes only) ─────────────────────────────────── */

    function draw() {
        var i, k, c, a, s, x, y;
        // Device px per canvas px. Equal to dpr, except for the few frames between a CSS
        // resize of the canvas and the (throttled) relayout: positions then still land
        // where the text mask expects them.
        var kx = measW > 0 ? devW / measW : dpr;
        var ky = measH > 0 ? devH / measH : dpr;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalAlpha = 1;
        ctx.clearRect(0, 0, devW, devH);

        if (linkN && pStr > 0.01) {
            ctx.lineWidth = dpr;
            ctx.strokeStyle = LINK_COLOR;
            x = pX * kx;
            y = pY * ky;
            for (k = 0; k < linkN; k++) {
                i = lI[k];
                a = LINK_ALPHA * pStr * (1 - lD[k] / (LINK_R * LINK_R)) * mk[i];
                if (a < 0.01) {
                    continue;
                }
                ctx.globalAlpha = a;
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(px[i] * kx, py[i] * ky);
                ctx.stroke();
            }
        }

        for (c = 0; c < 3; c++) {
            ctx.fillStyle = COLORS[c];
            for (i = 0; i < n; i++) {
                if (col[i] !== c) {
                    continue;
                }
                a = af[i];
                if (a < 0.01) {
                    continue;
                }
                if (px[i] < zR && py[i] > zT - 24 && py[i] < zB + 24) {
                    continue;
                }
                s = sd[i];
                x = Math.round(px[i] * kx - s * 0.5);
                y = Math.round(py[i] * ky - s * 0.5);
                if (x > devW || y > devH || x < -s || y < -s) {
                    continue;
                }
                ctx.globalAlpha = a;
                ctx.fillRect(x, y, s, s);
            }
        }

        // Reduced motion: the pointer light, HSG green over the same square (same size,
        // same place), so a point near the pointer turns greener and more opaque.
        if (glowN) {
            ctx.fillStyle = COLORS[0];
            for (i = 0; i < n; i++) {
                a = GLOW_A * gl[i];
                if (a < 0.01 || af[i] < 0.01) {
                    continue;
                }
                if (px[i] < zR && py[i] > zT - 24 && py[i] < zB + 24) {
                    continue;
                }
                s = sd[i];
                x = Math.round(px[i] * kx - s * 0.5);
                y = Math.round(py[i] * ky - s * 0.5);
                if (x > devW || y > devH || x < -s || y < -s) {
                    continue;
                }
                ctx.globalAlpha = a;
                ctx.fillRect(x, y, s, s);
            }
        }

        // Hard guarantee: nothing drawn behind text, links included.
        ctx.globalAlpha = 1;
        for (k = 0; k < rc; k += 4) {
            x = Math.floor(R[k] * kx);
            y = Math.floor(R[k + 1] * ky);
            ctx.clearRect(x, y, Math.ceil(R[k + 2] * kx) - x, Math.ceil(R[k + 3] * ky) - y);
        }
    }

    function markReady() {
        if (!ready) {
            ready = true;
            canvas.classList.add('is-ready');
        }
    }

    function clearCanvas() {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    /* ── Loop and lifecycle ───────────────────────────────────────────── */

    // Nothing left to animate: the entrance has landed, the flow has stopped, every
    // spring is at rest and the pointer strength has reached its goal. With reduced
    // motion there is no entrance or flow, so only the pointer light can be pending.
    function atRest() {
        return !layoutDirty && (reduced || (clock >= enterEnd && clock >= settleAt + SETTLE_DUR)) &&
            motV < REST_V && motD < REST_D && Math.abs(ptrGoal - pStr) < REST_P;
    }

    function frame(ts) {
        var dt, ft;
        raf = 0;
        if (!running) {
            return;
        }
        dt = lastTs ? (ts - lastTs) / 1000 : 1 / 60;
        if (dt > 1 / 30) {
            dt = 1 / 30;
        } else if (dt < 0) {
            dt = 0;
        }
        lastTs = ts;
        clock += dt;
        frameNo++;

        // Read phase.
        if (Math.min(2, window.devicePixelRatio || 1) !== dprRaw) {
            layoutDirty = true;
        }
        if (layoutDirty && ts - lastLayoutAt >= RELAYOUT_MS) {
            layout(ts);
        } else if (layoutDirty || measureDirty || ts < MEASURE_UNTIL ||
            window.pageYOffset !== lastScrollY || frameNo % REMEASURE_EVERY === 0) {
            measure();
        }

        // Write phase: canvas only.
        if (n) {
            ft = flowAt(clock);
            flowD = ft > flowT ? ft - flowT : 0;
            flowT = ft;
            step(dt, reduced);
            draw();
            markReady();
            if (atRest()) {
                // The settled frame is drawn: park until wake() (pointer, scroll, resize,
                // re-measure). No self-started motion after this point.
                return;
            }
        }
        raf = window.requestAnimationFrame(frame);
    }

    // Re-arms a parked loop (no-op while it runs, or while it is not allowed to run).
    function wake() {
        if (running && !raf) {
            lastTs = 0;
            raf = window.requestAnimationFrame(frame);
        }
    }

    function start() {
        running = true;
        wake();
    }

    function stop() {
        running = false;
        if (raf) {
            window.cancelAnimationFrame(raf);
            raf = 0;
        }
    }

    // Entrance and idle flow count as over: the points stay where the settled frame
    // puts them (its phase is the settled one), so only the pointer can change it now.
    function settleNow() {
        var i;
        for (i = 0; i < n; i++) {
            dl[i] = -10;
            vx[i] = 0;
            vy[i] = 0;
        }
        enterEnd = clock;
        setSettle(clock - SETTLE_DUR);
    }

    // Both tiers share the loop: with reduced motion each frame is the settled ribbon
    // (step(dt, true)), so the first frame draws it in place and parks; after that it
    // only runs while the pointer light changes.
    function refresh() {
        var wasReduced = reduced;
        reduced = mqReduce.matches;
        forced = mqForced.matches;
        if (n && reduced !== wasReduced) {
            // Live tier switch, either way: no second entrance. Into reduce the next frame
            // snaps to the settled frame; out of it the points are already there and the
            // idle flow picks up again (unless the visitor paused the animations).
            settleNow();
            if (!reduced && !paused) {
                resumeFlow();
            }
        }
        if (forced) {
            stop();
            clearCanvas();
            return;
        }
        if (visible && !doc.hidden) {
            start();
        } else {
            stop();
        }
    }

    function invalidateLayout() {
        layoutDirty = true;
        // The relayout glides the points to their new place (with reduced motion it puts
        // them there), then the loop parks again.
        wake();
    }

    // True for the hero copy and any element inside it.
    function inCopy(el) {
        while (el && el !== hero) {
            if (el.classList && el.classList.contains('hero__copy')) {
                return true;
            }
            el = el.parentNode;
        }
        return false;
    }

    function onChange(mq, fn) {
        if (mq.addEventListener) {
            mq.addEventListener('change', fn);
        } else if (mq.addListener) {
            mq.addListener(fn);
        }
    }

    var passive = { passive: true };

    if ('ResizeObserver' in window) {
        new window.ResizeObserver(invalidateLayout).observe(hero);
    } else {
        window.addEventListener('resize', invalidateLayout, passive);
    }

    if ('IntersectionObserver' in window) {
        new window.IntersectionObserver(function (entries) {
            visible = entries[entries.length - 1].isIntersecting;
            refresh();
        }, { rootMargin: '64px 0px' }).observe(hero);
    }

    doc.addEventListener('visibilitychange', refresh);
    // The page-wide pause button dispatches this (fx/scroll.js, band control).
    doc.addEventListener('fx:pause', function (event) {
        paused = !!(event.detail && event.detail.paused);
        if (paused) {
            pauseFlow();
        } else {
            resumeFlow();
        }
        wake();
    });
    onChange(mqReduce, refresh);
    onChange(mqForced, refresh);

    if (doc.fonts && doc.fonts.ready) {
        doc.fonts.ready.then(invalidateLayout);
    }
    // Only the copy's own entrance moves the text the field keeps clear of (not, say,
    // the countdown digits rolling in the tile every second).
    hero.addEventListener('animationend', function (event) {
        if (inCopy(event.target)) {
            measureDirty = true;
            wake();
        }
    }, passive);

    // Pointer: fine pointers only (checked per frame, so a change of input device just works).
    // The loop only runs for it while the pointer is over the hero, or while its push and
    // links (with reduced motion: its light and links) are still fading out after it left.
    window.addEventListener('pointermove', function (event) {
        if (event.pointerType === 'touch') {
            return;
        }
        ptrCX = event.clientX;
        ptrCY = event.clientY;
        ptrIn = true;
        if (pStr > 0.003 || (mqFine.matches && hero.contains(event.target))) {
            wake();
        }
    }, passive);
    doc.addEventListener('pointerout', function (event) {
        if (!event.relatedTarget) {
            ptrIn = false;
            if (pStr > 0.003) {
                wake();
            }
        }
    }, passive);
    window.addEventListener('blur', function () {
        ptrIn = false;
        if (pStr > 0.003) {
            wake();
        }
    });
    // Scrolling moves the hero under a resting pointer (no pointermove is fired then).
    window.addEventListener('scroll', function () {
        if (ptrIn && mqFine.matches) {
            wake();
        }
    }, passive);
    onChange(mqFine, wake);

    refresh();
})();
