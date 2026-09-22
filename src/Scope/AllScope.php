<?php
namespace Jankx\Extensions\CouponSystem\Scope;

/**
 * Scope: apply coupon to ALL items unconditionally.
 *
 * - `matches()` always returns true — no filtering needed.
 * - Picker type is "hidden" so the UI hides the value picker entirely.
 *
 * @package Jankx\Extensions\CouponSystem\Scope
 */
class AllScope implements CouponScopeStrategy
{
    public function getId(): string
    {
        return 'all';
    }

    public function getLabel(): string
    {
        return __('Tất cả sản phẩm / dịch vụ', 'jankx');
    }

    public function getPickerConfig(): array
    {
        return [
            'type' => 'hidden',
        ];
    }

    public function sanitizeValues(array $raw): array
    {
        // No values needed for "all".
        return [];
    }

    public function matches(array $item, array $values): bool
    {
        return true;
    }
}
