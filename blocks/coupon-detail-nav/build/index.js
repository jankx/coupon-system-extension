(() => {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;

    registerBlockType('jankx/coupon-detail-nav', {
        apiVersion: 3,
        title: 'Coupon Detail Navigation',
        category: 'jankx',
        icon: 'arrow-left-alt',
        description: 'Top navigation bar for the coupon detail page (Go Back / Share).',
        supports: { html: false },
        edit: function () {
            return el('div', { className: 'jd-editor-placeholder', style: { border: '1px dashed #cbd5e1', borderRadius: '12px', background: '#f8fafc', color: '#64748b', fontSize: '14px', fontWeight: 600, padding: '48px 24px', textAlign: 'center' } },
                el('span', { style: { display: 'block', marginBottom: '8px', fontSize: '22px' } }, '\u2190'),
                'Coupon Detail Navigation'
            );
        },
        save: function () { return null; }
    });
})();