<?php

namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;

class CouponsCollectableBlock extends Block
{
    protected $blockId = 'jankx/coupons-collectable';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $groups = CouponManager::get_instance()->getCouponGroups(get_current_user_id());
        $coupons = $groups['collectable'] ?? [];

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-coupon-panel is-active',
        ]);

        $output = sprintf('<div %s data-coupon-panel="collectable">', $wrapperAttrs);

        if (empty($coupons)) {
            $output .= '<div class="jankx-empty-state"><p>' . esc_html__('Chưa có mã nào để thu thập.', 'jankx') . '</p></div>';
        } else {
            $output .= '<div class="jankx-coupon-list">';
            foreach ($coupons as $coupon) {
                $output .= $this->renderCouponCard($coupon);
            }
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    protected function renderCouponCard(array $coupon): string
    {
        $typeLabel = $coupon['type'] === Coupon::TYPE_PERCENT
            ? number_format((float) $coupon['amount'], 0) . '%'
            : number_format((float) $coupon['amount'], 0, ',', '.') . 'đ';

        $output = '<div class="jankx-coupon-card jankx-coupon-card-' . esc_attr($coupon['status']) . '">';
        $output .= '<div class="jankx-coupon-card-left">';
        $output .= '<span class="jankx-coupon-amount">' . esc_html($typeLabel) . '</span>';
        $output .= '<span class="jankx-coupon-code">' . esc_html($coupon['code']) . '</span>';
        $output .= '</div>';
        $output .= '<div class="jankx-coupon-card-right">';
        if (!empty($coupon['title'])) {
            $output .= '<span class="jankx-coupon-title">' . esc_html($coupon['title']) . '</span>';
        }
        if (!empty($coupon['description'])) {
            $output .= '<span class="jankx-coupon-desc">' . esc_html($coupon['description']) . '</span>';
        }
        $output .= '<button type="button" class="jankx-btn jankx-btn-primary jankx-btn-sm jankx-coupon-collect" data-coupon-id="' . esc_attr($coupon['id']) . '">'
            . esc_html__('Thu thập', 'jankx') . '</button>';
        $output .= '</div></div>';

        return $output;
    }
}
