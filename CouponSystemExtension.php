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

        if (is_admin()) {
            $settingsPage = new \Jankx\Extensions\CouponSystem\Admin\SettingsPage();
            $settingsPage->register();
        }
    }

    /**
     * Register coupon sub-page with My Account
     */
    public function registerAccountSubPage(): void
    {
        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPage('coupons', [
            'label' => 'Mã ưu đãi',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg>',
            'priority' => 20,
            'extension' => 'coupon-system',
            'show_in_nav' => true,
        ]);
    }
}
