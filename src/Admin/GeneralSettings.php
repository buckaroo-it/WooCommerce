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
            'woocommerce_admin_field_buckaroo_api_credentials',
            [$this, 'render_api_credentials_card']
        );
        add_action(
            'woocommerce_admin_field_buckaroo_payment_list',
            [$this, 'render_payment_list']
        );
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
        add_action(
            'woocommerce_admin_field_buckaroo_submeniu',
            [$this, 'render_submeniu_field']
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
                // Top part: submenu, title, payment list
                WC_Admin_Settings::output_fields($this->get_general_top_settings());
                // API credentials card (full width)
                $this->render_api_credentials_card_inner();
                // General options fields wrapped in a matching card
                echo '<div class="bk-general-options-card">';
                echo '<div class="bk-general-options-card__header">';
                echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
                echo '<div><strong class="bk-general-options-card__title">' . esc_html__('General Options', 'wc-buckaroo-bpe-gateway') . '</strong><span class="bk-general-options-card__sub">' . esc_html__('Configure transaction, fee and locale settings.', 'wc-buckaroo-bpe-gateway') . '</span></div>';
                echo '</div>';
                WC_Admin_Settings::output_fields($this->get_general_right_settings());                echo '</div>';
                break;
            case 'verification':
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

    /**
     * Get general settings - kept for save() compatibility
     */
    public function get_general_settings()
    {
        return array_merge($this->get_general_top_settings(), $this->get_general_right_settings());
    }

    /**
     * Top part: submenu, intro text, payment list
     */
    public function get_general_top_settings()
    {
        return [
            [
                'title' => __('Buckaroo General Settings', 'wc-buckaroo-bpe-gateway'),
                'type' => 'buckaroo_submeniu',
                'links' => [
                    [
                        'name' => __('Documentation', 'wc-buckaroo-bpe-gateway'),
                        'href' => 'https://support.buckaroo.nl/categorieen/plugins/woocommerce',
                    ],
                    [
                        'name' => __('FAQ', 'wc-buckaroo-bpe-gateway'),
                        'href' => 'https://support.buckaroo.nl/categorieen/plugins/woocommerce/faq-woocommerce',
                    ],
                ],
            ],
            [
                'type' => 'title',
                'id' => 'buckaroo-general',
                'desc' => __(
                    'Integrate more then 30+ international payment methods in your WooCommerce webshop. Simply enable them into your WooCommerce webshop with the Buckaroo Payments plugin.</br>Please go to the <a href="https://plaza.buckaroo.nl/Configuration/Website/Index/">signup page</a> to create a Buckaroo account and start receiving payments.</br>Contact <a href="mailto:support@buckaroo.nl">support@buckaroo.nl</a> if you have any questions about this plugin.',
                    'wc-buckaroo-bpe-gateway'
                ),
            ],
            [
                'type' => 'buckaroo_payment_list',
            ],
            [
                'type' => 'sectionend',
                'id' => 'buckaroo-general-top',
            ],
        ];
    }

    /**
     * Right column: the other general fields
     */
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
                'title' => '', // hidden via CSS; card header already shows the title
            ],
        ];

        $settings = array_merge($settings, $this->get_fields_by_keys($generalFields));

        $settings[] = [
            'type' => 'sectionend',
            'id'   => 'buckaroo-general-options',
        ];

        return $settings;
    }

    /**
     * Get verification settings
     */
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
                'desc' => __('Configure verification settings for age verification and identity checking.', 'wc-buckaroo-bpe-gateway'),
            ],
        ];

        $settings = array_merge($settings, $this->get_fields_by_keys($verificationFields));

        $settings[] = [
            'type' => 'sectionend',
            'id' => 'buckaroo-verification',
        ];

        return $settings;
    }

    /**
     * Get advanced settings
     */
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

    /**
     * Get specific fields by their keys
     *
     * @param array $fieldKeys
     * @return array
     */
    public function get_fields_by_keys($fieldKeys)
    {
        $this->gateway->init_form_fields();
        $fields = [];

        foreach ($fieldKeys as $id) {
            if (!isset($this->gateway->form_fields[$id])) {
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
                    'desc' => $field['description'],
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
    protected function render_gateway_list()
    {
        ?>
<h2><?php echo esc_html__('Payment Methods', 'wc-buckaroo-bpe-gateway'); ?></h2>
<p>
        <?php
        echo esc_html__('Buckaroo payment methods are listed below and can be accessed, enabled or disabled.', 'wc-buckaroo-bpe-gateway');
        ?>
</p>
<tr valign="top">
<td class="wc_payment_gateways_wrapper" colspan="2">
<table class="wc_gateways widefat" cellspacing="0"
aria-describedby="payment_gateways_options-description">
<thead>
<tr>
        <?php
        $columns = [
            'name' => __('Method', 'wc-buckaroo-bpe-gateway'),
            'status' => __('Enabled', 'wc-buckaroo-bpe-gateway'),
            'action' => __('Actions', 'wc-buckaroo-bpe-gateway'),
        ];
        foreach ($columns as $key => $column) {
            echo '<th class="' . esc_attr($key) . '">' . esc_html($column) . '</th>';
        }
        ?>
</tr>
</thead>
<tbody>
        <?php
        foreach ($this->getBuckarooGateways() as $gateway) {
            echo '<tr data-gateway_id="' . esc_attr($gateway->id) . '">';

            foreach ($columns as $key => $column) {
                $method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
                $custom_title = $gateway->get_title();

                $width = '';

                if (in_array($key, ['status', 'action'], true)) {
                    $width = '1%';
                }

                echo '<td class="' . esc_attr($key) . '" width="' . esc_attr($width) . '">';

                switch ($key) {
                    case 'name':
                        echo '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id))) . '" class="wc-payment-gateway-method-title">' . wp_kses_post(str_replace('Buckaroo ', '', $method_title)) . '</a>';
                        if ($method_title !== $custom_title) {
                            echo '<span class="wc-payment-gateway-method-name">&nbsp;&ndash;&nbsp;' . wp_kses_post($custom_title) . '</span>';
                        }
                        break;
                    case 'action':
                        if (wc_string_to_bool($gateway->enabled)) {
                            /* Translators: %s Payment gateway name. */
                            echo '<a class="button alignright" aria-label="' . esc_attr(sprintf(__('Manage the "%s" payment method', 'wc-buckaroo-bpe-gateway'), $method_title)) . '" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id))) . '">' . esc_html__('Manage', 'wc-buckaroo-bpe-gateway') . '</a>';
                        } else {
                            /* Translators: %s Payment gateway name. */
                            echo '<a class="button alignright" aria-label="' . esc_attr(sprintf(__('Set up the "%s" payment method', 'wc-buckaroo-bpe-gateway'), $method_title)) . '" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id))) . '">' . esc_html__('Set up', 'wc-buckaroo-bpe-gateway') . '</a>';
                        }
                        break;
                    case 'status':
                        echo '<a class="wc-payment-gateway-method-toggle-enabled" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id))) . '">';
                        if (wc_string_to_bool($gateway->enabled)) {
                            /* Translators: %s Payment gateway name. */
                            echo '<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled" aria-label="' . esc_attr(sprintf(__('The "%s" payment method is currently enabled', 'wc-buckaroo-bpe-gateway'), $method_title)) . '">' . esc_attr__('Yes', 'wc-buckaroo-bpe-gateway') . '</span>';
                        } else {
                            /* Translators: %s Payment gateway name. */
                            echo '<span class="woocommerce-input-toggle woocommerce-input-toggle--disabled" aria-label="' . esc_attr(sprintf(__('The "%s" payment method is currently disabled', 'wc-buckaroo-bpe-gateway'), $method_title)) . '">' . esc_attr__('No', 'wc-buckaroo-bpe-gateway') . '</span>';
                        }
                        echo '</a>';
                        break;
                }

                echo '</td>';
            }

            echo '</tr>';
        }
        ?>
</tbody>
</table>
</td>
</tr>
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

            $this->getErrors();
        }
    }

    /**
     * Get fields for current section only
     */
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

    /**
     * Get field keys for a specific section
     */
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

    public function render_api_credentials_card()
    {
        echo '<tr><td colspan="2" style="padding:0;">';
        $this->render_api_credentials_card_inner();
        echo '</td></tr>';
    }

    public function render_api_credentials_card_inner()
    {
        $merchant_key    = $this->gateway->get_option('merchantkey', '');
        $secret_key      = $this->gateway->get_option('secretkey', '');
        $merchant_key_id = $this->gateway->get_field_key('merchantkey');
        $secret_key_id   = $this->gateway->get_field_key('secretkey');

        $this->gateway->init_form_fields();
        $test_btn_field = $this->gateway->form_fields['test_credentials'] ?? null;
        $auto_btn_field = $this->gateway->form_fields['auto_configure'] ?? null;
        ?>
<div class="bk-creds-card">

    <div class="bk-creds-card__header">
        <div class="bk-creds-card__header-left">
            <svg class="bk-creds-card__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div>
                <strong class="bk-creds-card__title"><?php esc_html_e('API credentials', 'wc-buckaroo-bpe-gateway'); ?></strong>
                <span class="bk-creds-card__sub"><?php esc_html_e('Find these in Buckaroo Plaza → My Buckaroo → Websites.', 'wc-buckaroo-bpe-gateway'); ?></span>
            </div>
        </div>
    </div>

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
                           class="regular-input bk-creds-input"
                           placeholder="<?php esc_attr_e('Enter your website key', 'wc-buckaroo-bpe-gateway'); ?>"
                           autocomplete="off" />
                    <button type="button" class="bk-key-btn bk-key-btn--toggle" data-target="<?php echo esc_attr($merchant_key_id); ?>" title="<?php esc_attr_e('Show / hide', 'wc-buckaroo-bpe-gateway'); ?>">
                        <svg class="bk-eye-show" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="bk-eye-hide" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
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
                           class="regular-input bk-creds-input"
                           placeholder="<?php esc_attr_e('Enter your secret key', 'wc-buckaroo-bpe-gateway'); ?>"
                           autocomplete="off" />
                    <button type="button" class="bk-key-btn bk-key-btn--toggle" data-target="<?php echo esc_attr($secret_key_id); ?>" title="<?php esc_attr_e('Show / hide', 'wc-buckaroo-bpe-gateway'); ?>">
                        <svg class="bk-eye-show" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="bk-eye-hide" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </td>
        </tr>

        <tr>
            <th></th>
            <td class="forminp">
                <div class="bk-creds-actions">
                    <?php if ($test_btn_field): ?>
                    <button type="button" id="<?php echo esc_attr($this->gateway->get_field_key('test_credentials')); ?>" class="button button-primary bk-creds-btn"
                        <?php foreach ((array)($test_btn_field['custom_attributes'] ?? []) as $attr => $val) { echo esc_attr($attr) . '="' . esc_attr($val) . '" '; } ?>>
                        <?php echo esc_html($test_btn_field['value'] ?? __('Test credentials', 'wc-buckaroo-bpe-gateway')); ?>
                    </button>
                    <p class="bk-autoconfigure-disclaimer"><?php esc_html_e('Click here to verify store key & secret key.', 'wc-buckaroo-bpe-gateway'); ?></p>
                    <?php endif; ?>
                    <?php if ($auto_btn_field && ($auto_btn_field['type'] ?? '') === 'button'): ?>
                    <button type="button" id="<?php echo esc_attr($this->gateway->get_field_key('auto_configure')); ?>" class="button bk-creds-btn"
                        <?php foreach ((array)($auto_btn_field['custom_attributes'] ?? []) as $attr => $val) { echo esc_attr($attr) . '="' . esc_attr($val) . '" '; } ?>>
                        <?php echo esc_html($auto_btn_field['value'] ?? __('Auto-configure', 'wc-buckaroo-bpe-gateway')); ?>
                    </button>
                    <p class="bk-autoconfigure-disclaimer"><?php esc_html_e('Automatically configure the Buckaroo plugin based on your active subscriptions. When you use this option, the plugin will connect to your Buckaroo account, check which payment methods are active, and enable them in Live mode. You will be asked to confirm before changes are applied.', 'wc-buckaroo-bpe-gateway'); ?></p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>

    </tbody></table>
</div>

<script>
(function () {
    document.querySelectorAll('.bk-key-btn--toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.querySelector('.bk-eye-show').style.display = hidden ? 'none' : '';
            btn.querySelector('.bk-eye-hide').style.display = hidden ? '' : 'none';
        });
    });
    document.querySelectorAll('.bk-key-btn--copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input || !input.value) return;
            navigator.clipboard.writeText(input.value).then(function () {
                btn.classList.add('bk-key-btn--copied');
                setTimeout(function () { btn.classList.remove('bk-key-btn--copied'); }, 1500);
            });
        });
    });
})();
</script>
        <?php
    }


    public function render_payment_list()
    {
        $gateways = $this->getBuckarooGateways();
        ?>
<div class="buckaroo-payment-cards">
        <?php
        foreach ($gateways as $gateway) {
            $method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
            $display_title = str_replace('Buckaroo ', '', $method_title);
            $custom_title = $gateway->get_title();
            $is_enabled = wc_string_to_bool($gateway->enabled);
            $manage_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id));

            $mode_label = '';
            $status_class = 'bk-status--disabled';
            $status_label = esc_html__('Inactive', 'wc-buckaroo-bpe-gateway');
            if ($is_enabled) {
                $mode = isset($gateway->mode) ? strtolower((string) $gateway->mode) : 'test';
                if ($mode === 'live') {
                    $status_class = 'bk-status--live';
                    $status_label = esc_html__('Active', 'wc-buckaroo-bpe-gateway');
                } else {
                    // 'test' or any other/unconfigured value defaults to Test
                    $status_class = 'bk-status--test';
                    $status_label = esc_html__('Test', 'wc-buckaroo-bpe-gateway');
                }
            }
            ?>
