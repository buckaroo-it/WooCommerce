import React,{useState}from 'react';
import DefaultDropdown from "../partials/buckaroo_creditcard_dropdown";

const CreditCard = ({creditCardIssuers, onCardNameChange, onCardNumberChange, onCardMonthChange,onCardYearChange,onCardCVCChange, onSelectCc, onEncryptedDataChange}) => {
    const paymentMethod = 'buckaroo-creditcard';
    const [creditCard, setCreditCard] = useState('');
    const handleSelectCreditCard = (selectedCc) => {
        setCreditCard(selectedCc);
        onSelectCc(selectedCc)
    };
    return (
        <div>

            <p class="form-row form-row-wide">
                <DefaultDropdown paymentMethod={paymentMethod} creditCardIssuers={creditCardIssuers} onSelectCc={handleSelectCreditCard}></DefaultDropdown>
            </p>
            <div className="method--bankdata">

                <p className="form-row">
                    <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardname`}>
                        Cardholder Name:
                        <span className="required">*</span>

                    </label>
                    <input
                        type="text"
                        name={`${paymentMethod}-cardname`}
                        id={`${paymentMethod}-cardname`}
                        placeholder="Cardholder Name:"
                        className="cardHolderName input-text"
                        maxLength="250"
                        autoComplete="off"
                        onChange={(e) => onCardNameChange(e.target.value)}
                    />

                </p>

                <p className="form-row">
                    <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardnumber`}>
                        Card Number:
                        <span className="required">*</span>
                    </label>

                    <input
                        type="text"
                        name={`${paymentMethod}-cardnumber`}
                        id={`${paymentMethod}-cardnumber`}
                        placeholder="Card Number:"
                        className="cardNumber input-text"
                        maxLength="250"
                        autoComplete="off"
                        onChange={(e) => onCardNumberChange(e.target.value)}
                    />
                </p>

                <p className="form-row">
                    <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardmonth`}>
                        Expiration Month:
                        <span className="required">*</span>
                    </label>

                    <input
                        type="text"
                        maxLength="2"
                        name={`${paymentMethod}-cardmonth`}
                        id={`${paymentMethod}-cardmonth`}
                        placeholder="Expiration Month:"
                        className="expirationMonth input-text"
                        autoComplete="off"
                        onChange={(e) => onCardMonthChange(e.target.value)}
                    />
                </p>

                <p className="form-row">
                    <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardyear`}>
                        Expiration Year:
                        <span className="required">*</span>
                    </label>
                    <input
                        type="text"
                        maxLength="4"
                        name={`${paymentMethod}-cardyear`}
                        id={`${paymentMethod}-cardyear`}
                        placeholder="Expiration Year:"
                        className="expirationYear input-text"
                        autoComplete="off"
                        onChange={(e) => onCardYearChange(e.target.value)}
                    />
                </p>

                <p className="form-row">
                    <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardcvc`}>
                        CVC:
                        <span className="required">*</span>
                    </label>
                    <input
                        type="password"
                        maxLength="4"
                        name={`${paymentMethod}-cardcvc`}
                        id={`${paymentMethod}-cardcvc`}
                        placeholder="CVC:"
                        className="cvc input-text"
                        autoComplete="off"
                        onChange={(e) => onCardCVCChange(e.target.value)}
                    />
                </p>

                <p className="form-row form-row-wide validate-required"></p>
                <p className="required" style={{ float: 'right' }}>*
                    Required
                </p>
                <input
                    type="hidden"
                    id={`${paymentMethod}-encrypted-data`}
                    name={`${paymentMethod}-encrypted-data`}
                    className="encryptedCardData input-text"
                    onChange={(e) => onEncryptedDataChange(e.target.value)}
                />

            </div>
        </div>
    );

};

export default CreditCard;

