import React, {useState} from 'react';
import {__} from "@wordpress/i18n";

const TermsAndConditionsCheckbox = ({paymentMethod, onCheckboxChange, billingData}) => {
    const [isChecked, setIsChecked] = useState(false);

    const tosLinks = {
        NL: "https://documents.myafterpay.com/consumer-terms-conditions/nl_nl/",
        BE: [
            {
                link: "https://documents.myafterpay.com/consumer-terms-conditions/nl_be/",
                label: 'Riverty | AfterPay conditions (Dutch)'
            },
            {
                link: "https://documents.myafterpay.com/consumer-terms-conditions/fr_be/",
                label: 'Riverty | AfterPay conditions (French)'
            }
        ],
        DE: "https://documents.myafterpay.com/consumer-terms-conditions/de_at/",
        FI: "https://documents.myafterpay.com/consumer-terms-conditions/fi_fi/",
        AT: "https://documents.myafterpay.com/consumer-terms-conditions/de_at/"
    };

    let fieldName = paymentMethod === "buckaroo_afterpaynew" ? 'buckaroo-afterpaynew-accept' : paymentMethod === "buckaroo_afterpay" ? 'buckaroo-afterpay-accept' : paymentMethod;
    let country = billingData.country;
    let labelText = __('Accept Riverty | AfterPay conditions:', 'wc-buckaroo-bpe-gateway');
    let termsUrl = tosLinks[country] || tosLinks['NL'];

    if (paymentMethod === 'buckaroo-billink') {
        labelText = __('Accept terms of use', 'wc-buckaroo-bpe-gateway');
        termsUrl = 'https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf';
    }
    const handleCheckboxChange = () => {
        setIsChecked(!isChecked);
        onCheckboxChange(!isChecked ? 'On' : 'Off');
    };

    return (
        <div>
            <a href={`${termsUrl}`} target="_blank">{labelText}</a>
            <span className="required">*</span>
            <input
                id={`${fieldName}-accept`}
                name={`${fieldName}-accept`}
                type="checkbox"
                value="ON"
                checked={isChecked}
                onChange={handleCheckboxChange}
            />
            <p className="required" style={{float: 'right'}}>*
                Required
            </p>
        </div>
    );
};

export default TermsAndConditionsCheckbox;
