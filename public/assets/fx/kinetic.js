// fx/kinetic.js – live and kinetic details. Progressive enhancement: the page
// is complete without this file (the countdown then shows the server-rendered
// time left, buttons and fields stay as in site.css).
//   1. Countdown in the deadline tile (days, hours, minutes): ticks once a
//      minute against the deadline itself (no drift), changed digits roll;
//      pauses off-screen and in hidden tabs. When the deadline passes, the
//      tile turns into the server's closed state.
//   2. Magnetic buttons [data-magnetic]: pulled a few pixels toward the pointer.
//   3. Pointer light [data-spot]: a soft light under the content of green fields.
//   4. Row sweeps: CSS only (fx/kinetic.css).
//   5. Menu: the sheet opens and closes in a view transition with the IWI cut.
// Pointer effects run only for a fine, hovering pointer. Two motion tiers:
// with motion allowed everything above runs; under prefers-reduced-motion:
// reduce nothing moves through space, but light and opacity stay ("fewer and
// gentler, not off"): the pointer light still follows the pointer and fades,
// the menu sheet cross-fades (kinetic.css), the countdown changes its text
// without rolling, and the magnetic pull is off. A change of the preference
// switches tiers live, in both directions. One requestAnimationFrame loop,
// reads before writes, idle when nothing moves.
(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    root.classList.add('js');

    // ── Tunables ───────────────────────────────────────────────────────────
    var MAG_REACH = 24;      // px around a button that still pulls it
    var MAG_PULL_X = 0.22;   // share of the horizontal pointer offset the button follows
    var MAG_PULL_Y = 0.32;   // share of the vertical pointer offset
    var MAG_MAX = 8;         // px, cap per axis
    var MAG_ICON = 0.45;     // the arrow moves this share of the button's offset again (max 3.6px)
    var MAG_FOLLOW = 0.07;   // s, time constant while pulled (exponential approach)
    var MAG_RETURN = 0.1;    // s, time constant of the spring back: at rest after ~450ms, like ease-out-expo
    var MAG_REST = 0.05;     // px, closer than this counts as arrived
    var CD_SLACK = 12;       // ms after each minute boundary before the clock is read again
    var CD_WORDS = { d: ['day', 'days'], h: ['hour', 'hours'], m: ['minute', 'minutes'] };
    var SPOT = '[data-spot]:not(.tile--closed)';

    var mqMotion = window.matchMedia('all') /* motion always on: reduced-motion setting ignored by decision of the project team, 12.10.2026 */;
    var mqFine = window.matchMedia('(hover: hover) and (pointer: fine)');
    var motionOK = mqMotion.matches;
    var pointerFx = mqFine.matches;          // pointer light: light, not movement, so both tiers
    var magnetFx = motionOK && pointerFx;    // magnetic pull: movement, motion allowed only
    var canTranslate = !!(window.CSS && CSS.supports && CSS.supports('translate', '1px 1px'));

    function onMedia(mq, fn) {
        if (mq.addEventListener) {
            mq.addEventListener('change', fn);
        } else if (mq.addListener) {
            mq.addListener(fn);
        }
    }

    function clamp(value, max) {
        return value > max ? max : (value < -max ? -max : value);
    }

    function px(value) {
        return (Math.round(value * 100) / 100) + 'px';
    }

    function two(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function observe(elements, fn) {
        if (!('IntersectionObserver' in window)) {
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            for (var i = 0; i < entries.length; i++) {
                fn(entries[i].target, entries[i].isIntersecting);
            }
        });
        for (var i = 0; i < elements.length; i++) {
            io.observe(elements[i]);
        }
    }

    // ── State ──────────────────────────────────────────────────────────────
    var ptrIn = false;       // a mouse or pen is over the page
    var ptrX = 0;
    var ptrY = 0;
    var ptrTarget = null;    // element under the pointer
    var ptrStale = false;    // the page scrolled under a resting pointer

    var spotHit = null;      // field under the pointer (read phase)
    var spotOn = null;       // field currently lit
    var spotX = 0;
    var spotY = 0;
    var spotSetX = NaN;      // last values written, to skip redundant writes
    var spotSetY = NaN;

    var magnets = [];
    var cd = null;           // countdown, see setupCountdown()
    var countdownDue = false;

    // ── The one frame loop ─────────────────────────────────────────────────
    var frameId = 0;
    var lastTime = 0;

    function requestFrame() {
        if (!frameId) {
            frameId = window.requestAnimationFrame(frame);
        }
    }

    function frame(now) {
        frameId = 0;
        var dt = lastTime ? Math.min((now - lastTime) / 1000, 0.05) : 1 / 60;

        // Reads: hit test and rects, before anything is written this frame.
        if (pointerFx) {
            readPointer();
        }

        // Writes.
        writeSpot();
        var moving = stepMagnets(dt);
        if (countdownDue) {
            countdownDue = false;
            renderCountdown(motionOK);
        }

        lastTime = moving ? now : 0;
        if (moving) {
            requestFrame();
        }
    }

    function readPointer() {
        if (ptrIn && ptrStale) {
            ptrTarget = doc.elementFromPoint(ptrX, ptrY);
        }
        ptrStale = false;

        spotHit = null;
        var field = ptrIn && ptrTarget && ptrTarget.closest ? ptrTarget.closest(SPOT) : null;
        if (field) {
            var r = field.getBoundingClientRect();
            spotHit = field;
            spotX = Math.round(ptrX - r.left);
            spotY = Math.round(ptrY - r.top);
        }

        if (magnetFx) {
            for (var i = 0; i < magnets.length; i++) {
                aimMagnet(magnets[i]);
            }
        }
    }

    // ── Pointer light ──────────────────────────────────────────────────────
    function writeSpot() {
        var next = pointerFx ? spotHit : null;
        var fresh = false;
        if (next !== spotOn) {
            if (spotOn) {
                spotOn.classList.remove('is-lit');
            }
            spotOn = next;
            spotSetX = NaN;
            spotSetY = NaN;
            fresh = true;
        }
        if (!spotOn) {
            return;
        }
        // Position first, then light up: the fade starts where the pointer is.
        if (spotX !== spotSetX || spotY !== spotSetY) {
            spotOn.style.setProperty('--mx', spotX + 'px');
            spotOn.style.setProperty('--my', spotY + 'px');
            spotSetX = spotX;
            spotSetY = spotY;
        }
        if (fresh) {
            spotOn.classList.add('is-lit');
        }
    }

    // ── Magnetic buttons ───────────────────────────────────────────────────
    if (canTranslate) {
        var magnetEls = doc.querySelectorAll('[data-magnetic]');
        for (var mi = 0; mi < magnetEls.length; mi++) {
            magnets.push({
                el: magnetEls[mi],
                icon: magnetEls[mi].querySelector('.icon'),
                visible: true,
                x: 0, y: 0,      // offset written last frame
                tx: 0, ty: 0,    // where it is heading
                moving: false
            });
        }
        observe(magnetEls, function (el, visible) {
            for (var i = 0; i < magnets.length; i++) {
                if (magnets[i].el === el) {
                    magnets[i].visible = visible;
                }
            }
        });
    }

    function aimMagnet(m) {
        var tx = 0;
        var ty = 0;
        if (ptrIn && m.visible && !m.el.disabled && m.el.getAttribute('aria-busy') !== 'true') {
            var r = m.el.getBoundingClientRect();
            if (r.width > 0) {
                // The rect includes our own offset; take it out so the pull never chases itself.
                var dx = ptrX - (r.left + r.width / 2 - m.x);
                var dy = ptrY - (r.top + r.height / 2 - m.y);
                if (Math.abs(dx) <= r.width / 2 + MAG_REACH && Math.abs(dy) <= r.height / 2 + MAG_REACH) {
                    tx = clamp(dx * MAG_PULL_X, MAG_MAX);
                    ty = clamp(dy * MAG_PULL_Y, MAG_MAX);
                }
            }
        }
        if (tx !== m.tx || ty !== m.ty) {
            m.tx = tx;
            m.ty = ty;
            m.moving = true;
        }
    }

    function stepMagnets(dt) {
        var moving = false;
        for (var i = 0; i < magnets.length; i++) {
            var m = magnets[i];
            if (!m.moving) {
                continue;
            }
            var k = 1 - Math.exp(-dt / (m.tx !== 0 || m.ty !== 0 ? MAG_FOLLOW : MAG_RETURN));
            m.x += (m.tx - m.x) * k;
            m.y += (m.ty - m.y) * k;
            if (Math.abs(m.tx - m.x) < MAG_REST && Math.abs(m.ty - m.y) < MAG_REST) {
                m.x = m.tx;
                m.y = m.ty;
                m.moving = false;
            } else {
                moving = true;
            }
            placeMagnet(m);
        }
        return moving;
    }

    function placeMagnet(m) {
        if (m.x === 0 && m.y === 0) {
            m.el.style.translate = '';
            if (m.icon) {
                m.icon.style.translate = '';
            }
            return;
        }
        m.el.style.translate = px(m.x) + ' ' + px(m.y);
        if (m.icon) {
            m.icon.style.translate = px(m.x * MAG_ICON) + ' ' + px(m.y * MAG_ICON);
        }
    }

    // ── Pointer input (shared by light and magnets) ────────────────────────
    function pointerLeft() {
        if (ptrIn) {
            ptrIn = false;
            ptrTarget = null;
            requestFrame();
        }
    }

    // Every button back to its place at once (no spring: the pull was just switched off).
    function resetMagnets() {
        for (var i = 0; i < magnets.length; i++) {
            var m = magnets[i];
            m.x = m.y = m.tx = m.ty = 0;
            m.moving = false;
            placeMagnet(m);
        }
    }

    function resetPointerFx() {
        ptrIn = false;
        ptrTarget = null;
        spotHit = null;
        if (spotOn) {
            spotOn.classList.remove('is-lit');
            spotOn = null;
        }
        resetMagnets();
    }

    doc.addEventListener('pointermove', function (event) {
        if (!pointerFx || event.pointerType === 'touch') {
            return;
        }
        ptrIn = true;
        ptrX = event.clientX;
        ptrY = event.clientY;
        ptrTarget = event.target;
        ptrStale = false;
        requestFrame();
    }, { passive: true });

    doc.addEventListener('pointerout', function (event) {
        if (!event.relatedTarget && event.pointerType !== 'touch') {
            pointerLeft();
        }
    }, { passive: true });

    window.addEventListener('blur', pointerLeft);

    // Content moves under a resting pointer while scrolling: hit-test again in the next frame.
    window.addEventListener('scroll', function () {
        if (pointerFx && ptrIn) {
            ptrStale = true;
            requestFrame();
        }
    }, { passive: true });

    // ── Countdown ──────────────────────────────────────────────────────────
    // Markup (server-rendered, right without JS):
    //   p.countdown[data-countdown][data-deadline][role=timer] > span.countdown__part >
    //   span.countdown__n[data-cd=d|h|m] (> span.sr-only "9" + span[aria-hidden] "09")
    //   + span.countdown__u[aria-hidden] "h" + span.sr-only " hours"
    // Each number becomes a text for assistive technology (.sr-only, unpadded)
    // plus aria-hidden digit cells that can roll; the spoken unit after it
    // follows the number. Heard: "18 days 9 hours 1 minute".
    function buildUnit(n) {
        var shown = n.querySelector('[aria-hidden="true"]') || n;
        var value = shown.textContent.trim();
        var sr = doc.createElement('span');
        sr.className = 'sr-only';
        var digits = doc.createElement('span');
        digits.className = 'countdown__digits';
        digits.setAttribute('aria-hidden', 'true');
        n.textContent = '';
        n.appendChild(sr);
        n.appendChild(digits);
        var unit = {
            sr: sr,
            word: n.parentNode.querySelector('.countdown__u + .sr-only'),
            words: CD_WORDS[n.getAttribute('data-cd')],
            count: NaN,
            digits: digits,
            cells: [],
            value: ''
        };
        fillCells(unit, value);
        speak(unit, parseInt(value, 10));

        return unit;
    }

    // The spoken figure: "9 hours", not "09 h"; one day, hour or minute in the singular.
    function speak(unit, count) {
        if (count === unit.count) {
            return;
        }
        unit.count = count;
        unit.sr.textContent = isNaN(count) ? '' : String(count);
        if (unit.word) {
            unit.word.textContent = ' ' + unit.words[count === 1 ? 0 : 1];
        }
    }

    function fillCells(unit, value) {
        unit.digits.textContent = '';
        unit.cells.length = 0;
        for (var i = 0; i < value.length; i++) {
            var box = doc.createElement('span');
            box.className = 'countdown__dg';
            var glyph = doc.createElement('span');
            glyph.className = 'countdown__glyph';
            glyph.textContent = value.charAt(i);
            box.appendChild(glyph);
            unit.digits.appendChild(box);
            unit.cells.push({ box: box, glyph: glyph, out: null });
        }
        unit.value = value;
    }

    function setDigit(cell, ch, animate) {
        if (cell.out && cell.out.parentNode) {
            cell.out.parentNode.removeChild(cell.out);
        }
        cell.out = null;
        if (!animate) {
            cell.glyph.className = 'countdown__glyph';
            cell.glyph.textContent = ch;
            return;
        }
        // The current digit leaves upwards (absolute, clipped); the new one rises in its place.
        var glyph = doc.createElement('span');
        glyph.className = 'countdown__glyph is-in';
        glyph.textContent = ch;
        cell.glyph.className = 'countdown__glyph is-out';
        cell.box.appendChild(glyph);
        cell.out = cell.glyph;
        cell.glyph = glyph;
    }

    // count: the number as spoken; value: the digits as shown ("09").
    function setUnit(unit, count, value, animate) {
        speak(unit, count);
        if (value === unit.value) {
            return;
        }
        if (value.length !== unit.value.length) {
            // Days lose a digit (10 → 9): rebuild, no roll.
            fillCells(unit, value);
            return;
        }
        for (var i = 0; i < value.length; i++) {
            if (value.charAt(i) !== unit.value.charAt(i)) {
                setDigit(unit.cells[i], value.charAt(i), animate);
            }
        }
        unit.value = value;
    }

    function renderCountdown(animate) {
        if (!cd || cd.over) {
            return;
        }
        var ms = cd.deadline - Date.now();
        // Whole minutes, rounded up (as on the server), so 0 m is reached exactly at the deadline.
        var left = ms > 0 ? Math.ceil(ms / 60000) : 0;
        window.clearTimeout(cd.timer);
        cd.timer = 0;
        if (left === 0) {
            closeCountdown();
            return;
        }
        var days = Math.floor(left / 1440);
        var hours = Math.floor((left % 1440) / 60);
        var minutes = left % 60;
        // Only figures that changed roll: the minutes once a minute, hours and days at their turn.
        setUnit(cd.units[0], days, String(days), animate);
        setUnit(cd.units[1], hours, two(hours), animate);
        setUnit(cd.units[2], minutes, two(minutes), animate);
        if (cd.running) {
            // Wake just after the next whole minute; every tick reads the clock again.
            cd.timer = window.setTimeout(tick, ms - (left - 1) * 60000 + CD_SLACK);
        }
    }

    // The deadline has passed: the tile takes the server's closed state (grey, "Submissions
    // closed", no countdown, no button). If it passed in the open page, the status line
    // created at setup says so once, politely.
    function closeCountdown() {
        cd.over = true;
        cd.running = false;
        cd.el.classList.add('is-over');
        cd.el.hidden = true;
        var tile = cd.tile;
        if (!tile) {
            return;
        }
        tile.classList.add('tile--closed');
        var label = tile.querySelector('.tile__label');
        if (label) {
            label.textContent = 'Submissions closed'; // the "Provisional" chip goes with it
        }
        var btn = tile.querySelector('.btn');
        if (btn) {
            var hadFocus = btn.contains(doc.activeElement);
            for (var i = magnets.length - 1; i >= 0; i--) {
                if (magnets[i].el === btn) {
                    magnets.splice(i, 1);
                }
            }
            btn.parentNode.removeChild(btn);
            if (hadFocus && label) {
                // Keep focus in the tile instead of letting it fall back to the document.
                label.setAttribute('tabindex', '-1');
                label.focus();
            }
        }
        if (cd.live) {
            cd.live.textContent = 'Submissions closed: the deadline has passed.';
        }
        requestFrame(); // the pointer light leaves the now grey tile
    }

    function tick() {
        cd.timer = 0;
        countdownDue = true;
        requestFrame();
    }

    // Runs while the tile is on screen and the tab is visible; catches up at once on return.
    function syncCountdown(resync) {
        if (!cd || cd.over) {
            return;
        }
        var run = !doc.hidden && cd.inView;
        if (run !== cd.running) {
            cd.running = run;
            if (!run) {
                window.clearTimeout(cd.timer);
                cd.timer = 0;
                return;
            }
            resync = true;
        }
        if (run && resync) {
            countdownDue = true;
            requestFrame();
        }
    }

    // Reduced motion switched on mid-roll: the leaving digit goes and the arriving one stands,
    // at once (an animation removed by the media query may never fire animationend).
    function settleCountdown() {
        if (!cd) {
            return;
        }
        for (var u = 0; u < cd.units.length; u++) {
            var cells = cd.units[u].cells;
            for (var i = 0; i < cells.length; i++) {
                if (cells[i].out && cells[i].out.parentNode) {
                    cells[i].out.parentNode.removeChild(cells[i].out);
                }
                cells[i].out = null;
                cells[i].glyph.className = 'countdown__glyph';
            }
        }
    }

    function settleGlyph(event) {
        var glyph = event.target;
        if (!glyph.classList || !glyph.classList.contains('countdown__glyph')) {
            return;
        }
        if (glyph.classList.contains('is-out')) {
            if (glyph.parentNode) {
                glyph.parentNode.removeChild(glyph);
            }
        } else {
            glyph.classList.remove('is-in');
        }
    }

    function setupCountdown(el) {
        if (!el) {
            return null;
        }
        var deadline = Date.parse(el.getAttribute('data-deadline') || '');
        if (isNaN(deadline)) {
            return null;
        }
        var keys = ['d', 'h', 'm'];
        var numbers = [];
        for (var i = 0; i < keys.length; i++) {
            var n = el.querySelector('[data-cd="' + keys[i] + '"]');
            if (!n) {
                return null;
            }
            numbers.push(n);
        }

        return {
            el: el,
            tile: el.closest ? el.closest('.tile') : null,
            live: null,      // polite status line, see closeCountdown()
            deadline: deadline,
            units: numbers.map(buildUnit),
            timer: 0,
            running: false,
            inView: true,
            over: false
        };
    }

    cd = setupCountdown(doc.querySelector('[data-countdown]'));
    if (cd) {
        renderCountdown(false); // align the server's figures with this clock, silently
        if (!cd.over && cd.tile) {
            // Empty until the deadline passes in the open page (a live region must exist
            // before its text changes to be announced).
            cd.live = doc.createElement('p');
            cd.live.className = 'sr-only';
            cd.live.setAttribute('role', 'status');
            cd.tile.appendChild(cd.live);
        }
        cd.el.addEventListener('animationend', settleGlyph);
        cd.el.addEventListener('animationcancel', settleGlyph);
        observe([cd.el], function (el, visible) {
            cd.inView = visible;
            syncCountdown(false);
        });
        doc.addEventListener('visibilitychange', function () {
            syncCountdown(true);
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                syncCountdown(true);
            }
        });
        syncCountdown(true);
    }

    // ── Menu sheet in a view transition ────────────────────────────────────
    // app.js closes the menu instantly on a link, Escape, focus or a click
    // outside; here only the summary's own toggle (mouse, Enter, Space) runs
    // in a transition, in both motion tiers. kinetic.css draws it: the IWI wipe with
    // motion allowed, a 0.2s cross-fade (opacity only) under reduced motion; without
    // view transitions the menu toggles instantly. The state the menu goes to is
    // decided at click time and only applied in the transition's callback; while
    // a transition is pending, Escape or a press outside the menu turns that
    // state into "closed" and skips the animation, so a pending open can never
    // land after the visitor asked to close.
    var menu = doc.querySelector('[data-menu]');
    var summary = menu ? menu.querySelector('summary') : null;
    var canVT = !!summary && typeof doc.startViewTransition === 'function';
    var vtRunning = 0;
    var menuVT = null;       // the pending menu transition: { transition, next }

    function vtDone() {
        vtRunning = Math.max(0, vtRunning - 1);
        if (!vtRunning) {
            root.classList.remove('is-menu-vt');
        }
    }

    function ignore() {}

    function cancelMenuVT(event) {
        if (!menuVT) {
            return;
        }
        if (event.type === 'keydown' ? event.key === 'Escape' : !menu.contains(event.target)) {
            menuVT.next = false;
            // A skipped transition still runs its update callback (now closing), unless it already ran;
            // then app.js closes the open menu in its own handler, and the wipe stops at once.
            menuVT.transition.skipTransition();
        }
    }

    if (canVT) {
        summary.addEventListener('click', function (event) {
            if (doc.hidden || event.defaultPrevented) {
                return;
            }
            event.preventDefault();
            // A second click while one is pending reverses that one, not a menu.open not yet applied.
            var state = { transition: null, next: menuVT ? !menuVT.next : !menu.open };
            vtRunning++;
            root.classList.add('is-menu-vt');
            try {
                state.transition = doc.startViewTransition(function () {
                    menu.open = state.next;
                });
            } catch (error) {
                if (menuVT) {
                    menuVT.next = state.next;
                }
                menu.open = state.next;
                vtDone();
                return;
            }
            menuVT = state;
            var settle = function () {
                if (menuVT === state) {
                    menuVT = null;
                }
                vtDone();
            };
            state.transition.finished.then(settle, settle);
            // A skipped transition (fast double click, Escape) rejects these; that is expected.
            if (state.transition.ready) {
                state.transition.ready.then(ignore, ignore);
            }
            if (state.transition.updateCallbackDone) {
                state.transition.updateCallbackDone.then(ignore, ignore);
            }
        }, true);
        // Capture phase: runs before app.js, which acts only on menu.open (not yet applied).
        doc.addEventListener('keydown', cancelMenuVT, true);
        doc.addEventListener('pointerdown', cancelMenuVT, { capture: true, passive: true });
    }

    // ── Preference changes ─────────────────────────────────────────────────
    // Switches tiers live in both directions. The menu (wipe or cross-fade) and the row sweeps
    // follow the media queries in kinetic.css on their own; the countdown reads motionOK per tick.
    function syncPreferences() {
        var wasMotion = motionOK;
        motionOK = mqMotion.matches;
        var fine = mqFine.matches;
        var mag = motionOK && fine;
        if (mag !== magnetFx) {
            magnetFx = mag;
            if (mag) {
                if (ptrIn) {
                    requestFrame(); // aim at the resting pointer
                }
            } else {
                resetMagnets();
            }
        }
        if (fine !== pointerFx) {
            pointerFx = fine;
            if (!fine) {
                resetPointerFx();
            }
        }
        if (wasMotion && !motionOK) {
            settleCountdown();
        }
        root.classList.toggle('has-vt', canVT);
    }

    onMedia(mqMotion, syncPreferences);
    onMedia(mqFine, syncPreferences);
    syncPreferences();
})();
