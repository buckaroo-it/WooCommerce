/**
 * Behaviour for the Buckaroo admin settings screens.
 *
 * Both features are inert when their elements are absent, so this file is safe to
 * enqueue on any admin page. Field ids come from wp_localize_script rather than
 * being echoed into the markup.
 */
(function () {
    'use strict';

    /** Show / hide the API credential values. */
    function initKeyToggles() {
        document.querySelectorAll('.bk-key-btn--toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                if (!input) {
                    return;
                }

                var hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';

                var show = button.querySelector('.bk-eye-show');
                var hide = button.querySelector('.bk-eye-hide');
                if (show) {
                    show.style.display = hidden ? 'none' : '';
                }
                if (hide) {
                    hide.style.display = hidden ? '' : 'none';
                }
            });
        });
    }

    /** Only reveal the hosted-fields credentials when that method is selected. */
    function initHostedFieldsRows() {
        var fields = (window.buckarooAdminSettings || {}).hostedFields;
        if (!fields) {
            return;
        }

        var select = document.getElementById(fields.select);
        if (!select) {
            return;
        }

        function toggleRows() {
            var isHostedFields = select.value === 'encrypt';

            [fields.clientId, fields.clientSecret].forEach(function (id) {
                var field = document.getElementById(id);
                var row = field ? field.closest('tr') : null;
                if (row) {
                    row.style.display = isHostedFields ? '' : 'none';
                }
            });
        }

        toggleRows();
        select.addEventListener('change', toggleRows);
    }

    function init() {
        initKeyToggles();
        initHostedFieldsRows();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
