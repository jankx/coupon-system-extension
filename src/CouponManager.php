<?php
namespace Jankx\Extensions\CouponSystem;

use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;

/**
 * Coupon manager: CRUD, lookup, session "applied coupon" and the public API
 * used by other extensions (purchase, events, birthday, ...) to auto-create
 * coupons.
 *
 * @package Jankx\Extensions\CouponSystem
 */
class CouponManager
{
    const SESSION_PREFIX = 'jankx_coupon_applied_';
    const SESSION_TTL = 30 * DAY_IN_SECONDS;

    /**
     * @var CouponManager|null
     */
    protected static $instance;

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /* ---------------------------------------------------------------------
     * Public API for other extensions
     * ------------------------------------------------------------------- */

    /**
     * Create a master coupon (used by the admin UI and other extensions).
     *
     * @param array $args {
     *     @type string  $title           Human readable name.
     *     @type string  $description     Short description shown to users.
     *     @type string  $code            Optional; auto generated when empty.
     *     @type string  $type            percent|fixed.
     *     @type float   $amount          Value.
     *     @type float   $min_order       Minimum subtotal.
     *     @type float   $max_discount    Cap for percent coupons.
     *     @type string  $valid_from      Y-m-d or empty.
     *     @type string  $expiry          Y-m-d or empty.
     *     @type int     $max_uses        Total usage limit (0 = unlimited).
     *     @type int     $per_user_limit  Per-user usage limit (0 = unlimited).
     *     @type bool    $is_collectable  Whether users can collect a slave copy.
     *     @type bool    $is_global       Available to everyone.
     *     @type string  $applies_to      all|product_type|product.
     *     @type array   $apply_values    Post types / product IDs.
     *     @type array   $user_ids        Allowed user IDs (empty = all).
     *     @type array   $roles           Allowed roles (empty = all).
     *     @type string  $origin          Source context (e.g. "birthday").
     *     @type string  $source          Exact source (e.g. "membership").
     * }
     * @return int|\WP_Error Coupon post ID on success.
     */
    public function create(array $args)
    {
        $type = $args['type'] ?? Coupon::TYPE_FIXED;
        if (!in_array($type, [Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED], true)) {
            return new \WP_Error('invalid_type', __('Loại giảm giá không hợp lệ.', 'jankx'));
        }

        $amount = (float) ($args['amount'] ?? 0);
        if ($amount <= 0) {
            return new \WP_Error('invalid_amount', __('Giá trị giảm giá phải lớn hơn 0.', 'jankx'));
        }
        if ($type === Coupon::TYPE_PERCENT && $amount > 100) {
            return new \WP_Error('invalid_amount', __('Phần trăm giảm giá không thể vượt quá 100.', 'jankx'));
        }

        $title = sanitize_text_field($args['title'] ?? '');
        if (!$title) {
            $title = __('Mã giảm giá', 'jankx');
        }

        $code = strtoupper(sanitize_key($args['code'] ?? ''));
        if (!$code) {
            $code = self::generateCode($title);
        }
        if (self::codeExists($code)) {
            return new \WP_Error('duplicate_code', sprintf(__('Mã "%s" đã tồn tại.', 'jankx'), $code));
        }

        $postId = wp_insert_post([
            'post_type'     => CouponPostType::POST_TYPE,
            'post_status'   => 'publish',
            'post_title'    => $title,
            'post_excerpt'  => sanitize_text_field($args['description'] ?? ''),
            'meta_input'    => $this->sanitizeMasterMeta($args, $code),
        ], true);

        if (is_wp_error($postId)) {
            return $postId;
        }

        $coupon = new Coupon((int) $postId);

        do_action('jankx/coupon/created', $coupon, $args);

        return (int) $postId;
    }

