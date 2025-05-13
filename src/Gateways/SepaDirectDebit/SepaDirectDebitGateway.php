<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class SepaDirectDebitGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = SepaDirectDebitProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_sepadirectdebit';
        $this->title = 'SEPA Direct Debit';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo SEPA Direct Debit';
        $this->setIcon('svg/sepa-directdebit.svg');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        $iban = $this->request->input('buckaroo-sepadirectdebit-iban');
        if (
            $this->request->input('buckaroo-sepadirectdebit-accountname') === null ||
            $iban === null
        ) {
            wc_add_notice(__('Please fill in all required fields', 'wc-buckaroo-bpe-gateway'), 'error');
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        if (! $this->isIBAN($iban)) {
            wc_add_notice(__('Wrong IBAN number', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        parent::validate_fields();
    }

    /**
     * Calculate checksum from iban and confirm validity of iban
     *
     * @param  string  $iban
     * @return bool
     */
    public static function isIBAN($iban)
    {
        // Normalize input (remove spaces and make upcase)
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            $country = substr($iban, 0, 2);
            $check = intval(substr($iban, 2, 2));
            $account = substr($iban, 4);

            // To numeric representation
            $search = range('A', 'Z');
            foreach (range(10, 35) as $tmp) {
                $replace[] = strval($tmp);
            }
            $numstr = str_replace($search, $replace, $account . $country . '00');

            // Calculate checksum
            $checksum = intval(substr($numstr, 0, 1));
            for ($pos = 1; $pos < strlen($numstr); $pos++) {
                $checksum *= 10;
                $checksum += intval(substr($numstr, $pos, 1));
                $checksum %= 97;
            }

            return  (98 - $checksum) == $check;
        } else {
            return false;
        }
    }
}
