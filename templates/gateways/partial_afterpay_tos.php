<?php

/**
 * The Template for displaying afterpay tos gateway template
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 2.25.0
 *
 * @link      https://www.buckaroo.eu/
 */

defined('ABSPATH') || exit;

$fieldName = $this->id === 'buckaroo_afterpaynew' ? 'buckaroo-afterpaynew-accept' : 'buckaroo-afterpay-accept';
$tosLinks = [
    'NL' => 'https://documents.myafterpay.com/consumer-terms-conditions/nl_nl/',
    'BE' => [
        [
            'link' => 'https://documents.myafterpay.com/consumer-terms-conditions/nl_be/',
            'label' => 'Riverty conditions (Dutch)',
        ],
        [
            'link' => 'https://documents.myafterpay.com/consumer-terms-conditions/fr_be/',
            'label' => 'Riverty conditions (French)',
        ],
    ],
    'DE' => 'https://documents.myafterpay.com/consumer-terms-conditions/de_at/',
    'FI' => 'https://documents.myafterpay.com/consumer-terms-conditions/fi_fi/',
    'AT' => 'https://documents.myafterpay.com/consumer-terms-conditions/de_at/',
];
$country = $this->getScalarCheckoutField('billing_country');
$country = ! empty($country) ? $country : $this->country;

// set default to NL
if (! isset($tosLinks[$country])) {
    $country = 'NL';
}

$tos = $tosLinks[$country];

if (! is_array($tos)) {
    $tosLabel = sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($tos),
        esc_html__('Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway')
    );
} else {
    $tosLinksHtml = [];
    foreach ($tos as $tosElement) {
        $tosLinksHtml[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($tosElement['link']),
            esc_html__($tosElement['label'], 'wc-buckaroo-bpe-gateway')
        );
    }
    $tosLabel = esc_html__('Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway') . ' ' . implode(' / ', $tosLinksHtml);
}

woocommerce_form_field(
    $fieldName,
    [
        'type' => 'checkbox',
        'label' => $tosLabel,
        'required' => true,
        'checked_value' => 'ON',
        'class' => ['form-row-wide'],
        'label_class' => ['woocommerce-form__label', 'woocommerce-form__label-for-checkbox'],
    ]
);
