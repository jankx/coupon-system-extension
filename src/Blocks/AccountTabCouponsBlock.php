<?php
namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Coupon;
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
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'profile';
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
        $output .= $this->renderPanel('collectable', __('Thu thập', 'jankx'), $groups['collectable'], true);
        $output .= $this->renderPanel('mine', __('Của tôi', 'jankx'), $groups['mine']);
        $output .= $this->renderPanel('used', __('Đã sử dụng', 'jankx'), $groups['used']);
        $output .= $this->renderPanel('unused', __('Không sử dụng', 'jankx'), $groups['unused']);
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

    protected function renderPanel(string $key, string $title, array $coupons, bool $active = false): string
    {
        $output = sprintf(
            '<div class="jankx-coupon-panel%s" data-coupon-panel="%s">',
            $active ? ' is-active' : '',
            esc_attr($key)
        );

        if (empty($coupons)) {
            $emptyText = $key === 'collectable'
                ? __('Chưa có mã nào để thu thập.', 'jankx')
                : sprintf(__('Chưa có mã nào ở mục "%s".', 'jankx'), $title);

            $output .= '<div class="jankx-empty-state"><p>' . esc_html($emptyText) . '</p></div>';
        } else {
            $output .= '<div class="jankx-coupon-list">';
            foreach ($coupons as $coupon) {
                $output .= $this->renderCouponCard($coupon, $key);
            }
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderCouponCard(array $coupon, string $group): string
    {
        $status = $coupon['status'];
        $typeLabel = $coupon['type'] === Coupon::TYPE_PERCENT
            ? number_format((float) $coupon['amount'], 0) . '%'
            : number_format((float) $coupon['amount'], 0, ',', '.') . 'đ';

        $output = '<div class="jankx-coupon-card jankx-coupon-card-' . esc_attr($status) . '">';
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
        if ((float) $coupon['min_order'] > 0) {
            $output .= '<span class="jankx-coupon-meta">'
                . esc_html(sprintf(__('Đơn tối thiểu %s', 'jankx'), number_format((float) $coupon['min_order'], 0, ',', '.') . 'đ'))
                . '</span>';
        }
        if (!empty($coupon['expiry'])) {
            $output .= '<span class="jankx-coupon-meta">'
                . esc_html(sprintf(__('Hết hạn %s', 'jankx'), $coupon['expiry']))
                . '</span>';
        }

        $output .= '<span class="jankx-badge jankx-coupon-status-badge jankx-badge-' . esc_attr($this->badgeClass($status)) . '">'
            . esc_html($coupon['status_label']) . '</span>';

        if ($group === 'collectable') {
            $output .= '<button type="button" class="jankx-btn jankx-btn-primary jankx-btn-sm jankx-coupon-collect" data-coupon-id="' . esc_attr($coupon['id']) . '">'
                . esc_html__('Thu thập', 'jankx') . '</button>';
        } elseif ($group === 'mine') {
            $output .= '<button type="button" class="jankx-btn jankx-btn-outline jankx-btn-sm jankx-coupon-copy" data-coupon-code="' . esc_attr($coupon['code']) . '">'
                . esc_html__('Sao chép mã', 'jankx') . '</button>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function badgeClass(string $status): string
    {
        switch ($status) {
            case Coupon::STATUS_ACTIVE:
                return 'success';
            case Coupon::STATUS_PAUSED:
            case Coupon::STATUS_EXPIRED:
            case Coupon::STATUS_INVALID:
                return 'danger';
            case Coupon::STATUS_EXHAUSTED:
                return 'warning';
            case Coupon::STATUS_USED:
                return 'secondary';
            default:
                return 'info';
        }
    }
}
