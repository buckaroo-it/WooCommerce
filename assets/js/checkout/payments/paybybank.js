class BuckarooPayByBank {
  /**
   *  toggle payByBank list
   */
  init() {
    this.onLoad();
    const self = this;

    jQuery('body').on('change', '.bk-paybybank-radio', () => {
      const value = jQuery('.bk-paybybank-radio:checked').val();
      jQuery('.bk-paybybank-real-value, .buckaroo-paybybank-select').val(value);
      self.setLogo();
    });

    jQuery('body').on('change', '.buckaroo-paybybank-select', function () {
      const value = jQuery(this).val();
      jQuery('.bk-paybybank-real-value').val(value);
      self.setLogo();
      self.setRadioSelectedFromRealValue();
    });

    jQuery('body').on('click', '.bk-toggle-wrap', () => {
      const toggle = jQuery('.bk-toggle');
      const textElement = jQuery('.bk-toggle-text');
      const isDown = toggle.is('.bk-toggle-down');
      toggle.toggleClass('bk-toggle-down bk-toggle-up');
      const textLess = textElement.attr('text-less');
      const textMore = textElement.attr('text-more');
      if (isDown) {
        textElement.text(textLess);
      } else {
        textElement.text(textMore);
      }

      self.getElementToToggle().toggle(isDown);
    });

    let isMobile = false;
    jQuery(window).on('resize', () => {
      const mobile = jQuery(window).width() < 768;
      if (isMobile !== mobile) {
        isMobile = mobile;
        jQuery('.bk-paybybank-mobile').toggle(isMobile);
        jQuery('.bk-paybybank-not-mobile').toggle(!isMobile);
      }
    });
  }

  setRadioSelectedFromRealValue() {
    const selected = jQuery(
      `.bk-paybybank-radio[value="${
        jQuery('.bk-paybybank-real-value').val()
      }"]`,
    );
    if (selected.length) {
      jQuery('.bk-toggle')
        .removeClass('bk-toggle-up')
        .addClass('bk-toggle-down');
      jQuery('.bk-toggle-text').text(
        jQuery('.bk-toggle-text').attr('text-more'),
      );

      jQuery('.custom-radio').hide();
      selected.closest('.custom-radio').show();
      selected.prop('checked', true);
    }
  }

  setLogo() {
    const code = jQuery('.bk-paybybank-real-value').val();
    if (buckaroo_global.payByBankLogos && code && code.length) {
      if (buckaroo_global.payByBankLogos[code]) {
        jQuery('.payment_method_buckaroo_paybybank > label > img').prop(
          'src',
          buckaroo_global.payByBankLogos[code],
        );
      }
    }
  }

  onLoad() {
    this.setLogo();
    const isMobile = jQuery(window).width() < 768;
    jQuery('.bk-paybybank-mobile').toggle(isMobile);
    jQuery('.bk-paybybank-not-mobile').toggle(!isMobile);

    this.getElementToToggle().hide();
  }

  getElementToToggle() {
    const hasSelected = jQuery('.bank-method-input:checked').length > 0;
    if (hasSelected) {
      return jQuery('.bank-method-input:not(:checked)').closest(
        '.custom-radio',
      );
    }
    return jQuery('.bk-paybybank-selector .custom-radio:nth-child(n+5)');
  }
}

export default BuckarooPayByBank;
