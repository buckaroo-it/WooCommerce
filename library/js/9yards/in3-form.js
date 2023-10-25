// This file holds the JS & jQuery behind the uploading
// and management of Buckaroo certificates, for the
// master settings page & all buckaroo payment methods.
jQuery(document).ready(function() {

    const in3_v2 = 'In3';
    const in3_v3 = 'iDEAL In3';
    const VERSION2 = 'v2';

    jQuery('#woocommerce_buckaroo_in3_api_version').on('change', function () {
        let apiVersion = jQuery(this).val();
        let titleField = jQuery('#woocommerce_buckaroo_in3_title');

        if (apiVersion === VERSION2) {
            titleField.val(in3_v2);
        } else {
            titleField.val(in3_v3);
        }
    });
});