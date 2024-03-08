import React, {useContext} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import PhoneDropdown from "../partials/buckaroo_phone";
import PaymentContext from '../PaymentProvider';

const In3 = ({ methodName, billing }) => {
    const { updateFormState } = useContext(PaymentContext);

    const handlePhoneChange = (value) => {
        updateFormState(`${methodName}-phone`, value);
    };


    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    return (
        <div>
            {billing.country === "NL" &&
                <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange} />
            }
            {billing.phone === "" &&
                <PhoneDropdown paymentMethod={methodName} billingData={billing} handlePhoneChange={handlePhoneChange} />
            }
            <FinancialWarning paymentMethod={methodName}></FinancialWarning>
        </div>
    );

};

export default In3;
