(() => {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;

    registerBlockType('jankx/coupon-detail', {
        apiVersion: 3,
        title: 'Coupon Detail',
        category: 'jankx',
        icon: 'tickets-alt',
        description: 'Renders the coupon detail main content: header card, offer box, metadata, Telegram banner, terms and feedback.',
        supports: { html: false },
        edit: function () {
            return el('div', { className: 'jd-editor-placeholder' },
                el('span', { style: { display: 'block', marginBottom: '8px', fontSize: '22px' } }, '\u{1F389}'),
                'Coupon Detail'
            );
        },
        save: function () { return null; }
    });
})();