<?php
namespace Jankx\Extensions\CouponSystem\Tests\Models;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;
use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;
use Jankx\Extensions\CouponSystem\Tests\Support\PostStore;
use Jankx\Extensions\CouponSystem\Tests\TestCase;

/**
 * Unit tests for CouponManager (Jankx\Extensions\CouponSystem\CouponManager).
 */
class CouponManagerTest extends TestCase
{
    protected function manager(): CouponManager
    {
        return CouponManager::get_instance();
    }

    /* ---------------------------------------------------------------------
     * Singleton
     * ------------------------------------------------------------------- */

    public function test_get_instance_is_a_singleton()
    {
        $this->assertSame(CouponManager::get_instance(), CouponManager::get_instance());
        $this->assertInstanceOf(CouponManager::class, CouponManager::get_instance());
    }

    /* ---------------------------------------------------------------------
     * create()
     * ------------------------------------------------------------------- */

    public function test_create_rejects_invalid_type()
    {
        $result = $this->manager()->create(['type' => 'voucher', 'amount' => 1000]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_type', $result->get_error_code());
    }

    public function test_create_rejects_zero_amount()
    {
        $result = $this->manager()->create(['type' => Coupon::TYPE_FIXED, 'amount' => 0]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_amount', $result->get_error_code());
    }

    public function test_create_rejects_percent_over_100()
    {
        $result = $this->manager()->create(['type' => Coupon::TYPE_PERCENT, 'amount' => 150]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_amount', $result->get_error_code());
    }

    public function test_create_creates_a_master_coupon()
    {
        $id = $this->manager()->create([
            'title'    => 'Giảm 50k',
            'code'     => 'sale10',
            'type'     => Coupon::TYPE_FIXED,
            'amount'   => 50000,
            'min_order' => 200000,
            'expiry'   => '2026-12-31',
        ]);

        $this->assertGreaterThan(0, $id);

        $post = PostStore::get($id);
        $this->assertSame(CouponPostType::POST_TYPE, $post->post_type);
        $this->assertSame('publish', $post->post_status);
        $this->assertSame('Giảm 50k', $post->post_title);

        $this->assertSame('SALE10', PostStore::meta($id, Coupon::META_PREFIX . 'code'));
        $this->assertSame(Coupon::TYPE_FIXED, PostStore::meta($id, Coupon::META_PREFIX . 'type'));
        $this->assertSame(50000.0, PostStore::meta($id, Coupon::META_PREFIX . 'amount'));
        $this->assertSame(200000.0, PostStore::meta($id, Coupon::META_PREFIX . 'min_order'));
        $this->assertSame(1, PostStore::meta($id, Coupon::META_PREFIX . 'is_global'));
        $this->assertSame('admin', PostStore::meta($id, Coupon::META_PREFIX . 'origin'));

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/created';
        });
        $this->assertCount(1, $fired);
    }

    public function test_create_uses_default_title_when_empty()
    {
        $id = $this->manager()->create(['type' => Coupon::TYPE_FIXED, 'amount' => 10000]);

        $this->assertSame('Mã giảm giá', PostStore::get($id)->post_title);
    }

    public function test_create_auto_generates_a_deterministic_code()
    {
        $id = $this->manager()->create([
            'title'  => 'SUMMER SALE',
            'type'   => Coupon::TYPE_FIXED,
            'amount' => 10000,
        ]);

        $this->assertSame('SUMM-AAAAAAAA', PostStore::meta($id, Coupon::META_PREFIX . 'code'));
    }

    public function test_create_sanitizes_the_code()
    {
        $id = $this->manager()->create([
            'code'   => 'sale 10!',
            'type'   => Coupon::TYPE_FIXED,
            'amount' => 10000,
        ]);

        $this->assertSame('SALE10', PostStore::meta($id, Coupon::META_PREFIX . 'code'));
    }

    public function test_create_rejects_duplicate_codes()
    {
        $this->manager()->create(['code' => 'SALE10', 'type' => Coupon::TYPE_FIXED, 'amount' => 10000]);

        $result = $this->manager()->create(['code' => 'sale10', 'type' => Coupon::TYPE_FIXED, 'amount' => 20000]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('duplicate_code', $result->get_error_code());
        $this->assertStringContainsString('SALE10', $result->get_error_message());
    }

    public function test_create_returns_the_post_error_when_insert_fails()
    {
        $GLOBALS['__wp_insert_post_fail'] = true;

        $result = $this->manager()->create(['type' => Coupon::TYPE_FIXED, 'amount' => 10000]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('db_insert_error', $result->get_error_code());
    }

    /* ---------------------------------------------------------------------
     * createForUser()
     * ------------------------------------------------------------------- */

    public function test_create_for_user_returns_null_for_unknown_user()
    {
        $this->assertNull($this->manager()->createForUser(2, ['amount' => 10000]));
    }

    public function test_create_for_user_creates_master_and_slave()
    {
        $this->setUser(2, ['subscriber']);

        $slave = $this->manager()->createForUser(2, [
            'title'  => 'Quà tặng',
            'code'   => 'GIFT2',
            'type'   => Coupon::TYPE_FIXED,
            'amount' => 30000,
        ]);

        $this->assertInstanceOf(Coupon::class, $slave);
        $this->assertTrue($slave->isSlave());
        $this->assertSame(2, $slave->getUserId());
        $this->assertSame('direct', $slave->getOrigin());
        $this->assertSame('extension', $slave->getSource());

        $masterId = $slave->getMasterId();
        $this->assertGreaterThan(0, $masterId);
        $this->assertSame(0, PostStore::meta($masterId, Coupon::META_PREFIX . 'is_collectable'));
        $this->assertSame(0, PostStore::meta($masterId, Coupon::META_PREFIX . 'is_global'));
        $this->assertSame([2], PostStore::meta($masterId, Coupon::META_PREFIX . 'user_ids'));

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/collected';
        });
        $this->assertCount(1, $fired);
    }

    public function test_create_for_user_returns_null_when_create_fails()
    {
        $this->setUser(2, ['subscriber']);

        $this->assertNull($this->manager()->createForUser(2, ['amount' => 0]));
    }

    /* ---------------------------------------------------------------------
     * Lookup
     * ------------------------------------------------------------------- */

    public function test_find_by_code_returns_null_for_empty_code()
    {
        $this->assertNull(CouponManager::findByCode(''));
        $this->assertNull(CouponManager::findByCode('!!!'));
    }

    public function test_find_by_code_returns_null_when_not_found()
    {
        $this->assertNull(CouponManager::findByCode('NOPE'));
    }

    public function test_find_by_code_is_case_insensitive()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'code' => 'SALE10']);

        $coupon = CouponManager::findByCode('sale10');

        $this->assertInstanceOf(Coupon::class, $coupon);
        $this->assertSame($id, $coupon->getId());
    }

    public function test_find_collectable_returns_only_collectable_masters()
    {
        $collectable = $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 1]);
        $notCollectable = $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 0]);
        $this->seedSlave($collectable, 2);

        $coupons = $this->manager()->findCollectable();

        $ids = array_map(function (Coupon $c) {
            return $c->getId();
        }, $coupons);

        $this->assertContains($collectable, $ids);
        $this->assertNotContains($notCollectable, $ids);
        $this->assertCount(1, $coupons);
    }

    public function test_find_by_user_returns_owned_slaves()
    {
        $masterId = $this->seedMaster();
        $slaveA = $this->seedSlave($masterId, 2);
        $this->seedSlave($masterId, 3);

        $coupons = $this->manager()->findByUser(2);

        $this->assertCount(1, $coupons);
        $this->assertSame($slaveA, $coupons[0]->getId());

        $this->assertSame([], $this->manager()->findByUser(0));
    }

    public function test_find_masters_for_user_returns_direct_masters()
    {
        $this->setUser(2, ['subscriber']);

        $slave = $this->manager()->createForUser(2, ['amount' => 30000]);
        $masterId = $slave->getMasterId();

        $masters = $this->manager()->findMastersForUser(2);

        $ids = array_map(function (Coupon $c) {
            return $c->getId();
        }, $masters);

        $this->assertContains($masterId, $ids);
        $this->assertNotContains($slave->getId(), $ids);

        $this->assertSame([], $this->manager()->findMastersForUser(0));
    }

    public function test_find_masters_for_user_excludes_global_masters()
    {
        $global = $this->seedMaster([Coupon::META_PREFIX . 'is_global' => 1]);

        $masters = $this->manager()->findMastersForUser(2);

        $ids = array_map(function (Coupon $c) {
            return $c->getId();
        }, $masters);

        $this->assertNotContains($global, $ids);
    }

    /* ---------------------------------------------------------------------
     * getCouponGroups()
     * ------------------------------------------------------------------- */

    public function test_get_coupon_groups_returns_empty_for_guest()
    {
        $groups = $this->manager()->getCouponGroups(0);

        $this->assertSame(['collectable' => [], 'mine' => [], 'used' => [], 'unused' => []], $groups);
    }

    public function test_get_coupon_groups_lists_collectable_masters()
    {
        $collectable = $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 1]);

        $groups = $this->manager()->getCouponGroups(2);

        $this->assertCount(1, $groups['collectable']);
        $this->assertSame($collectable, $groups['collectable'][0]['id']);
    }

    public function test_get_coupon_groups_hides_master_already_collected_up_to_limit()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'is_collectable' => 1,
            Coupon::META_PREFIX . 'per_user_limit' => 1,
        ]);
        $this->seedSlave($masterId, 2);

        $groups = $this->manager()->getCouponGroups(2);

        $this->assertSame([], $groups['collectable']);
    }

    public function test_get_coupon_groups_splits_owned_coupons_by_status()
    {
        $masterId = $this->seedMaster();
        $active = $this->seedSlave($masterId, 2);
        $used = $this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_USED]);
        $expired = $this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_EXPIRED]);

        $groups = $this->manager()->getCouponGroups(2);

        $this->assertSame([$active], array_column($groups['mine'], 'id'));
        $this->assertSame([$used], array_column($groups['used'], 'id'));
        $this->assertSame([$expired], array_column($groups['unused'], 'id'));
    }

    public function test_get_coupon_groups_puts_direct_masters_in_mine()
    {
        $this->setUser(2, ['subscriber']);
        $slave = $this->manager()->createForUser(2, ['amount' => 30000]);
        $masterId = $slave->getMasterId();

        $groups = $this->manager()->getCouponGroups(2);

        $this->assertContains($masterId, array_column($groups['mine'], 'id'));
    }

    /* ---------------------------------------------------------------------
     * collect()
     * ------------------------------------------------------------------- */

    public function test_collect_requires_login()
    {
        $result = $this->manager()->collect(1, 0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('đăng nhập', $result['message']);
    }

    public function test_collect_returns_not_found_for_unknown_master()
    {
        $result = $this->manager()->collect(999, 2);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Không tìm thấy', $result['message']);
    }

    public function test_collect_rejects_a_slave()
    {
        $masterId = $this->seedMaster();
        $slaveId = $this->seedSlave($masterId, 2);

        $result = $this->manager()->collect($slaveId, 2);

        $this->assertFalse($result['success']);
    }

    public function test_collect_rejects_non_collectable_master()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 0]);

        $result = $this->manager()->collect($id, 2);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('không thể thu thập', $result['message']);
    }

    public function test_collect_rejects_inactive_master()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);

        $result = $this->manager()->collect($id, 2);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('hiện không thể thu thập', $result['message']);
    }

    public function test_collect_rejects_when_per_user_limit_reached()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'is_collectable' => 1,
            Coupon::META_PREFIX . 'per_user_limit' => 1,
        ]);
        $this->seedSlave($masterId, 2);

        $result = $this->manager()->collect($masterId, 2);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('đã thu thập', $result['message']);
    }

    public function test_collect_succeeds()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'is_collectable' => 1,
            Coupon::META_PREFIX . 'code'           => 'WELCOME',
        ]);

        $result = $this->manager()->collect($masterId, 2);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('thành công', $result['message']);
        $this->assertTrue($result['coupon']['is_slave']);
        $this->assertSame($masterId, $result['coupon']['master_id']);
        $this->assertSame(2, $result['coupon']['user_id']);
    }

    /* ---------------------------------------------------------------------
     * Session / applied coupon
     * ------------------------------------------------------------------- */

    public function test_get_applied_returns_null_when_nothing_applied()
    {
        $this->assertNull($this->manager()->getApplied());
    }

    public function test_get_applied_returns_null_for_missing_coupon()
    {
        $GLOBALS['__transients'][$this->sessionKey()] = 999;

        $this->assertNull($this->manager()->getApplied());
    }

    public function test_get_applied_returns_the_coupon()
    {
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $coupon = $this->manager()->getApplied();

        $this->assertInstanceOf(Coupon::class, $coupon);
        $this->assertSame($id, $coupon->getId());
    }

    public function test_apply_rejects_unknown_code()
    {
        $result = $this->manager()->apply('NOPE');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('không tồn tại', $result['message']);
    }

    public function test_apply_rejects_invalid_coupon()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);

        $result = $this->manager()->apply('SALE10');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('hết hạn', $result['message']);
    }

    public function test_apply_succeeds_and_stores_the_coupon()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'amount' => 50000]);

        $result = $this->manager()->apply('SALE10');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('SALE10', $result['message']);
        $this->assertSame($id, $result['coupon']['id']);
        $this->assertSame(50000.0, $result['discount']);
        $this->assertSame($id, $GLOBALS['__transients'][$this->sessionKey()]);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/applied';
        });
        $this->assertCount(1, $fired);
    }

    public function test_apply_uses_provided_context()
    {
        $this->setCart('unit', 100000, []);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'min_order' => 400000]);

        $result = $this->manager()->apply('SALE10', ['subtotal' => 500000]);

        $this->assertTrue($result['success']);
        $this->assertSame($id, $result['coupon']['id']);
    }

    public function test_remove_applied_deletes_the_transient()
    {
        $result = $this->manager()->removeApplied();

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey($this->sessionKey(), $GLOBALS['__transients']);
    }

    public function test_remove_applied_fires_removed_when_coupon_was_applied()
    {
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $this->manager()->removeApplied();

        $this->assertArrayNotHasKey($this->sessionKey(), $GLOBALS['__transients']);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/removed';
        });
        $this->assertCount(1, $fired);
    }

    public function test_get_applied_discount_is_zero_without_coupon()
    {
        $this->assertSame(0.0, $this->manager()->getAppliedDiscount(0, null));
    }

    public function test_get_applied_discount_is_zero_for_invalid_coupon()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $this->assertSame(0.0, $this->manager()->getAppliedDiscount(0, null));
    }

    public function test_get_applied_discount_returns_coupon_discount()
    {
        $this->setCart('unit', 500000, []);
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount' => 10,
        ]);
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $this->assertSame(50000.0, $this->manager()->getAppliedDiscount(0, null));
    }

    /* ---------------------------------------------------------------------
     * onCheckoutCompleted()
     * ------------------------------------------------------------------- */

    public function test_on_checkout_completed_does_nothing_without_applied_coupon()
    {
        $this->manager()->onCheckoutCompleted(87);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertSame([], $fired);
    }

    public function test_on_checkout_completed_marks_master_used_and_clears_session()
    {
        $this->setUser(2, ['subscriber']);
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $order = new class {
            public function getId()
            {
                return 87;
            }
        };

        $this->manager()->onCheckoutCompleted($order);

        $this->assertSame(1, PostStore::meta($id, Coupon::META_PREFIX . 'used_count'));
        $this->assertArrayNotHasKey($this->sessionKey(), $GLOBALS['__transients']);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertCount(1, $fired);
        $this->assertSame(87, $fired[0]['args'][2]);
    }

    public function test_on_checkout_completed_accepts_order_with_id_property()
    {
        $this->setUser(2, ['subscriber']);
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $this->manager()->onCheckoutCompleted((object) ['ID' => 88]);

        $this->assertSame([2 => 1], PostStore::meta($id, Coupon::META_PREFIX . 'user_usage'));
        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertSame(88, $fired[0]['args'][2]);
    }

    public function test_on_checkout_completed_accepts_numeric_order()
    {
        $this->setUser(2, ['subscriber']);
        $id = $this->seedMaster();
        $GLOBALS['__transients'][$this->sessionKey()] = $id;

        $this->manager()->onCheckoutCompleted(89);

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertSame(89, $fired[0]['args'][2]);
    }

    public function test_on_checkout_completed_marks_slave_used()
    {
        $this->setUser(2, ['subscriber']);
        $masterId = $this->seedMaster();
        $slaveId = $this->seedSlave($masterId, 2);
        $GLOBALS['__transients'][$this->sessionKey()] = $slaveId;

        $this->manager()->onCheckoutCompleted((object) ['ID' => 90]);

        $this->assertSame(Coupon::STATUS_USED, PostStore::meta($slaveId, Coupon::META_PREFIX . 'status'));
        $this->assertSame(90, PostStore::meta($slaveId, Coupon::META_PREFIX . 'order_id'));
        $this->assertSame(1, PostStore::meta($masterId, Coupon::META_PREFIX . 'used_count'));
    }

    /* ---------------------------------------------------------------------
     * Code generation
     * ------------------------------------------------------------------- */

    public function test_generate_code_is_deterministic_with_stubbed_rand()
    {
        $this->assertSame('SUMM-AAAAAAAA', CouponManager::generateCode('SUMMER SALE'));
    }

    public function test_generate_code_without_seed_uses_random_only()
    {
        $this->assertSame('AAAAAAAA', CouponManager::generateCode(''));
    }

    public function test_generate_code_skips_existing_codes()
    {
        $existing = $this->seedMaster([Coupon::META_PREFIX . 'code' => 'SUMM-AAAAAAAA']);

        $this->assertSame('SUMM-AAAAAAAA', CouponManager::generateCode('SUMMER SALE', $existing));
    }

    public function test_generate_code_falls_back_after_20_collisions()
    {
        $this->seedMaster([Coupon::META_PREFIX . 'code' => 'SUMM-AAAAAAAA']);

        $this->assertSame('AAAAAAAA', CouponManager::generateCode('SUMMER SALE'));
    }

    public function test_code_exists()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'code' => 'SALE10']);

        $this->assertTrue(CouponManager::codeExists('sale10'));
        $this->assertTrue(CouponManager::codeExists('SALE10', 0));
        $this->assertFalse(CouponManager::codeExists('SALE10', $id));
        $this->assertFalse(CouponManager::codeExists('NOPE'));
        $this->assertFalse(CouponManager::codeExists(''));
    }
}
