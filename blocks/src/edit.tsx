import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-account-tab-coupons is-editor-preview',
    });

    return (
        <div {...blockProps}>
            <div style={{ padding: '20px', background: '#f8f9fa', borderRadius: '8px', border: '1px dashed #ddd' }}>
                <h3 style={{ margin: '0 0 16px', fontSize: '16px' }}>{__('Coupons', 'jankx')}</h3>
                <div style={{ textAlign: 'center', padding: '40px 20px', color: '#999' }}>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" strokeWidth="1.5">
                        <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/>
                        <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/>
                        <path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>
                    </svg>
                    <p style={{ marginTop: '12px' }}>{__('Your coupons will appear here', 'jankx')}</p>
                </div>
            </div>
        </div>
    );
}
