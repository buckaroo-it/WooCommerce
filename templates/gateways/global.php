<?php
/**
 * The Template for displaying global gateway template
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


if ($this->mode == 'test') {
?>
    <p> <?php echo _e('TEST MODE', 'wc-buckaroo-bpe-gateway');?></p>
<?php
}
if (strlen($this->description)) {
    echo wpautop(wptexturize($this->description));
}
