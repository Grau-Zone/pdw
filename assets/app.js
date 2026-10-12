// Progressive enhancement. The page works without this file: the server
// checks everything again; this only saves people from waiting for an upload
// that would be rejected, from submitting twice, and makes the menu and the
// file field nicer to use.
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    // Menu (<details>): close after choosing a section, on Escape, and when
    // focus or a click leaves it, so it never covers what has focus.
    var menu = document.querySelector('[data-menu]');
    if (menu) {
        var close = function () {
            menu.open = false;
        };
        menu.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                close();
            }
        });
        menu.addEventListener('focusout', function (event) {
            if (menu.open && event.relatedTarget && !menu.contains(event.relatedTarget)) {
                close();
            }
        });
        document.addEventListener('pointerdown', function (event) {
            if (menu.open && !menu.contains(event.target)) {
                close();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.open) {
                close();
                menu.querySelector('summary').focus();
            }
        });
    }

    var errors = document.getElementById('form-errors');
    if (errors) {
        errors.focus();
    }

    var form = document.querySelector('form[data-submission]');
    if (!form) {
        return;
    }

    var input = form.querySelector('input[type="file"]');
    var fileBox = form.querySelector('[data-file]');
    var fileName = form.querySelector('[data-file-name]');
    var fileError = form.querySelector('[data-file-error]');
    var maxBytes = parseInt(form.getAttribute('data-max-bytes'), 10);
    var emptyName = fileName ? fileName.textContent : '';

    function check() {
        var file = input.files && input.files[0];
        var message = '';
        if (file && file.size > maxBytes) {
            message = 'This file is larger than ' + Math.round(maxBytes / 1048576) + ' MB. Please reduce its size (e.g. compress images) and try again.';
        } else if (file && !/\.pdf$/i.test(file.name)) {
            message = 'Please choose a PDF file.';
        }
        input.setCustomValidity(message);
        if (fileName) {
            fileName.textContent = file ? file.name : emptyName;
        }
        if (fileError) {
            fileError.textContent = message;
        }
        if (fileBox) {
            fileBox.toggleAttribute('data-filled', !!file && !message);
            fileBox.toggleAttribute('data-invalid', !!message);
        }

        return message;
    }

    if (input) {
        input.addEventListener('change', function () {
            if (check()) {
                input.reportValidity();
            }
        });
    }

    // In the static preview the button is disabled and not a submit button.
    var label = form.querySelector('[data-label]');
    var button = label ? label.closest('button') : null;
    if (!button || button.type !== 'submit') {
        return;
    }
    var buttonLabel = label.textContent;

    form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-sending') === '1') {
            event.preventDefault();
            return;
        }
        form.setAttribute('data-sending', '1');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        label.textContent = 'Uploading… please keep this page open';
    });

    // Coming back via the browser's back button must not leave the form locked.
    window.addEventListener('pageshow', function () {
        form.removeAttribute('data-sending');
        button.disabled = false;
        button.removeAttribute('aria-busy');
        label.textContent = buttonLabel;
        if (input) {
            check();
        }
    });
})();
