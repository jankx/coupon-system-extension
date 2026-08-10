<?php
namespace Jankx\Extensions\CouponSystem\Rest;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;

/**
 * REST API for the coupon system.
 *
 * Routes:
 *   GET  /wp-json/jankx/coupon/v1/coupons          -> coupon groups of the current user
 *   POST /wp-json/jankx/coupon/v1/coupons/{id}/collect
 *   POST /wp-json/jankx/coupon/v1/cart/apply       { code }
 *   POST /wp-json/jankx/coupon/v1/cart/remove
 *
 * @package Jankx\Extensions\CouponSystem
 */
class CouponController
{
    const REST_NAMESPACE = 'jankx/coupon/v1';

    public function register_routes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/coupons', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getCoupons'],
            'permission_callback' => [$this, 'requireLogin'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/coupons/(?P<id>\d+)/collect', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'collectCoupon'],
            'permission_callback' => [$this, 'requireLogin'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/apply', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'applyCoupon'],
            'permission_callback' => '__return_true',
            'args'                => [
                'code' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/remove', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'removeCoupon'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function requireLogin(): bool
    {
        return is_user_logged_in();
    }

    public function getCoupons(\WP_REST_Request $request): \WP_REST_Response
    {
        $groups = CouponManager::get_instance()->getCouponGroups(get_current_user_id());

        return rest_ensure_response($groups);
    }

    public function collectCoupon(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = CouponManager::get_instance()->collect(
            (int) $request->get_param('id'),
            get_current_user_id()
        );

        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return rest_ensure_response($result);
    }

    public function applyCoupon(\WP_REST_Request $request): \WP_REST_Response
    {
        $code = strtoupper(sanitize_key($request->get_param('code')));
        if (!$code) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Vui lòng nhập mã giảm giá.', 'jankx'),
            ], 400);
        }

        $result = CouponManager::get_instance()->apply($code);

        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return rest_ensure_response(array_merge($result, [
            'cart' => $this->getCart(),
        ]));
    }

    public function removeCoupon(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = CouponManager::get_instance()->removeApplied();

        return rest_ensure_response(array_merge($result, [
            'cart' => $this->getCart(),
        ]));
    }

    protected function getCart(): array
    {
        if (!class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart')) {
            return [];
        }

        return \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance()->toArray();
    }
}
