<?php
/**
 * jankx/coupon-pill-icon – dynamic render.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

$couponId     = isset($block->context['jankx/couponId']) ? (int) $block->context['jankx/couponId'] : 0;
$isCollectable = !empty($block->context['jankx/couponCollectable']);
?>
<span class="jankx-coupon-pill-icon"<?php
    if ($couponId) {
        echo ' data-coupon-id="' . esc_attr($couponId) . '"';
    }
    echo $isCollectable ? ' data-coupon-collectable="1"' : '';
?> aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4zm-8.5-1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm.8-6.8a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06z"/></svg></span>
