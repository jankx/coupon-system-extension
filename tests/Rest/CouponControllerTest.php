<?php
namespace Jankx\Extensions\CouponSystem\Tests\Rest;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\Rest\CouponController;
use Jankx\Extensions\CouponSystem\Tests\TestCase;

/**
 * Unit tests for the coupon REST controller.
 */
class CouponControllerTest extends TestCase
{
    protected function controller(): CouponController
    {
        return new CouponController();
    }

    public function test_register_routes_registers_four_routes()
    {
        $controller = $this->controller();
        $controller->register_routes();

        $this->assertCount(4, $GLOBALS['__routes']);

        $routes = array_map(function ($entry) {
            return $entry['route'];
        }, $GLOBALS['__routes']);

        foreach (['/coupons', '/coupons/(?P<id>\d+)/collect', '/cart/apply', '/cart/remove'] as $route) {
            $this->assertContains($route, $routes);
        }

        foreach ($GLOBALS['__routes'] as $entry) {
            $this->assertSame(CouponController::REST_NAMESPACE, $entry['namespace']);
        }
    }

    public function test_register_routes_wires_callbacks_and_permissions()
    {
        $controller = $this->controller();
        $controller->register_routes();

        $byRoute = [];
        foreach ($GLOBALS['__routes'] as $entry) {
            $byRoute[$entry['route']] = $entry['args'];
        }

        $this->assertSame(\WP_REST_Server::READABLE, $byRoute['/coupons']['methods']);
        $this->assertSame([$controller, 'getCoupons'], $byRoute['/coupons']['callback']);
        $this->assertSame([$controller, 'requireLogin'], $byRoute['/coupons']['permission_callback']);

        $this->assertSame(\WP_REST_Server::CREATABLE, $byRoute['/coupons/(?P<id>\d+)/collect']['methods']);
        $this->assertSame([$controller, 'collectCoupon'], $byRoute['/coupons/(?P<id>\d+)/collect']['callback']);

        $this->assertSame(\WP_REST_Server::CREATABLE, $byRoute['/cart/apply']['methods']);
        $this->assertSame([$controller, 'applyCoupon'], $byRoute['/cart/apply']['callback']);
        $this->assertSame('__return_true', $byRoute['/cart/apply']['permission_callback']);
        $this->assertArrayHasKey('code', $byRoute['/cart/apply']['args']);

        $this->assertSame([$controller, 'removeCoupon'], $byRoute['/cart/remove']['callback']);
        $this->assertSame('__return_true', $byRoute['/cart/remove']['permission_callback']);
    }

    public function test_require_login()
    {
        $controller = $this->controller();

        $this->assertFalse($controller->requireLogin());

        $this->setUser(2, ['subscriber']);

        $this->assertTrue($controller->requireLogin());
    }

    public function test_get_coupons_returns_groups_for_current_user()
    {
        $this->setUser(2, ['subscriber']);
        $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 1]);

        $response = $this->controller()->getCoupons(new \WP_REST_Request('GET', '/jankx/coupon/v1/coupons'));

        $this->assertSame(200, $response->get_status());
        $this->assertCount(4, $response->get_data());
        $this->assertArrayHasKey('collectable', $response->get_data());
        $this->assertCount(1, $response->get_data()['collectable']);
    }

    public function test_collect_coupon_succeeds()
    {
        $this->setUser(2, ['subscriber']);
        $masterId = $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 1]);

        $request = new \WP_REST_Request('POST', '/jankx/coupon/v1/coupons/' . $masterId . '/collect');
        $request->set_param('id', $masterId);

        $response = $this->controller()->collectCoupon($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertTrue($response->get_data()['coupon']['is_slave']);
    }

    public function test_collect_coupon_fails_with_400()
    {
        $this->setUser(2, ['subscriber']);

        $request = new \WP_REST_Request('POST', '/jankx/coupon/v1/coupons/999/collect');
        $request->set_param('id', 999);

        $response = $this->controller()->collectCoupon($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
    }

    public function test_apply_coupon_requires_a_code()
    {
        $request = new \WP_REST_Request('POST', '/jankx/coupon/v1/cart/apply');

        $response = $this->controller()->applyCoupon($request);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('nhập mã', $response->get_data()['message']);
    }

    public function test_apply_coupon_fails_with_400_on_invalid_code()
    {
        $request = new \WP_REST_Request('POST', '/jankx/coupon/v1/cart/apply');
        $request->set_param('code', 'NOPE');

        $response = $this->controller()->applyCoupon($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
    }

    public function test_apply_coupon_succeeds()
    {
        $this->setCart('unit', 500000, []);
        $this->seedMaster([Coupon::META_PREFIX . 'amount' => 50000]);

        $request = new \WP_REST_Request('POST', '/jankx/coupon/v1/cart/apply');
        $request->set_param('code', 'SALE10');

        $response = $this->controller()->applyCoupon($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame(50000.0, $response->get_data()['discount']);
        $this->assertSame('unit', $response->get_data()['cart']['key']);
    }

    public function test_remove_coupon_returns_cart()
    {
        $this->setCart('unit', 500000, []);

        $response = $this->controller()->removeCoupon(new \WP_REST_Request('POST', '/jankx/coupon/v1/cart/remove'));

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertArrayHasKey('cart', $response->get_data());
    }
}
