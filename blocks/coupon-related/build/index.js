(() => {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;

    registerBlockType('jankx/coupon-related', {
        apiVersion: 3,
        title: 'Related Coupons',
        category: 'jankx',
        icon: 'star-half',
        description: 'Sidebar list of related coupon cards.',
        supports: { html: false },
        edit: function () {
            return el('div', { className: 'jd-editor-placeholder' },
                el('span', { style: { display: 'block', marginBottom: '8px', fontSize: '22px' } }, '\u{1F4E7}'),
                'Related Coupons'
            );
        },
        save: function () { return null; }
    });
})();