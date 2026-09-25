<?php
/**
 * jankx/coupon-pill-label – dynamic render.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

$ctx = is_array($block->context ?? null) ? $block->context : [];

$label = trim((string) ($ctx['jankx/couponLabel'] ?? ''));
if ($label === '') {
    $label = trim((string) ($ctx['jankx/couponCode'] ?? ''));
}

if ($label === '') {
    return;
}

$classes = 'jankx-coupon-pill-text';
if (!empty($ctx['jankx/couponCollected'])) {
    $classes .= ' is-collected';
}

echo '<span class="' . esc_attr($classes) . '">' . esc_html($label) . '</span>';
