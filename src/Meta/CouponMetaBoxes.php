<?php
namespace Jankx\Extensions\CouponSystem\Meta;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;
use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;

class CouponMetaBoxes
{
    const NONCE_NAME = 'jankx_coupon_meta_nonce';
    const NONCE_ACTION = 'jankx_coupon_meta_action';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . CouponPostType::POST_TYPE, [$this, 'saveMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'jankx_coupon_details',
            __('Cấu hình mã giảm giá', 'jankx'),
            [$this, 'renderDetailsMetaBox'],
            CouponPostType::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'jankx_coupon_info',
            __('Thông tin', 'jankx'),
            [$this, 'renderInfoMetaBox'],
            CouponPostType::POST_TYPE,
            'side',
            'high'
        );
    }

    public function renderInfoMetaBox(\WP_Post $post): void
    {
        $coupon = new Coupon($post->ID);
        ?>
        <p><strong><?php esc_html_e('Mã:', 'jankx'); ?></strong> <?php echo esc_html($coupon->getCode() ?: '—'); ?></p>
        <p><strong><?php esc_html_e('Trạng thái:', 'jankx'); ?></strong>
            <?php echo esc_html(Coupon::getStatusLabel($coupon->getEffectiveStatus())); ?></p>
        <p><strong><?php esc_html_e('Đã sử dụng:', 'jankx'); ?></strong> <?php echo esc_html($coupon->getUsedCount()); ?></p>
        <?php if ($coupon->isSlave()) : ?>
            <hr>
            <p><strong><?php esc_html_e('Loại:', 'jankx'); ?></strong> <?php esc_html_e('Bản sao cá nhân', 'jankx'); ?></p>
            <p><strong><?php esc_html_e('Người sở hữu:', 'jankx'); ?></strong> <?php echo esc_html($coupon->getUserId() ? (get_userdata($coupon->getUserId())->display_name ?? ('#' . $coupon->getUserId())) : '—'); ?></p>
            <p><strong><?php esc_html_e('Mã gốc (master):', 'jankx'); ?></strong>
                <?php $master = $coupon->getMaster(); ?>
                <?php if ($master) : ?>
                    <a href="<?php echo esc_url(get_edit_post_link($master->getId())); ?>"><?php echo esc_html($master->getCode()); ?></a>
                <?php else : ?>
                    <?php echo esc_html($coupon->getMasterId()); ?>
                <?php endif; ?>
            </p>
        <?php else : ?>
            <hr>
            <p><strong><?php esc_html_e('Loại:', 'jankx'); ?></strong> <?php esc_html_e('Master (toàn hệ thống)', 'jankx'); ?></p>
        <?php endif; ?>
        <?php
    }

    public function renderDetailsMetaBox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $coupon = new Coupon($post->ID);
        $fields = $this->getFields();
        $values = $this->getMetaValues($post->ID);
        ?>
        <div class="jankx-coupon-meta-box">
            <table class="form-table">
                <?php foreach ($fields as $key => $field): ?>
                    <?php if ($coupon->isSlave() && in_array($key, $this->getMasterOnlyFields(), true)) {
                        continue;
                    } ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($field['label']); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderField($key, $field, $values[$key] ?? ''); ?>
                            <?php if (!empty($field['description'])): ?>
                                <p class="description"><?php echo esc_html($field['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    public function saveMetaBoxes(int $postId): void
    {
        if (!isset($_POST[self::NONCE_NAME]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $coupon = new Coupon($postId);

        // Slave copies: keep ownership intact, only allow status changes.
        if ($coupon->isSlave()) {
            if (isset($_POST['coupon_status'])) {
                $status = sanitize_key($_POST['coupon_status']);
                if (in_array($status, [Coupon::STATUS_ACTIVE, Coupon::STATUS_PAUSED], true)) {
                    update_post_meta($postId, Coupon::META_PREFIX . 'status', $status);
                }
            }
            return;
        }

        $fields = $this->getFields();
        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $value = isset($_POST[$key]) ? 1 : 0;
            } elseif (in_array($type, ['number', 'number_float'], true)) {
                $value = isset($_POST[$key]) ? (float) $_POST[$key] : 0;
            } elseif ($type === 'list') {
                $raw = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
                $value = $this->parseList($raw, $field['list_type'] ?? 'int');
            } elseif ($key === 'coupon_valid_from' || $key === 'coupon_expiry') {
                $raw = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
                $value = $raw ? (int) strtotime($raw) : 0;
            } else {
                $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            }

            update_post_meta($postId, Coupon::META_PREFIX . $key, $value);
        }

        // Ensure a unique code, auto-generating when empty.
        $code = strtoupper(sanitize_key(get_post_meta($postId, Coupon::META_PREFIX . 'code', true)));
        if (!$code) {
            $code = CouponManager::generateCode(get_the_title($postId), $postId);
            update_post_meta($postId, Coupon::META_PREFIX . 'code', $code);
        } elseif (CouponManager::codeExists($code, $postId)) {
            $code = CouponManager::generateCode(get_the_title($postId), $postId);
            update_post_meta($postId, Coupon::META_PREFIX . 'code', $code);
        }

        // Derived state: a non-collectable global coupon cannot stay collectable.
        if ((int) get_post_meta($postId, Coupon::META_PREFIX . 'is_collectable', true) === 1
            && (int) get_post_meta($postId, Coupon::META_PREFIX . 'is_global', true) === 0) {
            update_post_meta($postId, Coupon::META_PREFIX . 'is_collectable', 0);
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        global $post_type;
        if (CouponPostType::POST_TYPE !== $post_type) {
            return;
        }

        $extension = \Jankx\Extensions\CouponSystem\CouponSystemExtension::get_instance();
        if (!$extension) {
            return;
        }

        wp_enqueue_style(
            'jankx-coupon-admin',
            $extension->get_extension_url() . '/assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'jankx-coupon-admin',
            $extension->get_extension_url() . '/assets/admin.js',
            [],
            '1.0.0',
            true
        );
    }

    protected function getMasterOnlyFields(): array
    {
        return [
            'coupon_type',
            'coupon_amount',
            'coupon_min_order',
            'coupon_max_discount',
            'coupon_valid_from',
            'coupon_expiry',
            'coupon_max_uses',
            'coupon_per_user_limit',
            'coupon_is_collectable',
            'coupon_is_global',
            'coupon_applies_to',
            'coupon_apply_values',
            'coupon_user_ids',
            'coupon_roles',
            'coupon_origin',
            'coupon_source',
        ];
    }

    protected function getFields(): array
    {
        return [
            'coupon_type' => [
                'label' => __('Loại giảm giá', 'jankx'),
                'type' => 'select',
                'options' => [
                    Coupon::TYPE_PERCENT => __('Giảm theo phần trăm (%)', 'jankx'),
                    Coupon::TYPE_FIXED => __('Giảm cố định (VNĐ)', 'jankx'),
                ],
                'description' => __('Kiểu giảm giá: phần trăm hoặc số tiền cố định.', 'jankx'),
            ],
            'coupon_amount' => [
                'label' => __('Giá trị giảm', 'jankx'),
                'type' => 'number_float',
                'description' => __('Giá trị giảm. Nếu là phần trăm, nhập 1-100.', 'jankx'),
            ],
            'coupon_min_order' => [
                'label' => __('Đơn tối thiểu', 'jankx'),
                'type' => 'number_float',
                'description' => __('Giá trị đơn hàng tối thiểu để áp dụng (VNĐ). 0 = không giới hạn.', 'jankx'),
            ],
            'coupon_max_discount' => [
                'label' => __('Giảm tối đa (VNĐ)', 'jankx'),
                'type' => 'number_float',
                'description' => __('Trần số tiền giảm cho mã giảm theo phần trăm. 0 = không giới hạn.', 'jankx'),
            ],
            'coupon_valid_from' => [
                'label' => __('Hiệu lực từ', 'jankx'),
                'type' => 'date',
                'description' => __('Ngày bắt đầu có hiệu lực.', 'jankx'),
            ],
            'coupon_expiry' => [
                'label' => __('Ngày hết hạn', 'jankx'),
                'type' => 'date',
                'description' => __('Ngày mã hết hiệu lực. Để trống nếu không hết hạn.', 'jankx'),
            ],
            'coupon_max_uses' => [
                'label' => __('Số lượt dùng tối đa', 'jankx'),
                'type' => 'number',
                'description' => __('Tổng số lần toàn hệ thống được dùng. 0 = không giới hạn.', 'jankx'),
            ],
            'coupon_per_user_limit' => [
                'label' => __('Giới hạn mỗi người', 'jankx'),
                'type' => 'number',
                'description' => __('Số lần mỗi người dùng được dùng/thu thập. 0 = không giới hạn.', 'jankx'),
            ],
            'coupon_is_global' => [
                'label' => __('Áp dụng cho tất cả người dùng', 'jankx'),
                'type' => 'checkbox',
                'description' => __('Bật: ai cũng dùng được. Tắt: chỉ người dùng trong danh sách bên dưới.', 'jankx'),
            ],
            'coupon_is_collectable' => [
                'label' => __('Cho phép thu thập (Collect)', 'jankx'),
                'type' => 'checkbox',
                'description' => __('Người dùng có thể vào my-account để nhận mã cá nhân từ mã master này.', 'jankx'),
            ],
            'coupon_applies_to' => [
                'label' => __('Áp dụng cho', 'jankx'),
                'type' => 'select',
                'options' => [
                    'all' => __('Tất cả sản phẩm', 'jankx'),
                    'product_type' => __('Loại sản phẩm cụ thể', 'jankx'),
                    'product' => __('Sản phẩm cụ thể', 'jankx'),
                ],
                'description' => __('Phạm vi sản phẩm được áp dụng mã.', 'jankx'),
            ],
            'coupon_apply_values' => [
                'label' => __('Giá trị áp dụng', 'jankx'),
                'type' => 'list',
                'list_type' => 'int',
                'description' => __('Với "Loại sản phẩm": nhập slug loại (tour, product...). Với "Sản phẩm cụ thể": nhập ID, phân cách bởi dấu phẩy.', 'jankx'),
            ],
            'coupon_user_ids' => [
                'label' => __('Chỉ định người dùng (ID)', 'jankx'),
                'type' => 'list',
                'list_type' => 'int',
                'description' => __('Danh sách user ID được phép dùng, phân cách bởi dấu phẩy.', 'jankx'),
            ],
            'coupon_roles' => [
                'label' => __('Chỉ định vai trò', 'jankx'),
                'type' => 'list',
                'list_type' => 'slug',
                'description' => __('Danh sách vai trò (administrator, subscriber...), phân cách bởi dấu phẩy.', 'jankx'),
            ],
            'coupon_status' => [
                'label' => __('Trạng thái', 'jankx'),
                'type' => 'select',
                'options' => [
                    Coupon::STATUS_ACTIVE => __('Hoạt động', 'jankx'),
                    Coupon::STATUS_PAUSED => __('Tạm dừng', 'jankx'),
                ],
                'description' => __('Tạm dừng để chặn dùng tạm thời. Hết hạn/hết lượt được tính tự động.', 'jankx'),
            ],
            'coupon_origin' => [
                'label' => __('Nguồn gốc', 'jankx'),
                'type' => 'text',
                'description' => __('Ngữ cảnh tạo mã: admin, birthday, event, purchase...', 'jankx'),
            ],
            'coupon_source' => [
                'label' => __('Nguồn chi tiết', 'jankx'),
                'type' => 'text',
                'description' => __('Nguồn chính xác tạo mã (vd: membership).', 'jankx'),
            ],
        ];
    }

    protected function renderField(string $key, array $field, $value): void
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'select':
                $options = $field['options'] ?? [];
                printf(
                    '<select id="%1$s" name="%1$s" class="regular-text">',
                    esc_attr($key)
                );
                foreach ($options as $optionValue => $optionLabel) {
                    printf(
                        '<option value="%1$s" %2$s>%3$s</option>',
                        esc_attr($optionValue),
                        selected($value, $optionValue, false),
                        esc_html($optionLabel)
                    );
                }
                echo '</select>';
                break;

            case 'checkbox':
                printf(
                    '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>',
                    esc_attr($key),
                    checked((bool) $value, true, false),
                    esc_html(__('Bật', 'jankx'))
                );
                break;

            case 'number':
                printf(
                    '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="regular-text" min="0" step="1">',
                    esc_attr($key),
                    esc_attr((string) $value)
                );
                break;

            case 'number_float':
                printf(
                    '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="regular-text" min="0" step="0.01">',
                    esc_attr($key),
                    esc_attr((string) $value)
                );
                break;

            case 'date':
                $date = $value ? wp_date('Y-m-d', (int) $value) : '';
                printf(
                    '<input type="date" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($key),
                    esc_attr($date)
                );
                break;

            case 'list':
                if ($field['list_type'] === 'int') {
                    $text = is_array($value) ? implode(', ', array_map('intval', $value)) : '';
                } else {
                    $text = is_array($value) ? implode(', ', array_map('sanitize_key', $value)) : '';
                }
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($key),
                    esc_attr($text)
                );
                break;

            default:
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($key),
                    esc_attr((string) $value)
                );
                break;
        }
    }

    protected function getMetaValues(int $postId): array
    {
        $fields = $this->getFields();
        $values = [];

        foreach ($fields as $key => $field) {
            $values[$key] = get_post_meta($postId, Coupon::META_PREFIX . $key, true);
        }

        return $values;
    }

    protected function parseList(string $raw, string $type): array
    {
        $parts = preg_split('/[\s,]+/', $raw);
        $parts = array_filter(array_map('trim', $parts));

        if ($type === 'int') {
            return array_values(array_unique(array_map('intval', $parts)));
        }

        return array_values(array_unique(array_map('sanitize_key', $parts)));
    }
}
