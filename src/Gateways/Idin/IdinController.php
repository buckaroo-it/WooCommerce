<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;

class IdinController
{
    public function returnHandler()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Buckaroo return; logs field names only.
        Logger::log(__METHOD__ . '|1|', ['request_fields' => array_keys($_POST)]);

        $responseParser = ResponseRegistry::getResponseFromRequest();

        if (! $this->isValidReply($responseParser) || ! $responseParser->isSuccess()) {
            Logger::log(__METHOD__ . '|10|');
            wc_add_notice(
                empty($responseParser->getStatusMessage()) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($responseParser->getStatusMessage()),
                'error'
            );
        } elseif (! IdinProcessor::consumeVerification($responseParser->getAdditionalInformation('idin_nonce'))) {
            Logger::log(__METHOD__ . '|12|');
            wc_add_notice(__('Verification has been failed', 'wc-buckaroo-bpe-gateway'), 'error');
        } else {
            $bin = $responseParser->getService('consumerbin');
            $isEighteen = $responseParser->getService('iseighteenorolder') === 'True' ? 1 : 0;
            Logger::log(__METHOD__ . '|5|', ['is_eighteen_or_older' => $isEighteen]);
            if ($isEighteen) {
                IdinProcessor::setCurrentUserIsVerified($bin);
                wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
            } else {
                wc_add_notice(__('According to iDIN you are under 18 years old', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Buckaroo return; the target is limited to allowed hosts by wp_safe_redirect().
        $hasRedirect = ! empty($_REQUEST['bk_redirect']) && is_string($_REQUEST['bk_redirect']);
        $redirect = $hasRedirect ? esc_url_raw(wp_unslash($_REQUEST['bk_redirect'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($hasRedirect) {
            Logger::log(__METHOD__ . '|15|');
            // An address esc_url_raw() rejects goes to the same fallback wp_safe_redirect() uses.
            wp_safe_redirect($redirect !== '' ? $redirect : admin_url());
            exit;
        }
    }

    protected function isValidReply(ResponseParser $responseParser): bool
    {
        $buckarooClient = new BuckarooClient($responseParser->isTest() ? 'test' : 'live');

        return $buckarooClient->isReplyHandlerValid($responseParser->get(null, null, false));
    }

    public function identify()
    {
        if (! $this->hasValidNonce()) {
            $this->sendError(esc_html__('Could not perform the operation', 'wc-buckaroo-bpe-gateway'));
        }

        if (! IdinProcessor::isIdin(IdinProcessor::getCartProductIds())) {
            $this->sendError(esc_html__('iDIN is disabled', 'wc-buckaroo-bpe-gateway'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified above by hasValidNonce().
        if (! is_string($_GET['issuer'] ?? null)) {
            $this->sendError(esc_html__('Please select a issuer from the list', 'wc-buckaroo-bpe-gateway'));
        }
        $issuer = sanitize_text_field(wp_unslash($_GET['issuer']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (! IdinProcessor::checkIfValidIssuer($issuer)) {
            $this->sendError(esc_html__('Please select a issuer from the list', 'wc-buckaroo-bpe-gateway'));
        }

        try {
            $gateway = new IdinGateway();
            $gateway->issuer = $issuer;

            wp_send_json(
                $gateway->process_payment('')
            );
        } catch (Throwable $th) {
            Logger::log(__METHOD__, $th);
            $this->sendError(esc_html__('Could not perform the operation', 'wc-buckaroo-bpe-gateway'));
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

    private function hasValidNonce(): bool
    {
        if (! isset($_REQUEST['_wpnonce']) || ! is_string($_REQUEST['_wpnonce'])) {
            return false;
        }

        return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), IdinProcessor::NONCE_ACTION);
    }

    public function reset()
    {
        if (! $this->hasValidNonce()) {
            $this->sendError(esc_html__('Could not perform the operation', 'wc-buckaroo-bpe-gateway'));
        }

        IdinProcessor::setCurrentUserIsNotVerified();

        wp_send_json(
            [
                'success' => true,
            ]
        );
    }
}
