<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;

/**
 * iDIN restricted to categories must also gate variations of a product in
 * such a category: the categories belong to the parent product.
 */
class Test_IdinCartCategories extends WP_UnitTestCase
{
    private const SETTINGS_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    /** @var int */
    private $idinCategoryId;

    /** @var int */
    private $otherCategoryId;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! WC()->session) {
            WC()->initialize_session();
        }
        if (! WC()->customer) {
            WC()->customer = new WC_Customer(0, true);
        }
        if (! WC()->cart) {
            WC()->initialize_cart();
        }
        WC()->cart->empty_cart(false);
        wp_set_current_user(0);
        IdinProcessor::setCurrentUserIsNotVerified();

        $this->idinCategoryId = (int) wp_insert_term('Adults only', 'product_cat')['term_id'];
        $this->otherCategoryId = (int) wp_insert_term('Everyone', 'product_cat')['term_id'];

        update_option(
            self::SETTINGS_OPTION,
            ['useidin' => 'test', 'idincategories' => [(string) $this->idinCategoryId]]
        );
        $this->resetCategoryCache();
    }

    protected function tearDown(): void
    {
        WC()->cart->empty_cart(false);
        IdinProcessor::setCurrentUserIsNotVerified();

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }
        wp_delete_term($this->idinCategoryId, 'product_cat');
        wp_delete_term($this->otherCategoryId, 'product_cat');
        $this->resetCategoryCache();

        parent::tearDown();
    }

    public function test_a_variation_of_a_product_in_an_idin_category_requires_verification(): void
    {
        [$productId, $variationId] = $this->createVariableProduct($this->idinCategoryId);
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1, $variationId, ['attribute_size' => 'L']));

        $this->assertSame([$productId], IdinProcessor::getCartProductIds());
        $this->assertTrue(IdinProcessor::isIdin(IdinProcessor::getCartProductIds()));
        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_a_verified_visitor_can_buy_a_variation_in_an_idin_category(): void
    {
        [$productId, $variationId] = $this->createVariableProduct($this->idinCategoryId);
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1, $variationId, ['attribute_size' => 'L']));

        IdinProcessor::setCurrentUserIsVerified('BIN123');

        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_a_variation_of_a_product_outside_the_idin_categories_needs_no_verification(): void
    {
        [$productId, $variationId] = $this->createVariableProduct($this->otherCategoryId);
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1, $variationId, ['attribute_size' => 'L']));

        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_a_simple_product_in_an_idin_category_requires_verification(): void
    {
        $product = new WC_Product_Simple();
        $product->set_name('Adults only simple');
        $product->set_status('publish');
        $product->set_regular_price('10.00');
        $product->set_category_ids([$this->idinCategoryId]);
        $productId = $product->save();
        $this->productIds[] = $productId;
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1));

        $this->assertSame([$productId], IdinProcessor::getCartProductIds());
        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    /**
     * @return int[] Parent product id and variation id.
     */
    private function createVariableProduct(int $categoryId): array
    {
        $size = new WC_Product_Attribute();
        $size->set_name('Size');
        $size->set_options(['L']);
        $size->set_visible(true);
        $size->set_variation(true);

        $product = new WC_Product_Variable();
        $product->set_name('Variable product');
        $product->set_status('publish');
        $product->set_attributes([$size]);
        $product->set_category_ids([$categoryId]);
        $productId = $product->save();

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($productId);
        $variation->set_status('publish');
        $variation->set_regular_price('10.00');
        $variation->set_attributes(['size' => 'L']);
        $variationId = $variation->save();

        $this->productIds[] = $variationId;
        $this->productIds[] = $productId;

        return [$productId, $variationId];
    }

    /**
     * isIdin() caches the configured categories for the request.
     */
    private function resetCategoryCache(): void
    {
        $property = new ReflectionProperty(IdinProcessor::class, 'idinCategories');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue(null, null);
    }
}
