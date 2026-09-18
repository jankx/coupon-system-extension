<?php

namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;

class CouponsMineBlock extends Block
{
    protected $blockId = 'jankx/coupons-mine';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $groups = CouponManager::get_instance()->getCouponGroups(get_current_user_id());
        $coupons = $groups['mine'] ?? [];

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-coupon-panel',
        ]);

        $output = sprintf('<div %s data-coupon-panel="mine">', $wrapperAttrs);

        if (empty($coupons)) {
            $output .= '<div class="jankx-empty-state"><p>' . esc_html__('Chưa có mã nào ở mục "Của tôi".', 'jankx') . '</p></div>';
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
        if ((float) $coupon['min_order'] > 0) {
            $output .= '<span class="jankx-coupon-meta">' . esc_html(sprintf(__('Đơn tối thiểu %s', 'jankx'), number_format((float) $coupon['min_order'], 0, ',', '.') . 'đ')) . '</span>';
        }
        if (!empty($coupon['expiry'])) {
            $output .= '<span class="jankx-coupon-meta">' . esc_html(sprintf(__('Hết hạn %s', 'jankx'), $coupon['expiry'])) . '</span>';
        }
        $output .= '<button type="button" class="jankx-btn jankx-btn-outline jankx-btn-sm jankx-coupon-copy" data-coupon-code="' . esc_attr($coupon['code']) . '">'
            . esc_html__('Sao chép mã', 'jankx') . '</button>';
        $output .= '</div></div>';

        return $output;
    }
}
