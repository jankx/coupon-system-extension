(() => {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;

    registerBlockType('jankx/coupon-source-cta', {
        apiVersion: 3,
        title: 'Coupon Source CTA',
        category: 'jankx',
        icon: 'admin-site',
        description: 'Banner to add the coupon as a preferred source on Google.',
        supports: { html: false },
        edit: function () {
            return el('div', { className: 'jd-editor-placeholder' },
                el('span', { style: { display: 'block', marginBottom: '8px', fontSize: '22px' } }, 'G'),
                'Coupon Source CTA'
            );
        },
        save: function () { return null; }
    });
})();