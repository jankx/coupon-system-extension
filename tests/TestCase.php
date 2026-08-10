<?php
namespace Jankx\Extensions\CouponSystem\Tests;

use Brain\Monkey;
use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;
use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;
use Jankx\Extensions\CouponSystem\Tests\Support\PostStore;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for the coupon system.
 *
 * Boots Brain Monkey, stubs the WP functions (see tests/bootstrap.php) and
 * seeds a clean in-memory post store / cart / user for every test.
 */
abstract class TestCase extends BaseTestCase
{
    const COUPON = CouponPostType::POST_TYPE;

    protected function setUp(): void
    {
        parent::setUp();

        Monkey\setUp();
        coupon_test_stub_wp_functions();

        \Jankx\Extensions\Ecommerce\Cart\Cart::reset('unit', 0.0, []);
        $this->resetSingleton(CouponManager::class, 'instance');
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__registered_filters'],
            $GLOBALS['__registered_actions'],
            $GLOBALS['__fired_actions'],
            $GLOBALS['__routes'],
            $GLOBALS['__transients'],
            $GLOBALS['__current_user'],
            $GLOBALS['__users'],
            $GLOBALS['__wp_rand'],
            $GLOBALS['__wp_insert_post_fail']
        );

        Monkey\tearDown();
        parent::tearDown();
    }

    protected function resetSingleton(string $class, string $prop): void
    {
        $reflection = new \ReflectionProperty($class, $prop);
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
    }

    protected function setUser(int $id, array $roles = []): void
    {
        $GLOBALS['__current_user'] = $id;
        $GLOBALS['__users'][$id] = (object) ['ID' => $id, 'roles' => $roles];
    }

    protected function setCart(string $key, float $subtotal, array $items = []): void
    {
        \Jankx\Extensions\Ecommerce\Cart\Cart::reset($key, $subtotal, $items);
    }

    protected function defaultMasterMeta(): array
    {
        return [
            Coupon::META_PREFIX . 'code'           => 'SALE10',
            Coupon::META_PREFIX . 'type'           => Coupon::TYPE_FIXED,
            Coupon::META_PREFIX . 'amount'         => 50000,
            Coupon::META_PREFIX . 'min_order'      => 0,
            Coupon::META_PREFIX . 'max_discount'   => 0,
            Coupon::META_PREFIX . 'valid_from'     => 0,
            Coupon::META_PREFIX . 'expiry'         => 0,
            Coupon::META_PREFIX . 'max_uses'       => 0,
            Coupon::META_PREFIX . 'used_count'     => 0,
            Coupon::META_PREFIX . 'per_user_limit' => 0,
            Coupon::META_PREFIX . 'is_collectable' => 1,
            Coupon::META_PREFIX . 'is_global'      => 1,
            Coupon::META_PREFIX . 'applies_to'     => 'all',
            Coupon::META_PREFIX . 'apply_values'   => [],
            Coupon::META_PREFIX . 'status'         => Coupon::STATUS_ACTIVE,
            Coupon::META_PREFIX . 'origin'         => 'admin',
            Coupon::META_PREFIX . 'source'         => 'manual',
            Coupon::META_PREFIX . 'user_ids'       => [],
            Coupon::META_PREFIX . 'roles'          => [],
        ];
    }

    /**
     * Seed a master coupon directly into the store.
     */
    protected function seedMaster(array $meta = [], array $post = []): int
    {
        return PostStore::insert(array_merge([
            'post_type'   => self::COUPON,
            'post_status' => 'publish',
            'post_title'  => 'Sale 10',
            'post_excerpt' => 'Test coupon',
        ], $post, [
            'meta_input' => array_merge($this->defaultMasterMeta(), $meta),
        ]));
    }

    /**
     * Seed a slave coupon belonging to $userId, copied from $masterId.
     */
    protected function seedSlave(int $masterId, int $userId, array $meta = []): int
    {
        return PostStore::insert([
            'post_type'   => self::COUPON,
            'post_status' => 'publish',
            'post_title'  => 'Sale 10 (SALE10)',
            'meta_input'  => array_merge([
                Coupon::META_PREFIX . 'master_id' => $masterId,
                Coupon::META_PREFIX . 'user_id'   => $userId,
                Coupon::META_PREFIX . 'code'      => 'SALE10-USER' . $userId,
                Coupon::META_PREFIX . 'status'    => Coupon::STATUS_ACTIVE,
                Coupon::META_PREFIX . 'origin'    => 'collect',
                Coupon::META_PREFIX . 'source'    => 'my-account',
            ], $meta),
        ]);
    }

    protected function sessionKey(): string
    {
        return CouponManager::SESSION_PREFIX . \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance()->getCartKey();
    }
}
