<?php
require_once dirname(__FILE__) . '/../library/include.php';
require_once dirname(__FILE__) . '/../library/api/paymentmethods/paymentmethod.php';

class IdinController
{
    private $logger;

    public function __construct()
    {
        $this->logger = new BuckarooLogger(BuckarooLogger::INFO, 'idin');
    }

    public function returnHandler()
    {
        $this->logger->logInfo(__METHOD__ . "|1|", $_POST);

        $response = new BuckarooResponseDefault($_POST);

        if ($response && $response->isValid() && $response->hasSucceeded()) {
            $bin = !empty($response->brq_service_idin_consumerbin) ? $response->brq_service_idin_consumerbin : 0;
            $isEighteen = $response->brq_service_idin_iseighteenorolder === 'True' ? 1 : 0;
            $this->logger->logInfo(__METHOD__ . "|5|", $bin);
            if ($isEighteen) {
                BuckarooIdin::setCurrentUserIsVerified($bin);
                wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
            } else {
                wc_add_notice(__('According to iDIN you are under 18 years old', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            $this->logger->logInfo(__METHOD__ . "|10|");
            wc_add_notice(
                empty($response->statusmessage) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($response->statusmessage),
                'error');
        }

        if (!empty($_REQUEST['bk_redirect'])) {
            $this->logger->logInfo(__METHOD__ . "|15|");
            wp_safe_redirect($_REQUEST['bk_redirect']);
        }
    }

    public function identify()
    {
        $this->logger->logInfo(__METHOD__ . "|1|");

        if (!BuckarooConfig::isIdin(BuckarooIdin::getCartProductIds())) {
            $this->sendError('iDIN is disabled');
        }

        $data = [];
        $data['currency']         = 'EUR';
        $data['amountDebit']      = 0;
        $data['amountCredit']     = 0;
        $data['mode'] = BuckarooConfig::getIdinMode();
        $url = parse_url($_SERVER['HTTP_REFERER']);
        $data['returnUrl'] = $url['scheme'] . '://' . $url['host'] . '/' . $url[' path'] .
            '?wc-api=WC_Gateway_Buckaroo_idin-return&bk_redirect='.urlencode($_SERVER['HTTP_REFERER']);
        $data['continueonincomplete'] = 'redirecttohtml';

        $data['services']['idin']['action']  = 'verify';
        $data['services']['idin']['version'] = '0';
        $data['customVars']['idin']['issuerId'] =
            (isset($_GET['issuer']) && BuckarooIdin::checkIfValidIssuer($_GET['issuer'])) ? $_GET['issuer'] : '';

        $soap = new BuckarooSoap($data);

        $response = BuckarooResponseFactory::getResponse($soap->transactionRequest('DataRequest'));

        $this->logger->logInfo(__METHOD__ . "|5|", $response);

        $processedResponse = fn_buckaroo_process_response(null, $response);

        $this->logger->logInfo(__METHOD__ . "|10|", $processedResponse);

        echo json_encode($processedResponse, JSON_PRETTY_PRINT);
        exit;
    }

    public function reset()
    {
        BuckarooIdin::setCurrentUserIsNotVerified();

        echo 'ok';
        die();
    }

    private function sendError($error)
    {
        echo json_encode([
            'result'   => 'error',
            'message' => $error
        ], JSON_PRETTY_PRINT);
        die();
    }
}
