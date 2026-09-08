import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, RangeControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
    const blockProps = useBlockProps({
        className: 'jankx-available-coupons is-editor-preview',
    });

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
                <ServerSideRender
                    block="jankx/available-coupons"
                    attributes={ attributes }
                />
            </div>
        </>
    );
}
