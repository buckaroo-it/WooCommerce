<?php

Class ApplePayController 
{       
    public function getShopInformation() 
    {
        $country_code = preg_replace('/\:\*/', '', get_option('woocommerce_default_country'));
    
        echo json_encode([
            'store_name'    => get_option('blogname'),
            'country_code'  => $country_code,
            'currency_code' => get_option('woocommerce_currency'),
            'culture_code'  => $country_code,
            'merchant_id'   => get_option('woocommerce_buckaroo_applepay_settings')["merchant_guid"]
        ], JSON_PRETTY_PRINT); 
        exit;
    }
     
    public function getItemsFromDetailPage()
    {       
        $items = self::createTemporaryCart(function () {
            global $woocommerce;

            $cart = $woocommerce->cart;

            $current_shown_product = [
                'product_id'   => $_GET['product_id'],
                'variation_id' => $_GET['variation_id'],
                'quantity'     => (int) $_GET['quantity'],
            ];

            $products = array_map(function ($product) {
                //var_dump($product);
                $id = $product['variation_id'] !== 0
                    ? $product['variation_id']
                    : $product['product_id'];

                return [
                    'type'       => 'product',
                    'id'         => $id,
                    'name'       => wc_get_product($id)->get_name(),
                    'price'      => $product['line_total'] + $product['line_tax'],
                    'quantity'   => $product['quantity'],
                    'attributes' => []
                ];
            }, $cart->get_cart_contents());

            $product = reset($products);

            $coupons = array_map(function ($coupon) use ($cart) {
                $price = $cart->get_coupon_discount_amount($coupon->get_code(), $cart->display_cart_ex_tax);
                return [
                    'type'       => 'coupon',
                    'id'         => $coupon->get_id(),
                    'name'       => "Coupon: {$coupon->get_code()}",
                    'price'      => "-{$price}",
                    'quantity'   => 1,
                    'attributes' => []
                ];
            }, $cart->get_coupons());

            $extra_charge = [];

            if (self::hasExtraCharge()) {
                $extra_charge = [[
                    'type'       => 'extra_charge',
                    'id'         => 99999,
                    'name'       => __("Payment fee", 'wc-buckaroo-bpe-gateway'),
                    'price'      => self::getExtracharge([$product], $coupons),
                    'quantity'   => 1,
                    'attributes' => ['taxable' => self::extraChargeIsTaxable()]
                ]];
            } 

            return array_merge([$product], $coupons, $extra_charge);
        });
        
        echo json_encode(array_values($items), JSON_PRETTY_PRINT);
        exit;
    }

    public function getItemsFromCart()
    {
        global $woocommerce;

        $cart = $woocommerce->cart;
    
        $products = array_map(function ($product) {
            //var_dump($product);
            $id = $product['variation_id'] !== 0 
                ? $product['variation_id']
                : $product['product_id'];
            return [
                'type'       => 'product',
                'id'         => $id,
                'name'       => wc_get_product($id)->get_name(),
                'price'      => $product['line_total'] + $product['line_tax'],
                'quantity'   => $product['quantity'],
                'attributes' => []
            ];
        }, $cart->get_cart_contents());

        $coupons = array_map(function ($coupon) use ($cart) {
            $price = $cart->get_coupon_discount_amount($coupon->get_code(), $cart->display_cart_ex_tax);
            return [
                'type'       => 'coupon',
                'id'         => $coupon->get_id(),
                'name'       => "Coupon: {$coupon->get_code()}",
                'price'      => "-{$price}",
                'quantity'   => 1,
                'attributes' => []
            ];
        }, $cart->get_coupons());

        $extra_charge = [];
        
        if (self::hasExtraCharge()) {
            $extra_charge = [[
                'type'       => 'extra_charge',
                'id'         => 99999,
                'name'       => __("Payment fee", 'wc-buckaroo-bpe-gateway'),
                'price'      => self::getExtracharge($products, $coupons),
                'quantity'   => 1,
                'attributes' => ['taxable' => self::extraChargeIsTaxable()]
            ]];
        } 

        $items = array_merge($products, $coupons, $extra_charge);

        echo json_encode(array_values($items), JSON_PRETTY_PRINT);
        exit;
    }

    public function getShippingMethods()
    {    
        function wcMethods() {
            global $woocommerce;
    
            $cart = $woocommerce->cart;
            $country_code = strtoupper($_GET['country_code']);

            $customer = $woocommerce->customer;
            $customer->set_shipping_country($country_code);

            $packages = $woocommerce->cart->get_shipping_packages();    
            
            return $woocommerce->shipping
                ->calculate_shipping_for_package(current($packages))['rates']
            ;
        }
        
        if (isset($_GET['product_id'])) {
            $wc_methods = self::createTemporaryCart(function () {
                return wcMethods();
            });
        } 
        
        else { $wc_methods = wcMethods(); }

        $shipping_methods = array_map(function ($wc_method) {
            return [
                'identifier' => $wc_method->get_id(),
                'detail'     => "",
                'label'      => $wc_method->get_label(),                
                'amount'     => (float) $wc_method->get_cost(),
            ];
        }, $wc_methods);
        
        echo json_encode(array_values($shipping_methods), JSON_PRETTY_PRINT);
        exit;
    }

    private static function getExtraCharge($products, $coupons)
    {        
        $settings = get_option('woocommerce_buckaroo_applepay_settings');
        $extra_charge_amount = (float) $settings['extrachargeamount'];

        if ($settings['extrachargetype'] === 'static') {
            return (float) $settings['extrachargeamount'];
        }

        if ($settings['extrachargetype'] === 'percentage') {
            $items = array_merge($products, $coupons);
            
            $item_prices = array_map(function ($item) {
                return (float) $item['price'];
            }, $items);

            $total_items_prices = array_reduce($item_prices, function ($a, $b) {
                return $a += $b;
            }, 0);

            return number_format($total_items_prices * $extra_charge_amount / 100, 2);
        }

        return 0;
    }

    /**
     * Some methods need to have a temporary cart if a user is on the product detail page
     * We empty the cart and put the current shown product + quantity in the cart and reapply the coupons
     * to determine the discounts, free shipping and other rules based on that cart
     * @return callback
     */
    private static function createTemporaryCart($callback) 
    {
        global $woocommerce;

        $cart = $woocommerce->cart;
        $country_code = strtoupper($_GET['country_code']);

        $current_shown_product = [
            'product_id'   => $_GET['product_id'],
            'variation_id' => $_GET['variation_id'],
            'quantity'     => (int) $_GET['quantity'],
        ];

        $original_cart_products = array_map(function ($product) {
            return [
                'product_id'   => $product['product_id'],
                'variation_id' => $product['variation_id'],
                'quantity'     => $product['quantity']
            ];
        }, $cart->get_cart_contents());
            
        $original_applied_coupons = array_map(function ($coupon) {
            return [
                'coupon_id' => $coupon->get_id(),
                'code'      => $coupon->get_code()
            ];
        }, $cart->get_coupons());
        
        $cart->empty_cart();
        $cart->add_to_cart(
            $current_shown_product['product_id'],
            $current_shown_product['quantity'],
            $current_shown_product['variation_id']
        );
        
        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }
        
        $temporary_cart_result = call_user_func($callback); 
        
        $cart->empty_cart();

        foreach ($original_cart_products as $original_product) {
            $cart->add_to_cart(
                $original_product['product_id'], 
                $original_product['quantity'], 
                $original_product['variation_id']
            );
        }

        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }

        wc_clear_notices();

        return $temporary_cart_result;
    }

    private static function hasExtraCharge() 
    {
        return (float) get_option('woocommerce_buckaroo_applepay_settings')['extrachargeamount'] > 0  ? true: false;
    }

    private static function extraChargeIsTaxable()
    {
        return get_option('woocommerce_buckaroo_applepay_settings')['extrachargetaxtype'] === 'included'  ? true: false;
    }
}
