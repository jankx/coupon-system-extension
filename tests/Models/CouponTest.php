<?php
namespace Jankx\Extensions\CouponSystem\Tests\Models;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\Tests\Support\PostStore;
use Jankx\Extensions\CouponSystem\Tests\TestCase;

/**
 * Unit tests for the Coupon model (Jankx\Extensions\CouponSystem\Coupon).
 */
class CouponTest extends TestCase
{
    /* ---------------------------------------------------------------------
     * Construction & identity
     * ------------------------------------------------------------------- */

    public function test_unknown_id_creates_empty_coupon()
    {
        $coupon = new Coupon(999);

        $this->assertSame(0, $coupon->getId());
        $this->assertFalse($coupon->exists());
        $this->assertSame('', $coupon->getTitle());
        $this->assertFalse($coupon->isSlave());
        $this->assertNull($coupon->getMaster());
        $this->assertSame($coupon, $coupon->getRuleSource());
    }

    public function test_can_be_constructed_from_a_wp_post()
    {
        $id = $this->seedMaster();
        $post = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::get($id);

        $coupon = new Coupon($post);

        $this->assertSame($id, $coupon->getId());
        $this->assertTrue($coupon->exists());
    }

    public function test_only_published_coupons_exist()
    {
        $id = $this->seedMaster([], ['post_status' => 'draft']);

        $this->assertFalse((new Coupon($id))->exists());
    }

    public function test_title_and_description_are_read_from_the_post()
    {
        $id = $this->seedMaster([], [
            'post_title'   => 'Giảm 50k',
            'post_excerpt' => 'Mô tả khuyến mãi',
        ]);

        $coupon = new Coupon($id);

        $this->assertSame('Giảm 50k', $coupon->getTitle());
        $this->assertSame('Mô tả khuyến mãi', $coupon->getDescription());
    }

    /* ---------------------------------------------------------------------
     * Meta getters
     * ------------------------------------------------------------------- */