    /**
     * Create a coupon directly for a specific user (no collection step).
     * Used by other extensions to reward users automatically.
     *
     * @param int   $userId Target user.
     * @param array $args   Same shape as {@see create()}.
     * @return Coupon|null
     */
    public function createForUser(int $userId, array $args): ?Coupon
    {
        if (!$userId || !get_userdata($userId)) {
            return null;
        }

        $args['origin'] = $args['origin'] ?? 'direct';
        $args['source'] = $args['source'] ?? 'extension';
        $args['is_collectable'] = false;
        $args['is_global'] = false;
        $args['user_ids'] = array_values(array_unique(array_merge(
            (array) ($args['user_ids'] ?? []),
            [$userId]
        )));

        $masterId = $this->create($args);
        if (is_wp_error($masterId)) {
            return null;
        }

        $master = new Coupon($masterId);
        if (!$master->exists()) {
            return null;
        }

        return $this->createSlave($master, $userId, [
            'origin' => $args['origin'],
            'source' => $args['source'],
        ]);
    }

    /* ---------------------------------------------------------------------
     * Lookup
     * ------------------------------------------------------------------- */

    public static function findByCode(string $code): ?Coupon
    {
        $code = strtoupper(sanitize_key($code));
        if (!$code) {
            return null;
        }

        $query = new \WP_Query([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => Coupon::META_PREFIX . 'code',
                    'value'   => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($query->posts)) {
            return null;
        }

        return new Coupon((int) $query->posts[0]);
    }

    /**
     * Collectable master coupons (the "Thu thập" pool on my-account).
     *
     * @return Coupon[]
     */
    public function findCollectable(): array
    {
        $query = new \WP_Query([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => Coupon::META_PREFIX . 'master_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => Coupon::META_PREFIX . 'is_collectable',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        $coupons = [];
        foreach ($query->posts as $post) {
            $coupon = new Coupon($post->ID);
            if ($coupon->exists()) {
                $coupons[] = $coupon;
            }
        }

        return $coupons;
    }

    /**
     * All slave coupons a user owns, oldest first.
     *
     * @return Coupon[]
     */
    public function findByUser(int $userId): array
    {
        if (!$userId) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => Coupon::META_PREFIX . 'user_id',
                    'value'   => $userId,
                    'compare' => '=',
                ],
            ],
        ]);

        $coupons = [];
        foreach ($query->posts as $post) {
            $coupons[] = new Coupon($post->ID);
        }

