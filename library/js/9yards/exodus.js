jQuery(document).ready(function() {
    if (jQuery('label').first().attr("for") && jQuery('label').first().attr("for").lastIndexOf('_')) {
        //Setup Location
        var label = jQuery('label').first().attr("for");
        var label = label.substring(0, label.lastIndexOf('_'));
        var locationName = label.substring(label.lastIndexOf('_'), label.length);
        if (locationName == '_mastersettings') {
            var exodusButton = document.getElementById("woocommerce_buckaroo_mastersettings_exodus");
                exodusButton.addEventListener("click", Primer);
                exodusButton.className += "button-primary";
                exodusButton.style.padding = "1px 10px";
                
            var name_translation = "Migrate";
                if (jQuery('label').first()[0].textContent == 'Migratie instellingen'){
                  name_translation = "Migratie"
                };
                exodusButton.value = name_translation;
            function Primer() {
                BeginExodus(exodusButton);
            }

            function BeginExodus(appendto) {
                jQuery.ajax({
                async: false,
                type: 'POST',
                url: "/?wc-api=WC_Gateway_Buckaroo_Exodus",
                dataType: "json",
                success: function(res) {
                    console.log(res);
                    if (res) {
                        appended = '<p>'+res+'</p>';
                    }

                  jQuery(appended).insertAfter(appendto);
                },
                error : function(error){
                    console.log(error.responseText);
                    if (error.responseText){
                        appended = '<p>'+error.responseText+'</p>';
                    }
                    jQuery(appended).insertAfter(appendto);
                }
              });
            }
        }
    }
});
