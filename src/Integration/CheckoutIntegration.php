<?php
namespace Jankx\Extensions\CouponSystem\Integration;

use Jankx\Extensions\CouponSystem\CouponManager;

/**
 * Bridges the coupon system into the base-ecommerce cart & checkout flow:
 *
 *  - Reduces the cart total through the `jankx/ecommerce/cart/discount` filter.
 *  - Blocks checkout when an applied coupon is no longer valid.
 *  - Marks the applied coupon as used once an order is completed.
 *
 * @package Jankx\Extensions\CouponSystem
 */
class CheckoutIntegration
{
    public function register(): void
    {
        add_filter('jankx/ecommerce/cart/discount', [$this, 'applyDiscount'], 10, 2);
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
