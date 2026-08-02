<?php
namespace Jankx\Extensions\CouponSystem\Meta;

class CouponMetaBoxes
{
    const NONCE_NAME = 'jankx_coupon_meta_nonce';
    const NONCE_ACTION = 'jankx_coupon_meta_action';
    const META_PREFIX = 'coupon_';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'jankx_coupon_details',
            __('Thông tin mã giảm giá', 'jankx'),
            [$this, 'renderDetailsMetaBox'],
            'jankx_coupon',
            'normal',
            'high'
        );
    }

    public function renderDetailsMetaBox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $fields = $this->getFields();
        $values = $this->getMetaValues($post->ID);
        ?>
        <div class="jankx-coupon-meta-box">
            <table class="form-table">
                <?php foreach ($fields as $key => $field): ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($field['label']); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderField($key, $field, $values[$key] ?? ''); ?>
                            <?php if (!empty($field['description'])): ?>
                                <p class="description">
                                    <?php echo esc_html($field['description']); ?>
                                </p>
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
            !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $fields = $this->getFields();
        foreach ($fields as $key => $field) {
            $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            update_post_meta($postId, self::META_PREFIX . $key, $value);
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        global $post_type;
        if ('jankx_coupon' !== $post_type) {
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
    }

    protected function getFields(): array
    {
        return [
            'coupon_code' => [
                'label' => __('Mã giảm giá', 'jankx'),
                'type' => 'text',
                'description' => __('Mã code để khách hàng nhập khi đặt tour.', 'jankx'),
            ],
            'coupon_type' => [
                'label' => __('Loại giảm giá', 'jankx'),
                'type' => 'select',
                'options' => [
                    'percent' => __('Giảm theo phần trăm (%)', 'jankx'),
                    'fixed' => __('Giảm cố định (VNĐ)', 'jankx'),
                ],
                'description' => __('Chọn kiểu giảm giá: phần trăm hoặc số tiền cố định.', 'jankx'),
            ],
            'coupon_amount' => [
                'label' => __('Giá trị giảm', 'jankx'),
                'type' => 'number',
                'description' => __('Giá trị giảm. Nếu là phần trăm, nhập từ 1-100.', 'jankx'),
            ],
            'coupon_min_order' => [
                'label' => __('Đơn tối thiểu', 'jankx'),
                'type' => 'number',
                'description' => __('Giá trị đơn hàng tối thiểu để áp dụng mã giảm giá (VNĐ).', 'jankx'),
            ],
            'coupon_max_uses' => [
                'label' => __('Số lần sử dụng tối đa', 'jankx'),
                'type' => 'number',
                'description' => __('Số lần mã giảm giá có thể sử dụng. Nhập 0 cho không giới hạn.', 'jankx'),
            ],
            'coupon_used_count' => [
                'label' => __('Đã sử dụng', 'jankx'),
                'type' => 'number',
                'description' => __('Số lần mã giảm giá đã được sử dụng.', 'jankx'),
            ],
            'coupon_expiry' => [
                'label' => __('Ngày hết hạn', 'jankx'),
                'type' => 'date',
                'description' => __('Ngày mã giảm giá hết hiệu lực.', 'jankx'),
            ],
            'coupon_applies_to' => [
                'label' => __('Áp dụng cho', 'jankx'),
                'type' => 'select',
                'options' => [
                    'all' => __('Tất cả', 'jankx'),
                    'tour' => __('Tour du lịch', 'jankx'),
                    'destination' => __('Điểm đến', 'jankx'),
                ],
                'description' => __('Loại sản phẩm mà mã giảm giá áp dụng.', 'jankx'),
            ],
        ];
    }

    protected function renderField(string $key, array $field, string $value): void
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

            case 'number':
                printf(
                    '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="regular-text" min="0" step="1">',
                    esc_attr($key),
                    esc_attr($value)
                );
                break;

            case 'date':
                printf(
                    '<input type="date" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($key),
                    esc_attr($value)
                );
                break;

            default:
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($key),
                    esc_attr($value)
                );
                break;
        }
    }

    protected function getMetaValues(int $postId): array
    {
        $fields = $this->getFields();
        $values = [];

        foreach ($fields as $key => $field) {
            $values[$key] = get_post_meta($postId, self::META_PREFIX . $key, true);
        }

        return $values;
    }
}
