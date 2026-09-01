<?php

namespace Buckaroo\Woocommerce\Admin;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Services\Logger;
use WC_Admin_Settings;
use WC_Settings_API;
use WC_Settings_Page;

class GeneralSettings extends WC_Settings_Page
{
    protected $gateway;

    /**
    Constructor.
     */
    public function __construct(WC_Settings_API $gateway)
    {
        $this->gateway = $gateway;
        Logger::log(__METHOD__ . '|1|', $_POST);
        $this->id = 'buckaroo_settings';
        $this->label = __('Buckaroo Settings', 'wc-buckaroo-bpe-gateway');
        parent::__construct();

        add_action(
            'woocommerce_admin_field_buckaroo_button',
            [$this, 'render_button_field']
        );
        add_action(
            'woocommerce_admin_field_buckaroo_hidden',
            [$this, 'render_hidden_field']
        );
        add_action(
            'woocommerce_admin_field_buckaroo_file',
            [$this, 'render_file_field']
        );
    }

    /**
    Version lower than 5.5 section compatibility

    @return void
     */
    public function get_sections()
    {
        return $this->get_own_sections();
    }

    /**
    {@inheritDoc}
     */
    protected function get_own_sections()
    {
        return [
            '' => __('General Settings', 'wc-buckaroo-bpe-gateway'),
            'methods' => __('Payment Methods', 'wc-buckaroo-bpe-gateway'),
            'verification' => __('Verification Settings', 'wc-buckaroo-bpe-gateway'),
            'advanced' => __('Advanced Settings', 'wc-buckaroo-bpe-gateway'),
            'report' => __('Reports', 'wc-buckaroo-bpe-gateway'),
        ];
    }

    /**
    {@inheritDoc}
     */
    public function output()
    {
        global $current_section, $hide_save_button;

        switch ($current_section) {
            case '':
                $this->render_intro_card();
                $this->render_payment_list();
                $this->render_api_credentials_card_inner();
                echo '<div class="bk-general-options-card">';
                echo '<h2>' . esc_html__('General Options', 'wc-buckaroo-bpe-gateway') . '</h2>';
                echo '<p class="description">' . esc_html__('Configure transaction, fee and locale settings.', 'wc-buckaroo-bpe-gateway') . '</p>';
                WC_Admin_Settings::output_fields($this->get_general_right_settings());
                echo '</div>';
                break;
            case 'verification':
                $this->render_verification_header();
                WC_Admin_Settings::output_fields($this->get_verification_settings());
                break;
            case 'advanced':
                WC_Admin_Settings::output_fields($this->get_advanced_settings());
                break;
            case 'methods':
                $this->render_gateway_list();
                $hide_save_button = true;
                break;
            case 'report':
                if (isset($_GET['log_file'])) {
                    (new ReportPage())->display_log_file(sanitize_text_field($_GET['log_file']));
                } else {
                    (new ReportPage())->output_report();
                }
                $hide_save_button = true;
                break;
        }
    }



    public function get_general_right_settings()
    {
        $generalFields = [
            'transactiondescription',
            'refund_description',
            'feetax',
            'paymentfeevat',
            'culture'
        ];

        $settings = [
            [
                'type'  => 'title',
                'id'    => 'buckaroo-general-options',
                'title' => '',
            ],
        ];

        $settings = array_merge($settings, $this->get_fields_by_keys($generalFields));

        $settings[] = [
            'type' => 'sectionend',
            'id'   => 'buckaroo-general-options',
        ];

        return $settings;
    }

    public function get_verification_settings()
    {
        $verificationFields = [
            'useidin',
            'idincategories'
        ];

        $settings = [
            [
                'title' => __('Verification Settings', 'wc-buckaroo-bpe-gateway'),
                'type' => 'title',
                'id' => 'buckaroo-verification',
            ],
        ];

        $settings = array_merge($settings, $this->get_fields_by_keys($verificationFields));

        $settings[] = [
            'type' => 'sectionend',
            'id' => 'buckaroo-verification',
        ];

        return $settings;
    }

