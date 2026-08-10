(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var isGlobal = document.getElementById('coupon_is_global');
        var isCollectable = document.getElementById('coupon_is_collectable');

        if (isGlobal && isCollectable) {
            function sync() {
                if (!isGlobal.checked) {
                    isCollectable.checked = false;
                    isCollectable.disabled = true;
                } else {
                    isCollectable.disabled = false;
                }
            }

            isGlobal.addEventListener('change', sync);
            sync();
        }
    });
})();
