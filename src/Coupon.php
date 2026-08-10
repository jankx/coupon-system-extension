<?php
namespace Jankx\Extensions\CouponSystem;

use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;

/**
 * Coupon model backed by the jankx_coupon post type.
 *
 * A coupon is either a "master" (created by the admin, defines the real
 * rules) or a "slave" (a personal copy a user collects from a master). All
 * business rules — amount, dates, limits, scope — live on the master and are
 * validated through it, so an expired or exhausted master automatically
 * invalidates every slave copied from it.
 *
 * @package Jankx\Extensions\CouponSystem
 */
class Coupon
{
    const TYPE_PERCENT = 'percent';
    const TYPE_FIXED   = 'fixed';

    // Master statuses
    const STATUS_ACTIVE     = 'active';
    const STATUS_PAUSED     = 'paused';
    const STATUS_EXPIRED    = 'expired';
    const STATUS_EXHAUSTED  = 'exhausted';

    // Slave statuses
    const STATUS_USED       = 'used';
    const STATUS_INVALID    = 'invalid';

    const META_PREFIX = '_coupon_';

    /**
     * @var \WP_Post|null
     */
    protected $post;

    /**
     * @var int
     */
    protected $id = 0;

    public function __construct($coupon)
    {
        if (is_numeric($coupon)) {
            $this->post = get_post((int) $coupon);
        } elseif ($coupon instanceof \WP_Post) {
            $this->post = $coupon;
        }

        $this->id = $this->post ? (int) $this->post->ID : 0;
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE    => __('Active', 'jankx'),
            self::STATUS_PAUSED    => __('Paused', 'jankx'),
            self::STATUS_EXPIRED   => __('Expired', 'jankx'),
            self::STATUS_EXHAUSTED => __('Exhausted', 'jankx'),
            self::STATUS_USED      => __('Used', 'jankx'),
            self::STATUS_INVALID   => __('Invalid', 'jankx'),
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        $labels = self::getStatuses();

        return $labels[$status] ?? ucfirst($status);
    }

    /* ---------------------------------------------------------------------
     * Basic identity
     * ------------------------------------------------------------------- */

    public function getId(): int
    {
        return $this->id;
    }

    public function exists(): bool
    {
        return $this->post && $this->post->post_status === 'publish';
    }

    public function getTitle(): string
    {
        return $this->post ? (string) $this->post->post_title : '';
    }

    public function getDescription(): string
    {
        return $this->post ? (string) $this->post->post_excerpt : '';
    }

    public function isSlave(): bool
    {
        return (int) $this->getMeta('master_id') > 0;
    }

    public function getMasterId(): int
    {
        return (int) $this->getMeta('master_id');
    }

    public function getMaster(): ?self
    {
        if (!$this->isSlave()) {
            return null;
        }

        $master = new self((int) $this->getMeta('master_id'));

        return $master->exists() ? $master : null;
    }

    /**
     * Resolve the coupon that carries the business rules.
     */
    public function getRuleSource(): self
    {
        return $this->getMaster() ?: $this;
    }

    /* ---------------------------------------------------------------------
     * Meta getters (delegated to the master for slave coupons)
     * ------------------------------------------------------------------- */

    public function getCode(): string
    {
        return strtoupper((string) $this->getMeta('code'));
    }

    public function getType(): string
    {
        return $this->getRuleSource()->getMeta('type') ?: self::TYPE_FIXED;
    }

    public function getAmount(): float
    {
        return (float) $this->getRuleSource()->getMeta('amount');
    }

    public function getMinOrder(): float
    {
        return (float) $this->getRuleSource()->getMeta('min_order');
    }

    public function getMaxDiscount(): float
    {
        return (float) $this->getRuleSource()->getMeta('max_discount');
    }

    public function getValidFromTimestamp(): int
    {
        return (int) $this->getRuleSource()->getMeta('valid_from');
    }

    public function getExpiryTimestamp(): int
    {
        return (int) $this->getRuleSource()->getMeta('expiry');
    }

    public function getMaxUses(): int
    {
        return (int) $this->getRuleSource()->getMeta('max_uses');
    }

    public function getUsedCount(): int
    {
        return (int) $this->getRuleSource()->getMeta('used_count');
    }

    public function getPerUserLimit(): int
    {
        return (int) $this->getRuleSource()->getMeta('per_user_limit');
    }

    public function isCollectable(): bool
    {
        return (bool) $this->getRuleSource()->getMeta('is_collectable');
    }

