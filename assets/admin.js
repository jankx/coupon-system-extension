(function ($, wp) {
    'use strict';

    $(document).ready(function () {
        var $globalCheckbox = $('#coupon_is_global');
        var $collectableCheckbox = $('#coupon_is_collectable');

        if ($globalCheckbox.length && $collectableCheckbox.length) {
            function syncGlobalCollectable() {
                if (!$globalCheckbox.prop('checked')) {
                    $collectableCheckbox.prop('checked', false);
                    $collectableCheckbox.prop('disabled', true);
                } else {
                    $collectableCheckbox.prop('disabled', false);
                }
            }
            $globalCheckbox.on('change', syncGlobalCollectable);
            syncGlobalCollectable();
        }

        // Scope Picker Logic
        if (typeof jankxCouponAdmin === 'undefined' || !jankxCouponAdmin.scopes) {
            return;
        }

        var $appliesTo = $('#coupon_applies_to');
        var $applyValuesRow = $('#row-coupon-apply-values');
        var $applyValuesSelect = $('#coupon_apply_values_ids');
        var $applyValuesDesc = $('#coupon_apply_values_desc');

        function initScopePicker(scopeId) {
            // Destroy existing Select2 if it exists
            if ($applyValuesSelect.hasClass('select2-hidden-accessible')) {
                $applyValuesSelect.select2('destroy');
            }
            
            $applyValuesSelect.empty();
            $applyValuesRow.hide();

            var config = jankxCouponAdmin.scopes[scopeId] || null;
            if (!config || config.type === 'hidden') {
                return;
            }

            $applyValuesRow.show();
            if (config.label) {
                $applyValuesRow.find('label').text(config.label);
            }
            $applyValuesDesc.text(config.description || '');

            var select2Config = {
                placeholder: config.placeholder || 'Select items...',
                width: '100%',
                allowClear: true
            };

            if (config.type === 'select2_static') {
                // Populate options
                var options = config.options || [];
                $.each(options, function(i, opt) {
                    var $option = $('<option>', {
                        value: opt.value,
                        text: opt.label
                    });
                    $applyValuesSelect.append($option);
                });
            } else if (config.type === 'select2_ajax') {
                // Determine the base route. If there are multiple post types configured, 
                // we might need a generic endpoint, but for now we search across the first one
                // or just use /wp/v2/search ? type=post&subtype=... 
                // Wait, WordPress default REST API supports searching specific post types: 
                // /wp/v2/{rest_base}?search=...
                // If there are multiple, we'd need a custom endpoint or query multiple.
                // For simplicity, if config.post_types is provided, we can build a combined search
                // using /wp/v2/search ? type=post&subtype[]=tour&subtype[]=product
                // Actually /wp/v2/search is easiest for multiple post types.
                
                var subtypes = [];
                if (config.post_types) {
                    $.each(config.post_types, function(i, pt) {
                        if (pt.post_type) subtypes.push(pt.post_type);
                    });
                }

                select2Config.ajax = {
                    url: jankxCouponAdmin.restUrl + 'wp/v2/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        var query = {
                            search: params.term,
                            type: 'post',
                            per_page: 20
                        };
                        
                        if (subtypes.length > 0) {
                            query.subtype = subtypes.join(',');
                        }
                        
                        return query;
                    },
                    processResults: function (data) {
                        return {
                            results: $.map(data, function(item) {
                                return {
                                    id: item.id,
                                    text: item.title
                                };
                            })
                        };
                    },
                    cache: true
                };
            }

            $applyValuesSelect.select2(select2Config);

            // Pre-populate if we have existing values
            if (jankxCouponAdmin.existingValues && jankxCouponAdmin.existingValues.length > 0) {
                $.each(jankxCouponAdmin.existingValues, function(i, val) {
                    if ($applyValuesSelect.find('option[value="' + val.id + '"]').length === 0) {
                        var $option = new Option(val.text, val.id, true, true);
                        $applyValuesSelect.append($option);
                    }
                });
                $applyValuesSelect.trigger('change');
            }
        }

        $appliesTo.on('change', function () {
            // When user changes scope, clear existing values because they don't apply anymore
            if (jankxCouponAdmin.existingValues && jankxCouponAdmin.existingValues.length > 0) {
                 // Only clear if the user actually clicked to change it, 
                 // but we can't easily tell here. We'll just rely on the server to discard mismatched values.
            }
            initScopePicker($(this).val());
        });

        // Initial setup
        if ($appliesTo.length) {
            initScopePicker($appliesTo.val());
        }
    });

})(jQuery, wp);
