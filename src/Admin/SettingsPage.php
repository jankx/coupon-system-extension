<?php
namespace Jankx\Extensions\CouponSystem\Admin;

class SettingsPage
{
    const PAGE_SLUG = 'jankx-coupon-settings';
    const OPTION_GROUP = 'jankx_coupon_settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 25);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'jankx-theme-options',
            __('Coupon System Settings', 'jankx'),
            __('Mã giảm giá', 'jankx'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, 'jankx_coupon_enabled', [
            'default' => '1',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_coupon_default_limit', [
            'default' => '100',
            'sanitize_callback' => 'absint',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_coupon_auto_generate', [
            'default' => '0',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        $extension = \Jankx\Extensions\CouponSystem\CouponSystemExtension::get_instance();
        if (!$extension) {
            return;
        }

        wp_enqueue_style(
            'jankx-coupon-admin',
            $extension->get_extension_url() . '/assets/admin.css',
            [],
            '1.0.0'
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cài đặt Hệ thống Mã giảm giá', 'jankx'); ?></h1>
            <p class="description">
                <?php esc_html_e('Quản lý cài đặt cho hệ thống mã giảm giá của Nobitour.', 'jankx'); ?>
            </p>

            <form method="post" action="options.php" style="max-width: 700px; margin-top: 20px;">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jankx_coupon_enabled">
                                <?php esc_html_e('Kích hoạt hệ thống mã giảm giá', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="jankx_coupon_enabled"
                                    name="jankx_coupon_enabled"
                                    class="regular-text">
                                <option value="1" <?php selected(get_option('jankx_coupon_enabled', '1'), '1'); ?>>
                                    <?php esc_html_e('Bật', 'jankx'); ?>
                                </option>
                                <option value="0" <?php selected(get_option('jankx_coupon_enabled', '1'), '0'); ?>>
                                    <?php esc_html_e('Tắt', 'jankx'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Bật hoặc tắt toàn bộ hệ thống mã giảm giá.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_coupon_default_limit">
                                <?php esc_html_e('Giới hạn sử dụng mặc định', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="jankx_coupon_default_limit"
                                   name="jankx_coupon_default_limit"
                                   value="<?php echo esc_attr(get_option('jankx_coupon_default_limit', '100')); ?>"
                                   class="regular-text"
                                   min="0"
                                   step="1">
                            <p class="description">
                                <?php esc_html_e('Số lần sử dụng tối đa mặc định khi tạo mã mới. Nhập 0 cho không giới hạn.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_coupon_auto_generate">
                                <?php esc_html_e('Tự động tạo mã giảm giá', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="jankx_coupon_auto_generate"
                                    name="jankx_coupon_auto_generate"
                                    class="regular-text">
                                <option value="1" <?php selected(get_option('jankx_coupon_auto_generate', '0'), '1'); ?>>
                                    <?php esc_html_e('Bật', 'jankx'); ?>
                                </option>
                                <option value="0" <?php selected(get_option('jankx_coupon_auto_generate', '0'), '0'); ?>>
                                    <?php esc_html_e('Tắt', 'jankx'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Tự động tạo mã code ngẫu nhiên khi tạo mã giảm giá mới.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Lưu cài đặt', 'jankx')); ?>
            </form>

            <div style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; max-width: 700px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Quản lý mã giảm giá', 'jankx'); ?></h2>
                <p>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=jankx_coupon')); ?>"
                       class="button button-primary">
                        <?php esc_html_e('Xem tất cả mã giảm giá', 'jankx'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=jankx_coupon')); ?>"
                       class="button">
                        <?php esc_html_e('Thêm mã mới', 'jankx'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}
