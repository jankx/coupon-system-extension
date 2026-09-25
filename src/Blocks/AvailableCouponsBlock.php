<?php
namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\CouponManager;

class AvailableCouponsBlock extends Block
{
    protected $blockId = 'jankx/available-coupons';

    public function render($attributes, $content = '', $block = null)
    {
        $limit = isset($attributes['limit']) ? (int) $attributes['limit'] : 5;
        $showMore = isset($attributes['showMore']) ? (bool) $attributes['showMore'] : true;
        $title = isset($attributes['title']) ? sanitize_text_field($attributes['title']) : '';

        $manager = CouponManager::get_instance();
        $coupons = $manager->findAvailableForDisplay($limit, $this->resolvePostId($block));

        $isEditor = (defined('REST_REQUEST') && REST_REQUEST && !empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false) || (is_admin() && !wp_doing_ajax());

        if (empty($coupons)) {
            if ($isEditor) {
                $wrapperAttrs = get_block_wrapper_attributes([
                    'class' => 'jankx-available-coupons is-editor-preview',
                ]);

                $output = sprintf('<div %s>', $wrapperAttrs);
                if (!empty($title)) {
                    $output .= '<div class="jankx-available-coupons-header">';
                    $output .= '<h4 class="jankx-available-coupons-title">' . esc_html($title) . '</h4>';
                    $output .= '</div>';
                }
                $output .= '<div class="jankx-available-coupons-body">';
                $output .= '<div class="jankx-coupons-pills-list">';
                $output .= '<div class="jankx-coupon-pill"><span class="jankx-coupon-pill-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4zm-8.5-1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm.8-6.8a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06z"/></svg></span><span class="jankx-coupon-pill-text">Giảm 300K cho đơn từ VND 2 triệu</span></div>';
                $output .= '<div class="jankx-coupon-pill"><span class="jankx-coupon-pill-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4zm-8.5-1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm.8-6.8a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06z"/></svg></span><span class="jankx-coupon-pill-text">Giảm 10% cho đơn từ VND 500K</span></div>';
                $output .= '</div>';
                if ($showMore) {
                    $output .= '<button type="button" class="jankx-coupons-more-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></button>';
                }
                $output .= '</div>';
                $output .= '</div>';

                return $output;
            }

            return '';
        }

        $userId = get_current_user_id();

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-available-coupons',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($title)) {
            $output .= '<div class="jankx-available-coupons-header">';
            $output .= '<h4 class="jankx-available-coupons-title">' . esc_html($title) . '</h4>';
            $output .= '</div>';
        }

        $output .= '<div class="jankx-available-coupons-body">';
        $output .= '<div class="jankx-coupons-pills-list">';

        foreach ($coupons as $coupon) {
            $pillLabel = $coupon->getPillLabel();
            $isCollectable = $coupon->isCollectable();
            $isUserCollected = false;

            if ($userId && $isCollectable) {
                $limitCount = $coupon->getPerUserLimit();
                if ($limitCount > 0 && $manager->countUserSlaves($coupon->getId(), $userId) >= $limitCount) {
                    $isUserCollected = true;
                }
            }

            $output .= sprintf(
                '<div class="jankx-coupon-pill%s" data-coupon-id="%d" data-coupon-code="%s" data-coupon-collectable="%s" data-coupon-collected="%s">',
                $isUserCollected ? ' is-collected' : '',
                (int) $coupon->getId(),
                esc_attr($coupon->getCode()),
                $isCollectable ? '1' : '0',
                $isUserCollected ? '1' : '0'
            );

            // Ticket Icon with notch and % symbol
            $output .= '<span class="jankx-coupon-pill-icon" aria-hidden="true">';
            $output .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">';
            $output .= '<path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4zm-8.5-1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm.8-6.8a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06z"/>';
            $output .= '</svg>';
            $output .= '</span>';

            $output .= '<span class="jankx-coupon-pill-text">' . esc_html($pillLabel) . '</span>';
            $output .= '</div>';
        }

        $output .= '</div>'; // .jankx-coupons-pills-list

        if ($showMore) {
            $output .= '<button type="button" class="jankx-coupons-more-btn" aria-label="' . esc_attr__('Xem tất cả mã giảm giá', 'jankx') . '">';
            $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
            $output .= '<polyline points="9 18 15 12 9 6"></polyline>';
            $output .= '</svg>';
            $output .= '</button>';
        }

        $output .= '</div>'; // .jankx-available-coupons-body

        // Modal for coupon details & collection
        $output .= $this->renderModal($coupons, $userId);

        $output .= '</div>'; // .jankx-available-coupons

