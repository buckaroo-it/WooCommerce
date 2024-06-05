import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import useFormData from "../hooks/useFormData";
import {__} from "@wordpress/i18n";

const Billink = ({onStateChange, methodName, gateway: {genders, b2b}, billing}) => {

    const initialState = {
        [`${methodName}-company-coc-registration`]: '',
        [`${methodName}-VatNumber`]: '',
        [`${methodName}-gender`]: '',
        [`${methodName}-birthdate`]: '',
        [`${methodName}-b2b`]: '',
    };

    const { handleChange, updateFormState } = useFormData(initialState, onStateChange);

    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    const handleTermsChange = (value) => {
        updateFormState(`${methodName}-accept`, value);
    };

    return (
        <div>
            <div id="buckaroo_billink_b2b">
                {billing?.company !== '' && (
                    <p className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-billink-company-coc-registration">
                            {__('CoC-number:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <input
                            id={`${methodName}-company-coc-registration`}
                            name={`${methodName}-company-coc-registration`}
                            className="input-text"
                            type="text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={handleChange}
                        />
                    </p>
                ) && (
                    <p className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-billink-VatNumber">
                            {__('CoC-number:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <input
                            id={`${methodName}-VatNumber`}
                            name={`${methodName}-VatNumber`}
                            className="input-text"
                            type="text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={handleChange}
                        />
                    </p>
                )}
                <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>
                <TermsAndConditionsCheckbox
                    paymentMethod={methodName}
                    handleTermsChange={handleTermsChange}
                    billingData={billing}
                    b2b={b2b}
                />
                <FinancialWarning paymentMethod={methodName}></FinancialWarning>
            </div>
            <div id="buckaroo_billink_b2c">
                <GenderDropdown paymentMethod={methodName} genders={genders}
                                handleChange={handleChange}></GenderDropdown>
                <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>
                <TermsAndConditionsCheckbox
                    paymentMethod={methodName}
                    handleTermsChange={handleTermsChange}
                    billingData={billing}
                    b2b={b2b}
                />
                <FinancialWarning paymentMethod={methodName}></FinancialWarning>
            </div>
        </div>
    );

};

export default Billink;
