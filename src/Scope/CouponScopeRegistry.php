<?php
namespace Jankx\Extensions\CouponSystem\Scope;

/**
 * Registry for all available coupon scope strategies.
 *
 * Built-in scopes (AllScope, ProductScope, ProductTypeScope) are registered
 * automatically. External code can add more via the filter:
 *
 *   add_filter('jankx/coupon/scopes', function(array $scopes): array {
 *       $scopes[] = new MyCategoryScope();
 *       return $scopes;
 *   });
 *
 * Usage:
 *   $registry = CouponScopeRegistry::getInstance();
 *   $scope    = $registry->get('product');   // null if not found
 *   $all      = $registry->all();            // [ id => strategy ]
 *
 * @package Jankx\Extensions\CouponSystem\Scope
 */
class CouponScopeRegistry
{
    /**
     * @var CouponScopeRegistry|null
     */
    private static $instance;

    /**
     * @var CouponScopeStrategy[] Keyed by scope ID.
     */
    private $strategies = [];

    /**
     * @var bool Whether built-in scopes + filter have been applied.
     */
    private $booted = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the registry: register defaults and apply the extension filter.
     * Safe to call multiple times — runs only once.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Register built-in scopes.
        $defaults = [
            new AllScope(),
            new ProductScope(),
            new ProductTypeScope(),
        ];

        foreach ($defaults as $scope) {
            $this->register($scope);
        }

        /**
         * Filter: jankx/coupon/scopes
         *
         * Allows external code to register additional CouponScopeStrategy
         * implementations. Return the modified array.
         *
         * @param CouponScopeStrategy[] $scopes Indexed array of strategies.
         */
        $filtered = apply_filters('jankx/coupon/scopes', array_values($this->strategies));

        // Re-build the map from the potentially modified filter result.
        $this->strategies = [];
        foreach ($filtered as $scope) {
            if ($scope instanceof CouponScopeStrategy) {
                $this->register($scope);
            }
        }
    }

    /**
     * Register a scope strategy.
     */
    public function register(CouponScopeStrategy $strategy): void
    {
        $this->strategies[$strategy->getId()] = $strategy;
    }

    /**
     * Retrieve a scope by its ID.
     *
     * @param string $id
     * @return CouponScopeStrategy|null
     */
    public function get(string $id): ?CouponScopeStrategy
    {
        return $this->strategies[$id] ?? null;
    }

    /**
     * Return all registered scopes, keyed by ID.
     *
     * @return CouponScopeStrategy[]
     */
    public function all(): array
    {
        return $this->strategies;
    }

    /**
     * Return an associative array of [ id => label ] for building <select> options.
     *
     * @return string[]
     */
    public function getOptions(): array
    {
        $options = [];
        foreach ($this->strategies as $id => $strategy) {
            $options[$id] = $strategy->getLabel();
        }
        return $options;
    }

    /**
     * Return the picker configs for all scopes, indexed by scope ID.
     * Used to localize data for the admin JS.
     *
     * @return array[]
     */
    public function getPickerConfigs(): array
    {
        $configs = [];
        foreach ($this->strategies as $id => $strategy) {
            $configs[$id] = $strategy->getPickerConfig();
        }
        return $configs;
    }
}
