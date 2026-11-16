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

    /** On phones the section menu scrolls sideways; keep the current section in view. */
    function scrollCurrentSectionIntoView() {
        var current = document.querySelector('ul.subsubsub a.current[href*="tab=buckaroo_settings"]');
        var menu = current && current.closest('ul.subsubsub');
        if (!menu || menu.scrollWidth <= menu.clientWidth) {
            return;
        }

        var item = current.parentElement;
        menu.scrollLeft = item.offsetLeft - menu.offsetLeft - (menu.clientWidth - item.offsetWidth) / 2;
    }

    /**
     * Log search on the Reports page. WooCommerce wraps the page in its settings
     * form, so the search builds a link instead of submitting a form.
     */
    function initLogSearch() {
        var toolbar = document.querySelector('[data-bk-logs-search]');
        if (!toolbar) {
            return;
        }

        var input = toolbar.querySelector('input[type="search"]');
        var button = toolbar.querySelector('.bk-logs-search .button');

        function search() {
            var url = new URL(toolbar.getAttribute('data-bk-logs-search'), window.location.href);
            var query = input.value.trim();
            if (query) {
                url.searchParams.set('s', query);
            } else {
                url.searchParams.delete('s');
            }
            window.location.href = url.toString();
        }

        button.addEventListener('click', search);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                search();
            }
        });
        // Clearing with the search field's "x" shows everything again
        input.addEventListener('search', function () {
            if (input.value === '') {
                search();
            }
        });
    }

    function init() {
        initHostedFieldsRows();
        scrollCurrentSectionIntoView();
        initLogSearch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
