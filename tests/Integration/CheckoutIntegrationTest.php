<?php
namespace Jankx\Extensions\CouponSystem\Tests\Integration;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;
use Jankx\Extensions\CouponSystem\Integration\CheckoutIntegration;
use Jankx\Extensions\CouponSystem\Tests\Support\PostStore;
use Jankx\Extensions\CouponSystem\Tests\TestCase;

/**
 * Unit tests for CheckoutIntegration (cart/discount filter, checkout
 * validation and order completion hooks).
 */
class CheckoutIntegrationTest extends TestCase
{
    protected function integration(): CheckoutIntegration
    {
        return new CheckoutIntegration();
    }

    public function test_register_wires_cart_and_checkout_hooks()
    {
        $integration = $this->integration();
        $integration->register();

        $filters = [];
        foreach ($GLOBALS['__registered_filters'] as $entry) {
            $filters[$entry['tag']] = $entry['callback'];
        }

        $this->assertSame([$integration, 'applyDiscount'], $filters['jankx/ecommerce/cart/coupon_discount']);
        $this->assertSame([$integration, 'handleApplyCoupon'], $filters['jankx/ecommerce/cart/coupon/apply']);
        $this->assertSame([$integration, 'handleRemoveCoupon'], $filters['jankx/ecommerce/cart/coupon/remove']);
        $this->assertSame([$integration, 'getAppliedCoupons'], $filters['jankx/ecommerce/cart/coupons']);
        $this->assertSame([$integration, 'validateAppliedCoupon'], $filters['jankx/ecommerce/checkout/validate_customer']);

        $actions = [];
        foreach ($GLOBALS['__registered_actions'] as $entry) {
            $actions[$entry['tag']] = $entry['callback'];
        }

        $this->assertSame([$integration, 'onCheckoutCompleted'], $actions['jankx/ecommerce/checkout/completed']);
    }

    public function test_apply_discount_returns_input_when_no_coupon_applied()
    {
        $this->setCart('unit', 500000, []);

        $result = $this->integration()->applyDiscount(20000, \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance());

        $this->assertSame(20000.0, $result);
    }

    public function test_apply_discount_adds_coupon_discount()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount' => 10,
        ]);
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $result = $this->integration()->applyDiscount(20000, \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance());

        $this->assertSame(70000.0, $result);
    }

    public function test_apply_discount_is_zero_when_applied_coupon_invalid()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $result = $this->integration()->applyDiscount(10000, \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance());

        $this->assertSame(10000.0, $result);
    }

    public function test_validate_applied_coupon_keeps_errors_when_nothing_applied()
    {
        $errors = ['Địa chỉ sai'];

        $this->assertSame($errors, $this->integration()->validateAppliedCoupon($errors, []));
    }

    public function test_validate_applied_coupon_keeps_errors_when_coupon_valid()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $result = $this->integration()->validateAppliedCoupon([], []);

        $this->assertSame([], $result);
    }

    public function test_validate_applied_coupon_appends_error_when_invalid()
    {
        $this->setCart('unit', 100000, []);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'min_order' => 400000]);
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $result = $this->integration()->validateAppliedCoupon([], []);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('tối thiểu', $result[0]);
    }

    public function test_handle_apply_coupon_delegates_to_manager()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount' => 10,
        ]);

        $result = $this->integration()->handleApplyCoupon(
            ['success' => false, 'message' => 'default'],
            \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance(),
            'SALE10'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(50000.0, $result['discount']);
        $this->assertSame($id, $GLOBALS['__transients'][$this->sessionKey()]);
    }

    public function test_handle_remove_coupon_clears_session()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $result = $this->integration()->handleRemoveCoupon(
            ['success' => false, 'message' => 'default'],
            \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance(),
            ''
        );

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey($this->sessionKey(), $GLOBALS['__transients']);
    }

    public function test_get_applied_coupons_lists_applied_coupon()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount' => 10,
        ]);
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $coupons = $this->integration()->getAppliedCoupons([], \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance());

        $this->assertCount(1, $coupons);
        $this->assertSame($id, $coupons[0]['id']);
        $this->assertSame(50000.0, $coupons[0]['discount']);
    }

    public function test_on_checkout_completed_does_nothing_without_coupon()
    {
        $this->integration()->onCheckoutCompleted(87);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertSame([], $fired);
    }

    public function test_on_checkout_completed_marks_coupon_used()
    {
        $this->setUser(2, ['subscriber']);
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $order = new class {
            public function getId()
            {
                return 91;
            }
        };

        $this->integration()->onCheckoutCompleted($order);

        $this->assertSame(1, PostStore::meta($id, Coupon::META_PREFIX . 'used_count'));
        $this->assertArrayNotHasKey($this->sessionKey(), $GLOBALS['__transients']);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertCount(1, $fired);
        $this->assertSame(91, $fired[0]['args'][2]);
    }
}
