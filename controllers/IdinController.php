<?php
require_once dirname(__FILE__) . '/../library/include.php';
require_once dirname(__FILE__) . '/../library/api/paymentmethods/paymentmethod.php';

class IdinController
{

    public function returnHandler()
    {
        Buckaroo_Logger::log(__METHOD__ . "|1|", wc_clean($_POST));

        $response = new BuckarooResponseDefault(wc_clean($_POST));

        if ($response && $response->isValid() && $response->hasSucceeded()) {
            $bin = !empty($response->brq_service_idin_consumerbin) ? $response->brq_service_idin_consumerbin : 0;
            $isEighteen = $response->brq_service_idin_iseighteenorolder === 'True';
            Buckaroo_Logger::log(__METHOD__ . "|5|", $bin);
            if ($isEighteen) {
                BuckarooIdin::setCurrentUserIsVerified($bin);
                wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
            } else {
                wc_add_notice(__('According to iDIN you are under 18 years old', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            Buckaroo_Logger::log(__METHOD__ . "|10|");
            wc_add_notice(
                empty($response->statusmessage) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($response->statusmessage),
                'error'
            );
        }

        if (!empty($_REQUEST['bk_redirect']) && is_string($_REQUEST['bk_redirect'])) {
            Buckaroo_Logger::log(__METHOD__ . "|15|");
            wp_safe_redirect($_REQUEST['bk_redirect']);
            exit;
        }
    }

    public function identify()
    {
        if (!BuckarooConfig::isIdin(BuckarooIdin::getCartProductIds())) {
            $this->sendError(esc_html__('iDIN is disabled'));
        }

        if (!is_string($_GET['issuer'] ?? null)) {
            $this->sendError(esc_html__('Please select a issuer from the list'));
        }
        $issuer = sanitize_text_field($_GET['issuer']);

        try {
            $idin = new Buckaroo_Client_Processor(
                new Buckaroo_Idin_Payload($issuer)
            );
            $return = new Buckaroo_Idin_Processor();
            wp_send_json(
                $return->process($idin->process())
            );
        } catch (\Throwable $th) {
            Buckaroo_Logger::log(__METHOD__ . (string)$th);
            $this->sendError(esc_html__('Could not perform the operation'));
        }
    }

    public function reset()
    {
        BuckarooIdin::setCurrentUserIsNotVerified();

        wp_send_json([
            'success'   => true,
        ]);
    }

    private function sendError($error)
    {
        wp_send_json([
            'result'   => 'error',
            'message' => $error
        ]);
    }
}
