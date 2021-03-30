<style>

    .pop-up-effect{
        position:fixed;
        padding:0;
        margin:0;
        top:0;
        left:0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        z-index: 9999;
    }

    .bpe_container {
        background-color: white;
        max-width: 380px;
        margin: 60px auto 0;
        color: #5f7285 !important;
        z-index: 10000;
        position: absolute;
        top: 50%;
        left: 50%;
        margin-right: -50%;
        transform: translate(-50%, -50%);
    }

    .amount {
        font-size: 25px;
    }

    #qrProgress {
        border: solid 2px #f4ede4;
        color: black;
    }

    #qrProgress p {
        font-size: 20px;
    }

    .amountWrapper {
        vertical-align: middle;
        text-align: center;
        margin-left: auto;
        margin-right: auto;
        padding: 18px 35px;
        border: solid 2px #f4ede4;
    }

    .bpe_paymentmethodlogo {
        border: 0!important;
        padding: 0!important;
    }

    /* specific */

    .payconiq-footer {
        color: #5f7285 !important;
    }

    .bpe_container .bpe_paymentform th {
        text-align: left;
        padding: 7px 70px 7px 0;
        line-height: 30px;
        font-weight: normal;
        color: black;
    }

    .bpe_container form {
        padding: 20px 20px 30px;
        border-bottom: 1px solid #efefef;
    }

    /* global styles */
    a {
        color: #4eaaff;
    }

    /* helpers */
    .bpe_hidden {
        display: none;
    }

    /* error styling */
    select.bpe_error_field,
    input.bpe_error_field,
    textarea.bpe_error_field {
        border: 1px solid #FF4646;
        outline: none;
    }

    .bpe_messages {
        background: #fcf8e3;
        padding: 10px;
    }

    .bpe_messages:before {
        content: '';
        display: inline-block;
        vertical-align: top;
        margin-right: 5px;
        opacity: .6;
    }

    .bpe_messages.bpe_info {
        background: #dbeeff;
    }

    .bpe_messages.bpe_error {
        background: #FF8691;
    }

    .bpe_messages.bpe_warning {
        background: #FFCB8F;
    }

    .bpe_messages.bpe_ok {
        background: #BAFFC9;
    }

    .bpe_messages p {
        display: inline-block;
        margin: 0;
        line-height: 22px;
        vertical-align: top;
    }

    #bpe_language_selection {
        padding: 4px;
        border: 1px solid #ddd;
    }

    #bpe_language_selection_form {
        border: none;
        float: right;
        padding: 35px 30px;
    }

    .bpe_paymentform {
        position: relative;
        width: 100%;
    }

    .bpe_description_image {
        cursor: pointer;
        float: right;
        margin: 3px 0 0;
        display: block;
        -moz-box-sizing: border-box;
        -webkit-box-sizing: border-box;
        box-sizing: border-box;
        padding-left: 24px;
    }

    .bpe_description,
    .bpe_explanationhtml {
        border-top: 20px solid #f5f5f5;
    }

    .bpe_container .bpe_paymentform .bpe_description td,
    .bpe_container .bpe_paymentform .bpe_explanationhtml td {
        color: black;
        clear: both;
        padding: 10px;
        width: auto;
    }

    table td {
        color: black !important;
        border: none !important;
    }

    .bpe_explanationhtml h1,
    .bpe_explanationhtml h2,
    .bpe_explanationhtml h3,
    .bpe_explanationhtml h4 {
        margin: 10px;
        font-size: 16px;
    }

    .bpe_explanationhtml p {
        margin: 10px;
    }

    /* Payment method list */
    .bpe_paymentmethod_list {
        margin: 0;
        padding: 0;
        list-style-type: none;
    }

    .bpe_paymentmethod_list li {
        display: block;
        overflow: hidden;
        padding: .5em;
        margin: 0 0 .5em;
        background: #fff;
        cursor: pointer;
        border-bottom: 3px solid #f5f5f5;
    }

    .bpe_paymentmethod_list li:hover {
        border-color: rgba(42, 152, 255, .6);
    }

    .bpe_paymentmethod_list li:active {
        border-color: rgba(42, 152, 255, 1);
    }

    .bpe_paymentmethod_list li label {
        cursor: pointer;
        float: left;
        height: 25px;
        line-height: 25px;
    }

    .bpe_paymentmethod_list li img {
        max-height: 25px;
        float: right;
    }

    .bpe_footer {
        margin: 80px 0 10px;
        border-top: 1px solid #efefef;
        padding-top: 10px;
        text-align: right;
        font-size: .9em;
    }

    .bpe_footer a {
        font-style: italic;
    }

    /* Payconiq specifics */
    #payconiqQrWrapper {
        margin: auto;
        padding: 10px 0 20px;
        color: #fff;
    }

    img {
        display: initial !important;
        max-width: 100%;
        max-height: 100%;
        height: auto;
    }

    .payconiq-cancel {
        float: right;
        font-weight: bold;
    }

    @media only screen and (max-width: 480px) {
        #bpe_language_selection_form {
            padding: 22px 10px;
        }

        .bpe_container h2 {
            font-size: 1.8em;
            margin: 18px 0 -50px 10px;
        }

        .bpe_container .bpe_paymentform th,
        .bpe_container .bpe_paymentform td {
            clear: both;
            display: block;
            width: 100%;
            padding: 0 3px;
            color: black;
        }

        .bpe_container .bpe_paymentform td {
            padding: 0 4px 10px;
            color: black;
        }

        .bpe_footer {
            margin: 70px 0 0;
        }

        /* Payconiq specifics */
        #payconiqQrWrapper {
            margin: 0 -6px -30px -14px;
        }
    }
