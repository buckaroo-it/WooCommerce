jQuery(document).ready(function ($) {

    if ($('#woocommerce_buckaroo_mastersettings_culture').length > 0 ) {
    var browserLanguage = navigator.language || navigator.userLanguage;

    var languageMap = {
        'en': 'en-US',
        'nl': 'nl-NL',
        'fr': 'fr-FR',
        'de': 'de-DE'
    };

    var selectedOption = languageMap[browserLanguage] || 'en-US';
    $('#woocommerce_buckaroo_mastersettings_culture').val(selectedOption);
}
});