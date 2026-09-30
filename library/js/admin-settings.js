/**
 * Behaviour for the Buckaroo admin settings screens.
 *
 * This behaviour is inert when its elements are absent, so this file is safe to
 * enqueue on any admin page. Field ids come from wp_localize_script rather than
 * being echoed into the markup.
 */
(function () {
    'use strict';

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
        initHostedFieldsRows();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
