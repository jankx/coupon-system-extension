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

        if (is_admin()) {
            $settingsPage = new \Jankx\Extensions\CouponSystem\Admin\SettingsPage();
            $settingsPage->register();
        }
    }
}