    public function get_advanced_settings()
    {
        $advancedFields = [
            'debugmode',
            'logstorage'
        ];

        $settings = [
            [
                'title' => __('Advanced Settings', 'wc-buckaroo-bpe-gateway'),
                'type' => 'title',
                'id' => 'buckaroo-advanced',
                'desc' => __('Configure advanced debugging and development settings.', 'wc-buckaroo-bpe-gateway'),
            ],
        ];

        $settings = array_merge($settings, $this->get_fields_by_keys($advancedFields));

        $settings[] = [
            'type' => 'sectionend',
            'id' => 'buckaroo-advanced',
        ];

        return $settings;
    }

    public function get_fields_by_keys($fieldKeys)
    {
        $this->gateway->init_form_fields();
        $fields = [];

        foreach ($fieldKeys as $id) {
            if (! isset($this->gateway->form_fields[$id])) {
                continue;
            }

            $field = $this->gateway->form_fields[$id];
            $type = $field['type'];

            if (in_array($field['type'], ['button', 'hidden', 'file'])) {
                $type = 'buckaroo_' . $field['type'];
            }

            $field = array_merge(
                $field,
                [
                    'id' => $this->gateway->get_field_key($id),
                    'desc' => $field['description'] ?? '',
                    'value' => $this->gateway->get_option($id),
                    'type' => $type,
                ]
            );
            unset($field['description']);
            $fields[] = $field;
        }

        return $fields;
    }

    /**
    Render the gateway list

    @return void
     */
    /**
     * One card for a gateway, shared by the Payment Methods list and the settings page.
     * The subtitle is passed in because the two pages show different metadata.
     */
    private function render_gateway_card(AbstractPaymentGateway $gateway, string $subtitle, bool $inList = false): void
    {
        $method_title  = $gateway->get_method_title() ?: $gateway->get_title();
        $display_title = str_replace('Buckaroo ', '', $method_title);
        $manage_url    = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id));
        $is_enabled    = wc_string_to_bool($gateway->enabled);

        $status_class = 'bk-status--disabled';
        $status_label = esc_html__('Inactive', 'wc-buckaroo-bpe-gateway');
        if ($is_enabled) {
            $mode = isset($gateway->mode) ? strtolower((string) $gateway->mode) : 'test';
            if ($mode === 'live') {
                $status_class = 'bk-status--live';
                $status_label = esc_html__('Active', 'wc-buckaroo-bpe-gateway');
            } else {
                $status_class = 'bk-status--test';
                $status_label = esc_html__('Test', 'wc-buckaroo-bpe-gateway');
            }
        }

        ?>
<div class="buckaroo-payment-card<?php echo $inList ? ' buckaroo-gateway-list__card' : ''; ?>"<?php echo $inList ? ' data-gateway_id="' . esc_attr($gateway->id) . '"' : ''; ?>>
    <div class="buckaroo-payment-card-icon">
        <?php if (! empty($gateway->icon)) : ?>
        <img src="<?php echo esc_url($gateway->icon); ?>" alt="<?php echo esc_attr($display_title); ?>">
        <?php else : ?>
        <span class="buckaroo-payment-card-icon-placeholder"><?php echo esc_html(strtoupper(substr($display_title, 0, 2))); ?></span>
        <?php endif; ?>
    </div>
    <div class="buckaroo-payment-card-info">
        <div class="buckaroo-payment-card-title"><?php echo esc_html($display_title); ?></div>
        <div class="buckaroo-payment-card-subtitle"><?php echo wp_kses_post($subtitle); ?></div>
    </div>
    <div class="buckaroo-payment-card-actions">
        <span class="bk-status-pill <?php echo esc_attr($status_class); ?>">
            <span class="bk-status-pill-dot"></span><?php echo esc_html($status_label); ?>
        </span>
        <?php
        if ($inList) :
            $toggle_label = $is_enabled
                /* Translators: %s Payment gateway name. */
                ? sprintf(__('The "%s" payment method is currently enabled', 'wc-buckaroo-bpe-gateway'), $method_title)
                /* Translators: %s Payment gateway name. */
                : sprintf(__('The "%s" payment method is currently disabled', 'wc-buckaroo-bpe-gateway'), $method_title);
            ?>
        <a class="wc-payment-gateway-method-toggle-enabled" href="<?php echo esc_url($manage_url); ?>" title="<?php echo esc_attr($toggle_label); ?>">
            <span class="woocommerce-input-toggle <?php echo esc_attr($is_enabled ? 'woocommerce-input-toggle--enabled' : 'woocommerce-input-toggle--disabled'); ?>" aria-label="<?php echo esc_attr($toggle_label); ?>"><?php echo esc_html($is_enabled ? __('Yes', 'wc-buckaroo-bpe-gateway') : __('No', 'wc-buckaroo-bpe-gateway')); ?></span>
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url($manage_url); ?>" class="buckaroo-payment-card-settings" title="<?php echo esc_attr__('Settings', 'wc-buckaroo-bpe-gateway'); ?>">
            <?php echo $this->settings_icon(); ?>
        </a>
    </div>
