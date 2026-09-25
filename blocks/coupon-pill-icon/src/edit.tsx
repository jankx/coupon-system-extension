import { useBlockProps } from '@wordpress/block-editor';

export default function Edit({ context }: { context?: Record<string, unknown> }) {
    const couponId = Number(context?.['jankx/couponId'] ?? 0);
    const isCollectable = Boolean(context?.['jankx/couponCollectable']);

    const blockProps = useBlockProps({ className: 'jankx-coupon-pill-icon' });

    return (
        <span
            {...blockProps}
            aria-hidden="true"
            data-coupon-id={couponId || undefined}
            data-coupon-collectable={isCollectable ? '1' : undefined}
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4zm-8.5-1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm.8-6.8a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06z" />
            </svg>
        </span>
    );
}
