<?php
namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\CouponManager;

class AccountTabCouponsBlock extends Block
{
    protected $blockId = 'jankx/account-tab-coupons';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $activeTab = get_query_var('jankx_account_page');
        if (empty($activeTab)) {
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'coupons';
        }

        $is_editor = defined('REST_REQUEST') && REST_REQUEST && !empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;

        if (!$is_editor && $activeTab !== 'coupons') {
            return '';
        }

        $groups = CouponManager::get_instance()->getCouponGroups(get_current_user_id());

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-coupons',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Kho mã giảm giá', 'jankx') . '</h2>';

        $output .= '<div class="jankx-coupon-tabs" role="tablist">';
        $output .= $this->renderTabButton('collectable', __('Thu thập', 'jankx'), count($groups['collectable']), true);
        $output .= $this->renderTabButton('mine', __('Của tôi', 'jankx'), count($groups['mine']));
        $output .= $this->renderTabButton('used', __('Đã sử dụng', 'jankx'), count($groups['used']));
        $output .= $this->renderTabButton('unused', __('Không sử dụng', 'jankx'), count($groups['unused']));
        $output .= '</div>';

        $output .= '<div class="jankx-coupon-panels">';
        if (!empty($content)) {
            $output .= $content;
        }
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    protected function renderTabButton(string $key, string $label, int $count, bool $active = false): string
    {
        return sprintf(
            '<button type="button" class="jankx-coupon-tab%s" data-coupon-tab="%s" role="tab" aria-selected="%s">%s <span class="jankx-coupon-count">%d</span></button>',
            $active ? ' is-active' : '',
            esc_attr($key),
            $active ? 'true' : 'false',
            esc_html($label),
            $count
        );
    }
}
