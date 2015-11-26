(function( $ ) {
    "use strict";

    $('body').on('change', 'input[name="payment_method"]', function() { $('body').trigger('update_checkout'); });
})(jQuery);