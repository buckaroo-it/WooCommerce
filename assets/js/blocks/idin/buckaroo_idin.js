import React from 'react';

function BuckarooIdin() {
    // Assuming you have a way to get translations and URLs
    const ageVerificationText = "Age verification"; // Replace with a translation function if needed
    const ageVerifiedText = "You have verified your age already"; // Replace with a translation function if needed

    return (
        <div id="buckaroo_idin" className="buckaroo-idin buckaroo-idin-passed form-row">
            <h3 id="buckaroo_idin_heading">{ageVerificationText}</h3>
            <fieldset>
                <div>
                    <img className="buckaroo_idin_logo" src={idinLogo} alt="iDIN logo" />
                    <p className="buckaroo_idin_prompt">{ageVerifiedText}</p>
                </div>
            </fieldset>
        </div>
    );
}

export default BuckarooIdin;