</div>
        <?php
    }

    private function settings_icon(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<line x1="4" y1="6" x2="20" y2="6"/>'
            . '<line x1="4" y1="12" x2="20" y2="12"/>'
            . '<line x1="4" y1="18" x2="20" y2="18"/>'
            . '<circle cx="8" cy="6" r="2" fill="currentColor" stroke="none"/>'
            . '<circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"/>'
            . '<circle cx="10" cy="18" r="2" fill="currentColor" stroke="none"/>'
            . '</svg>';
    }

    protected function render_gateway_list()
    {
        ?>
<h2><?php echo esc_html__('Payment Methods', 'wc-buckaroo-bpe-gateway'); ?></h2>
<p><?php echo esc_html__('Buckaroo payment methods are listed below and can be accessed, enabled or disabled.', 'wc-buckaroo-bpe-gateway'); ?></p>
<div class="buckaroo-gateway-list">
<div class="buckaroo-gateway-list__header">
    <span class="buckaroo-gateway-list__header-method"><?php echo esc_html__('Method', 'wc-buckaroo-bpe-gateway'); ?></span>
    <span class="buckaroo-gateway-list__header-enabled"><?php echo esc_html__('Status', 'wc-buckaroo-bpe-gateway'); ?></span>
    <span class="buckaroo-gateway-list__header-enabled"><?php echo esc_html__('Enabled', 'wc-buckaroo-bpe-gateway'); ?></span>
    <span class="buckaroo-gateway-list__header-actions"><?php echo esc_html__('Settings', 'wc-buckaroo-bpe-gateway'); ?></span>
</div>
        <?php
        foreach ($this->getBuckarooGateways() as $gateway) {
            $currencies = $gateway->getSupportedCurrencies();
            $countries  = $gateway->getSupportedCountries();
            $country_label  = $gateway->getCountryLabel();
            $currency_label = $gateway->getCurrencyLabel();

            $country_html = '<span class="bk-meta-countries">'
                . ($country_label !== null ? esc_html($country_label) : implode('<span class="bk-meta-sep"> &middot; </span>', array_map('esc_html', $countries)))
                . '</span>';

            $currency_html = '<span class="bk-meta-currencies">'
                . ($currency_label !== null ? esc_html($currency_label) : implode('<span class="bk-meta-sep"> &middot; </span>', array_map('esc_html', $currencies)))
                . '</span>';

            $this->render_gateway_card(
                $gateway,
                $country_html . '<span class="bk-meta-divider"> | </span>' . $currency_html,
                true
            );
        }
        ?>
</div>
        <?php
    }

    /**
    Filter gateways to display only our gateways

    @return array
     */
    protected function getBuckarooGateways()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $gateways = array_filter(
            $gateways,
            function ($gateway) {
                return $gateway instanceof AbstractPaymentGateway;
            }
        );

        return $this->sortGatewaysAlfa($gateways);
    }

    /**
    Sort payment gateway alphabetically by name

    @param array $gateway

    @return array
     */
    protected function sortGatewaysAlfa($gateways)
    {
        uasort(
            $gateways,
            function ($a, $b) {
                return strcmp(
                    strtolower(str_replace('Buckaroo ', '', $a->get_method_title())),
                    strtolower(str_replace('Buckaroo ', '', $b->get_method_title()))
                );
            }
        );

        return $gateways;
    }

    public function save()
    {
        global $current_section;

        if (in_array($current_section, ['', 'verification', 'advanced'])) {
            $originalFields = $this->gateway->form_fields;
            $this->gateway->form_fields = $this->get_current_section_fields($current_section);

            $this->gateway->process_admin_options();
            $this->gateway->form_fields = $originalFields;

            if ($current_section === '') {
                $this->validateApiCredentials();
            }

            $this->getErrors();
        }
    }

    /** `required` on the inputs only binds the browser, so confirm the keys really arrived. */
    private function validateApiCredentials(): void
    {
        $labels = [
            'merchantkey' => __('Website key', 'wc-buckaroo-bpe-gateway'),
            'secretkey' => __('Secret key', 'wc-buckaroo-bpe-gateway'),
        ];

        foreach ($labels as $key => $label) {
            if (trim((string) $this->gateway->get_option($key, '')) === '') {
                $this->gateway->add_error(
                    /* Translators: %s Payment credential name. */
                    sprintf(__('%s is required. Payments will fail until it is filled in.', 'wc-buckaroo-bpe-gateway'), $label)
                );
            }
        }
    }

    private function get_current_section_fields($section)
    {
        $this->gateway->init_form_fields();
        $allFields = $this->gateway->form_fields;
        $sectionFields = [];

        $fieldKeys = $this->get_section_field_keys($section);

        foreach ($fieldKeys as $key) {
            if (isset($allFields[$key])) {
                $sectionFields[$key] = $allFields[$key];
            }
        }

        return $sectionFields;
    }

    private function get_section_field_keys($section)
    {
        switch ($section) {
            case '':
                return [
                    'merchantkey',
                    'secretkey',
                    'test_credentials',
                    'auto_configure',
                    'transactiondescription',
                    'refund_description',
                    'feetax',
                    'paymentfeevat',
                    'culture'
                ];
            case 'verification':
                return ['useidin', 'idincategories'];
            case 'advanced':
                return ['debugmode', 'logstorage'];
            default:
                return [];
        }
    }

    /**
    Display any form validation errors to the page

    @return void
     */
    public function getErrors()
    {
        $errors = $this->gateway->get_errors();
        if (count($errors)) {
            foreach ($errors as $error) {
                WC_Admin_Settings::add_error($error);
            }
        }
    }


    public function render_intro_card()
    {
        ?>
<div class="bk-intro-hero">
    <div class="bk-intro-hero__logo">
        <img src="<?php echo esc_url(plugin_dir_url(BK_PLUGIN_FILE) . 'library/buckaroo_images/buckaroo_small.png'); ?>"
             alt="Buckaroo"
             class="bk-intro-hero__logo-img" />
    </div>
    <p class="bk-intro-hero__desc">
        <?php esc_html_e('Give every payment the attention it deserves, with 40+ methods in one plugin.', 'wc-buckaroo-bpe-gateway'); ?>
    </p>
    <div class="bk-intro-hero__actions">
        <a href="https://docs.buckaroo.io/docs/woocommerce" target="_blank" rel="noopener" class="bk-hero-btn bk-hero-btn--docs">
            <span class="dashicons dashicons-book" style="font-size:16px;width:16px;height:16px;vertical-align:middle;"></span>
            <?php esc_html_e('Documentation', 'wc-buckaroo-bpe-gateway'); ?>
        </a>
        <a href="mailto:support@buckaroo.nl" class="bk-hero-btn bk-hero-btn--contact">
            <span class="dashicons dashicons-email-alt" style="font-size:16px;width:16px;height:16px;vertical-align:middle;"></span>
            <?php esc_html_e('Contact', 'wc-buckaroo-bpe-gateway'); ?>
        </a>
    </div>
</div>
        <?php
    }

    public function render_verification_header()
    {
        $idin_logo_url = plugin_dir_url(BK_PLUGIN_FILE) . 'library/buckaroo_images/idin_logo.svg';
        ?>
<div class="bk-gateway-summary-card">
    <div class="bk-gateway-summary-card__icon">
        <img src="<?php echo esc_url($idin_logo_url); ?>" alt="iDIN" />
    </div>
    <div class="bk-gateway-summary-card__info">
        <div class="bk-gateway-summary-card__title"><?php esc_html_e('iDIN', 'wc-buckaroo-bpe-gateway'); ?></div>
        <div class="bk-gateway-summary-card__desc"><?php esc_html_e('Configure age verification and identity checking settings using iDIN.', 'wc-buckaroo-bpe-gateway'); ?></div>
        <div class="bk-gateway-summary-card__tags">
            <span class="bk-tag">NL</span>
            <span class="bk-tag">EUR</span>
        </div>
    </div>
</div>
        <?php
    }

    /** Renders a field's declared custom_attributes, such as `required`. */
    private function custom_attributes(array $field): string
    {
        $attributes = '';
        foreach ((array) ($field['custom_attributes'] ?? []) as $attribute => $value) {
            $attributes .= esc_attr($attribute) . '="' . esc_attr($value) . '" ';
        }

        return $attributes;
    }


    public function render_api_credentials_card_inner()
    {
        $merchant_key    = $this->gateway->get_option('merchantkey', '');
        $secret_key      = $this->gateway->get_option('secretkey', '');
        $merchant_key_id = $this->gateway->get_field_key('merchantkey');
        $secret_key_id   = $this->gateway->get_field_key('secretkey');

        $this->gateway->init_form_fields();
        $merchant_field = $this->gateway->form_fields['merchantkey'] ?? [];
        $secret_field   = $this->gateway->form_fields['secretkey'] ?? [];
        $test_btn_field = $this->gateway->form_fields['test_credentials'] ?? null;
        $auto_btn_field = $this->gateway->form_fields['auto_configure'] ?? null;
        ?>
<div class="bk-creds-card">

    <h2><?php esc_html_e('API credentials', 'wc-buckaroo-bpe-gateway'); ?></h2>

    <table class="form-table bk-creds-table"><tbody>

        <tr>
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($merchant_key_id); ?>"><?php esc_html_e('Website key', 'wc-buckaroo-bpe-gateway'); ?></label>
            </th>
            <td class="forminp">
                <div class="bk-creds-field">
                    <input type="password"
                           id="<?php echo esc_attr($merchant_key_id); ?>"
                           name="<?php echo esc_attr($merchant_key_id); ?>"
                           value="<?php echo esc_attr($merchant_key); ?>"
                           class="input-text regular-input"
                           placeholder="<?php esc_attr_e('Enter your website key', 'wc-buckaroo-bpe-gateway'); ?>"
                           autocomplete="off"
                           <?php echo $this->custom_attributes($merchant_field); ?>/>
                    <button type="button" class="bk-key-btn bk-key-btn--toggle" data-target="<?php echo esc_attr($merchant_key_id); ?>" title="<?php esc_attr_e('Show / hide', 'wc-buckaroo-bpe-gateway'); ?>">
                        <svg class="bk-eye-show" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="bk-eye-hide" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                    <?php if ($test_btn_field) : ?>
                    <button type="button"
                        id="<?php echo esc_attr($this->gateway->get_field_key('test_credentials')); ?>"
                        class="button button-primary bk-creds-inline-btn"
                        title="<?php esc_attr_e('Click here to verify store key & secret key.', 'wc-buckaroo-bpe-gateway'); ?>"
                        <?php echo $this->custom_attributes($test_btn_field); ?>
                    ><?php echo esc_html($test_btn_field['value'] ?? __('Test credentials', 'wc-buckaroo-bpe-gateway')); ?></button>
                    <?php endif; ?>
                </div>
                <?php if (! empty($merchant_field['description'])) : ?>
                <p class="description"><?php echo wp_kses_post($merchant_field['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($secret_key_id); ?>"><?php esc_html_e('Secret key', 'wc-buckaroo-bpe-gateway'); ?></label>
            </th>
            <td class="forminp">
                <div class="bk-creds-field">
                    <input type="password"
                           id="<?php echo esc_attr($secret_key_id); ?>"
                           name="<?php echo esc_attr($secret_key_id); ?>"
                           value="<?php echo esc_attr($secret_key); ?>"
                           class="input-text regular-input"
                           placeholder="<?php esc_attr_e('Enter your secret key', 'wc-buckaroo-bpe-gateway'); ?>"
                           autocomplete="off"
                           <?php echo $this->custom_attributes($secret_field); ?>/>
                    <button type="button" class="bk-key-btn bk-key-btn--toggle" data-target="<?php echo esc_attr($secret_key_id); ?>" title="<?php esc_attr_e('Show / hide', 'wc-buckaroo-bpe-gateway'); ?>">
                        <svg class="bk-eye-show" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="bk-eye-hide" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                    <?php if ($auto_btn_field && ($auto_btn_field['type'] ?? '') === 'button') : ?>
                    <button type="button"
                        id="<?php echo esc_attr($this->gateway->get_field_key('auto_configure')); ?>"
                        class="button bk-creds-inline-btn"
                        title="<?php esc_attr_e('Automatically configure the Buckaroo plugin based on your active subscriptions. When you use this option, the plugin will connect to your Buckaroo account, check which payment methods are active, and enable them in Live mode. You will be asked to confirm before changes are applied.', 'wc-buckaroo-bpe-gateway'); ?>"
                        <?php echo $this->custom_attributes($auto_btn_field); ?>
                    ><?php echo esc_html($auto_btn_field['value'] ?? __('Auto-configure', 'wc-buckaroo-bpe-gateway')); ?></button>
                    <?php endif; ?>
                </div>
                <?php if (! empty($secret_field['description'])) : ?>
                <p class="description"><?php echo wp_kses_post($secret_field['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>

    </tbody></table>
</div>

        <?php
    }


    public function render_payment_list()
    {
        $gateways = $this->getBuckarooGateways();
        ?>
<div class="buckaroo-payment-cards">
        <?php
        foreach ($gateways as $gateway) {
            $currencies = $gateway->getSupportedCurrencies();
            $countries  = $gateway->getSupportedCountries();
            $parts      = [];

            $country_label = $gateway->getCountryLabel();

            $parts[] = '<span class="bk-meta-countries">'
                . ($country_label !== null ? esc_html($country_label) : implode('<span class="bk-meta-sep"> &middot; </span>', array_map('esc_html', $countries)))
                . '</span>';

            $currency_label = $gateway->getCurrencyLabel();

            if ($currency_label !== null) {
                $cc_icons = '';
                if ($gateway->id === 'buckaroo_creditcard') {
                    $cc_icons_url = plugin_dir_url(BK_PLUGIN_FILE) . 'library/buckaroo_images/creditcards/';
                    $cc_icons     = '<span class="bk-cc-icons">'
                        . '<img src="' . esc_url($cc_icons_url . 'visa.svg') . '" alt="Visa" class="bk-cc-icon">'
                        . '<img src="' . esc_url($cc_icons_url . 'mastercard.svg') . '" alt="Mastercard" class="bk-cc-icon">'
                        . '<img src="' . esc_url($cc_icons_url . 'amex.svg') . '" alt="Amex" class="bk-cc-icon">'
                        . '</span>';
                }
                $parts[] = '<span class="bk-meta-currencies">' . esc_html($currency_label) . ' ' . $cc_icons . '</span>';
            } else {
                $currency_text = implode('<span class="bk-meta-sep"> &middot; </span>', array_map('esc_html', $currencies));
                $gc_icons      = '';
                if ($gateway->id === 'buckaroo_giftcard') {
                    $gc_icons_url = plugin_dir_url(BK_PLUGIN_FILE) . 'library/buckaroo_images/giftcards/';
                    $gc_icons     = '<span class="bk-cc-icons">'
                        . '<img src="' . esc_url($gc_icons_url . 'VVVgiftcard.svg') . '" alt="VVV Cadeaukaart" class="bk-cc-icon">'
                        . '<img src="' . esc_url($gc_icons_url . 'fashioncheque.svg') . '" alt="Fashioncheque" class="bk-cc-icon">'
                        . '<img src="' . esc_url($gc_icons_url . 'yourgift.svg') . '" alt="Yourgift" class="bk-cc-icon">'
                        . '</span>';
                }
                $parts[] = '<span class="bk-meta-currencies">' . $currency_text . ' ' . $gc_icons . '</span>';
            }

            $this->render_gateway_card(
                $gateway,
                ! empty($parts) ? implode('<span class="bk-meta-divider"> | </span>', $parts) : '&nbsp;'
            );
        }
        ?>
</div>
        <?php
    }

    /**
    Add custom file field

    @param array $value

    @return void
     */
    public function render_file_field($value)
    {
        ?>
<tr>
<td>
<input
name="<?php echo esc_attr($value['id']); ?>"
id="<?php echo esc_attr($value['id']); ?>"
type="file"
value="<?php echo esc_attr($value['value']); ?>"
class="<?php echo esc_attr($value['class']); ?>"
/>
</td><tr>
        <?php
    }


    /**
    Add custom hidden field

    @param array $value

    @return void
     */
    public function render_hidden_field($value)
    {
        ?>
<tr>
<input
name="<?php echo esc_attr($value['id']); ?>"
id="<?php echo esc_attr($value['id']); ?>"
type="hidden"
value="<?php echo esc_attr($value['value']); ?>"
class="<?php echo esc_attr($value['class']); ?>"
/>
</tr>
        <?php
    }

    /**
    Add custom button field

    @param array $value

    @return void
     */
    public function render_button_field($value)
    {
        $custom_attributes = [];

        $field_description = WC_Admin_Settings::get_field_description($value);
        $description = $field_description['description'];
        $tooltip_html = $field_description['tooltip_html'];

        ?>
<tr valign="top">
<th scope="row" class="titledesc">
<label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?>
        <?php
        echo wp_kses(
            $tooltip_html,
            [
                'span' => [
                    'class' => true,
                    'data-tip' => true,
                ],
            ]
        );
        ?>
</label>
</th>
<td class="forminp forminp-<?php echo esc_attr(sanitize_title($value['type'])); ?>">
<input
name="<?php echo esc_attr($value['id']); ?>"
id="<?php echo esc_attr($value['id']); ?>"
type="button"
style="<?php echo esc_attr($value['css']); ?>"
value="<?php echo esc_attr($value['value']); ?>"
class="<?php echo esc_attr($value['class']); ?> input-text regular-input "
placeholder="<?php echo esc_attr($value['placeholder']); ?>"

        <?php
        if (! empty($value['custom_attributes']) && is_array($value['custom_attributes'])) {
            foreach ($value['custom_attributes'] as $attribute => $attribute_value) {
                echo esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }
        ?>
/>
        <?php
        echo esc_html($value['suffix']);
        echo wp_kses(
            $description,
            [
                'p' => [
                    'class' => true,
                    'style' => true,
                ],
            ]
        );
        ?>
</td>
</tr>
        <?php
    }
}
