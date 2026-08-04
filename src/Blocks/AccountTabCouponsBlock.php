<?php
/**
 * Account Tab Coupons Block
 *
 * @package Jankx\Gutenberg\Blocks
 */

namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;

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
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'profile';
        }

        if ($activeTab !== 'coupons') {
            return '';
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-coupons',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h2 class="jankx-section-title">Your Coupons</h2>';
        $output .= '<div class="jankx-empty-state">';
        $output .= '<p>You have no coupons yet.</p>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}
