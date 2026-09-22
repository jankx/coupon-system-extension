<?php
namespace Jankx\Extensions\CouponSystem\Scope;

/**
 * Scope: apply coupon to all posts belonging to a specific post type.
 *
 * Stores an array of post type slugs in `_coupon_apply_values`.
 * The UI picker is a static Select2 (multi-select) with options built from
 * the registered public post types, filterable via
 * `jankx/coupon/product_type_scope/post_types`.
 *
 * @package Jankx\Extensions\CouponSystem\Scope
 */
class ProductTypeScope implements CouponScopeStrategy
{
    public function getId(): string
    {
        return 'product_type';
    }

    public function getLabel(): string
    {
        return __('Theo loại sản phẩm (post type)', 'jankx');
    }

    public function getPickerConfig(): array
    {
        /**
         * Filter the list of post type options shown in the ProductType picker.
         * Defaults to the curated list of "product-like" post types on nibitour.vn.
         *
         * Each entry: [ 'value' => string slug, 'label' => string ]
         *
         * @param array[] $options
         */
        $options = apply_filters('jankx/coupon/product_type_scope/post_types', [
            ['value' => 'tour',             'label' => __('Tour du lịch', 'jankx')],
            ['value' => 'product',          'label' => __('Sản phẩm', 'jankx')],
            ['value' => 'experience',       'label' => __('Trải nghiệm', 'jankx')],
            ['value' => 'destination_tour', 'label' => __('Tour điểm đến', 'jankx')],
        ]);

        return [
            'type'        => 'select2_static',
            'label'       => __('Chọn loại sản phẩm', 'jankx'),
            'placeholder' => __('Chọn loại sản phẩm...', 'jankx'),
            'options'     => $options,
        ];
    }

    public function sanitizeValues(array $raw): array
    {
        return array_values(array_unique(array_filter(array_map('sanitize_key', $raw))));
    }

    public function matches(array $item, array $values): bool
    {
        $productId = (int) ($item['product_id'] ?? 0);
        if (!$productId) {
            return false;
        }

        $postType = get_post_type($productId);

        return $postType && in_array($postType, $values, true);
    }
}
