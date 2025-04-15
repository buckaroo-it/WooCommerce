class BuckarooCreditCardsHostedFields {
	constructor() {
		this.form = jQuery( 'form[name=checkout]' );
		this.sdkClient = null;
		this.tokenExpiresAt = null;
		this.paymentMethodId = 'buckaroo_creditcard';
		this.isSubmitting = false;
		this.submitEvents = [];
		this.fieldSelectors = [];
		this.refreshTimeout = null;
	}

	async initialize() {
		try {
			const token = await this.fetchToken();
			await this.setupSDK( token );
			await this.mountHostedFields();
		} catch ( error ) {
			console.error( 'Hosted fields initialization failed:', error );
			this.showError( 'Failed to initialize payment form' );
		}
	}

	async fetchToken() {
		const tokenResponse = await fetch(
			'/?wc-api=WC_Gateway_Buckaroo_creditcard-hosted-fields-token'
		);
		const tokenData = await tokenResponse.json();
		this.tokenExpiresAt = Date.now() + tokenData.expires_in * 1000;
		this.scheduleTokenRefresh( tokenData.expires_in );
		return tokenData.access_token;
	}

	async setupSDK( token ) {
		this.sdkClient = new BuckarooHostedFieldsSdk.HFClient( token );
		this.sdkClient.setLanguage( buckaroo_global.locale );
		this.sdkClient.setSupportedServices( this.getSupportedServices() );
		await this.sdkClient.startSession( ( event ) =>
			this.sdkClient.handleValidation(
				event,
				`${ this.paymentMethodId }-name-error`,
				`${ this.paymentMethodId }-number-error`,
				`${ this.paymentMethodId }-expiry-error`,
				`${ this.paymentMethodId }-cvc-error`
			)
		);
	}

	getSupportedServices() {
		const mapIssuer = ( issuer ) => {
			const mapping = {
				amex: 'Amex',
				maestro: 'Maestro',
				mastercard: 'MasterCard',
				visa: 'Visa',
			};
			return mapping[ issuer.servicename ] || issuer.servicename;
		};

		const services =
			this.paymentMethodId === 'buckaroo_creditcard'
				? buckaroo_global.creditCardIssuers
				: [
						{
							servicename: this.paymentMethodId.replace(
								'buckaroo_creditcard_',
								''
							),
						},
				  ];

		return services.map( mapIssuer );
	}

	async mountHostedFields() {
		this.fieldSelectors = [
			`#${ this.paymentMethodId }-name-wrapper`,
			`#${ this.paymentMethodId }-number-wrapper`,
			`#${ this.paymentMethodId }-expiry-wrapper`,
			`#${ this.paymentMethodId }-cvc-wrapper`,
		];

		const fields = [
			{
				selector: this.fieldSelectors[ 0 ],
				mount: this.sdkClient.mountCardHolderName,
				config: {
					id: 'ccname',
					placeHolder: 'John Doe',
					labelSelector: `#${ this.paymentMethodId }-name-label`,
					baseStyling: {},
				},
			},
			{
				selector: this.fieldSelectors[ 1 ],
				mount: this.sdkClient.mountCardNumber,
				config: {
					id: 'ccnumber',
					placeHolder: '4111 1111 1111 1111',
					labelSelector: `#${ this.paymentMethodId }-number-label`,
					baseStyling: {},
				},
			},
			{
				selector: this.fieldSelectors[ 2 ],
				mount: this.sdkClient.mountExpiryDate,
				config: {
					id: 'ccexpiry',
					placeHolder: 'MM/YY',
					labelSelector: `#${ this.paymentMethodId }-expiry-label`,
					baseStyling: {},
				},
			},
			{
				selector: this.fieldSelectors[ 3 ],
				mount: this.sdkClient.mountCvc,
				config: {
					id: 'cccvc',
					placeHolder: '123',
					labelSelector: `#${ this.paymentMethodId }-cvc-label`,
					baseStyling: {},
				},
			},
		];

		await Promise.all(
			fields.map( ( field ) =>
				field.mount( field.selector, field.config )
			)
		);
	}

	scheduleTokenRefresh( expiresIn ) {
		if ( this.refreshTimeout ) clearTimeout( this.refreshTimeout );
		const refreshTime = Math.max( expiresIn * 1000 - 1000, 0 );
		this.refreshTimeout = setTimeout(
			() => this.refreshToken(),
			refreshTime
		);
	}

	async refreshToken() {
		try {
			this.fieldSelectors.forEach( ( selector ) =>
				jQuery( selector ).find( 'iframe' ).remove()
			);
			await this.initialize();
		} catch ( error ) {
			console.error( 'Token refresh failed:', error );
			this.showError( 'Payment form refresh failed' );
		}
	}

	async handleFormSubmit() {
		if ( ! this.sdkClient ) {
			this.showError( 'Payment form not initialized' );
			return false;
		}

		if ( Date.now() > this.tokenExpiresAt ) {
			try {
				await this.refreshToken();
			} catch ( error ) {
				this.showError( 'Session expired, please try again' );
				return false;
			}
		}

		try {
			const issuer = this.sdkClient.getService();
			this.setFormIssuer( issuer );

			const paymentToken = await this.sdkClient.submitSession();
			this.setFormEncryptedData( paymentToken );

			return true;
		} catch ( error ) {
			console.error( 'Payment submission failed:', error );
			this.showError( error.message || 'Invalid payment details' );
			return false;
		}
	}

	setFormEncryptedData( token ) {
		this.form
			.find( `[name="${ this.paymentMethodId }-encrypted-data"]` )
			.val( token );
	}

	setFormIssuer( issuer ) {
		this.form
			.find( `[name="${ this.paymentMethodId }-creditcard-issuer"]` )
			.val( issuer );
	}

	showError( message ) {
		const $error = this.form.find( `.${ this.paymentMethodId }-hf-error` );
		$error.text( message );
		jQuery( 'html, body' ).animate(
			{ scrollTop: $error.offset().top - 100 },
			500
		);
		jQuery( document.body ).trigger( 'checkout_error' );
	}

	overrideFormSubmit() {
		const formEl = this.form.get( 0 );
		const events = jQuery._data( formEl, 'events' );
		if ( events?.submit ) {
			this.submitEvents = events.submit.map( ( e ) => e.handler );
		}

		this.form.off( 'submit' );

		this.form.on( 'submit', async ( e ) => {
			e.preventDefault();
			if ( this.isSubmitting ) return;

			this.isSubmitting = true;
			try {
				const paymentMethod = this.selectedPaymentMethod() || '';
				if ( paymentMethod.includes( 'buckaroo_creditcard' ) ) {
					const isValid = await this.handleFormSubmit();
					if ( isValid ) this.triggerSubmitHandlers( e );
				} else {
					this.triggerSubmitHandlers( e );
				}
			} catch ( error ) {
				console.error( 'Form submission error:', error );
				this.showError( 'An error occurred during submission' );
			} finally {
				this.isSubmitting = false;
			}
		} );
	}

	triggerSubmitHandlers( e ) {
		this.submitEvents.forEach( ( handler ) =>
			handler.call( this.form, e )
		);
	}

	selectedPaymentMethod() {
		return jQuery( '[name="payment_method"]:checked' ).val() || '';
	}

	listen() {
		jQuery( 'body' ).on( 'updated_checkout', () => {
			const paymentMethod = this.selectedPaymentMethod();
			if ( paymentMethod.includes( 'buckaroo_creditcard' ) ) {
				this.paymentMethodId = paymentMethod;
				this.initialize();
				this.overrideFormSubmit();
			}
		} );
	}

	cleanup() {
		if ( this.refreshTimeout ) clearTimeout( this.refreshTimeout );
		this.form.off( 'submit' );
		jQuery( 'body' ).off( 'updated_checkout' );
	}
}

export default BuckarooCreditCardsHostedFields;