    public function isGlobal(): bool
    {
        return (bool) $this->getRuleSource()->getMeta('is_global');
    }

    public function getAppliesTo(): string
    {
        return (string) $this->getRuleSource()->getMeta('applies_to');
    }

    public function getApplyValues(): array
    {
        $values = $this->getRuleSource()->getMeta('apply_values');

        return is_array($values) ? array_map('intval', $values) : [];
    }

    public function getAllowedUserIds(): array
    {
        $ids = $this->getRuleSource()->getMeta('user_ids');

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    public function getAllowedRoles(): array
    {
        $roles = $this->getRuleSource()->getMeta('roles');

        return is_array($roles) ? array_map('sanitize_key', $roles) : [];
    }

    public function getStatus(): string
    {
        return (string) $this->getMeta('status');
    }

    public function getOrigin(): string
    {
        return (string) $this->getMeta('origin');
    }

    public function getSource(): string
    {
        return (string) $this->getMeta('source');
    }

    /* ---------------------------------------------------------------------
     * Slave-only meta
     * ------------------------------------------------------------------- */

    public function getUserId(): int
    {
        return (int) $this->getMeta('user_id');
    }

    public function getOrderId(): int
    {
        return (int) $this->getMeta('order_id');
    }

    public function getUsedAt(): string
    {
        return (string) $this->getMeta('used_at');
    }

    /* ---------------------------------------------------------------------
     * Effective status
     * ------------------------------------------------------------------- */

    /**
     * Compute the real status taking date/limits into account. The stored
     * status of a master is only a manual override (paused), while expiry and
     * exhaustion are derived at read time.
     */
    public function getEffectiveStatus(): string
    {
        if ($this->isSlave()) {
            $master = $this->getMaster();
            if (!$master) {
                return self::STATUS_INVALID;
            }

            if ($this->getStatus() === self::STATUS_USED) {
                return self::STATUS_USED;
            }
            if (in_array($this->getStatus(), [self::STATUS_INVALID, self::STATUS_EXPIRED], true)) {
                return $this->getStatus();
            }
            if ($this->isExpired()) {
                return self::STATUS_EXPIRED;
            }

            return $master->getEffectiveStatus();
        }

        if ($this->getStatus() === self::STATUS_PAUSED) {
            return self::STATUS_PAUSED;
        }
        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }
        if ($this->isExhausted()) {
            return self::STATUS_EXHAUSTED;
        }

        return self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        $expiry = $this->getExpiryTimestamp();
        if ($expiry > 0 && $expiry < time()) {
            return true;
        }

        $validFrom = $this->getValidFromTimestamp();
        if ($validFrom > 0 && $validFrom > time()) {
            return true;
        }

        return false;
    }

    public function isExhausted(): bool
    {
        $max = $this->getMaxUses();

        return $max > 0 && $this->getUsedCount() >= $max;
    }

    /* ---------------------------------------------------------------------
     * Discount calculation
     * ------------------------------------------------------------------- */

    public function getDiscount(float $subtotal): float
    {
        $type = $this->getType();
        $amount = $this->getAmount();

        if ($type === self::TYPE_PERCENT) {
            $discount = $subtotal * ($amount / 100);
            $max = $this->getMaxDiscount();
            if ($max > 0) {
                $discount = min($discount, $max);
            }
        } else {
            $discount = $amount;
        }

        return (float) max(0, min($discount, $subtotal));
    }

    /* ---------------------------------------------------------------------
     * Validation
     * ------------------------------------------------------------------- */

    /**
     * Validate the coupon for use with a cart.
     *
     * @param array $context {
     *     @type float  $subtotal Current cart subtotal.
     *     @type int    $user_id  Current user ID (0 for guest).
     *     @type array  $items    Cart items: array of [product_id, quantity, unit_price, args].
     * }
     * @return string[] List of error messages (empty = valid).
     */
    public function validate(array $context = []): array
    {
        $errors = [];
        $subtotal = (float) ($context['subtotal'] ?? 0);
        $userId = (int) ($context['user_id'] ?? get_current_user_id());
        $items = $context['items'] ?? [];

        if (!$this->exists()) {
            return [__('Mã giảm giá không tồn tại.', 'jankx')];
        }

        $master = $this->isSlave() ? $this->getMaster() : $this;
        if (!$master) {
            return [__('Mã giảm giá không hợp lệ.', 'jankx')];
        }

        // Slave ownership.
        if ($this->isSlave()) {
            if ($userId > 0 && $this->getUserId() && $this->getUserId() !== $userId) {
                return [__('Mã giảm giá không thuộc về bạn.', 'jankx')];
            }
            $slaveStatus = $this->getStatus();
            if ($slaveStatus === self::STATUS_USED) {
                return [__('Mã giảm giá đã được sử dụng.', 'jankx')];
            }
            if (in_array($slaveStatus, [self::STATUS_INVALID, self::STATUS_EXPIRED], true)) {
                return [__('Mã giảm giá không còn hiệu lực.', 'jankx')];
            }
        }

        $errors = array_merge($errors, $master->validateMasterRules($context));

        return apply_filters('jankx/coupon/validate', $errors, $this, $context);
    }

