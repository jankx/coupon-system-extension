<?php
namespace Jankx\Extensions\CouponSystem\PostTypes;

class CouponPostType
{
    const POST_TYPE = 'jankx_coupon';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void
    {
        $labels = [
            'name'                  => _x('Mã giảm giá', 'Post type general name', 'jankx'),
            'singular_name'         => _x('Mã giảm giá', 'Post type singular name', 'jankx'),
            'menu_name'             => _x('Mã giảm giá', 'Admin Menu text', 'jankx'),
            'name_admin_bar'        => __('Mã giảm giá', 'jankx'),
            'add_new'               => __('Thêm mới', 'jankx'),
            'add_new_item'          => __('Thêm mới mã giảm giá', 'jankx'),
            'new_item'              => __('Mã giảm giá mới', 'jankx'),
            'edit_item'             => __('Chỉnh sửa mã giảm giá', 'jankx'),
            'view_item'             => __('Xem mã giảm giá', 'jankx'),
            'all_items'             => __('Tất cả mã giảm giá', 'jankx'),
            'search_items'          => __('Tìm kiếm mã giảm giá', 'jankx'),
            'parent_item_colon'     => __('Mã giảm giá cha:', 'jankx'),
            'not_found'             => __('Không tìm thấy mã giảm giá', 'jankx'),
            'not_found_in_trash'    => __('Không tìm thấy mã giảm giá trong thùng rác', 'jankx'),
            'featured_image'        => __('Hình ảnh mã giảm giá', 'jankx'),
            'set_featured_image'    => __('Đặt hình ảnh mã giảm giá', 'jankx'),
            'remove_featured_image' => __('Xóa hình ảnh mã giảm giá', 'jankx'),
            'use_featured_image'    => __('Sử dụng làm hình ảnh mã giảm giá', 'jankx'),
            'archives'              => __('Mã giảm giá', 'jankx'),
            'attributes'            => __('Thuộc tính mã giảm giá', 'jankx'),
            'filter_items_list'     => __('Lọc danh sách mã giảm giá', 'jankx'),
            'items_list_navigation' => __('Điều hướng danh sách mã giảm giá', 'jankx'),
            'items_list'            => __('Danh sách mã giảm giá', 'jankx'),
            'item_published'        => __('Mã giảm giá đã xuất bản', 'jankx'),
            'item_published_privately' => __('Mã giảm giá đã xuất bản riêng tư', 'jankx'),
            'item_reverted_to_draft' => __('Mã giảm giá đã chuyển về bản nháp', 'jankx'),
            'item_updated'          => __('Mã giảm giá đã cập nhật', 'jankx'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 30,
            'menu_icon'          => 'dashicons-tickets-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}
