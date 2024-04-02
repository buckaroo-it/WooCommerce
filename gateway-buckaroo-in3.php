<?php



/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_In3 extends WC_Gateway_Buckaroo
{
    public const DEFAULT_ICON_VALUE = 'defaultIcon';
    public const VERSION_FLAG = 'buckaroo_in3_version';
    public const VERSION3 = 'v3';
    public const VERSION2 = 'v2';
    public const IN3_V2_TITLE = 'In3';
    public const IN3_V3_TITLE = 'iDEAL In3';

    public $type;
    public $vattype;
    public function __construct()
    {
        $this->id                     = 'buckaroo_in3';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo In3';

        $this->title = $this->getTitleForVersion();

        parent::__construct();

        $this->set_icons();
        $this->add_refund_support();
    }

    private function getTitleForVersion() {
        return $this->get_option('api_version') === self::VERSION2 ? self::IN3_V2_TITLE : self::IN3_V3_TITLE;
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type       = 'in3';
        $this->vattype    = $this->get_option('vattype');
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        $birthdate = $this->request('buckaroo-in3-birthdate');

        $country = $this->request('billing_country');

        if ($country === 'NL' && !$this->validate_date($birthdate, 'd-m-Y')) {
            wc_add_notice(__("You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        
        if (
            $this->request('billing_phone') === null &&
            $this->request('buckaroo-in3-phone') === null
        ) {
            wc_add_notice(
                sprintf(
                    __("Please fill in a phone number for %s. This is required in order to use this payment method.", 'wc-buckaroo-bpe-gateway'),
                    $this->getTitleForVersion()
                ),
                'error'
            );
        }

        parent::validate_fields();
    }

    /**
     * Set icons based on version
     *
     * @return void
     */
    private function set_icons()
    {
        $icon = $this->get_option('icon');

        if (
            $this->get_option('api_version') === 'v2' ||
            $icon === self::DEFAULT_ICON_VALUE
        ) {
            $this->set_icon('svg/in3.svg', 'svg/in3.svg');
            return;
        }
        $this->set_icon($icon, $icon);
    }

   
    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->add_financial_warning_field();
        $this->form_fields['api_version'] = array(
            'title'       => __('Api version', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Chose the api version for this payment method.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array(
                self::VERSION3 => __('V3 (iDEAL In3)'),
                self::VERSION2 => __('V2 (Capayabel/In3)'),
            ),
            'default'     => self::VERSION3
        );

        $this->form_fields['icon'] = array(
            'title'       => __('Payment Logo', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'in3_logo',
            'description' => __('Determines the logo that will be shown in the checkout', 'wc-buckaroo-bpe-gateway'),
            'options'     => array(
                'svg/in3-ideal.svg' => BuckarooConfig::getIconPath('svg/in3-ideal.svg', 'svg/in3-ideal.svg'),
                self::DEFAULT_ICON_VALUE => BuckarooConfig::getIconPath('svg/in3.svg', 'svg/in3.svg'),
            ),
            'default'     => 'svg/in3-ideal.svg'
        );
    }


    /**
     * Create custom logo selector
     *
     * @param mixed $key
     * @param mixed $data
     *
     * @return void
     */
    public function generate_in3_logo_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
            'options'           => array(),
        );

        $data  = wp_parse_args($data, $defaults);
        $value = $this->get_option($key);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. 
                                                                                                                ?></label>
            </th>
            <td>
                <fieldset>
                    <div class="bk-in3-logo-wrap">
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <?php foreach ((array) $data['options'] as $option_key => $option_value) : ?>
                            <label class="bk-in3-logo" for="bk-logo-<?php echo esc_attr($option_key); ?>">
                                <input type="radio" id="bk-logo-<?php echo esc_attr($option_key); ?>" name="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($option_key); ?>" <?php checked((string) $option_key, esc_attr($value)); ?>>
                                <img src="<?php echo esc_url($option_value); ?>" / alt="<?php echo esc_attr($option_key); ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. 
                    ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }
}
