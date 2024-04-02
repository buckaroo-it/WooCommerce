<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) WC_Buckaroo\Dependencies\Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3Old\Models;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Address;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Company;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Email;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Person;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Phone;
use WC_Buckaroo\Dependencies\Buckaroo\Models\ServiceParameter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Billink\Models\Article;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3Old\Service\ParameterKeys\AddressAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3Old\Service\ParameterKeys\ArticleAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3Old\Service\ParameterKeys\CompanyAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3Old\Service\ParameterKeys\PhoneAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Traits\CountableGroupKey;

class Pay extends ServiceParameter
{
    use CountableGroupKey;

    /**
     * @var array|string[]
     */
    private array $countableProperties = ['articles', 'subtotals'];

    /**
     * @var string
     */
    protected string $customerType;
    /**
     * @var string
     */
    protected string $invoiceDate;

    /**
     * @var Person
     */
    protected Person $customer;
    /**
     * @var CompanyAdapter
     */
    protected CompanyAdapter $company;
    /**
     * @var AddressAdapter
     */
    protected AddressAdapter $address;
    /**
     * @var Email
     */
    protected Email $email;
    /**
     * @var PhoneAdapter
     */
    protected PhoneAdapter $phone;

    /**
     * @var array
     */
    protected array $articles = [];
    /**
     * @var array
     */
    protected array $subtotals = [];

    /**
     * @var array|\string[][]
     */
    protected array $groupData = [
        'articles' => [
            'groupType' => 'ProductLine',
        ],
        'address' => [
            'groupType' => 'Address',
        ],
        'customer' => [
            'groupType' => 'Person',
        ],
        'company' => [
            'groupType' => 'Company',
        ],
        'phone' => [
            'groupType' => 'Phone',
        ],
        'email' => [
            'groupType' => 'Email',
        ],
    ];

    /**
     * @param array|null $articles
     * @return array
     */
    public function articles(?array $articles = null)
    {
        if (is_array($articles))
        {
            foreach ($articles as $article)
            {
                $this->articles[] = new ArticleAdapter(new Article($article));
            }
        }

        return $this->articles;
    }

    /**
     * @param $company
     * @return CompanyAdapter
     */
    public function company($company = null)
    {
        if (is_array($company))
        {
            $this->company = new CompanyAdapter(new Company($company));
        }

        return $this->company;
    }

    /**
     * @param $customer
     * @return Person
     */
    public function customer($customer = null)
    {
        if (is_array($customer))
        {
            $this->customer = new Person($customer);
        }

        return $this->customer;
    }

    /**
     * @param $address
     * @return AddressAdapter
     */
    public function address($address = null)
    {
        if (is_array($address))
        {
            $this->address = new AddressAdapter(new Address($address));
        }

        return $this->address;
    }

    /**
     * @param $email
     * @return Email
     */
    public function email($email = null)
    {
        if (is_string($email))
        {
            $this->email = new Email($email);
        }

        return $this->email;
    }

    /**
     * @param $phone
     * @return PhoneAdapter
     */
    public function phone($phone = null)
    {
        if (is_array($phone))
        {
            $this->phone = new PhoneAdapter(new Phone($phone));
        }

        return $this->phone;
    }

    /**
     * @param array|null $subtotals
     * @return array
     */
    public function subtotals(?array $subtotals = null)
    {
        if (is_array($subtotals))
        {
            foreach ($subtotals as $subtotal)
            {
                $this->subtotals[] = new Subtotal($subtotal);
            }
        }

        return $this->subtotals;
    }
}