<div class="buckaroo-payment-card">
    <div class="buckaroo-payment-card-icon">
            <?php if ($gateway->icon !== null): ?>
        <img src="<?php echo esc_url($gateway->icon); ?>" alt="<?php echo esc_attr($display_title); ?>">
            <?php else: ?>
        <span style="width:48px;height:48px;border-radius:10px;background:#1a2340;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;text-align:center;line-height:1.2;flex-shrink:0;"><?php echo esc_html(strtoupper(substr($display_title, 0, 2))); ?></span>
            <?php endif; ?>
    </div>
    <div class="buckaroo-payment-card-info">
        <div class="buckaroo-payment-card-title"><?php echo esc_html($display_title); ?></div>
        <?php
            $currencies = $gateway->getSupportedCurrencies();
            $countries  = $gateway->getSupportedCountries();

            $parts = [];

            // â”€â”€ Country label â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $european_countries = ['AT','BE','BG','CH','CY','CZ','DE','DK','EE','ES','FI','FR','GB','GR','HR','HU','IE','IS','IT','LI','LT','LU','LV','MT','NL','NO','PL','PT','RO','SE','SI','SK'];

            if (empty($countries)) {
                $parts[] = '<span class="bk-meta-countries">Global</span>';
            } elseif (count($countries) >= 5 && count(array_diff($countries, $european_countries)) === 0) {
                $parts[] = '<span class="bk-meta-countries">Europe</span>';
            } else {
                $parts[] = '<span class="bk-meta-countries">' . implode('<span class="bk-meta-sep"> &middot; </span>', array_map('esc_html', $countries)) . '</span>';
            }

            $multi_currency_threshold = 6;
            if (count($currencies) >= $multi_currency_threshold) {
                $parts[] = '<span class="bk-meta-currencies">Multi-currency</span>';
            } else {
                $parts[] = '<span class="bk-meta-currencies">' . implode('<span class="bk-meta-sep"> &middot; </span>', array_map('esc_html', $currencies)) . '</span>';
            }
        ?>
        <div class="buckaroo-payment-card-subtitle"><?php echo !empty($parts) ? wp_kses_post(implode('<span class="bk-meta-divider"> | </span>', $parts)) : '&nbsp;'; ?></div>
    </div>
    <div class="buckaroo-payment-card-actions">
        <span class="bk-status-pill <?php echo esc_attr($status_class); ?>">
            <span class="bk-status-pill-dot"></span><?php echo esc_html($status_label); ?>
        </span>
        <a href="<?php echo esc_url($manage_url); ?>" class="buckaroo-payment-card-settings" title="<?php echo esc_attr__('Settings', 'wc-buckaroo-bpe-gateway'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="4" y1="12" x2="20" y2="12"/>
                <line x1="4" y1="18" x2="20" y2="18"/>
                <circle cx="8" cy="6" r="2" fill="currentColor" stroke="none"/>
                <circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"/>
                <circle cx="10" cy="18" r="2" fill="currentColor" stroke="none"/>
            </svg>
        </a>
    </div>
</div>
            <?php
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

    public function render_submeniu_field($value)
    {
        ?>
<h2><?php echo esc_html($value['title']); ?></h2>
<tr>
<td>
<ul class="subsubsub" style="width:100%;margin-bottom:10px">
        <?php
        foreach ($value['links'] as $key => $link) {
            $endSlash = $key === count($value['links']) - 1 ? '' : '|';
            echo '<li><b><a href="' . esc_url($link['href']) . '"> ' . esc_html($link['name']) . ' </a></b>' . esc_html($endSlash);
        }
        ?>
</ul>
</td>
<tr>
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
