<?php

namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;

class CouponsUnusedBlock extends Block
{
    protected $blockId = 'jankx/coupons-unused';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $groups = CouponManager::get_instance()->getCouponGroups(get_current_user_id());
        $coupons = $groups['unused'] ?? [];

        $wrapperAttrs = get_block_wrapper_attributes(['class' => 'jankx-coupon-panel']);
        $output = sprintf('<div %s data-coupon-panel="unused">', $wrapperAttrs);

        if (empty($coupons)) {
            $output .= '<div class="jankx-empty-state"><p>' . esc_html__('Chưa có mã nào ở mục "Không sử dụng".', 'jankx') . '</p></div>';
        } else {
            $output .= '<div class="jankx-coupon-list">';
            foreach ($coupons as $coupon) {
                $output .= $this->renderCard($coupon);
            }
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    protected function renderCard(array $coupon): string
    {
        $typeLabel = $coupon['type'] === Coupon::TYPE_PERCENT
            ? number_format((float) $coupon['amount'], 0) . '%'
            : number_format((float) $coupon['amount'], 0, ',', '.') . 'đ';

        $output = '<div class="jankx-coupon-card jankx-coupon-card-unused">';
        $output .= '<div class="jankx-coupon-card-left">';
        $output .= '<span class="jankx-coupon-amount">' . esc_html($typeLabel) . '</span>';
        $output .= '<span class="jankx-coupon-code">' . esc_html($coupon['code']) . '</span>';
        $output .= '</div>';
        $output .= '<div class="jankx-coupon-card-right">';
        if (!empty($coupon['title'])) {
            $output .= '<span class="jankx-coupon-title">' . esc_html($coupon['title']) . '</span>';
        }
        $output .= '<span class="jankx-badge jankx-badge-info">' . esc_html__('Chưa sử dụng', 'jankx') . '</span>';
        $output .= '</div></div>';
        return $output;
    }
}
