<?php
namespace Jankx\Extensions\CouponSystem\Scope;

/**
 * Scope: apply coupon only to specific posts (by ID).
 *
 * Stores an array of post IDs in `_coupon_apply_values`.
 * The UI picker is a Select2 AJAX control that searches across
 * the post types configured via the filter `jankx/coupon/product_scope/post_types`.
 *
 * Default searchable post types: tour, product, experience, destination_tour.
 *
 * @package Jankx\Extensions\CouponSystem\Scope
 */
class ProductScope implements CouponScopeStrategy
{
    public function getId(): string
    {
        return 'product';
    }

    public function getLabel(): string
    {
        return __('Sản phẩm / dịch vụ cụ thể', 'jankx');
    }

    public function getPickerConfig(): array
    {
        /**
         * Filter the list of post types that appear in the Product scope picker.
         *
         * Each entry should be an array with keys:
         *   - label     (string)  Human-readable post type name.
         *   - rest_base (string)  WP REST API base path, e.g. "tour".
         *
         * @param array[] $postTypes
         */
        $postTypes = apply_filters('jankx/coupon/product_scope/post_types', [
            [
                'label'     => __('Tour du lịch', 'jankx'),
                'rest_base' => 'tour',
                'post_type' => 'tour',
            ],
            [
                'label'     => __('Sản phẩm', 'jankx'),
                'rest_base' => 'product',
                'post_type' => 'product',
            ],
            [
                'label'     => __('Trải nghiệm', 'jankx'),
                'rest_base' => 'experience',
                'post_type' => 'experience',
            ],
            [
                'label'     => __('Tour điểm đến', 'jankx'),
                'rest_base' => 'destination_tours',
                'post_type' => 'destination_tour',
            ],
        ]);

        return [
            'type'        => 'select2_ajax',
            'label'       => __('Chọn sản phẩm / dịch vụ', 'jankx'),
            'placeholder' => __('Gõ để tìm sản phẩm...', 'jankx'),
            'post_types'  => $postTypes,
        ];
    }

    public function sanitizeValues(array $raw): array
    {
        return array_values(array_unique(array_filter(array_map('absint', $raw))));
    }

    public function matches(array $item, array $values): bool
    {
        $productId = (int) ($item['product_id'] ?? 0);
        if (!$productId) {
            return false;
        }

        return in_array($productId, $values, true);
    }
}
