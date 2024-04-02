<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\KlarnaKP\Models;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Address;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Email;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Person;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Phone;
use WC_Buckaroo\Dependencies\Buckaroo\Models\ServiceParameter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\KlarnaKP\Service\ParameterKeys\AddressAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\KlarnaKP\Service\ParameterKeys\EmailAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\KlarnaKP\Service\ParameterKeys\PhoneAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\KlarnaKP\Service\ParameterKeys\RecipientAdapter;

class Recipient extends ServiceParameter
{
    private string $type;

    protected RecipientAdapter $recipient;

    protected AddressAdapter $address;

    protected PhoneAdapter $phone;

    protected EmailAdapter $email;

    public function __construct(string $type, ?array $values = null)
    {
        $this->type = $type;

        parent::__construct($values);
    }

    public function phone($phone = null)
    {
        if (is_array($phone))
        {
            $this->phone = new PhoneAdapter(new Phone($phone), $this->type);
        }

        return $this->phone;
    }

    public function email($email = null)
    {
        if (is_string($email))
        {
            $this->email = new EmailAdapter(new Email($email), $this->type);
        }

        return $this->email;
    }

    public function address($address = null)
    {
        if (is_array($address))
        {
            $this->address = new AddressAdapter(new Address($address), $this->type);
        }

        return $this->address;
    }

    public function recipient($recipient = null)
    {
        if (is_array($recipient))
        {
            $this->recipient = new RecipientAdapter(new Person($recipient), $this->type);
        }

        return $this->recipient;
    }
}
