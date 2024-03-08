import React, {useContext} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import PaymentContext from '../PaymentProvider';

const Billink = ({methodName, gateway: {genders, b2b}, billing}) => {
    const { updateFormState, handleChange } = useContext(PaymentContext);

    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    const handleTermsChange = (value) => {
        updateFormState(`${methodName}-accept`, +value);
    };

    return (
        <div id="buckaroo_billink_b2c">
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange}></GenderDropdown>
            <BirthDayField paymentMethod={methodName} handleChange={handleBirthDayChange}/>
            <TermsAndConditionsCheckbox
                paymentMethod={methodName}
                b2b={b2b}
                billingData={billing}
                handleTermsChange={handleTermsChange}
            />
            <FinancialWarning paymentMethod={methodName}></FinancialWarning>
        </div>
    );

};

export default Billink;
