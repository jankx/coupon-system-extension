<?php
namespace Jankx\Extensions\CouponSystem;

use Jankx\Extensions\AbstractExtension;

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

        // Register sub-page with My Account
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

        // Always register blocks so ServerSideRender works in editor
        $this->registerBlocks();

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

        $blockPath = $blocksDir;
        if (!file_exists($blockPath . '/block.json')) {
            return;
        }

        $block = new \Jankx\Extensions\CouponSystem\Blocks\AccountTabCouponsBlock($blockPath);
        $block->setBlockPath($blockPath);
        $block->boot();
        $block->register();
    }

    /**
     * Check if current page is My Account page and register blocks if so
     */
    public function maybeRegisterFrontendBlocks(): void
    {
        if (!$this->isMyAccountPage()) {
            return;
        }

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
        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPage('coupons', [
            'label' => 'Coupons',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg>',
            'priority' => 20,
            'extension' => 'coupon-system',
            'show_in_nav' => true,
        ]);
    }
}
