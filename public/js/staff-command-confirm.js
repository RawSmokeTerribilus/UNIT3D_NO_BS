(function () {
    'use strict';

    function bindConfirmHandlers() {
        var forms = document.querySelectorAll('form[data-confirm]');
        for (var i = 0; i < forms.length; i++) {
            (function (form) {
                if (form.dataset.confirmBound === '1') {
                    return;
                }
                form.dataset.confirmBound = '1';
                form.addEventListener('submit', function (event) {
                    // NOBS: un botón con su propio data-confirm (p. ej. el de «último
                    // aviso» junto al de banear) manda sobre el del formulario.
                    var submitter = event.submitter;
                    var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
                    if (message && !window.confirm(message)) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                });
            })(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindConfirmHandlers);
    } else {
        bindConfirmHandlers();
    }
})();
