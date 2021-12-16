jQuery(document).ready(function() {
    if (jQuery('label').first().attr("for") && jQuery('label').first().attr("for").lastIndexOf('_')) {
        //Get Location
        var label = jQuery('label').first().attr("for");
        var label = label.substring(0, label.lastIndexOf('_'));
        var locationName = label.substring(label.lastIndexOf('_'), label.length);
        
        var usemaster = jQuery("#woocommerce_buckaroo"+locationName+"_usemaster");
        var checked = (usemaster.attr('checked') ? 1 : 0);
        if (usemaster.length != 0){
            
            function togglelocal(checked){
                // console.log('usemaster:'+checked);

                var merchantKeyTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_merchantkey").closest('tr');
                var secretKeyTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_secretkey").closest('tr');
                var thumbprintTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_thumbprint").closest('tr');
                var uploadButtonTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_upload").closest('tr');
                // var modeTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_mode").closest('tr');
                var selectCertificateTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_selectcertificate").closest('tr');
                var cultureTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_culture").closest('tr');
                var transactionDescriptionTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_transactiondescription").closest('tr');
                if (checked == 1) {
                    merchantKeyTR.style.display = "none";
                    secretKeyTR.style.display = "none";
                    thumbprintTR.style.display = "none";
                    uploadButtonTR.style.display = "none";
                    // modeTR.style.display = "none";
                    selectCertificateTR.style.display = "none";
                    cultureTR.style.display = "none";
                    transactionDescriptionTR.style.display = "none";
                } else {
                    merchantKeyTR.style.display = "table-row";
                    secretKeyTR.style.display = "table-row";
                    thumbprintTR.style.display = "table-row";
                    uploadButtonTR.style.display = "table-row";
                    // modeTR.style.display = "table-row";
                    selectCertificateTR.style.display = "table-row";
                    cultureTR.style.display = "table-row";
                    transactionDescriptionTR.style.display = "table-row";
                }
                return checked;
            }

            //Initialise correct display
            togglelocal(checked);
            //Watch for clicks that tick/untick
            jQuery(usemaster).click(function() {
                checked = (checked == 1 ? 0 : 1);
                togglelocal(checked);
            });
        }
    }
});