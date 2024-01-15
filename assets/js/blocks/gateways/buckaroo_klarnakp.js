import React,{useState} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";

const KlarnaKp = ({paymentName,genders, onSelectGender}) => {
    const paymentMethod = 'buckaroo-klarnakp';

    const [gender, setGender] = useState(null);
    const handleSelectGender = (selectedGender) => {
        setGender(selectedGender);
        onSelectGender(selectedGender)
    };

    return (
        <fieldset id="buckaroo_klaranakp">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={handleSelectGender}></GenderDropdown>
            <FinancialWarning></FinancialWarning>
        </fieldset>
    );

};

export default KlarnaKp;
