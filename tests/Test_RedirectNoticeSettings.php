<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;

class Test_RedirectNoticeSettings extends WP_UnitTestCase
{
    public function test_redirect_notice_setting_is_enabled_until_merchant_turns_it_off(): void
    {
        global $current_section;

        $option = 'woocommerce_buckaroo_mastersettings_settings';
        $original = get_option($option, null);
        $previousSection = $current_section;

        try {
            delete_option($option);
            $gateway = new PaymentMethodSettings();
            $page = new GeneralSettings($gateway);
            $fieldKey = $gateway->get_field_key('show_redirect_notice');
            $fields = $page->get_general_right_settings();

            $this->assertContains($fieldKey, array_column($fields, 'id'));
            $this->assertSame('yes', $gateway->get_option('show_redirect_notice'));

            $current_section = '';
            $gateway->set_post_data([]);
            $page->save();
            $this->assertSame('no', (new PaymentMethodSettings())->get_option('show_redirect_notice'));

            $gateway->set_post_data([$fieldKey => '1']);
            $page->save();
            $this->assertSame('yes', (new PaymentMethodSettings())->get_option('show_redirect_notice'));
        } finally {
            $current_section = $previousSection;
            if ($original === null) {
                delete_option($option);
            } else {
                update_option($option, $original);
            }
        }
    }
}
