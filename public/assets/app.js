// Progressive enhancement for the submission form. The server checks
// everything again; this only saves people from waiting for an upload that
// would be rejected, and from submitting twice.
(function () {
    'use strict';

    var errors = document.getElementById('form-errors');
    if (errors) {
        errors.focus();
    }

    var form = document.querySelector('form[data-submission]');
    if (!form) {
        return;
    }

    var input = form.querySelector('input[type="file"]');
    var button = form.querySelector('button[type="submit"]');
    var maxBytes = parseInt(form.getAttribute('data-max-bytes'), 10);
    var buttonLabel = button.textContent;

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        var message = '';
        if (file && file.size > maxBytes) {
            message = 'This file is larger than ' + Math.round(maxBytes / 1048576) + ' MB. Please reduce its size (e.g. compress images) and try again.';
        } else if (file && !/\.pdf$/i.test(file.name)) {
            message = 'Please choose a PDF file.';
        }
        input.setCustomValidity(message);
        if (message) {
            input.reportValidity();
        }
    });

    form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-sending') === '1') {
            event.preventDefault();
            return;
        }
        form.setAttribute('data-sending', '1');
        button.disabled = true;
        button.textContent = 'Uploading… please keep this page open';
    });

    // Coming back via the browser's back button must not leave the form locked.
    window.addEventListener('pageshow', function () {
        form.removeAttribute('data-sending');
        button.disabled = false;
        button.textContent = buttonLabel;
    });
})();
