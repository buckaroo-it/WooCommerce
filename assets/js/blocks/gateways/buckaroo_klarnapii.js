import React,{useState} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";

const Klaranapii = ({paymentName,genders, onSelectGender}) => {
    const paymentMethod = 'buckaroo-klarnapay';

    const [gender, setGender] = useState(null);
    const handleSelectGender = (selectedGender) => {
        setGender(selectedGender);
        onSelectGender(selectedGender)
    };

    return (
        <fieldset id="buckaroo_klarnapii">
            <FinancialWarning></FinancialWarning>
        </fieldset>
    );

};

export default Klaranapii;
