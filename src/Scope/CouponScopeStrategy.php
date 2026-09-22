<?php
namespace Jankx\Extensions\CouponSystem\Scope;

/**
 * Contract for a coupon scope strategy.
 *
 * Each strategy encapsulates:
 *  - how the admin UI picker should be rendered (via `getPickerConfig()`),
 *  - how raw picker values are sanitised before storage (`sanitizeValues()`),
 *  - how stored values are matched against a cart item at validation time (`matches()`).
 *
 * New scopes can be registered at runtime through the filter
 * `jankx/coupon/scopes` (see CouponScopeRegistry).
 *
 * @package Jankx\Extensions\CouponSystem\Scope
 */
interface CouponScopeStrategy
{
    /**
     * Unique machine-readable identifier stored in `_coupon_applies_to`.
     * Must be a valid sanitize_key() string.
     */
    public function getId(): string;

    /**
     * Human-readable label shown in the "Áp dụng cho" dropdown.
     */
    public function getLabel(): string;

    /**
     * Configuration consumed by the JS Select2 picker.
     *
     * Shape:
     * ```
     * [
     *   'type'        => 'hidden'           // hides the picker (AllScope)
     *                  | 'select2_ajax'     // AJAX-powered search
     *                  | 'select2_static',  // static option list
     *   'label'       => string,            // field label above picker
     *   'placeholder' => string,            // Select2 placeholder text
     *   // Only for select2_ajax:
     *   'rest_base'   => string,            // WP REST base, e.g. 'tour'
     *   'rest_args'   => array,             // extra query args appended to REST call
     *   // Only for select2_static:
     *   'options'     => [ value => label ],
     * ]
     * ```
     *
     * @return array
     */
    public function getPickerConfig(): array;

    /**
     * Sanitise raw picker values before they are persisted to post meta.
     *
     * @param array $raw Unsanitised values from the admin form POST.
     * @return array     Clean values ready for update_post_meta().
     */
    public function sanitizeValues(array $raw): array;

    /**
     * Return true if the given cart item is covered by this scope
     * using the stored $values.
     *
     * @param array $item   A single cart item array, at minimum:
     *                      [ 'product_id' => int, ... ]
     * @param array $values The stored apply_values for the coupon.
     * @return bool
     */
    public function matches(array $item, array $values): bool;
}