        return $output;
    }

    protected function resolvePostId($block): int
    {
        if ($block instanceof \WP_Block && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }

        $postId = get_the_ID();
        if ($postId) {
            return (int) $postId;
        }

        global $post;
        if ($post && isset($post->ID)) {
            return (int) $post->ID;
        }

        return 0;
    }

    protected function renderModal(array $coupons, int $userId): string
    {
        $manager = CouponManager::get_instance();

        $output = '<div class="jankx-coupon-modal" id="jankx-available-coupons-modal" aria-hidden="true" role="dialog">';
        $output .= '<div class="jankx-coupon-modal-backdrop"></div>';
        $output .= '<div class="jankx-coupon-modal-dialog">';

        $output .= '<div class="jankx-coupon-modal-header">';
        $output .= '<h3 class="jankx-coupon-modal-title">' . esc_html__('Mã giảm giá dành cho bạn', 'jankx') . '</h3>';
        $output .= '<button type="button" class="jankx-coupon-modal-close" aria-label="' . esc_attr__('Đóng', 'jankx') . '">&times;</button>';
        $output .= '</div>';

        $output .= '<div class="jankx-coupon-modal-body">';
        $output .= '<div class="jankx-coupon-modal-list">';

        foreach ($coupons as $coupon) {
            $pillLabel = $coupon->getPillLabel();
            $isCollectable = $coupon->isCollectable();
            $isUserCollected = false;

            if ($userId && $isCollectable) {
                $limitCount = $coupon->getPerUserLimit();
                if ($limitCount > 0 && $manager->countUserSlaves($coupon->getId(), $userId) >= $limitCount) {
                    $isUserCollected = true;
                }
            }

            $output .= '<div class="jankx-coupon-modal-card">';

            $output .= '<div class="jankx-coupon-modal-card-left">';
            $output .= '<span class="jankx-coupon-amount">' . esc_html($pillLabel) . '</span>';
            $output .= '<span class="jankx-coupon-code">' . esc_html($coupon->getCode()) . '</span>';
            $output .= '</div>';

            $output .= '<div class="jankx-coupon-modal-card-right">';
            if (!empty($coupon->getTitle())) {
                $output .= '<span class="jankx-coupon-title">' . esc_html($coupon->getTitle()) . '</span>';
            }
            if (!empty($coupon->getDescription())) {
                $output .= '<span class="jankx-coupon-desc">' . esc_html($coupon->getDescription()) . '</span>';
            }
            if ($coupon->getMinOrder() > 0) {
                $output .= '<span class="jankx-coupon-meta">'
                    . esc_html(sprintf(__('Đơn tối thiểu %s', 'jankx'), number_format($coupon->getMinOrder(), 0, ',', '.') . 'đ'))
                    . '</span>';
            }
            if ($coupon->getExpiryTimestamp()) {
                $output .= '<span class="jankx-coupon-meta">'
                    . esc_html(sprintf(__('Hết hạn %s', 'jankx'), wp_date('Y-m-d', $coupon->getExpiryTimestamp())))
                    . '</span>';
            }

            $output .= '<div class="jankx-coupon-modal-card-action">';
            if ($isCollectable && !$isUserCollected) {
                $output .= '<button type="button" class="jankx-btn jankx-btn-primary jankx-btn-sm jankx-coupon-collect" data-coupon-id="' . esc_attr($coupon->getId()) . '" data-coupon-code="' . esc_attr($coupon->getCode()) . '">'
                    . esc_html__('Thu thập', 'jankx') . '</button>';
            } elseif ($isUserCollected) {
                $output .= '<span class="jankx-badge jankx-badge-success">' . esc_html__('Đã nhận', 'jankx') . '</span> ';
                $output .= '<button type="button" class="jankx-btn jankx-btn-outline jankx-btn-sm jankx-coupon-copy" data-coupon-code="' . esc_attr($coupon->getCode()) . '">'
                    . esc_html__('Sao chép mã', 'jankx') . '</button>';
            } else {
                $output .= '<button type="button" class="jankx-btn jankx-btn-outline jankx-btn-sm jankx-coupon-copy" data-coupon-code="' . esc_attr($coupon->getCode()) . '">'
                    . esc_html__('Sao chép mã', 'jankx') . '</button>';
            }
            $output .= '</div>'; // .jankx-coupon-modal-card-action

            $output .= '</div>'; // .jankx-coupon-modal-card-right
            $output .= '</div>'; // .jankx-coupon-modal-card
        }

        $output .= '</div>'; // .jankx-coupon-modal-list
        $output .= '</div>'; // .jankx-coupon-modal-body

        $output .= '</div>'; // .jankx-coupon-modal-dialog
        $output .= '</div>'; // .jankx-coupon-modal

        return $output;
    }
}
