<?php

namespace Jankx\Extensions\CouponSystem\MyAccount;

use Jankx\Extensions\MyAccount\SubPage\AbstractSubPage;

class CouponsSubPage extends AbstractSubPage
{
    public function getSlug(): string
    {
        return 'coupons';
    }

    public function getLabel(): string
    {
        return __('Mã giảm giá', 'jankx');
    }

    public function getIcon(): string
    {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg>';
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getExtension(): ?string
    {
        return 'coupon-system';
    }

    public function getContent(): string
    {
        return '<!-- wp:jankx/account-tab-coupons /-->';
    }
}