<?php
namespace Jankx\Extensions\CouponSystem\PostTypes;

use Jankx\Extensions\CouponSystem\Coupon;

class CouponPostType
{
    const POST_TYPE = 'jankx_coupon';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_filter('use_block_editor_for_post_type', [$this, 'disableGutenberg'], 10, 2);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'registerColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'registerSortableColumns']);

        add_action('restrict_manage_posts', [$this, 'renderFilters'], 10, 2);
        add_filter('parse_query', [$this, 'applyFilters']);
    }

    public function disableGutenberg(string $enabled, string $postType): bool
    {
        if ($postType === self::POST_TYPE) {
            return false;
        }
        return $enabled;
    }

    public function registerPostType(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name' => _x('Mã giảm giá', 'Post type general name', 'jankx'),
            'singular_name' => _x('Mã giảm giá', 'Post type singular name', 'jankx'),
            'menu_name' => _x('Mã giảm giá', 'Admin Menu text', 'jankx'),
            'name_admin_bar' => __('Mã giảm giá', 'jankx'),
            'add_new' => __('Thêm mới', 'jankx'),
            'add_new_item' => __('Thêm mới mã giảm giá', 'jankx'),
            'new_item' => __('Mã giảm giá mới', 'jankx'),
            'edit_item' => __('Chỉnh sửa mã giảm giá', 'jankx'),
            'view_item' => __('Xem mã giảm giá', 'jankx'),
            'all_items' => __('Tất cả mã giảm giá', 'jankx'),
            'search_items' => __('Tìm kiếm mã giảm giá', 'jankx'),
            'parent_item_colon' => __('Mã giảm giá cha:', 'jankx'),
            'not_found' => __('Không tìm thấy mã giảm giá', 'jankx'),
            'not_found_in_trash' => __('Không tìm thấy mã giảm giá trong thùng rác', 'jankx'),
            'featured_image' => __('Hình ảnh mã giảm giá', 'jankx'),
            'set_featured_image' => __('Đặt hình ảnh mã giảm giá', 'jankx'),
            'remove_featured_image' => __('Xóa hình ảnh mã giảm giá', 'jankx'),
            'use_featured_image' => __('Sử dụng làm hình ảnh mã giảm giá', 'jankx'),
            'archives' => __('Mã giảm giá', 'jankx'),
            'attributes' => __('Thuộc tính mã giảm giá', 'jankx'),
            'filter_items_list' => __('Lọc danh sách mã giảm giá', 'jankx'),
            'items_list_navigation' => __('Điều hướng danh sách mã giảm giá', 'jankx'),
            'items_list' => __('Danh sách mã giảm giá', 'jankx'),
            'item_published' => __('Mã giảm giá đã xuất bản', 'jankx'),
            'item_published_privately' => __('Mã giảm giá đã xuất bản riêng tư', 'jankx'),
            'item_reverted_to_draft' => __('Mã giảm giá đã chuyển về bản nháp', 'jankx'),
            'item_updated' => __('Mã giảm giá đã cập nhật', 'jankx'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => false,
            'rewrite' => array(
                'slug' => 'coupon'
            ),
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 30,
            'menu_icon' => 'dashicons-tickets-alt',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /* ---------------------------------------------------------------------
     * Admin list columns
     * ------------------------------------------------------------------- */

    public function registerColumns(array $columns): array
    {
        $newColumns = [
            'cb' => $columns['cb'],
            'code' => __('Mã', 'jankx'),
            'title' => __('Tiêu đề', 'jankx'),
            'type' => __('Loại giảm giá', 'jankx'),
            'status' => __('Trạng thái', 'jankx'),
            'uses' => __('Đã dùng / Tối đa', 'jankx'),
            'owner' => __('Chủ sở hữu', 'jankx'),
            'expiry' => __('Hết hạn', 'jankx'),
            'date' => $columns['date'],
        ];

        return $newColumns;
    }

    public function renderColumn(string $column, int $postId): void
    {
        $coupon = new Coupon($postId);

        switch ($column) {
            case 'code':
                echo '<strong>' . esc_html($coupon->getCode() ?: '—') . '</strong>';
                if ($coupon->isSlave()) {
                    echo '<br><span class="description">' . esc_html__('bản sao', 'jankx') . '</span>';
                }
                break;

            case 'type':
                if ($coupon->getType() === Coupon::TYPE_PERCENT) {
                    echo esc_html(number_format($coupon->getAmount(), 0) . '%');
                } else {
                    echo esc_html(number_format($coupon->getAmount(), 0, ',', '.') . 'đ');
                }
                break;

            case 'status':
                $status = $coupon->getEffectiveStatus();
                printf(
                    '<span class="jankx-coupon-status jankx-coupon-status-%s">%s</span>',
                    esc_attr($status),
                    esc_html(Coupon::getStatusLabel($status))
                );
                break;

            case 'uses':
                $max = $coupon->getMaxUses();
                echo esc_html($coupon->getUsedCount());
                echo $max > 0 ? esc_html(' / ' . $max) : '';
                break;

            case 'owner':
                if ($coupon->isSlave()) {
                    $userId = $coupon->getUserId();
                    $user = $userId ? get_userdata($userId) : null;
                    echo $user ? esc_html($user->display_name) : esc_html('#' . $userId);
                } elseif ($coupon->isGlobal()) {
                    esc_html_e('Tất cả', 'jankx');
                } else {
                    esc_html_e('Chỉ định', 'jankx');
                }
                break;

            case 'expiry':
                $expiry = $coupon->getExpiryTimestamp();
                echo $expiry ? esc_html(wp_date('d/m/Y', $expiry)) : '—';
                break;
        }
    }

    public function registerSortableColumns(array $columns): array
    {
        $columns['code'] = 'jankx_coupon_code';
        $columns['status'] = 'jankx_coupon_status';
        $columns['expiry'] = 'jankx_coupon_expiry';

        return $columns;
    }

    /* ---------------------------------------------------------------------
     * Admin filters
     * ------------------------------------------------------------------- */

    public function renderFilters(string $postType, string $which): void
    {
        if ($postType !== self::POST_TYPE || $which !== 'top') {
            return;
        }

        $status = isset($_GET['coupon_status']) ? sanitize_key($_GET['coupon_status']) : '';
        $type = isset($_GET['coupon_kind']) ? sanitize_key($_GET['coupon_kind']) : '';

        $statuses = Coupon::getStatuses();
        $statuses = array_merge(['' => __('Tất cả trạng thái', 'jankx')], $statuses);
        ?>
        <select name="coupon_status">
            <?php foreach ($statuses as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="coupon_kind">
            <option value="" <?php selected($type, ''); ?>><?php esc_html_e('Tất cả loại', 'jankx'); ?></option>
            <option value="master" <?php selected($type, 'master'); ?>><?php esc_html_e('Master (toàn hệ thống)', 'jankx'); ?>
            </option>
            <option value="slave" <?php selected($type, 'slave'); ?>><?php esc_html_e('Slave (bản sao cá nhân)', 'jankx'); ?>
            </option>
        </select>
        <?php
    }

    public function applyFilters(\WP_Query $query): void
    {
        if (!is_admin() || $query->get('post_type') !== self::POST_TYPE || !$query->is_main_query()) {
            return;
        }

        $metaQuery = (array) $query->get('meta_query');

        if (isset($_GET['coupon_kind']) && $_GET['coupon_kind'] === 'slave') {
            $metaQuery[] = [
                'key' => Coupon::META_PREFIX . 'master_id',
                'compare' => 'EXISTS',
            ];
        } elseif (isset($_GET['coupon_kind']) && $_GET['coupon_kind'] === 'master') {
            $metaQuery[] = [
                'key' => Coupon::META_PREFIX . 'master_id',
                'compare' => 'NOT EXISTS',
            ];
        }

        if (!empty($_GET['coupon_status'])) {
            $metaQuery[] = [
                'key' => Coupon::META_PREFIX . 'status',
                'value' => sanitize_key($_GET['coupon_status']),
            ];
        }

        if ($metaQuery) {
            $query->set('meta_query', $metaQuery);
        }
    }
}
