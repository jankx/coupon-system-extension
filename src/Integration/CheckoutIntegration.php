<?php
namespace Jankx\Extensions\CouponSystem\Integration;

use Jankx\Extensions\CouponSystem\CouponManager;

/**
 * Bridges the coupon system into the base-ecommerce cart & checkout flow:
 *
 *  - Provides the flexible coupon API of base-ecommerce:
 *    applies/removes coupons through `jankx/ecommerce/cart/coupon/apply`
 *    and `jankx/ecommerce/cart/coupon/remove`, lists them through
 *    `jankx/ecommerce/cart/coupons`.
 *  - Reduces the cart total (order-value by default) through the
 *    `jankx/ecommerce/cart/coupon_discount` filter.
 *  - Blocks checkout when an applied coupon is no longer valid.
 *  - Marks the applied coupon as used once an order is completed.
 *
 * @package Jankx\Extensions\CouponSystem
 */
class CheckoutIntegration
{
    public function register(): void
    {
        add_filter('jankx/ecommerce/cart/coupon_discount', [$this, 'applyDiscount'], 10, 2);
        add_filter('jankx/ecommerce/cart/coupon/apply', [$this, 'handleApplyCoupon'], 10, 3);
        add_filter('jankx/ecommerce/cart/coupon/remove', [$this, 'handleRemoveCoupon'], 10, 3);
        add_filter('jankx/ecommerce/cart/coupons', [$this, 'getAppliedCoupons'], 10, 2);
        add_filter('jankx/ecommerce/checkout/validate_customer', [$this, 'validateAppliedCoupon'], 20, 2);
        add_action('jankx/ecommerce/checkout/completed', [$this, 'onCheckoutCompleted'], 10);
    }

    /**
     * @param float  $discount
     * @param object $cart
     */
    public function applyDiscount(float $discount, $cart): float
    {
        if (!$this->isEcommerceLoaded()) {
            return $discount;
        }

        $couponDiscount = CouponManager::get_instance()->getAppliedDiscount($discount, $cart);

        return (float) ($discount + $couponDiscount);
    }

    /**
     * Apply a coupon code through the base-ecommerce coupon API.
     *
     * @param array{success: bool, message: string} $result
     * @param object                                $cart
     * @param string                                $code
     */
    public function handleApplyCoupon(array $result, $cart, string $code): array
    {
        if (!$this->isEcommerceLoaded() || trim($code) === '') {
            return $result;
        }

        return CouponManager::get_instance()->apply($code);
    }

    /**
     * Remove the applied coupon through the base-ecommerce coupon API.
     *
     * @param array{success: bool, message: string} $result
     * @param object                                $cart
     * @param string                                $code
     */
    public function handleRemoveCoupon(array $result, $cart, string $code): array
    {
        if (!$this->isEcommerceLoaded()) {
            return $result;
        }

        return CouponManager::get_instance()->removeApplied();
    }

    /**
     * Expose applied coupons to the cart payload.
     *
     * @param array  $coupons
     * @param object $cart
     */
    public function getAppliedCoupons(array $coupons, $cart): array
    {
        if (!$this->isEcommerceLoaded()) {
            return $coupons;
        }

        $coupon = CouponManager::get_instance()->getApplied();
        if (!$coupon) {
            return $coupons;
        }

        $context = $this->buildCartContext($cart);
        $coupons[] = array_merge($coupon->toArray(), [
            'discount' => $coupon->getDiscount((float) ($context['subtotal'] ?? 0)),
        ]);

        return $coupons;
    }

    /**
     * Reject checkout if the applied coupon is no longer valid, so the order
     * cannot be created while an invalid coupon is attached.
     *
     * @param string[] $errors
     * @param array    $customer
     * @return string[]
     */
    public function validateAppliedCoupon(array $errors, array $customer): array
    {
        if (!$this->isEcommerceLoaded()) {
            return $errors;
        }

        $coupon = CouponManager::get_instance()->getApplied();
        if (!$coupon) {
            return $errors;
        }

        $context = $this->buildCartContext();
        $validation = $coupon->validate($context);
        if (!empty($validation)) {
            $errors[] = implode(' ', $validation);
        }

        return $errors;
    }

    /**
     * @param mixed $order
     */
    public function onCheckoutCompleted($order): void
    {
        if (!$this->isEcommerceLoaded()) {
            return;
        }

        CouponManager::get_instance()->onCheckoutCompleted($order);
    }

    protected function isEcommerceLoaded(): bool
    {
        return class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart');
    }

    protected function buildCartContext(): array
    {
        if (!class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart')) {
            return [];
        }

        $cart = \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance();

        return [
            'subtotal' => $cart->getSubtotal(),
            'user_id'  => get_current_user_id(),
            'items'    => array_map(function ($item) {
                return $item->toArray();
            }, array_values($cart->getItems())),
        ];
    }
}
