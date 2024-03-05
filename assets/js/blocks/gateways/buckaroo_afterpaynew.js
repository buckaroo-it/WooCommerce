import React, {useContext} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import TermsAndConditionsCheckbox from '../partials/buckaroo_terms_and_condition';
import {__} from '@wordpress/i18n';
import PhoneDropdown from '../partials/buckaroo_phone';
import PaymentContext from '../PaymentProvider';

const AfterPayNew = ({ methodName, gateway: {customer_type, b2b}, billing}) => {

    const { updateFormState, handleChange } = useContext(PaymentContext);

    const handleTermsChange = (value) => {
        updateFormState(`${methodName}-accept`, +value);
    };

    const handleBirthDayChange = (value) => {
        console.log(`${methodName}-birthdate`, value);
        updateFormState(`${methodName}-birthdate`, value);
    };
    const handlePhoneChange = (value) => {
        updateFormState(`${methodName}-phone`, value);
    };

    return (
        <div>
            <PhoneDropdown paymentMethod={methodName} billingData={billing} handlePhoneChange={handlePhoneChange}/>

            {(['BE', 'NL', 'DE'].includes(billing.country)) && (
                <div>
                    <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>
                </div>
            )}

            {billing.country === 'NL' && customer_type !== 'b2c' && (
                <p className="form-row form-row-wide validate-required">
                    <label htmlFor="buckaroo-afterpaynew-coc">
                        {__('CoC-number:', 'wc-buckaroo-bpe-gateway')}
                        <span className="required">*</span>
                    </label>
                    <input
                        id="buckaroo-afterpaynew-coc"
                        name="buckaroo-afterpaynew-coc"
                        className="input-text"
                        type="text"
                        maxLength="250"
                        autoComplete="off"
                        onChange={handleChange}
                    />
                </p>
            )}

            {billing.country === 'FI' && (
                <p className="form-row form-row-wide validate-required">
                    <label htmlFor="buckaroo-afterpaynew-identification-number">
                        {__('Identification Number:', 'wc-buckaroo-bpe-gateway')}
                        <span className="required">*</span>
                    </label>
                    <input
                        id="buckaroo-afterpaynew-identification-number"
                        name="buckaroo-afterpaynew-identification-number"
                        className="input-text"
                        type="text"
                        maxLength="250"
                        autoComplete="off"
                        onChange={handleChange}
                    />
                </p>
            )}

            <TermsAndConditionsCheckbox
                paymentMethod={methodName}
                handleTermsChange={handleTermsChange}
                billingData={billing}
                b2b={b2b}
            />

            <FinancialWarning paymentMethod={methodName}/>
        </div>
    );
};

export default AfterPayNew;
