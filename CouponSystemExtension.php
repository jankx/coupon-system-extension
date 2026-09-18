<?php
namespace Jankx\Extensions\CouponSystem;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\CouponSystem\Blocks\AccountTabCouponsBlock;
use Jankx\Extensions\CouponSystem\Integration\CheckoutIntegration;
use Jankx\Extensions\CouponSystem\Rest\CouponController;

class CouponSystemExtension extends AbstractExtension
{
    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\CouponSystem\\';
            $base_dir = __DIR__ . '/src/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        $postType = new \Jankx\Extensions\CouponSystem\PostTypes\CouponPostType();
        $postType->register();

        $metaBoxes = new \Jankx\Extensions\CouponSystem\Meta\CouponMetaBoxes();
        $metaBoxes->register();

        // Bridge into the ecommerce cart/checkout flow.
        (new CheckoutIntegration())->register();

        // Coupon detail page (frontend renderer + feedback AJAX).
        (new \Jankx\Extensions\CouponSystem\Frontend\CouponDetailRenderer())->register();

        // REST API for coupons, collection and cart apply.
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Register sub-page with My Account
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

        // Always register blocks so ServerSideRender works in editor
        if (did_action('init')) {
            $this->registerBlocks();
        } else {
            add_action('init', [$this, 'registerBlocks']);
        }

        // Frontend assets on the my-account and cart pages.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        if (is_admin()) {
            $settingsPage = new \Jankx\Extensions\CouponSystem\Admin\SettingsPage();
            $settingsPage->register();
        } else {
            add_action('template_redirect', [$this, 'maybeRegisterFrontendBlocks']);
        }
    }

    /**
     * Register Gutenberg blocks for this extension
     */
    public function registerBlocks(): void
    {
        $blocksDir = __DIR__ . '/blocks';
        if (!is_dir($blocksDir)) {
            return;
        }

        if (file_exists($blocksDir . '/block.json')) {
            $block = new \Jankx\Extensions\CouponSystem\Blocks\AccountTabCouponsBlock($blocksDir);
            $block->setBlockPath($blocksDir);
            $block->boot();
            $block->register();
        }

        foreach (glob($blocksDir . '/*', GLOB_ONLYDIR) as $blockDir) {
            if ($blockDir === $blocksDir . '/build' || $blockDir === $blocksDir . '/src') {
                continue;
            }

            if (!file_exists($blockDir . '/block.json')) {
                continue;
            }

            $blockJson = json_decode(file_get_contents($blockDir . '/block.json'), true);
            $blockName = $blockJson['name'] ?? '';

            if ($blockName === 'jankx/account-tab-coupons' && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $block = new \Jankx\Extensions\CouponSystem\Blocks\AccountTabCouponsBlock($blockDir);
                $block->setBlockPath($blockDir);
                $block->boot();
                $block->register();
            } elseif ($blockName === 'jankx/available-coupons' && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $block = new \Jankx\Extensions\CouponSystem\Blocks\AvailableCouponsBlock($blockDir);
                $block->setBlockPath($blockDir);
                $block->boot();
                $block->register();
            } elseif ($blockName === 'jankx/coupon-detail-nav' && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $block = new \Jankx\Extensions\CouponSystem\Blocks\CouponDetailNav($blockDir);
                $block->setBlockPath($blockDir);
                $block->boot();
                $block->register();
            } elseif ($blockName === 'jankx/coupon-detail' && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $block = new \Jankx\Extensions\CouponSystem\Blocks\CouponDetail($blockDir);
                $block->setBlockPath($blockDir);
                $block->boot();
                $block->register();
            } elseif ($blockName === 'jankx/coupon-related' && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $block = new \Jankx\Extensions\CouponSystem\Blocks\CouponRelated($blockDir);
                $block->setBlockPath($blockDir);
                $block->boot();
                $block->register();
            } elseif ($blockName === 'jankx/coupon-source-cta' && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $block = new \Jankx\Extensions\CouponSystem\Blocks\CouponSourceCta($blockDir);
                $block->setBlockPath($blockDir);
                $block->boot();
                $block->register();
            } elseif ($blockName && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                register_block_type_from_metadata($blockDir);
            }
        }
    }

    /**
     * Check and register blocks on frontend if needed
     */
    public function maybeRegisterFrontendBlocks(): void
    {
        $this->registerBlocks();
    }

    /**
     * Check if current page is My Account page or a sub-page
     */
    protected function isMyAccountPage(): bool
    {
        $pageId = get_option('jankx_my_account_page_id', 0);
        if (!$pageId) {
            return false;
        }

        if (is_page($pageId)) {
            return true;
        }

        $subPage = get_query_var('jankx_account_page');
        if (!empty($subPage)) {
            return true;
        }

        global $post;
        if ($post && has_shortcode($post->post_content, 'jankx_my_account')) {
            return true;
        }

        return false;
    }

    /**
     * Register coupon sub-page with My Account
     */
    public function registerAccountSubPage(): void
    {
        if (!class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension')) {
            return;
        }

        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPageClass(new \Jankx\Extensions\CouponSystem\MyAccount\CouponsSubPage());
    }

    /**
     * Register the coupon REST routes.
     */
    public function register_rest_routes(): void
    {
        (new CouponController())->register_routes();
    }

    /**
     * Frontend assets for the my-account coupons tab, cart page, and available coupons block.
     */
    public function enqueue_frontend_assets(): void
    {
        $isCartPage = class_exists('\Jankx\Extensions\Ecommerce\EcommerceExtension')
            && \Jankx\Extensions\Ecommerce\EcommerceExtension::get_cart_page_id()
            && is_page(\Jankx\Extensions\Ecommerce\EcommerceExtension::get_cart_page_id());

        $hasAvailableCouponsBlock = function_exists('has_block') && has_block('jankx/available-coupons');
        $hasAccountCouponsBlock   = function_exists('has_block') && has_block('jankx/account-tab-coupons');

        if (!$this->isMyAccountPage() && !$isCartPage && !$hasAvailableCouponsBlock && !$hasAccountCouponsBlock && !is_singular()) {
            return;
        }

        wp_enqueue_style(
            'jankx-account-coupons',
            $this->get_extension_url() . '/assets/account-coupons.css',
            [],
            filemtime($this->get_extension_path() . '/assets/account-coupons.css')
        );

        wp_enqueue_script(
            'jankx-account-coupons',
            $this->get_extension_url() . '/assets/account-coupons.js',
            [],
            filemtime($this->get_extension_path() . '/assets/account-coupons.js'),
            true
        );

        wp_localize_script('jankx-account-coupons', 'jankxCoupon', [
            'restUrl' => esc_url_raw(rest_url(CouponController::REST_NAMESPACE)),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'error'     => __('Đã xảy ra lỗi, vui lòng thử lại.', 'jankx'),
                'copied'    => __('Đã sao chép!', 'jankx'),
                'collected' => __('Đã thu thập mã thành công!', 'jankx'),
                'enterCode' => __('Vui lòng nhập mã giảm giá.', 'jankx'),
            ],
        ]);
    }
}
