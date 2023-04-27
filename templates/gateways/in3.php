<?php
/**
 * The Template for displaying in3 gateway template
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

$country = $this->getScalarCheckoutField('billing_country');
$country = !empty($country) ? $country : $this->country;

?>
<fieldset>
    <?php if ($country == "NL") : 
        $this->getPaymentTemplate('partial_birth_field');
        ?>
    <?php endif;?>
</fieldset>