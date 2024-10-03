<?php
require_once __DIR__ . '/../library/include.php';
require_once __DIR__ . '/../library/api/paymentmethods/paymentmethod.php';

class RivertyController {
    public function returnHandler() {
        $post_data = wc_clean($_POST);
        $response = new BuckarooResponseDefault($post_data);

        if ($response && $response->isValid() && $response->hasSucceeded()) {
            wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
        } else {
            wc_add_notice(
                empty($response->statusmessage) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($response->statusmessage),
                'error'
            );
        }

        if (!empty($_REQUEST['bk_redirect']) && is_string($_REQUEST['bk_redirect'])) {
            wp_safe_redirect($_REQUEST['bk_redirect']);
            exit;
        }
    }
}
