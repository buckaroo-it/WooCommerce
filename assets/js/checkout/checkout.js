import BuckarooPayByBank from './payments/paybybank';

class BuckarooCheckout {
  listen() {
    const paybyBank = new BuckarooPayByBank();
    const self = this;
    paybyBank.init();
    jQuery('body').on('change', 'input[name="payment_method"]', () => {
      jQuery('body').trigger('update_checkout');
    });
    jQuery('body').on('updated_checkout', (e) => {
      self.afterpay();
      self.afterpaynew();
      self.bilink();
      self.klarna();
      paybyBank.onLoad();
    });
    /**
     * toggle between bilink payment types on company name change
     */
    jQuery('#billing_company').on('input', function () {
      self.bilink_toggle(jQuery(this).val());

      jQuery('#buckaroo-afterpaynew-company-coc-registration')
        .parent()
        .toggle(jQuery.trim(jQuery(this).val()).length !== 0);
    });

    /**
     * toggle afterpay b2b fields
     */
    jQuery('body').on('change', '#buckaroo-afterpay-b2b', function () {
      const b2bActive = jQuery(this).is(':checked');
      const birthdate = jQuery('#buckaroo-afterpay-birthdate');
      const birthdateParent = birthdate.parent();

      const gender = jQuery('[name="buckaroo-afterpay-gender"]');
      const genderParent = gender.parent();

      jQuery('#showB2BBuckaroo').toggle(b2bActive);

      if (jQuery('#billing_company').length) {
        jQuery('#buckaroo-afterpay-company-name').val(
          jQuery('#billing_company').val(),
        );
      }

      birthdate.prop('disabled', b2bActive);
      birthdateParent.toggle(!b2bActive);
      birthdateParent.toggleClass('validate-required', !b2bActive);

      gender.prop('disabled', !b2bActive);
      genderParent.toggle(!b2bActive);
    });
  }

  /**
   * toggle phone number
   */
  afterpay() {
    if (jQuery('input[name=billing_phone]').length) {
      jQuery('#buckaroo-afterpay-phone').parent().hide();
    }
  }

  /**
   * toggle phone number
   */
  afterpaynew() {
    if (jQuery('input[name=billing_phone]').length) {
      jQuery('#buckaroo-afterpaynew-phone').parent().hide();
    }
    jQuery('#buckaroo-afterpaynew-company-coc-registration')
      .parent()
      .toggle(
        jQuery.trim(jQuery('input[name=billing_company]').val()).length !== 0,
      );
  }

  /**
   * toggle between bilink payment types
   */
  bilink() {
    const billinkCompany = jQuery('#billing_company');
    if (billinkCompany.length) {
      this.bilink_toggle(billinkCompany.val());
    }
  }

  /**
   * toggle between bilink payment types
   *
   * @param {string} val Company field value
   */
  bilink_toggle(val) {
    const billinkB2b = jQuery('#buckaroo_billink_b2b');
    const billinkB2c = jQuery('#buckaroo_billink_b2c');
    if (billinkB2b.length && billinkB2c.length) {
      const toggleState = jQuery.trim(val).length > 0;
      billinkB2b.toggle(toggleState);
      billinkB2c.toggle(!toggleState);
    }
  }

  /**
   * hide karna phone if exists
   */
  klarna() {
    if (jQuery('input[name=billing_phone]').length) {
      jQuery('input[id^="buckaroo-klarna"][type="tel"]').parent().hide();
    }
  }
}

export default BuckarooCheckout;