    public function test_get_code_is_uppercased()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'code' => 'sale10',
        ]);

        $this->assertSame('SALE10', (new Coupon($id))->getCode());
    }

    public function test_type_defaults_to_fixed_when_missing()
    {
        $id = $this->seedMaster();
        PostStore::updateMeta($id, Coupon::META_PREFIX . 'type', '');

        $this->assertSame(Coupon::TYPE_FIXED, (new Coupon($id))->getType());
    }

    public function test_numeric_meta_is_cast_to_the_right_types()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'          => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount'        => '15.5',
            Coupon::META_PREFIX . 'min_order'     => '200000',
            Coupon::META_PREFIX . 'max_discount'  => '30000',
            Coupon::META_PREFIX . 'valid_from'    => '1700000000',
            Coupon::META_PREFIX . 'expiry'        => '1800000000',
            Coupon::META_PREFIX . 'max_uses'      => '50',
            Coupon::META_PREFIX . 'used_count'    => '3',
            Coupon::META_PREFIX . 'per_user_limit' => '2',
        ]);

        $coupon = new Coupon($id);

        $this->assertSame(Coupon::TYPE_PERCENT, $coupon->getType());
        $this->assertSame(15.5, $coupon->getAmount());
        $this->assertSame(200000.0, $coupon->getMinOrder());
        $this->assertSame(30000.0, $coupon->getMaxDiscount());
        $this->assertSame(1700000000, $coupon->getValidFromTimestamp());
        $this->assertSame(1800000000, $coupon->getExpiryTimestamp());
        $this->assertSame(50, $coupon->getMaxUses());
        $this->assertSame(3, $coupon->getUsedCount());
        $this->assertSame(2, $coupon->getPerUserLimit());
    }

    public function test_boolean_and_array_meta()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'is_collectable' => 1,
            Coupon::META_PREFIX . 'is_global'      => 0,
            Coupon::META_PREFIX . 'applies_to'     => 'product',
            Coupon::META_PREFIX . 'apply_values'   => [10, '20'],
            Coupon::META_PREFIX . 'user_ids'       => ['2', 3],
            Coupon::META_PREFIX . 'roles'          => ['Subscriber', 'Administrator'],
        ]);

        $coupon = new Coupon($id);

        $this->assertTrue($coupon->isCollectable());
        $this->assertFalse($coupon->isGlobal());
        $this->assertSame('product', $coupon->getAppliesTo());
        $this->assertSame([10, 20], $coupon->getApplyValues());
        $this->assertSame([2, 3], $coupon->getAllowedUserIds());
        $this->assertSame(['subscriber', 'administrator'], $coupon->getAllowedRoles());
    }

    public function test_status_origin_and_source()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'status' => Coupon::STATUS_PAUSED,
            Coupon::META_PREFIX . 'origin' => 'birthday',
            Coupon::META_PREFIX . 'source' => 'event',
        ]);

        $coupon = new Coupon($id);

        $this->assertSame(Coupon::STATUS_PAUSED, $coupon->getStatus());
        $this->assertSame('birthday', $coupon->getOrigin());
        $this->assertSame('event', $coupon->getSource());
    }

    public function test_slave_only_meta()
    {
        $masterId = $this->seedMaster();
        $id = $this->seedSlave($masterId, 2, [
            Coupon::META_PREFIX . 'order_id' => 87,
            Coupon::META_PREFIX . 'used_at'  => '2026-08-09 10:00:00',
        ]);

        $slave = new Coupon($id);

        $this->assertSame(2, $slave->getUserId());
        $this->assertSame(87, $slave->getOrderId());
        $this->assertSame('2026-08-09 10:00:00', $slave->getUsedAt());
        $this->assertSame($masterId, $slave->getMasterId());
    }

    /* ---------------------------------------------------------------------
     * Master / slave relationship
     * ------------------------------------------------------------------- */

    public function test_slave_resolves_its_master_and_rule_source()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'amount' => 100000,
        ]);
        $slaveId = $this->seedSlave($masterId, 2);

        $slave = new Coupon($slaveId);
        $master = $slave->getMaster();

        $this->assertInstanceOf(Coupon::class, $master);
        $this->assertSame($masterId, $master->getId());
        $this->assertTrue($slave->isSlave());
        $this->assertSame($masterId, $slave->getRuleSource()->getId());
        $this->assertSame(100000.0, $slave->getAmount());
    }

    public function test_slave_with_missing_master_has_no_rule_source()
    {
        $id = $this->seedSlave(999, 2);

        $slave = new Coupon($id);

        $this->assertNull($slave->getMaster());
        $this->assertSame($slave, $slave->getRuleSource());
    }

    /* ---------------------------------------------------------------------
     * Effective status
     * ------------------------------------------------------------------- */

    public function test_effective_status_active_by_default()
    {
        $this->assertSame(Coupon::STATUS_ACTIVE, (new Coupon($this->seedMaster()))->getEffectiveStatus());
    }

    public function test_effective_status_paused()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'status' => Coupon::STATUS_PAUSED]);

        $this->assertSame(Coupon::STATUS_PAUSED, (new Coupon($id))->getEffectiveStatus());
    }

    public function test_effective_status_expired_by_date()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);

        $this->assertSame(Coupon::STATUS_EXPIRED, (new Coupon($id))->getEffectiveStatus());
    }

    public function test_effective_status_expired_when_valid_from_is_in_future()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'valid_from' => time() + 3600]);

        $this->assertSame(Coupon::STATUS_EXPIRED, (new Coupon($id))->getEffectiveStatus());
        $this->assertTrue((new Coupon($id))->isExpired());
    }

    public function test_effective_status_exhausted()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'max_uses'   => 5,
            Coupon::META_PREFIX . 'used_count' => 5,
        ]);

        $this->assertSame(Coupon::STATUS_EXHAUSTED, (new Coupon($id))->getEffectiveStatus());
        $this->assertTrue((new Coupon($id))->isExhausted());
    }

    public function test_slave_without_master_is_invalid()
    {
        $id = $this->seedSlave(999, 2);

        $this->assertSame(Coupon::STATUS_INVALID, (new Coupon($id))->getEffectiveStatus());
    }

    public function test_slave_used_is_used()
    {
        $masterId = $this->seedMaster();
        $id = $this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_USED]);

        $this->assertSame(Coupon::STATUS_USED, (new Coupon($id))->getEffectiveStatus());
    }

    public function test_slave_invalid_or_expired_status_is_kept()
    {
        $masterId = $this->seedMaster();

        $invalid = new Coupon($this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_INVALID]));
        $expired = new Coupon($this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_EXPIRED]));

        $this->assertSame(Coupon::STATUS_INVALID, $invalid->getEffectiveStatus());
        $this->assertSame(Coupon::STATUS_EXPIRED, $expired->getEffectiveStatus());
    }

    public function test_slave_follows_master_status()
    {
        $active = $this->seedMaster();
        $paused = $this->seedMaster([Coupon::META_PREFIX . 'status' => Coupon::STATUS_PAUSED]);
        $expired = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);
        $exhausted = $this->seedMaster([
            Coupon::META_PREFIX . 'max_uses'   => 1,
            Coupon::META_PREFIX . 'used_count' => 1,
        ]);

        $this->assertSame(
            Coupon::STATUS_ACTIVE,
            (new Coupon($this->seedSlave($active, 2)))->getEffectiveStatus()
        );
        $this->assertSame(
            Coupon::STATUS_PAUSED,
            (new Coupon($this->seedSlave($paused, 2)))->getEffectiveStatus()
        );
        $this->assertSame(
            Coupon::STATUS_EXPIRED,
            (new Coupon($this->seedSlave($expired, 2)))->getEffectiveStatus()
        );
        $this->assertSame(
            Coupon::STATUS_EXHAUSTED,
            (new Coupon($this->seedSlave($exhausted, 2)))->getEffectiveStatus()
        );
    }

    /* ---------------------------------------------------------------------
     * Discount calculation
     * ------------------------------------------------------------------- */

    public function test_fixed_discount_is_the_amount()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_FIXED,
            Coupon::META_PREFIX . 'amount' => 50000,
        ]);

        $this->assertSame(50000.0, (new Coupon($id))->getDiscount(1000000));
    }

    public function test_percent_discount()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount' => 10,
        ]);

        $this->assertSame(100000.0, (new Coupon($id))->getDiscount(1000000));
    }

    public function test_percent_discount_is_capped_by_max_discount()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'          => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount'        => 20,
            Coupon::META_PREFIX . 'max_discount'  => 30000,
        ]);

        $this->assertSame(30000.0, (new Coupon($id))->getDiscount(1000000));
    }

    public function test_discount_never_exceeds_subtotal()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_FIXED,
            Coupon::META_PREFIX . 'amount' => 50000,
        ]);

        $this->assertSame(20000.0, (new Coupon($id))->getDiscount(20000));
    }

    public function test_discount_is_never_negative()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_FIXED,
            Coupon::META_PREFIX . 'amount' => 0,
        ]);

        $this->assertSame(0.0, (new Coupon($id))->getDiscount(100000));
    }

    public function test_slave_discount_reads_master_rules()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'type'   => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount' => 10,
        ]);
        $slaveId = $this->seedSlave($masterId, 2);

        $this->assertSame(100000.0, (new Coupon($slaveId))->getDiscount(1000000));
    }

    /* ---------------------------------------------------------------------
     * Validation
     * ------------------------------------------------------------------- */

    public function test_validate_returns_error_for_unknown_coupon()
    {
        $coupon = new Coupon(999);

        $this->assertNotEmpty($coupon->validate(['subtotal' => 100000]));
    }

    public function test_validate_returns_error_when_slave_master_missing()
    {
        $id = $this->seedSlave(999, 2);

        $this->assertNotEmpty((new Coupon($id))->validate(['subtotal' => 100000]));
    }

    public function test_validate_rejects_slave_of_another_user()
    {
        $masterId = $this->seedMaster();
        $id = $this->seedSlave($masterId, 2);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 3]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('không thuộc về bạn', $errors[0]);
    }

    public function test_validate_allows_slave_owner()
    {
        $masterId = $this->seedMaster();
        $id = $this->seedSlave($masterId, 2);

        $this->assertSame([], (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]));
    }

    public function test_validate_rejects_used_slave()
    {
        $masterId = $this->seedMaster();
        $id = $this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_USED]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]);

        $this->assertStringContainsString('đã được sử dụng', $errors[0]);
    }

    public function test_validate_rejects_invalid_slave()
    {
        $masterId = $this->seedMaster();
        $id = $this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'status' => Coupon::STATUS_INVALID]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]);

        $this->assertStringContainsString('không còn hiệu lực', $errors[0]);
    }

    public function test_validate_rejects_expired_master()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000]);

        $this->assertStringContainsString('hết hạn', $errors[0]);
    }

    public function test_validate_rejects_exhausted_master()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'max_uses'   => 1,
            Coupon::META_PREFIX . 'used_count' => 1,
        ]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000]);

        $this->assertStringContainsString('hết lượt', $errors[0]);
    }

    public function test_validate_rejects_paused_master()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'status' => Coupon::STATUS_PAUSED]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000]);

        $this->assertStringContainsString('tạm dừng', $errors[0]);
    }

    public function test_validate_enforces_minimum_order()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'min_order' => 300000]);

        $coupon = new Coupon($id);

        $errors = $coupon->validate(['subtotal' => 200000]);
        $this->assertStringContainsString('tối thiểu', $errors[0]);

        $this->assertSame([], $coupon->validate(['subtotal' => 300000]));
    }

    public function test_validate_enforces_allowed_user_ids()
    {
        $this->setUser(3, ['subscriber']);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'user_ids' => [1, 2]]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 3]);

        $this->assertStringContainsString('không áp dụng cho tài khoản', $errors[0]);
    }

    public function test_validate_passes_for_allowed_user()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'user_ids' => [1, 2]]);

        $this->assertSame([], (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]));
    }

    public function test_validate_enforces_roles()
    {
        $this->setUser(2, ['customer']);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'roles' => ['subscriber']]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]);

        $this->assertStringContainsString('vai trò', $errors[0]);
    }

    public function test_validate_passes_for_allowed_role()
    {
        $this->setUser(2, ['subscriber']);
        $id = $this->seedMaster([Coupon::META_PREFIX . 'roles' => ['subscriber']]);

        $this->assertSame([], (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]));
    }

    public function test_validate_enforces_per_user_limit()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'per_user_limit' => 1,
            Coupon::META_PREFIX . 'user_usage'     => [2 => 1],
        ]);

        $errors = (new Coupon($id))->validate(['subtotal' => 100000, 'user_id' => 2]);

        $this->assertStringContainsString('hết lượt', $errors[0]);
    }

    public function test_validate_applies_to_product_type()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'applies_to'   => 'product_type',
            Coupon::META_PREFIX . 'apply_values' => ['tour'],
        ]);
        $tourId = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::insert([
            'post_type' => 'tour', 'post_status' => 'publish',
        ]);
        $hotelId = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::insert([
            'post_type' => 'hotel', 'post_status' => 'publish',
        ]);

        $coupon = new Coupon($id);
        $items = [['product_id' => $hotelId, 'quantity' => 1, 'unit_price' => 500000]];

        $this->assertStringContainsString('không áp dụng', $coupon->validate(['subtotal' => 500000, 'items' => $items])[0]);

        $items = [['product_id' => $hotelId, 'quantity' => 1], ['product_id' => $tourId, 'quantity' => 1]];
        $this->assertSame([], $coupon->validate(['subtotal' => 1000000, 'items' => $items]));
    }

    public function test_validate_applies_to_specific_products()
    {
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'applies_to'   => 'product',
            Coupon::META_PREFIX . 'apply_values' => [10],
        ]);

        $coupon = new Coupon($id);

        $this->assertSame([], $coupon->validate([
            'subtotal' => 100000,
            'items'    => [['product_id' => 10, 'quantity' => 1]],
        ]));

        $this->assertStringContainsString('không áp dụng', $coupon->validate([
            'subtotal' => 100000,
            'items'    => [['product_id' => 11, 'quantity' => 1]],
        ])[0]);
    }

    public function test_validate_passes_for_a_valid_coupon()
    {
        $id = $this->seedMaster();

        $this->assertSame([], (new Coupon($id))->validate(['subtotal' => 100000]));
    }

    public function test_get_user_usage_count()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'user_usage' => [2 => 3]]);

        $coupon = new Coupon($id);

        $this->assertSame(3, $coupon->getUserUsageCount(2));
        $this->assertSame(0, $coupon->getUserUsageCount(5));
    }

    /* ---------------------------------------------------------------------
     * Mutations
     * ------------------------------------------------------------------- */

    public function test_set_status_updates_meta()
    {
        $id = $this->seedMaster();

        (new Coupon($id))->setStatus(Coupon::STATUS_PAUSED);

        $this->assertSame(Coupon::STATUS_PAUSED, PostStore::meta($id, Coupon::META_PREFIX . 'status'));
    }

    public function test_increment_used_count_bumps_global_and_per_user()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'used_count' => 2]);

        (new Coupon($id))->incrementUsedCount(7);

        $this->assertSame(3, PostStore::meta($id, Coupon::META_PREFIX . 'used_count'));
        $this->assertSame([7 => 1], PostStore::meta($id, Coupon::META_PREFIX . 'user_usage'));
    }

    public function test_mark_used_on_slave_updates_slave_and_master()
    {
        $masterId = $this->seedMaster();
        $slaveId = $this->seedSlave($masterId, 2);

        (new Coupon($slaveId))->markUsed(2, 87);

        $this->assertSame(Coupon::STATUS_USED, PostStore::meta($slaveId, Coupon::META_PREFIX . 'status'));
        $this->assertSame('2026-08-10 12:00:00', PostStore::meta($slaveId, Coupon::META_PREFIX . 'used_at'));
        $this->assertSame(87, PostStore::meta($slaveId, Coupon::META_PREFIX . 'order_id'));
        $this->assertSame(1, PostStore::meta($masterId, Coupon::META_PREFIX . 'used_count'));
        $this->assertSame([2 => 1], PostStore::meta($masterId, Coupon::META_PREFIX . 'user_usage'));

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/used';
        });
        $this->assertCount(1, $fired);
        $this->assertSame(87, $fired[0]['args'][2]);
    }

    public function test_mark_used_without_order_id_keeps_order_empty()
    {
        $masterId = $this->seedMaster();
        $slaveId = $this->seedSlave($masterId, 2);

        (new Coupon($slaveId))->markUsed(2);

        $this->assertSame(0, (new Coupon($slaveId))->getOrderId());
    }

    public function test_mark_used_on_master_only_bumps_its_own_counters()
    {
        $id = $this->seedMaster();

        (new Coupon($id))->markUsed(5, 90);

        $this->assertSame(1, PostStore::meta($id, Coupon::META_PREFIX . 'used_count'));
        $this->assertSame([5 => 1], PostStore::meta($id, Coupon::META_PREFIX . 'user_usage'));
        $this->assertSame(Coupon::STATUS_ACTIVE, (new Coupon($id))->getStatus());
    }

    public function test_mark_invalid_sets_expired_or_invalid()
    {
        $masterId = $this->seedMaster();
        $slaveId = $this->seedSlave($masterId, 2);

        (new Coupon($slaveId))->markInvalid('expired');
        $this->assertSame(Coupon::STATUS_EXPIRED, PostStore::meta($slaveId, Coupon::META_PREFIX . 'status'));

        (new Coupon($slaveId))->markInvalid('invalid');
        $this->assertSame(Coupon::STATUS_INVALID, PostStore::meta($slaveId, Coupon::META_PREFIX . 'status'));
    }

    /* ---------------------------------------------------------------------
     * collect()
     * ------------------------------------------------------------------- */

    public function test_collect_returns_null_for_non_collectable_master()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'is_collectable' => 0]);

        $this->assertNull((new Coupon($id))->collect(2));
    }

    public function test_collect_returns_null_for_inactive_master()
    {
        $id = $this->seedMaster([Coupon::META_PREFIX . 'expiry' => time() - 3600]);

        $this->assertNull((new Coupon($id))->collect(2));
    }

    public function test_collect_returns_null_for_slave_or_missing_user()
    {
        $masterId = $this->seedMaster();
        $slaveId = $this->seedSlave($masterId, 2);

        $this->assertNull((new Coupon($slaveId))->collect(2));
        $this->assertNull((new Coupon($masterId))->collect(0));
    }

    public function test_collect_creates_a_personal_slave()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'code'   => 'WELCOME',
            Coupon::META_PREFIX . 'amount' => 20000,
        ]);

        $slave = (new Coupon($masterId))->collect(2);

        $this->assertInstanceOf(Coupon::class, $slave);
        $this->assertNotSame($masterId, $slave->getId());
        $this->assertTrue($slave->isSlave());
        $this->assertSame($masterId, $slave->getMasterId());
        $this->assertSame(2, $slave->getUserId());
        $this->assertSame('WELC-AAAAAAAA', $slave->getCode());
        $this->assertSame(Coupon::STATUS_ACTIVE, $slave->getStatus());
        $this->assertSame('collect', $slave->getOrigin());
        $this->assertSame('my-account', $slave->getSource());
        $this->assertSame(20000.0, $slave->getAmount());
        $this->assertSame('Sale 10 (WELCOME)', $slave->getTitle());

        $fired = array_filter($GLOBALS['__fired_actions'], function ($entry) {
            return $entry['tag'] === 'jankx/coupon/collected';
        });
        $this->assertCount(1, $fired);
    }

    public function test_collect_returns_null_when_post_insert_fails()
    {
        $masterId = $this->seedMaster();
        $GLOBALS['__wp_insert_post_fail'] = true;

        $this->assertNull((new Coupon($masterId))->collect(2));
    }

    /* ---------------------------------------------------------------------
     * toArray()
     * ------------------------------------------------------------------- */

    public function test_to_array_maps_a_master_coupon()
    {
        $expiry = mktime(0, 0, 0, 8, 3, 2027);
        $id = $this->seedMaster([
            Coupon::META_PREFIX . 'type'    => Coupon::TYPE_PERCENT,
            Coupon::META_PREFIX . 'amount'  => 10,
            Coupon::META_PREFIX . 'expiry'  => $expiry,
        ]);

        $data = (new Coupon($id))->toArray();

        $this->assertSame($id, $data['id']);
        $this->assertSame('SALE10', $data['code']);
        $this->assertSame('Sale 10', $data['title']);
        $this->assertSame('Test coupon', $data['description']);
        $this->assertSame(Coupon::TYPE_PERCENT, $data['type']);
        $this->assertSame(10.0, $data['amount']);
        $this->assertSame(Coupon::STATUS_ACTIVE, $data['status']);
        $this->assertSame('Active', $data['status_label']);
        $this->assertFalse($data['is_slave']);
        $this->assertSame(0, $data['master_id']);
        $this->assertSame('', $data['master_title']);
        $this->assertSame(date('Y-m-d', $expiry), $data['expiry']);
        $this->assertSame('', $data['valid_from']);
        $this->assertSame('admin', $data['origin']);
        $this->assertSame('manual', $data['source']);
    }

    public function test_to_array_maps_a_slave_coupon()
    {
        $masterId = $this->seedMaster([
            Coupon::META_PREFIX . 'code' => 'WELCOME',
            Coupon::META_PREFIX . 'title' => 'master-title',
        ], ['post_title' => 'Welcome master']);
        $slaveId = $this->seedSlave($masterId, 2, [Coupon::META_PREFIX . 'code' => 'WELCOME-USER2']);

        $data = (new Coupon($slaveId))->toArray();

        $this->assertTrue($data['is_slave']);
        $this->assertSame($masterId, $data['master_id']);
        $this->assertSame('Welcome master', $data['master_title']);
        $this->assertSame(2, $data['user_id']);
        $this->assertSame('collect', $data['origin']);
        $this->assertSame('my-account', $data['source']);
    }

    public function test_get_status_labels()
    {
        $labels = Coupon::getStatuses();

        $this->assertSame('Active', $labels[Coupon::STATUS_ACTIVE]);
        $this->assertSame('Active', Coupon::getStatusLabel(Coupon::STATUS_ACTIVE));
        $this->assertSame('Custom', Coupon::getStatusLabel('custom'));
    }
}
