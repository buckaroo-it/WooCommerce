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
                WC_Admin_Settings::output_fields($this->get_general_settings());
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
     * Get general settings
     */
    public function get_general_settings()
    {
        $generalFields = [
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

        $settings = [
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
        ];

        $settings = array_merge($settings, $this->get_fields_by_keys($generalFields));

        $settings[] = [
            'type' => 'sectionend',
            'id' => 'buckaroo-general',
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

    public function render_payment_list()
    {
        $gateways = $this->getBuckarooGateways();
        $containerHeight = ceil(count($gateways) / 3) * 45;
        ?>
<ul class="buckaroo-payment-list" style="height:<?php echo esc_attr($containerHeight); ?>px">
        <?php
        foreach ($gateways as $gateway) {
            $method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
            ?>
<li>
            <?php
            if ($gateway->icon !== null) {
                ?>
<img class="buckaroo-payment-list-icon" src="<?php echo esc_url($gateway->icon); ?>">
                <?php
            }
            echo wp_kses_post(str_replace('Buckaroo ', '', $method_title));
            if (wc_string_to_bool($gateway->enabled)) {
                if ($gateway->mode === 'live') {
                    echo '<b class="buckaroo-payment-status buckaroo-payment-enabled-live">' . esc_html__('enabled (live)', 'wc-buckaroo-bpe-gateway') . '</b>';
                } else {
                    echo '<b class="buckaroo-payment-status buckaroo-payment-enabled-test">' . esc_html__('enabled (test)', 'wc-buckaroo-bpe-gateway') . '</b>';
                }
            } else {
                echo '<b class="buckaroo-payment-status buckaroo-payment-disabled">' . esc_html__('disabled', 'wc-buckaroo-bpe-gateway') . '</b>';
            }

            ?>
<a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id))); ?>">
            <?php echo esc_html__('edit', 'wc-buckaroo-bpe-gateway'); ?>
</a>
</li>
            <?php
        }
        ?>

</ul>
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