    /**
     * Validate the master rules (shared by master + slave usage).
     */
    protected function validateMasterRules(array $context): array
    {
        $errors = [];
        $subtotal = (float) ($context['subtotal'] ?? 0);
        $userId = (int) ($context['user_id'] ?? get_current_user_id());
        $items = $context['items'] ?? [];

        $status = $this->getEffectiveStatus();
        if ($status !== self::STATUS_ACTIVE) {
            switch ($status) {
                case self::STATUS_EXPIRED:
                    $errors[] = __('Mã giảm giá đã hết hạn.', 'jankx');
                    break;
                case self::STATUS_EXHAUSTED:
                    $errors[] = __('Mã giảm giá đã hết lượt sử dụng.', 'jankx');
                    break;
                case self::STATUS_PAUSED:
                    $errors[] = __('Mã giảm giá hiện đang tạm dừng.', 'jankx');
                    break;
                default:
                    $errors[] = __('Mã giảm giá không còn hiệu lực.', 'jankx');
            }
        }

        if ($this->getMinOrder() > 0 && $subtotal < $this->getMinOrder()) {
            $errors[] = sprintf(
                __('Giá trị đơn hàng tối thiểu để dùng mã là %s.', 'jankx'),
                number_format($this->getMinOrder(), 0, ',', '.') . 'đ'
            );
        }

        if ($userId > 0) {
            $allowedIds = $this->getAllowedUserIds();
            if (!empty($allowedIds) && !in_array($userId, $allowedIds, true)) {
                $errors[] = __('Mã giảm giá không áp dụng cho tài khoản của bạn.', 'jankx');
            }

            $allowedRoles = $this->getAllowedRoles();
            if (!empty($allowedRoles)) {
                $user = get_userdata($userId);
                $userRoles = $user ? (array) $user->roles : [];
                if (!array_intersect($allowedRoles, $userRoles)) {
                    $errors[] = __('Mã giảm giá không áp dụng cho vai trò của bạn.', 'jankx');
                }
            }

            $limit = $this->getPerUserLimit();
            if ($limit > 0 && $this->getUserUsageCount($userId) >= $limit) {
                $errors[] = __('Bạn đã sử dụng hết lượt cho mã giảm giá này.', 'jankx');
            }
        }

        if ($errors) {
            return $errors;
        }

        if (!empty($items) && $this->getAppliesTo() !== 'all') {
            $matched = false;
            $appliesTo = $this->getAppliesTo();
            $values = $this->getApplyValues();

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if (!$productId) {
                    continue;
                }

                if ($appliesTo === 'product_type') {
                    $postType = get_post_type($productId);
                    if (in_array($postType, $values, true)) {
                        $matched = true;
                        break;
                    }
                } elseif ($appliesTo === 'product' && in_array($productId, $values, true)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $errors[] = __('Mã giảm giá không áp dụng cho sản phẩm trong giỏ hàng.', 'jankx');
            }
        }

        return $errors;
    }

    public function getUserUsageCount(int $userId): int
    {
        $usage = $this->getMeta('user_usage');
        $usage = is_array($usage) ? $usage : [];

        return (int) ($usage[$userId] ?? 0);
    }

    /* ---------------------------------------------------------------------
     * Mutations
     * ------------------------------------------------------------------- */

    public function setStatus(string $status): void
    {
        update_post_meta($this->id, self::META_PREFIX . 'status', sanitize_key($status));
    }

    public function incrementUsedCount(int $userId = 0): void
    {
        $this->getRuleSource()->incrementMeta('used_count');

        if ($userId > 0) {
            $this->getRuleSource()->incrementUserUsage($userId);
        }
    }

    protected function incrementMeta(string $key): void
    {
        $value = (int) $this->getMeta($key) + 1;
        update_post_meta($this->id, self::META_PREFIX . $key, $value);
    }

