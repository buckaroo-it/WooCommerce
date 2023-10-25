// This file holds the JS & jQuery behind the uploading 
// and management of Buckaroo certificates, for the 
// master settings page & all buckaroo payment methods.
jQuery(document).ready(function() {
    

    if (jQuery('.wrap.woocommerce label').first().attr("for") && jQuery('.wrap.woocommerce label').first().attr("for").lastIndexOf('_')) {
        //Get Location
        var label = jQuery('.wrap.woocommerce label').first().attr("for");
        var label = label.substring(0, label.lastIndexOf('_'));
        var ifbuck = label.substring(0, label.lastIndexOf('_'));
        var locationName = label.substring(label.lastIndexOf('_'), label.length);
        if (ifbuck == 'woocommerce_buckaroo'){
            jQuery("select").each(function() {
                this.style.padding = "2px";
            });
        }
        if (ifbuck == 'woocommerce_buckaroo' && locationName == '_mastersettings'){

            //Setup listeners for both buttons
            document.getElementById("woocommerce_buckaroo"+locationName+"_upload").addEventListener("click", imitateChoose);
            document.getElementById("woocommerce_buckaroo"+locationName+"_choosecertificate").addEventListener("change", fileuploaded);

            //Work out the next id for _certificateuploadtime,_certificatecontents & _certificatename
            var key = 1;
            var next_key = 1;
            while(document.getElementById("woocommerce_buckaroo"+locationName+"_certificatecontents"+key) != null) {
                next_key = key;
                key++;
            }
            //Dynamically hide some dynamic fields
            var hide_key = next_key;
            while(hide_key > 0){
                var certificateuploadtimeTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_certificateuploadtime"+hide_key).closest('tr');
                    certificateuploadtimeTR.style.display = "none";
                var certificatecontentsTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_certificatecontents"+hide_key).closest('tr');
                    certificatecontentsTR.style.display = "none";
                var certificatenameTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_certificatename"+hide_key).closest('tr');
                    certificatenameTR.style.display = "none";
                hide_key--;
            }
            //Hide the vanilla upload button & add styles to our one
            var uploadCertificate = document.getElementById("woocommerce_buckaroo"+locationName+"_upload");
                uploadCertificate.className += "button-primary";
                uploadCertificate.value = "Upload";
                uploadCertificate.style.padding = "1px 10px";
            var choosecertificateTR = document.querySelector("#woocommerce_buckaroo"+locationName+"_choosecertificate").closest('tr');
                choosecertificateTR.style.display = "none";

            //If a certificate has been uploaded, print upload time below button 
            var last_upload_time = jQuery("#woocommerce_buckaroo"+locationName+"_certificateuploadtime"+(next_key-1));
            var appended = "<p> No certificate uploaded</p>";
            if (last_upload_time.val()) {
                var appended = "<p>"+last_upload_time.val()+"</p>";
            }
            jQuery(appended).insertAfter(uploadCertificate);

            //Prototype a nicely formatted date in local time (YYYY-MM-DD @ HH:mi:ss)
            Date.prototype.localdatetime = function() {
                var mm = this.getMonth() + 1; // getMonth() is zero-based
                var dd = this.getDate();
                var hh = this.getHours();
                var mi = this.getMinutes();
                var ss = this.getSeconds();
                return [this.getUTCFullYear(),'-',
                    (mm>9 ? '' : '0') + mm,'-',
                    (dd>9 ? '' : '0') + dd,' @ ',
                    (hh>9 ? '' : '0') + hh,':',
                    (mi>9 ? '' : '0') + mi,':',
                    (ss>9 ? '' : '0') + ss,
                ].join('');
            };

            //Imitate vanilla upload button and trigger "choose file" popup
            function imitateChoose() {
                var chooseCertificate = document.querySelector("#woocommerce_buckaroo"+locationName+"_choosecertificate");
                chooseCertificate.click();
            }

            //Upload: Step 1 - Grab file and check it exists
            function fileuploaded(e) {
                var fileInput = jQuery('#woocommerce_buckaroo'+locationName+'_choosecertificate');
                var input = fileInput.get(0);
                
                // Create a reader object
                var reader = new FileReader();
                // var fileName = '';
                if (input.files.length) {
                    var textFile = input.files[0];


                    //Record upload date
                    var new_upload_time = jQuery("#woocommerce_buckaroo"+locationName+"_certificateuploadtime"+next_key);
                    var date = new Date();
                    new_upload_time.val(date.localdatetime());

                    //Record Filename
                    var certificate_name = jQuery("#woocommerce_buckaroo"+locationName+"_certificatename"+next_key);
                    certificate_name.val(date.localdatetime()+": "+textFile.name);

                    reader.readAsText(textFile);
                    jQuery(reader).on('load', processFile);
                }
            }

            //Upload: Step 2 - Get file contents and pass it to ajax
            function processFile(e) {
                var file = e.target.result;
                if (file && file.length) {
                    //Record file contents
                    var destination = jQuery("#woocommerce_buckaroo"+locationName+"_certificatecontents"+next_key);
                    destination.val(file);

                    // //Record upload date
                    // var new_upload_time = jQuery("#woocommerce_buckaroo"+locationName+"_certificateuploadtime"+next_key);
                    // var date = new Date();
                    // new_upload_time.val(date.localdatetime());

                    alert("Success");
                } else {
                    alert("Error");
                }
            }
        }
    }
    buckarooAdmin.init();
});

