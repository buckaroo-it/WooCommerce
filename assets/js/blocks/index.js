const BuckarooComponent = (props) =>{
    let {image_path, useEffect, gateway} = props
    const [ selectedIssuer, selectIssuer ] = wp.element.useState('');
    const [ processingErrorMessage, setErrorMessage ] = wp.element.useState('');
    const [ cocNumber, setCocNumber ] = wp.element.useState('');
    const [ vatNumber, setVatNumber ] = wp.element.useState('');
    const [ dob, selectDate ] = wp.element.useState('');
    const { eventRegistration, emitResponse } = props;
    const {onPaymentSetup, onCheckoutValidation, onCheckoutFail} = eventRegistration;
    let payIssuers = gateway.issuers;

    useEffect(() => {
        const unsubscribddeProcessing = onCheckoutFail(
            (props) => {
                setErrorMessage(props.processingResponse.paymentDetails.errorMessage);
                return {
                    type: emitResponse.responseTypes.FAIL,
                    errorMessage: 'Error',
                    message: 'Error occurred, please try again',
                };
            }
        );
        return () => {
            unsubscribddeProcessing()
        };
    }, [onCheckoutFail]);

    useEffect(() => {
        const unsubscribeCheckoutValidation = onCheckoutValidation(
            () => {
                if (gateway.showVatField == true && gateway.vatRequired == true && !vatNumber.length) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        errorMessage: gateway.texts.requiredVatNumber
                    };
                } else if (gateway.showCocField == true && gateway.cocRequired == true && !cocNumber.length) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        errorMessage: gateway.texts.requiredCocNumber
                    };
                }

                if (gateway.showbirthdate == true && gateway.birthdateRequired == true && !dob.length) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        errorMessage: gateway.texts.dobRequired
                    };
                }
            }
        );
        return () => {
            unsubscribeCheckoutValidation()
        };
    }, [onCheckoutValidation, dob, vatNumber, cocNumber]);

    useEffect(() => {
        const unsubscribe = onPaymentSetup(() => {
            let response = {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {},
            };
            let paymentMethodData = {};
            paymentMethodData['isblocks'] = '1',
                paymentMethodData['selectedIssuer'] = 'asd';
            paymentMethodData['vat_number'] = vatNumber;
            paymentMethodData['coc_number'] = cocNumber;
            paymentMethodData[gateway.paymentMethodId + '_birthdate'] = dob;
            response.meta.paymentMethodData = paymentMethodData;
            return response;
        });
        return () => {
            unsubscribe();
        };
    }, [onPaymentSetup, selectedIssuer, dob, cocNumber, vatNumber]);

    return React.createElement('div', {className: 'PPMFWC_container'},
        React.createElement('span', {className: 'description'}, gateway.description),
        React.createElement('span', {className: 'descriptionError'}, processingErrorMessage),
        React.createElement('div', {},
            (gateway.paymentMethodId == 'pay_gateway_ideal' && gateway.issuersSelectionType == 'select' ?
                React.createElement('div', {className: 'field'},
                    React.createElement('span', {className: 'payLabel'}, gateway.texts.issuer),
                    React.createElement('select',  {onChange: (e)=>{
                                selectIssuer(e.target.value)
                            }},
                        React.createElement("option", {}, gateway.texts.selectissuer),
                        ...payIssuers.map(issuer => React.createElement("option", {value: issuer.option_sub_id}, issuer.name))
                    )
                ) : ''),
            (gateway.paymentMethodId == 'pay_gateway_ideal' && gateway.issuersSelectionType == 'radio' ?
                React.createElement('div', {className: 'field'},
                    React.createElement('div', {className: 'issuerlist'},
                        ...payIssuers.map(
                            issuer =>
                                React.createElement('div', {className: 'issuerradio'},
                                    React.createElement("label", {},
                                        React.createElement('input', {type: 'radio', value: issuer.option_sub_id, id: 'ideal_'+issuer.option_sub_id, name: 'ideal_issuer_list', onChange: (e)=>{ selectIssuer(e.target.value)}}),
                                        React.createElement('img', {src: issuer.image_path, className: 'issuerlogo'}, null),
                                        issuer.name
                                    ),
                                ),
                        ),
                    ),
                ) : ''),
            (gateway.showbirthdate == true ?
                React.createElement('div', {className: 'field'},
                    React.createElement('span', {className: 'payLabel'}, gateway.texts.enterbirthdate),
                    React.createElement('input', {type: 'date', onChange: (e)=>{ selectDate(e.target.value)}})
                ) : '' )
        ))

}

function BuckarooLabel({image_path, title})
{
    return React.createElement('div', {className: 'buckaroo_method_block'},
        title, React.createElement('img', {src: image_path, style: {float: 'right'}}, null));
}

const registerBuckarooPaymentMethods = ({wc, buckaroo_gateways}) => {
    console.log(buckaroo_gateways)
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {useEffect} = wp.element;
    buckaroo_gateways.forEach(

        (gateway) => {
            registerPaymentMethod(createOptions(gateway, BuckarooComponent, useEffect));
        }
    );
}

const createOptions = (gateway, BuckarooComponent, useEffect) => {
    return {
        name: gateway.paymentMethodId,
        label: React.createElement(BuckarooLabel, {image_path: gateway.image_path, title: gateway.title}),
        paymentMethodId: gateway.paymentMethodId,
        edit: React.createElement('div', null, ''),
        canMakePayment: ({cartTotals, billingData}) => {
            return true
        },
        ariaLabel: gateway.title,
        content: React.createElement(BuckarooComponent, {gateway: gateway, image_path: gateway.image_path, useEffect: useEffect})
    }
}

registerBuckarooPaymentMethods(window)