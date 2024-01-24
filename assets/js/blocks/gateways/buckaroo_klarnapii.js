import React,{useState} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";

const Klaranapii = ({paymentName,genders, onSelectGender}) => {
    const paymentMethod = 'buckaroo-klarnapii';

    const [gender, setGender] = useState(null);
    const handleSelectGender = (selectedGender) => {
        setGender(selectedGender);
        onSelectGender(selectedGender)
    };

    return (
        <div id="buckaroo_klarnapay">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={handleSelectGender}></GenderDropdown>
            <FinancialWarning></FinancialWarning>
        </div>
    );

};


export default Klaranapii;