buckarooAdmin = {
    testButton: function () {
        let buckarooTestButton = jQuery('[id$="test_credentials"]');
        buckarooTestButton.addClass('button-primary');
        buckarooTestButton.val(buckarooTestButton.attr('title'));
    
        buckarooTestButton.on('click', function() {
                let website_key = jQuery('[name^="woocommerce_buckaroo_"][name$="_merchantkey"]').val();
                let secret_key = jQuery('[name^="woocommerce_buckaroo_"][name$="_secretkey"]').val();
                jQuery.post(
                    ajaxurl,
                    {
                        action:'buckaroo_test_credentials',
                        website_key,
                        secret_key
                    },
                    function(response) {
                        alert(response);
                    }
                )
        });
    },
    credicardToggleSelect: function() {
        this.setCredicardSeparate(
            jQuery('#woocommerce_buckaroo_creditcard_creditcardmethod').val()
        );
        var self = this;
        jQuery('#woocommerce_buckaroo_creditcard_creditcardmethod')
        .on('change', function() {
            self.setCredicardSeparate(
                jQuery(this).val()
            );
            
        })
    },
    setCredicardSeparate(value) {
        jQuery('#woocommerce_buckaroo_creditcard_show_in_checkout').closest('tr').toggle(value === 'encrypt');
    },

    in3ToggleLogoSelector() {
        const iconSelector = jQuery('.bk-in3-logo-wrap').closest('tr');
        const apiVersionSelector = jQuery('#woocommerce_buckaroo_in3_api_version');
        iconSelector.toggle(
            apiVersionSelector.val() === 'v3'
        );
        apiVersionSelector.on('change', function() {
            let canShowIconSelector = jQuery(this).val() === 'v3';
            iconSelector.toggle(canShowIconSelector);
        })
    },

    in3FrontEndLabel(){
        jQuery('#woocommerce_buckaroo_in3_api_version').on('change', function () {
            let apiVersion = jQuery(this).val();
            let titleField = jQuery('#woocommerce_buckaroo_in3_title');

            const label = apiVersion === buckaroo_php_vars.version2 ? buckaroo_php_vars.in3_v2:buckaroo_php_vars.in3_v3;
            titleField.val(label)
        });
    },

    init: function() {
        this.testButton();
        this.credicardToggleSelect();
        this.in3ToggleLogoSelector();
        this.in3FrontEndLabel();
    }
}