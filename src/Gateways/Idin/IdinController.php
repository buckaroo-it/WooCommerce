<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Woocommerce\Components\OrderArticles;
use Buckaroo\Woocommerce\Components\OrderDetails;
use Buckaroo\Woocommerce\Handlers\ResponseHandlers\ResponseParser;
use Buckaroo\Woocommerce\SDK\BuckarooClient;
use Buckaroo_Http_Request;
use Buckaroo_Logger;
use BuckarooConfig;
use Throwable;
use WC_Order;

class IdinController
{
    public function returnHandler()
    {
        $post_data = wc_clean($_POST);
        $idinProcessor = new IdinProcessor(
            new IdinGateway(),
            new Buckaroo_Http_Request(),
            $od = new OrderDetails(new WC_Order),
            new OrderArticles($od, new IdinGateway())
        );
        $payment = new BuckarooClient($idinProcessor);
        $replyHandler = new ReplyHandler($payment->client()->config(), $post_data);
        $responseParser = ResponseParser::make($post_data);

        // set_current_user($responseParser->getAdditionalInformation('current_user_id'));

        if ($replyHandler->validate()->isValid() && ($responseParser->isSuccess() || $responseParser->isPendingProcessing())) {
            $bin = $responseParser->getService('consumerbin');
            $isEighteen = $responseParser->getService('iseighteenorolder') === 'True';

            if ($isEighteen) {
                IdinProcessor::setCurrentUserIsVerified($bin);
                wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
            } else {
                wc_add_notice(__('According to iDIN you are under 18 years old', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            wc_add_notice(
                empty($responseParser->getSubCodeMessage()) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($responseParser->getSubCodeMessage()),
                'error'
            );
        }

        if (!empty($_REQUEST['bk_redirect']) && is_string($_REQUEST['bk_redirect'])) {
            wp_safe_redirect($_REQUEST['bk_redirect']);
            exit;
        }
    }

    public function identify()
    {
        if (!BuckarooConfig::isIdin(IdinProcessor::getCartProductIds())) {
            $this->sendError(esc_html__('iDIN is disabled'));
        }

        if (!is_string($_GET['issuer'] ?? null)) {
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
            Buckaroo_Logger::log(__METHOD__ . (string)$th);
            $this->sendError(esc_html__('Could not perform the operation'));
            throw $th;
        }
    }

    private function sendError($error)
    {
        wp_send_json(
            array(
                'result' => 'error',
                'message' => $error,
            )
        );
    }

    public function reset()
    {
        IdinProcessor::setCurrentUserIsNotVerified();

        wp_send_json(
            array(
                'success' => true,
            )
        );
    }
}
