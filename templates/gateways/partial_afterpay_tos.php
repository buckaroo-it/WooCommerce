<?php
/**
 * The Template for displaying afterpay tos gateway template
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */

defined('ABSPATH') || exit;

$fieldName = $this->id === "buckaroo_afterpaynew" ? 'buckaroo-afterpaynew-accept' : 'buckaroo-afterpay-accept';
$tosLinks = [
    "NL"=>"https://documents.myafterpay.com/consumer-terms-conditions/nl_nl/",
    "BE"=>[
        [
        "link"=>"https://documents.myafterpay.com/consumer-terms-conditions/nl_be/",
        "label"=> 'Afterpay conditions (Dutch)'
        ],
        [
        "link"=>"https://documents.myafterpay.com/consumer-terms-conditions/fr_be/",
        "label"=>'Afterpay conditions (French)'
        ]
    ],
    "DE"=>"https://documents.myafterpay.com/consumer-terms-conditions/de_at/",
    "FI"=>"https://documents.myafterpay.com/consumer-terms-conditions/fi_fi/",
    "AT"=>"https://documents.myafterpay.com/consumer-terms-conditions/de_at/"
];
$country = $this->geCheckoutField('billing_country');
$country = !empty($country) ? $country : $this->country;
    
//set default to NL
if (!isset($tosLinks[$country])) { 
    $country = 'NL';
}

$tos = $tosLinks[$country];

?>

<p class="form-row form-row-wide validate-required">
<?php
if (!is_array($tos)) {
    ?>
    <a 
    href="<?php echo $tos ?>"
    target="_blank">
        <?php echo _e('Accept Afterpay conditions:', 'wc-buckaroo-bpe-gateway'); ?>
    </a>
    <?php
} else {
    echo _e('Accept Afterpay conditions:', 'wc-buckaroo-bpe-gateway');
}
?>
    <span class="required">*</span> 
    <input id="<?php echo $fieldName; ?>"
    name="<?php echo $fieldName; ?>"
    type="checkbox"
    value="ON" />
    <?php
    if (is_array($tos)) {
        foreach ($tos as $tosElement) {                
            ?>
            <br>
            <a href="<?php echo $tosElement['link']; ?>" target="_blank">
                <?php echo _e($tosElement['label'], 'wc-buckaroo-bpe-gateway'); ?>
            </a>
            <?php
        }
    }
    ?>
</p>
    
<p class="required" style="float:right;">*
    <?php echo _e('Required', 'wc-buckaroo-bpe-gateway') ?>
</p>
