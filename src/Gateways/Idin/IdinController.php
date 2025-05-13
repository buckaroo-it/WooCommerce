<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;

class IdinController
{
    public function returnHandler()
    {
        Logger::log(__METHOD__ . '|1|', wc_clean($_POST));

        $responseParser = ResponseRegistry::getResponseFromRequest();
        $buckarooClient = new BuckarooClient($responseParser->isTest() ? 'test' : 'live');

        if ($buckarooClient->isReplyHandlerValid($responseParser->get(null, null, false)) && $responseParser->isSuccess()) {
            $bin = $responseParser->getService('consumerbin');
            $isEighteen = $responseParser->getService('iseighteenorolder') === 'True' ? 1 : 0;
            Logger::log(__METHOD__ . '|5|', $bin);
            if ($isEighteen) {
                IdinProcessor::setCurrentUserIsVerified($bin);
                wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
            } else {
                wc_add_notice(__('According to iDIN you are under 18 years old', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            Logger::log(__METHOD__ . '|10|');
            wc_add_notice(
                empty($responseParser->getStatusMessage()) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($responseParser->getStatusMessage()),
                'error'
            );
        }

        if (! empty($_REQUEST['bk_redirect']) && is_string($_REQUEST['bk_redirect'])) {
            Logger::log(__METHOD__ . '|15|');
            wp_safe_redirect($_REQUEST['bk_redirect']);
            exit;
        }
    }

    public function identify()
    {
        if (! IdinProcessor::isIdin(IdinProcessor::getCartProductIds())) {
            $this->sendError(esc_html__('iDIN is disabled'));
        }

        if (! is_string($_GET['issuer'] ?? null)) {
            $this->sendError(esc_html__('Please select a issuer from the list'));
        }
        $issuer = sanitize_text_field($_GET['issuer']);

        try {
            $gateway = new IdinGateway();
            $gateway->issuer = $issuer;

            wp_send_json(
                $gateway->process_payment('')
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__ . (string) $th);
            $this->sendError(esc_html__('Could not perform the operation'));
            throw $th;
        }
    }

    private function sendError($error)
    {
        wp_send_json(
            [
                'result' => 'error',
                'message' => $error,
            ]
        );
    }

    public function reset()
    {
        IdinProcessor::setCurrentUserIsNotVerified();

        wp_send_json(
            [
                'success' => true,
            ]
        );
    }
}
