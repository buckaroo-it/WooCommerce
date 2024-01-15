import React,{useState} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";

const KlarnaKp = ({paymentName,genders, onSelectGender}) => {
    const paymentMethod = 'buckaroo-klarna';

    const [gender, setGender] = useState(null);
    const handleSelectGender = (selectedGender) => {
        setGender(selectedGender);
        onSelectGender(selectedGender)
    };

    return (
        <fieldset id="buckaroo_billink_b2c">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={handleSelectGender}></GenderDropdown>
        </fieldset>
    );

};

export default KlarnaKp;
