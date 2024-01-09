import React from 'react';
const AfterPayB2B = ({field}) => {
    return (
        <div>
            <a href='#' target="_blank">Accept Riverty | AfterPay conditions:</a>
            <span className="required">*</span>
            <input
                id={`${field}-accept`}
                name={`${field}-accept`}
                type="checkbox"
                value="ON"
            />
            <p className="required" style={{float: 'right'}}>*
                Required
            </p>
        </div>
    );
};


export default AfterPayB2B;
