import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit({ context }: { context?: Record<string, unknown> }) {
    const label =
        (context?.['jankx/couponLabel'] as string) ||
        (context?.['jankx/couponCode'] as string) ||
        __('Giảm giá', 'jankx');

    const isCollected = Boolean(context?.['jankx/couponCollected']);
    const blockProps = useBlockProps({
        className: 'jankx-coupon-pill-text' + (isCollected ? ' is-collected' : ''),
    });

    return <span {...blockProps}>{label}</span>;
}
