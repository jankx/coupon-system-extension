<?php
namespace Jankx\Extensions\CouponSystem\Blocks;

use Jankx\Extensions\CouponSystem\Block;
use Jankx\Extensions\CouponSystem\Frontend\CouponDetailRenderer;

class CouponDetail extends Block
{
    protected $blockId = 'jankx/coupon-detail';

    public function render($attributes, $content = '', $block = null)
    {
        return (new CouponDetailRenderer())->renderMain();
    }
}