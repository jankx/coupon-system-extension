import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes } ) {
    const blockProps = useBlockProps({
        className: 'jankx-account-tab-coupons is-editor-preview',
    });

    return (
        <div {...blockProps}>
            <ServerSideRender
                block="jankx/account-tab-coupons"
                attributes={attributes}
            />
        </div>
    );
}
