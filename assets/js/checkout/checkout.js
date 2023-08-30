class BuckarooCheckout {
  listen() {
    let self = this;
    self.paybyBank();
    jQuery("body").on("change", 'input[name="payment_method"]', function () {
      jQuery("body").trigger("update_checkout");
    });
    jQuery("body").on("updated_checkout", function (e) {
      self.afterpay();
      self.afterpaynew();
      self.bilink();
      self.klarna();
      self.initPayByBank();
    });
    /**
     * toggle between bilink payment types on company name change
     */
    jQuery("#billing_company").on("input", function () {
      self.bilink_toggle(jQuery(this).val());

      jQuery("#buckaroo-afterpaynew-coc")
        .parent()
        .toggle(jQuery.trim(jQuery(this).val()).length !== 0);
    });

    /**
     * toggle afterpay b2b fields
     */
    jQuery("body").on("change", "#buckaroo-afterpay-b2b", function () {
      let b2bActive = jQuery(this).is(":checked");
      let birthdate = jQuery("#buckaroo-afterpay-birthdate");
      let birthdateParent = birthdate.parent();

      let gender = jQuery('[name="buckaroo-afterpay-gender"]');
      let genderParent = gender.parent();

      jQuery("#showB2BBuckaroo").toggle(b2bActive);

      if (jQuery("#billing_company").length) {
        jQuery("#buckaroo-afterpay-CompanyName").val(
          jQuery("#billing_company").val()
        );
      }

      birthdate.prop("disabled", !b2bActive);
      birthdateParent.toggle(!b2bActive);
      birthdateParent.toggleClass("validate-required", !b2bActive);

      gender.prop("disabled", !b2bActive);
      genderParent.toggle(!b2bActive);
    });
  }

  /**
   * toggle phone number
   */
  afterpay() {
    if (jQuery("input[name=billing_phone]").length) {
      jQuery("#buckaroo-afterpay-phone").parent().hide();
    }
  }
  /**
   * toggle phone number
   */
  afterpaynew() {
    if (jQuery("input[name=billing_phone]").length) {
      jQuery("#buckaroo-afterpaynew-phone").parent().hide();
    }
    jQuery("#buckaroo-afterpaynew-coc")
      .parent()
      .toggle(
        jQuery.trim(jQuery("input[name=billing_company]").val()).length !== 0
      );
  }
  /**
   * toggle between bilink payment types
   */
  bilink() {
    let billinkCompany = jQuery("#billing_company");
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
    let billinkB2b = jQuery("#buckaroo_billink_b2b");
    let billinkB2c = jQuery("#buckaroo_billink_b2c");
    if (billinkB2b.length && billinkB2c.length) {
      var toggleState = jQuery.trim(val).length > 0;
      billinkB2b.toggle(toggleState);
      billinkB2c.toggle(!toggleState);
    }
  }
  /**
   * hide karna phone if exists
   */
  klarna() {
    if (jQuery("input[name=billing_phone]").length) {
      jQuery('input[id^="buckaroo-klarna"][type="tel"]').parent().hide();
    }
  }

  /**
   *  toggle payByBank list
   */
  paybyBank() {
    this.initPayByBank();
    const self = this;

    jQuery("body").on("change", ".bk-paybybank-radio", function () {
      let value = jQuery(".bk-paybybank-radio:checked").val();
      jQuery(".bk-paybybank-real-value, .buckaroo-paybybank-select").val(value);
      self.setPayByBankLogo();
    });

    jQuery("body").on("change", ".buckaroo-paybybank-select", function () {
      let value = jQuery(this).val();
      jQuery(".bk-paybybank-real-value").val(value);
      self.setPayByBankLogo();
      self.setRadioSelectedFromRealValue();
    });

    jQuery("body").on("click", ".bk-toggle-wrap", function () {
      const toggle = jQuery(".bk-toggle");
      const textElement = jQuery(".bk-toggle-text");
      const isDown = toggle.is(".bk-toggle-down");
      toggle.toggleClass("bk-toggle-down bk-toggle-up");
      const textLess = textElement.attr("text-less");
      const textMore = textElement.attr("text-more");
      if (isDown) {
        textElement.text(textLess);
      } else {
        textElement.text(textMore);
      }

      self.getElementToToggle().toggle(isDown);
    });

    let isMobile = false;
    jQuery(window).on("resize", function () {
      let mobile = jQuery(window).width() < 768;
      if (isMobile !== mobile) {
        isMobile = mobile;
        jQuery(".bk-paybybank-mobile").toggle(isMobile);
        jQuery(".bk-paybybank-not-mobile").toggle(!isMobile);
      }
    });
  }

  setRadioSelectedFromRealValue() {
    const selected = jQuery(
      '.bk-paybybank-radio[value="' +
        jQuery(".bk-paybybank-real-value").val() +
        '"]'
    );
    if (selected.length) {
      jQuery(".bk-toggle")
        .removeClass("bk-toggle-up")
        .addClass("bk-toggle-down");
      jQuery(".bk-toggle-text").text(
        jQuery(".bk-toggle-text").attr("text-more")
      );

      jQuery(".custom-radio").hide();
      selected.closest(".custom-radio").show();
      selected.prop("checked", true);
    }
  }
  setPayByBankLogo() {
    const code = jQuery(".bk-paybybank-real-value").val();
    if (buckaroo_global.payByBankLogos && code.length) {
      if (buckaroo_global.payByBankLogos[code]) {
        jQuery(".payment_method_buckaroo_paybybank > label > img").prop(
          "src",
          buckaroo_global.payByBankLogos[code]
        );
      }
    }
  }

  initPayByBank() {
    this.setPayByBankLogo();
    let isMobile = jQuery(window).width() < 768;

    if (isMobile) {
      jQuery(".bk-paybybank-mobile").toggle(isMobile);
      jQuery(".bk-paybybank-not-mobile").toggle(!isMobile);
    }

    this.getElementToToggle().hide();
  }

  getElementToToggle() {
    const hasSelected = jQuery(".bank-method-input:checked").length > 0;
    if (hasSelected) {
      return jQuery(".bank-method-input:not(:checked)").closest(
        ".custom-radio"
      );
    }
    return jQuery(".bk-paybybank-selector .custom-radio:nth-child(n+5)");
  }
}

export default BuckarooCheckout;