</style>

<div class="pop-up-effect"></div>

<div class="bpe_container">
    <div class="bpe_logo">
        <img class="bpe_paymentmethodlogo" src="<?PHP echo plugin_dir_url( dirname( __FILE__ ) ) . 'payconiq/payconiq-logo.png' ?>" alt="Payconiq">
    </div>

    <form method="post" action="./pay.aspx" id="ctl01" onsubmit="return FinalizeForm();">
        <div class="aspNetHidden">
            <input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE"
                   value="/wEPDwUENTM4MWRkFUOr5mP6iVgLFIepFecJi58kl+NQIIc3o7Y2P1zO9co="/>
        </div>

        <div class="aspNetHidden">

            <input type="hidden" name="__VIEWSTATEGENERATOR" id="__VIEWSTATEGENERATOR" value="8DDA56B6"/>
        </div>
        <input type="hidden" name="brq_transaction" id="brq_transaction" value="0ED0575FF21C4624B7F1E0975C35FC64"/>
        <input type="hidden" name="brq_service_Payconiq_action" id="brq_service_Payconiq_action" value="Pay"/>
        <input type="hidden" name="bst" id="bst" value="EE37015BF28744BA9866FA2321487F10"/>
        <div class="amountWrapper">
            <span class="amount"><?php echo $_GET['currency'] . ' ' .$_GET['amount'] ?></span>
        </div>

        <div id="payconiqQrWrapper">
            <div id="payconiqQr"></div>
        </div>
        <div class="payconiq-footer">
            <div id="instruction" class="payconiq-qr__footer__texts">
                <span class="payconiq-qr__container__header--text">1. <?php echo __("Open your Payconiq app. ", 'wc-buckaroo-bpe-gateway')?></span> <br>
                <span class="payconiq-qr__container__header--text">2. <?php echo __("Point your camera at this QR code. ", 'wc-buckaroo-bpe-gateway')?></span> <br>
                <span class="payconiq-qr__container__header--text">3. <?php echo __("Confirm payment with your PIN or fingerprint. ", 'wc-buckaroo-bpe-gateway')?> </span>
            </div>
            <div class="payconiq-cancel"><a href="<?php echo $_GET["returnUrl"] ?>&order_id=<?php echo $_GET["order_id"] ?>">Cancel payment</a></div>
        </div>
    </form>

</div>

<script
    src="https://code.jquery.com/jquery-3.3.1.js"
    integrity="sha256-2Kok7MbOyxpgUVvAk/HJ2jigOSYS2auK4Pfzbm7uH60="
    crossorigin="anonymous"></script>
<script src="https://checkout.buckaroo.nl/api/buckaroosdk/script"></script>
<script>
    $(document).ready(function () {
        BuckarooSdk.Payconiq.initiate("#payconiqQr", "<?php echo $_GET["transactionKey"] ?>", function(status, params) {
            if (status == 'PROCESSING'){
                $('.payconiq-cancel').hide();
            }
            // true if the SDK should redirect the browser to the ReturnUrl
            return true;
        });
    });
</script>