    protected function incrementUserUsage(int $userId): void
    {
        $usage = $this->getMeta('user_usage');
        $usage = is_array($usage) ? $usage : [];
        $usage[$userId] = (int) ($usage[$userId] ?? 0) + 1;
        update_post_meta($this->id, self::META_PREFIX . 'user_usage', $usage);
    }

    /**
     * Mark the coupon used. For a slave this also bumps the master counters.
     */
    public function markUsed(int $userId = 0, int $orderId = 0): void
    {
        $userId = $userId ?: get_current_user_id();

        if ($this->isSlave()) {
            update_post_meta($this->id, self::META_PREFIX . 'status', self::STATUS_USED);
            update_post_meta($this->id, self::META_PREFIX . 'used_at', current_time('mysql'));
            if ($orderId) {
                update_post_meta($this->id, self::META_PREFIX . 'order_id', $orderId);
            }

            $master = $this->getMaster();
            if ($master) {
                $master->incrementUsedCount($userId);
            }
        } else {
            $this->incrementUsedCount($userId);
        }

        do_action('jankx/coupon/used', $this, $userId, $orderId);
    }

    /**
     * Mark a collected-but-never-used slave as expired/invalid so it can be
     * looked up later under the "unused" tab.
     */
    public function markInvalid(string $reason = 'expired'): void
    {
        $status = $reason === 'invalid' ? self::STATUS_INVALID : self::STATUS_EXPIRED;
        $this->setStatus($status);
    }

    /**
     * Create a personal slave copy of a collectable master.
     */
    public function collect(int $userId): ?self
    {
        if ($this->isSlave() || !$this->exists() || !$userId) {
            return null;
        }

        if (!$this->isCollectable()) {
            return null;
        }

        if ($this->getEffectiveStatus() !== self::STATUS_ACTIVE) {
            return null;
        }

        $masterId = $this->getId();
        $code = CouponManager::generateCode($this->getCode() . '-' . $userId, $this->id);

        $postId = wp_insert_post([
            'post_type'    => CouponPostType::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => sprintf('%s (%s)', $this->getTitle(), $this->getCode()),
            'post_excerpt' => $this->getDescription(),
            'meta_input'   => [
                self::META_PREFIX . 'master_id'      => $masterId,
                self::META_PREFIX . 'user_id'        => $userId,
                self::META_PREFIX . 'code'           => $code,
                self::META_PREFIX . 'status'         => self::STATUS_ACTIVE,
                self::META_PREFIX . 'origin'         => 'collect',
                self::META_PREFIX . 'source'         => 'my-account',
            ],
        ], true);

        if (is_wp_error($postId)) {
            return null;
        }

        $slave = new self((int) $postId);

        do_action('jankx/coupon/collected', $slave, $this, $userId);

        return $slave;
    }

    /* ---------------------------------------------------------------------
     * Serialization
     * ------------------------------------------------------------------- */

    public function toArray(): array
    {
        $status = $this->getEffectiveStatus();
        $master = $this->getMaster();

        return [
            'id'           => $this->getId(),
            'code'         => $this->getCode(),
            'title'        => $this->getTitle(),
            'description'  => $this->getDescription(),
            'type'         => $this->getType(),
            'amount'       => $this->getAmount(),
            'min_order'    => $this->getMinOrder(),
            'max_discount' => $this->getMaxDiscount(),
            'valid_from'   => $this->getValidFromTimestamp() ? wp_date('Y-m-d', $this->getValidFromTimestamp()) : '',
            'expiry'       => $this->getExpiryTimestamp() ? wp_date('Y-m-d', $this->getExpiryTimestamp()) : '',
            'status'       => $status,
            'status_label' => self::getStatusLabel($status),
            'is_slave'     => $this->isSlave(),
            'master_id'    => $this->getMasterId(),
            'master_title' => $master ? $master->getTitle() : '',
            'applies_to'   => $this->getAppliesTo(),
            'max_uses'     => $this->getMaxUses(),
            'used_count'   => $this->getUsedCount(),
            'origin'       => $this->getOrigin(),
            'source'       => $this->getSource(),
            'user_id'      => $this->getUserId(),
            'used_at'      => $this->getUsedAt(),
        ];
    }

    /* ---------------------------------------------------------------------
     * Raw meta helpers
     * ------------------------------------------------------------------- */

    public function getMeta(string $key)
    {
        return get_post_meta($this->id, self::META_PREFIX . $key, true);
    }

    public function setMeta(string $key, $value): void
    {
        update_post_meta($this->id, self::META_PREFIX . $key, $value);
    }
}