        return $coupons;
    }

    /**
     * Master coupons owned by a user (auto-created via createForUser and
     * not yet collected). Used to show them in "Của tôi".
     *
     * @return Coupon[]
     */
    public function findMastersForUser(int $userId): array
    {
        if (!$userId) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => Coupon::META_PREFIX . 'master_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => Coupon::META_PREFIX . 'is_global',
                    'value'   => '0',
                    'compare' => '=',
                ],
                [
                    'key'     => Coupon::META_PREFIX . 'user_ids',
                    'compare' => 'LIKE',
                    // Matches the PHP-serialized array entry e.g. a:1:{i:0;i:2;}
                    'value'   => 'i:' . $userId . ';',
                ],
            ],
        ]);

        $coupons = [];
        foreach ($query->posts as $post) {
            $coupons[] = new Coupon($post->ID);
        }

        return $coupons;
    }

    /**
     * Group a user's coupons for the my-account tabs.
     *
     * @return array {
     *     @type array $collectable Masters the user can collect right now.
     *     @type array $mine        Usable coupons (slave + direct masters).
     *     @type array $used        Slave coupons already used.
     *     @type array $unused      Collected-but-unusable (expired, invalid, master paused/exhausted).
     * }
     */
    public function getCouponGroups(int $userId): array
    {
        $groups = [
            'collectable' => [],
            'mine'        => [],
            'used'        => [],
            'unused'      => [],
        ];

        if (!$userId) {
            return $groups;
        }

        // Collectable pool.
        foreach ($this->findCollectable() as $master) {
            if ($master->getEffectiveStatus() !== Coupon::STATUS_ACTIVE) {
                continue;
            }
            $limit = $master->getPerUserLimit();
            if ($limit > 0 && $this->countUserSlaves($master->getId(), $userId) >= $limit) {
                continue;
            }
            $groups['collectable'][] = $master->toArray();
        }

        // Personal coupons.
        $owned = array_merge($this->findByUser($userId), $this->findMastersForUser($userId));

        foreach ($owned as $coupon) {
            $status = $coupon->getEffectiveStatus();

            if ($status === Coupon::STATUS_USED) {
                $groups['used'][] = $coupon->toArray();
            } elseif (in_array($status, [Coupon::STATUS_EXPIRED, Coupon::STATUS_INVALID, Coupon::STATUS_PAUSED, Coupon::STATUS_EXHAUSTED], true)) {
                $groups['unused'][] = $coupon->toArray();
            } else {
                $groups['mine'][] = $coupon->toArray();
            }
        }

        return $groups;
    }

    /* ---------------------------------------------------------------------
     * Collection
     * ------------------------------------------------------------------- */

    /**
     * Collect a master coupon into a personal slave coupon.
     *
     * @return array [success, message, coupon?]
     */
    public function collect(int $masterId, int $userId): array
    {
        if (!$userId) {
            return ['success' => false, 'message' => __('Vui lòng đăng nhập để thu thập mã.', 'jankx')];
        }

        $master = new Coupon($masterId);
        if (!$master->exists() || $master->isSlave()) {
            return ['success' => false, 'message' => __('Không tìm thấy mã giảm giá.', 'jankx')];
        }

        if (!$master->isCollectable()) {
            return ['success' => false, 'message' => __('Mã giảm giá này không thể thu thập.', 'jankx')];
        }

        if ($master->getEffectiveStatus() !== Coupon::STATUS_ACTIVE) {
            return ['success' => false, 'message' => __('Mã giảm giá hiện không thể thu thập.', 'jankx')];
        }

        $limit = $master->getPerUserLimit();
        $owned = $this->countUserSlaves($master->getId(), $userId);
        if ($limit > 0 && $owned >= $limit) {
            return ['success' => false, 'message' => __('Bạn đã thu thập mã này rồi.', 'jankx')];
        }

        $slave = $master->collect($userId);
        if (!$slave) {
            return ['success' => false, 'message' => __('Không thể thu thập mã giảm giá.', 'jankx')];
        }

        return [
            'success' => true,
            'message' => __('Đã thu thập mã giảm giá thành công.', 'jankx'),
            'coupon'  => $slave->toArray(),
        ];
    }

    /* ---------------------------------------------------------------------
     * Applied coupon (session based)
     * ------------------------------------------------------------------- */

    public function getSessionKey(): string
    {
        if (class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart')) {
            return self::SESSION_PREFIX . \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance()->getCartKey();
        }

        return self::SESSION_PREFIX . md5((string) get_current_user_id());
    }

    public function getApplied(): ?Coupon
    {
        $couponId = (int) get_transient($this->getSessionKey());
        if (!$couponId) {
            return null;
        }

        $coupon = new Coupon($couponId);

        return $coupon->exists() ? $coupon : null;
    }

    /**
     * Apply a coupon code to the current cart session.
     *
     * @return array [success, message, coupon?, discount?]
     */
    public function apply(string $code, array $context = []): array
    {
        $coupon = self::findByCode($code);
        if (!$coupon) {
            return ['success' => false, 'message' => __('Mã giảm giá không tồn tại.', 'jankx')];
        }

        $context = array_merge($this->buildCartContext(), $context);
        $errors = $coupon->validate($context);
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        set_transient($this->getSessionKey(), $coupon->getId(), self::SESSION_TTL);

        do_action('jankx/coupon/applied', $coupon, $context);

        return [
            'success'  => true,
            'message'  => sprintf(__('Đã áp dụng mã "%s".', 'jankx'), $coupon->getCode()),
            'coupon'   => $coupon->toArray(),
            'discount' => $coupon->getDiscount((float) ($context['subtotal'] ?? 0)),
        ];
    }

    public function removeApplied(): array
    {
        $coupon = $this->getApplied();
        delete_transient($this->getSessionKey());

        if ($coupon) {
            do_action('jankx/coupon/removed', $coupon);
        }

        return [
            'success' => true,
            'message' => __('Đã gỡ mã giảm giá.', 'jankx'),
        ];
    }

    /**
     * Discount value for the current cart (read by the cart/discount filter).
     */
    public function getAppliedDiscount(float $current, $cart): float
    {
        $coupon = $this->getApplied();
        if (!$coupon) {
            return 0.0;
        }

        $context = $this->buildCartContext($cart);
        $errors = $coupon->validate($context);
        if (!empty($errors)) {
            return 0.0;
        }

        return $coupon->getDiscount((float) ($context['subtotal'] ?? 0));
    }

    /**
     * Called on checkout completed: mark the applied coupon used.
     */
    public function onCheckoutCompleted($order): void
    {
        $coupon = $this->getApplied();
        if (!$coupon) {
            return;
        }

        $orderId = 0;
        if (is_object($order) && method_exists($order, 'getId')) {
            $orderId = (int) $order->getId();
        } elseif (is_object($order) && isset($order->ID)) {
            $orderId = (int) $order->ID;
        } elseif (is_numeric($order)) {
            $orderId = (int) $order;
        }

        $userId = get_current_user_id();
        $coupon->markUsed($userId, $orderId);

        $this->removeApplied();
    }

    /* ---------------------------------------------------------------------
     * Code generation
     * ------------------------------------------------------------------- */

    public static function generateCode(string $seed, int $excludeId = 0): string
    {
        $seed = strtoupper(sanitize_key($seed));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($i = 0; $i < 20; $i++) {
            $random = '';
            for ($j = 0; $j < 8; $j++) {
                $random .= $alphabet[wp_rand(0, strlen($alphabet) - 1)];
            }

            $code = $seed ? substr($seed, 0, 4) . '-' . $random : $random;
            if (!self::codeExists($code, $excludeId)) {
                return $code;
            }
        }

        return $random;
    }

    public static function codeExists(string $code, int $excludeId = 0): bool
    {
        $code = strtoupper(sanitize_key($code));
        if (!$code) {
            return false;
        }

        $query = new \WP_Query([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => $excludeId ? [$excludeId] : [],
            'meta_query'     => [
                [
                    'key'     => Coupon::META_PREFIX . 'code',
                    'value'   => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($query->posts);
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    protected function createSlave(Coupon $master, int $userId, array $extra = []): ?Coupon
    {
        if (!$master->exists() || !$userId) {
            return null;
        }

        $code = self::generateCode($master->getCode() . '-' . $userId, $master->getId());

        $postId = wp_insert_post([
            'post_type'    => CouponPostType::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => sprintf('%s (%s)', $master->getTitle(), $master->getCode()),
            'post_excerpt' => $master->getDescription(),
            'meta_input'   => [
                Coupon::META_PREFIX . 'master_id' => $master->getId(),
                Coupon::META_PREFIX . 'user_id'   => $userId,
                Coupon::META_PREFIX . 'code'      => $code,
                Coupon::META_PREFIX . 'status'    => Coupon::STATUS_ACTIVE,
                Coupon::META_PREFIX . 'origin'    => $extra['origin'] ?? 'direct',
                Coupon::META_PREFIX . 'source'    => $extra['source'] ?? 'extension',
            ],
        ], true);

        if (is_wp_error($postId)) {
            return null;
        }

        $slave = new Coupon((int) $postId);
        do_action('jankx/coupon/collected', $slave, $master, $userId);

        return $slave;
    }

    protected function countUserSlaves(int $masterId, int $userId): int
    {
        $query = new \WP_Query([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => Coupon::META_PREFIX . 'master_id',
                    'value'   => $masterId,
                    'compare' => '=',
                ],
                [
                    'key'     => Coupon::META_PREFIX . 'user_id',
                    'value'   => $userId,
                    'compare' => '=',
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    /**
     * Build a validation context from the current cart.
     *
     * @param mixed $cart Cart instance (optional).
     * @return array {subtotal, user_id, items}
     */
    protected function buildCartContext($cart = null): array
    {
        $context = [
            'subtotal' => 0.0,
            'user_id'  => get_current_user_id(),
            'items'    => [],
        ];

        if (!$cart && class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart')) {
            $cart = \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance();
        }

        if (!$cart || !is_object($cart)) {
            return $context;
        }

        if (method_exists($cart, 'getSubtotal')) {
            $context['subtotal'] = (float) $cart->getSubtotal();
        }

        if (method_exists($cart, 'getItems')) {
            foreach ($cart->getItems() as $item) {
                if (method_exists($item, 'toArray')) {
                    $context['items'][] = $item->toArray();
                }
            }
        }

        return $context;
    }

    /**
     * Normalize the master coupon meta for storage.
     */
    protected function sanitizeMasterMeta(array $args, string $code): array
    {
        $meta = [
            Coupon::META_PREFIX . 'code'           => $code,
            Coupon::META_PREFIX . 'type'           => $args['type'] ?? Coupon::TYPE_FIXED,
            Coupon::META_PREFIX . 'amount'         => (float) ($args['amount'] ?? 0),
            Coupon::META_PREFIX . 'min_order'      => (float) ($args['min_order'] ?? 0),
            Coupon::META_PREFIX . 'max_discount'   => (float) ($args['max_discount'] ?? 0),
            Coupon::META_PREFIX . 'valid_from'     => $this->toTimestamp($args['valid_from'] ?? ''),
            Coupon::META_PREFIX . 'expiry'         => $this->toTimestamp($args['expiry'] ?? ''),
            Coupon::META_PREFIX . 'max_uses'       => (int) ($args['max_uses'] ?? 0),
            Coupon::META_PREFIX . 'used_count'     => (int) ($args['used_count'] ?? 0),
            Coupon::META_PREFIX . 'per_user_limit' => (int) ($args['per_user_limit'] ?? 0),
            Coupon::META_PREFIX . 'is_collectable' => $this->toBool($args['is_collectable'] ?? false),
            Coupon::META_PREFIX . 'is_global'      => $this->toBool($args['is_global'] ?? true),
            Coupon::META_PREFIX . 'applies_to'     => sanitize_key($args['applies_to'] ?? 'all'),
            Coupon::META_PREFIX . 'apply_values'   => array_map('intval', (array) ($args['apply_values'] ?? [])),
            Coupon::META_PREFIX . 'status'         => sanitize_key($args['status'] ?? Coupon::STATUS_ACTIVE),
            Coupon::META_PREFIX . 'origin'         => sanitize_key($args['origin'] ?? 'admin'),
            Coupon::META_PREFIX . 'source'         => sanitize_key($args['source'] ?? 'manual'),
            Coupon::META_PREFIX . 'user_ids'       => array_map('intval', (array) ($args['user_ids'] ?? [])),
            Coupon::META_PREFIX . 'roles'          => array_map('sanitize_key', (array) ($args['roles'] ?? [])),
        ];

        return $meta;
    }

    protected function toTimestamp($value): int
    {
        if (!$value) {
            return 0;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $timestamp = strtotime((string) $value);
        if (!$timestamp) {
            return 0;
        }

        return (int) $timestamp;
    }

    protected function toBool($value): int
    {
        return (bool) $value ? 1 : 0;
    }
}
