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

        $this->assertSame([$integration, 'applyDiscount'], $filters['jankx/ecommerce/cart/discount']);
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
