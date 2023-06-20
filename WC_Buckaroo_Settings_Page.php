<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_Buckaroo_Settings_Page', false)) {
    return new WC_Buckaroo_Settings_Page(
        new WC_Gateway_Buckaroo_MasterSettings()
    );
}

/**
 * WC_Buckaroo_Settings_Page.
 */
class WC_Buckaroo_Settings_Page extends WC_Settings_Page
{

    protected $gateway;
    /**
     * Constructor.
     */
    public function __construct(WC_Settings_API $gateway)
    {
        $this->gateway = $gateway;

        $this->id    = 'buckaroo_settings';
        $this->label = __('Buckaroo Settings', 'wc-buckaroo-bpe-gateway');
        parent::__construct();

        add_action(
            'woocommerce_admin_field_buckaroo_payment_list', [$this, "render_payment_list"]
        );
        add_action(
            'woocommerce_admin_field_buckaroo_button', [$this, "render_button_field"]
        );
        add_action(
            'woocommerce_admin_field_buckaroo_hidden', [$this, "render_hidden_field"]
        );
        add_action(
            'woocommerce_admin_field_buckaroo_file', [$this, "render_file_field"]
        );
        add_action(
            'woocommerce_admin_field_buckaroo_submeniu', [$this, "render_submeniu_field"]
        );
    }
    /**
     * @inheritDoc
     */
    protected function get_own_sections()
    {
        return array(
            '' => __('General', 'wc-buckaroo-bpe-gateway'),
            'methods' => __('Payment methods', 'wc-buckaroo-bpe-gateway'),
            'report' => __('Report', 'wc-buckaroo-bpe-gateway')
        );
    }
    /**
     * Version lower than 5.5 section compatibility
     *
     * @return void
     */
    public function get_sections()
    {
        return $this->get_own_sections();
    }
    /**
     * @inheritDoc
     */
    public function output()
    {
        
        global $current_section, $hide_save_button;


        if (version_compare(WOOCOMMERCE_VERSION, '5.5.0', '<')) {
            if ($current_section === '') {
                WC_Admin_Settings::output_fields($this->get_settings_for_default_section());
            }
        }

        parent::output();
        

        if ($current_section === 'report') {
            (new Buckaroo_Report_Page())->output_report();
            $hide_save_button = true;
        } 
        if ($current_section === 'methods') {
            $this->render_gateway_list();
            $hide_save_button = true;
        }
        if ($current_section === 'logs' && isset($_GET['log_file'])) {
            (new Buckaroo_Report_Page())->display_log_file(
                sanitize_text_field($_GET['log_file'])
            );
            $hide_save_button = true;
        }
    }
    public function save()
    {
        if (version_compare(WOOCOMMERCE_VERSION, '5.5.0', '<')) {
            $this->save_settings_for_current_section();
        }
        parent::save();
    }
    /** 
     * @inheritDoc 
     * */
    public function save_settings_for_current_section()
    {
        global $current_section;
        if ($current_section === '') {
            $this->gateway->process_admin_options();
            $this->getErrors();
            //update certificate list with new values
            $this->gateway->initCerificateFields();
        }
    }
    /**
     * Display any form validation errors to the page
     *
     * @return void
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
    /** 
     * @inheritDoc 
     * */
    public function get_settings_for_default_section()
    {
        $settings = array_merge(
            array(
                array(
                    'title' => __(
                        'Buckaroo settings', 'wc-buckaroo-bpe-gateway'
                    ),
                    'type'=>'buckaroo_submeniu',
                    'links'=> [
                        [
                            "name" => __('Documentation', 'wc-buckaroo-bpe-gateway'),
                            "href" => 'https://support.buckaroo.nl/categorieen/plugins/woocommerce'
                        ],
                        [
                            "name" => __('FAQ', 'wc-buckaroo-bpe-gateway'),
                            "href" => 'https://support.buckaroo.nl/categorieen/plugins/woocommerce/faq-woocommerce'
                        ]
                    ]
                ),
                array(
                    'type'  => 'title',
                    'id'    => 'buckaroo-page',
                    'desc' => __(
                        'Integrate more then 30+ international payment methods in your WooCommerce webshop. Simply enable them into your WooCommerce webshop with the Buckaroo Payments plugin.
                        Please go to the <a href="https://plaza.buckaroo.nl/Configuration/Website/Index/">signup page</a> to create a Buckaroo account and start receiving payments.</br>
                        Contact <a href="mailto:support@buckaroo.nl">support@buckaroo.nl</a> if you have any questions about this plugin.',
                        'wc-buckaroo-bpe-gateway'
                    ),
                ),
                array(
                    'type' => 'buckaroo_payment_list'
                ),
                array(
                    'title' => __(
                        'General settings', 'wc-buckaroo-bpe-gateway'
                    ),
                    'type'  => 'title',
                    'id'    => 'buckaroo-general-settings',
                   
                ),
            ),
            $this->get_master_settings()
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'buckaroo-page',
        );
        return $settings;
    }
    /**
     * Get master fields
     *
     * @return array
     */
    public function get_master_settings()
    {
        $fields = [];
        foreach ($this->gateway->form_fields as $id => $field) {

            $type =$field['type'];

            if (in_array($field['type'], ['button', 'hidden', 'file'])) {
                $type = "buckaroo_".$field['type'];
            }

            $field = array_merge(
                $field,
                array(
                    'id' => $this->gateway->get_field_key($id),
                    'desc' => $field['description'],
                    'value' => $this->gateway->get_option($id),
                    'type' =>  $type
                )
            );
            unset($field['description']);
            $fields[] = $field;
        }
        return $fields;
    }
    public function render_payment_list()
    {
        $gateways = $this->getBuckarooGateways();
        $containerHeight = ceil(count($gateways) / 3) * 45
        ?>
        <ul class="buckaroo-payment-list" style="height:<?php echo esc_attr($containerHeight); ?>px">
        <?php foreach ($gateways as $gateway) {
            $method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
        ?>
            <li>
                <?php 
                if ($gateway->icon !== null) {
                    ?>
                    <img class="buckaroo-payment-list-icon" src="<?php echo esc_url($gateway->icon); ?>">
                    <?php 
                }
                echo wp_kses_post(str_replace("Buckaroo ", "", $method_title));
                if (wc_string_to_bool($gateway->enabled)) {
                    if ($gateway->mode === 'live') {
                        echo '<b class="buckaroo-payment-status buckaroo-payment-enabled-live">'.esc_html__('enabled (live)', 'wc-buckaroo-bpe-gateway').'</b>'; 
                    } else {
                        echo '<b class="buckaroo-payment-status buckaroo-payment-enabled-test">'.esc_html__('enabled (test)', 'wc-buckaroo-bpe-gateway').'</b>'; 
                    }
                } else {
                    echo '<b class="buckaroo-payment-status buckaroo-payment-disabled">'.esc_html__('disabled', 'wc-buckaroo-bpe-gateway').'</b>';
                }

                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id)));?>">
                <?php echo esc_html__('edit', 'wc-buckaroo-bpe-gateway'); ?>
            </a>
            </li>
        <?php
        }?>

        </ul>
        <?php
    }
    /**
     * Add custom file field
     *
     * @param array $value
     *
     * @return void
     */
    public function render_file_field($value)
    {
        ?>
            <tr><td>
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
                        <?php foreach ($value['links'] as $key => $link) {
                            $endSlash = $key === count($value['links']) - 1 ? '' : '|';
                            echo '<li><b><a href="'.esc_url($link['href']).'"> '.esc_html($link['name']).' </a></b>'.esc_html($endSlash);
                        }
                        ?>
                    </ul>
                </td>
            <tr>
        <?php
    }
     /**
     * Add custom hidden field
     *
     * @param array $value
     *
     * @return void
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
     * Add custom button field
     *
     * @param array $value
     *
     * @return void
     */
    public function render_button_field($value)
    {
        $custom_attributes = array();

        
        $field_description = WC_Admin_Settings::get_field_description($value);
        $description       = $field_description['description'];
        $tooltip_html      = $field_description['tooltip_html'];

        ?><tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?>
                            <?php 
                                echo wp_kses($tooltip_html, array("span"=>array("class"=>true, "data-tip"=>true)));
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
                            if (!empty($value['custom_attributes']) && is_array($value['custom_attributes'])) {
                                foreach ($value['custom_attributes'] as $attribute => $attribute_value) {
                                    echo esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
                                }
                            }
                        ?>
                        />
                        <?php 
                            echo esc_html($value['suffix']); 
                            echo wp_kses($description, array("p"=>array("class" =>true, "style"=>true)));
                        ?>
                    </td>
                </tr>
        <?php
    }
    /**
     * Filter gateways to display only our gateways
     *
     * @return array
     */
    protected function getBuckarooGateways()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $gateways = array_filter(
            $gateways,
            function ($gateway) {
                return $gateway instanceof WC_Gateway_Buckaroo;
            }
        );
        return $this->sortGatewaysAlfa($gateways);
    }
    /**
     * Sort payment gateway alphabetically by name
     *
     * @param array $gateway
     *
     * @return array
     */
    protected function sortGatewaysAlfa($gateways)
    {
        uasort(
            $gateways,
            function ($a, $b) {
                return strcmp(
                    strtolower(str_replace("Buckaroo ", "", $a->get_method_title())), 
                    strtoLower(str_replace("Buckaroo ", "", $b->get_method_title()))
                );
            }
        );
        return $gateways;
    }
    /**
     * Render the gateway list
     *
     * @return void
     */
    protected function render_gateway_list()
    {
        ?>
        <h2><?php echo esc_html__('Payment methods', 'wc-buckaroo-bpe-gateway'); ?></h2>
        <p>
            <?php 
             echo esc_html__('Buckaroo payment methods are listed below and can be accessed, enabled or disabled.', 'wc-buckaroo-bpe-gateway'); 
            ?>
        </p>
        <tr valign="top">
        <td class="wc_payment_gateways_wrapper" colspan="2">
            <table class="wc_gateways widefat" cellspacing="0" aria-describedby="payment_gateways_options-description">
                <thead>
                    <tr>
                        <?php
                        $columns = array(
                            'name'        => __('Method', 'wc-buckaroo-bpe-gateway'),
                            'status'      => __('Enabled', 'wc-buckaroo-bpe-gateway'),
                            'action'      => '',
                        );
                        foreach ( $columns as $key => $column ) {
                            echo '<th class="' . esc_attr($key) . '">' . esc_html($column) . '</th>';
                        }
                        ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ( $this->getBuckarooGateways() as $gateway ) {
    
                            echo '<tr data-gateway_id="' . esc_attr($gateway->id) . '">';
    
                            foreach ( $columns as $key => $column ) {
                                
                                $method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
                                $custom_title = $gateway->get_title();
                                
                                $width = '';

                                if (in_array($key, array( 'status', 'action' ), true)) {
                                    $width = '1%';
                                }

                                echo '<td class="' . esc_attr($key) . '" width="' . esc_attr($width) . '">';
    
                                switch ( $key ) {
                                case 'name':
                                    echo '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id))) . '" class="wc-payment-gateway-method-title">' . wp_kses_post(str_replace("Buckaroo ", "", $method_title)) . '</a>';
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
}
