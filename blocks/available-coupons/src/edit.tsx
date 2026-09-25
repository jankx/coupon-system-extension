import { useBlockProps, InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import { PanelBody, TextControl, RangeControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
    const blockProps = useBlockProps({
        className: 'jankx-available-coupons is-editor-preview',
    });

    const innerBlocksProps = {
        template: [
            ['jankx/coupon-pill-icon', {}],
            ['jankx/coupon-pill-label', {}]
        ],
        allowedBlocks: [
            'jankx/coupon-pill-icon',
            'jankx/coupon-pill-label',
            'core/paragraph',
            'core/image'
        ],
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Cấu hình mã giảm giá', 'jankx' ) }>
                    <TextControl
                        label={ __( 'Tiêu đề block', 'jankx' ) }
                        value={ attributes.title || '' }
                        onChange={ ( value ) => setAttributes( { title: value } ) }
                        placeholder={ __( 'Mã giảm giá dành cho bạn', 'jankx' ) }
                    />
                    <RangeControl
                        label={ __( 'Số lượng mã hiển thị', 'jankx' ) }
                        value={ attributes.limit || 5 }
                        onChange={ ( value ) => setAttributes( { limit: value } ) }
                        min={ 1 }
                        max={ 20 }
                    />
                    <ToggleControl
                        label={ __( 'Hiển thị nút xem thêm (>)', 'jankx' ) }
                        checked={ attributes.showMore ?? true }
                        onChange={ ( value ) => setAttributes( { showMore: value } ) }
                    />
                </PanelBody>
            </InspectorControls>
            
            <div { ...blockProps }>
                { attributes.title && (
                    <div className="jankx-available-coupons-header">
                        <h4 className="jankx-available-coupons-title">{ attributes.title }</h4>
                    </div>
                ) }
                <div className="jankx-available-coupons-body">
                    <div className="jankx-coupons-pills-list">
                        {/* Editor preview: showing the template editable area */}
                        <div className="jankx-coupon-pill jankx-editor-template-area">
                            <InnerBlocks { ...innerBlocksProps } />
                        </div>
                        {/* Fake second pill to show how it looks as a list */}
                        <div className="jankx-coupon-pill" style={{ opacity: 0.5, pointerEvents: 'none' }}>
                            <span className="jankx-coupon-pill-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4zm-8.5-1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm.8-6.8a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06z"/></svg></span>
                            <span className="jankx-coupon-pill-text">Mã giảm giá mẫu #2</span>
                        </div>
                    </div>
                    { attributes.showMore && (
                        <button type="button" className="jankx-coupons-more-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
                    ) }
                </div>
            </div>
        </>
    );
}
