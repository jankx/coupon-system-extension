<?php
namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Frontend\CouponDetailRenderer;

class CouponSourceCta extends Block
{
    protected $blockId = 'jankx/coupon-source-cta';

    public function render($attributes, $content = '', $block = null)
    {
        return (new CouponDetailRenderer())->renderSourceCta();
    }
}