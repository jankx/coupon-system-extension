<?php
namespace Jankx\Extensions\CouponSystem\Frontend;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;

class CouponDetailRenderer
{
    const FEEDBACK_ACTION = 'jankx_coupon_feedback';
    const FEEDBACK_NONCE  = 'jankx_coupon_feedback_nonce';
    const FEEDBACK_META   = '_coupon_feedback';

    public function register(): void
    {
        add_action('wp_footer', [$this, 'printInlineScripts'], 20);
        add_action('wp_ajax_' . self::FEEDBACK_ACTION, [$this, 'handleFeedback']);
        add_action('wp_ajax_nopriv_' . self::FEEDBACK_ACTION, [$this, 'handleFeedback']);
    }

    public function printInlineScripts(): void
    {
        if (!$this->isCouponPage()) {
            return;
        }

        $coupon = $this->getCurrentCoupon();
        $data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => self::FEEDBACK_ACTION,
            'nonce'   => wp_create_nonce(self::FEEDBACK_NONCE),
            'postId'  => $coupon ? $coupon->getId() : 0,
        ];

        printf(
            '<script type="application/json" id="jankx-coupon-detail-data">%s</script>',
            wp_json_encode($data)
        );
        printf('<script type="text/javascript">%s</script>', self::getJs());
    }

    /* ---------------------------------------------------------------------
     * Block render callbacks
     * ------------------------------------------------------------------- */

    public function renderNavigation(): string
    {
        if ($this->isEditorRender()) {
            return $this->editorPlaceholder(__('Coupon Detail Navigation', 'jankx'));
        }

        ob_start();
        ?>
        <nav class="jd-nav" aria-label="<?php echo esc_attr__('Coupon navigation', 'jankx'); ?>">
            <button type="button" class="jd-nav__back jd-btn jd-btn-ghost">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"></path></svg>
                <span><?php echo esc_html__('Go Back', 'jankx'); ?></span>
            </button>
            <span class="jd-nav__spacer"></span>
            <button type="button" class="jd-nav__share jd-btn jd-btn-ghost">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                <span><?php echo esc_html__('Share', 'jankx'); ?></span>
            </button>
        </nav>
        <?php
        return ob_get_clean();
    }

    public function renderMain(): string
    {
        if ($this->isEditorRender()) {
            return $this->editorPlaceholder(__('Coupon Detail', 'jankx'));
        }

        $coupon = $this->getCurrentCoupon();
        if (!$coupon) {
            return '';
        }

        ob_start();
        echo $this->renderHero($coupon);
        echo $this->renderTerms($coupon);
        echo $this->renderFeedback($coupon);
        return ob_get_clean();
    }

    public function renderRelated(): string
    {
        if ($this->isEditorRender()) {
            return $this->editorPlaceholder(__('Related Coupons', 'jankx'));
        }

        $current = $this->getCurrentCoupon();

        $args = [
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 4,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];
        if ($current) {
            $args['post__not_in'] = [$current->getId()];
        }

        $posts = get_posts($args);
        if (empty($posts)) {
            return '';
        }

        ob_start();
        ?>
        <aside class="jd-related" aria-label="<?php echo esc_attr__('Related coupons', 'jankx'); ?>">
            <h3 class="jd-sidebar-title"><?php echo esc_html__('Related Coupons', 'jankx'); ?></h3>
            <div class="jd-related__list">
                <?php foreach ($posts as $post) : $item = new Coupon($post->ID); ?>
                    <a class="jd-related-card" href="<?php echo esc_url(get_permalink($post->ID)); ?>">
                        <span class="jd-related-card__logo"><?php echo esc_html($item->getMerchantName()); ?></span>
                        <span class="jd-related-card__body">
                            <span class="jd-related-card__title"><?php echo esc_html($item->getTitle()); ?></span>
                            <span class="jd-related-card__discount"><?php echo esc_html($this->getDiscountLabel($item)); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }

    public function renderSourceCta(): string
    {
        if ($this->isEditorRender()) {
            return $this->editorPlaceholder(__('Google Source CTA', 'jankx'));
        }

        $coupon = $this->getCurrentCoupon();
        $query  = urlencode($coupon ? $coupon->getMerchantName() : get_bloginfo('name'));
        $url    = add_query_arg(['q' => $query], 'https://www.google.com/search');

        ob_start();
        ?>
        <aside class="jd-source-cta">
            <span class="jd-source-cta__icon" aria-hidden="true">G</span>
            <p class="jd-source-cta__text"><?php echo esc_html__('Add as a preferred source on Google', 'jankx'); ?></p>
            <a class="jd-btn jd-btn-primary jd-btn-block" href="<?php echo esc_url($url); ?>" target="_blank" rel="nofollow noopener">
                <?php echo esc_html__('Add to Google', 'jankx'); ?>
            </a>
        </aside>
        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * Section renderers
     * ------------------------------------------------------------------- */

    protected function renderHero(Coupon $coupon): string
    {
        $merchantUrl  = $coupon->getMerchantUrl();
        $merchantName = $coupon->getMerchantName();
        $merchantLogo = $coupon->getMerchantLogo();
        $discountLabel = $this->getDiscountLabel($coupon);
        $code         = $coupon->getCode();

        ob_start();
        ?>
        <div class="jd-hero">
            <div class="jd-hero__top">
                <div class="jd-hero__headline">
                    <span class="jd-hero__tag">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 0.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z"/></svg>
                        <?php echo esc_html__('Deal of the Day', 'jankx'); ?>
                    </span>
                    <h1 class="jd-hero__discount"><?php echo esc_html($discountLabel); ?></h1>
                    <h2 class="jd-hero__title"><?php echo esc_html($coupon->getTitle()); ?></h2>
                </div>
                <div class="jd-hero__logo">
                    <?php if ($merchantLogo) : ?>
                        <img src="<?php echo esc_url($merchantLogo); ?>" alt="<?php echo esc_attr($merchantName); ?>" loading="lazy">
                    <?php else : ?>
                        <span class="jd-hero__logo-fallback"><?php echo esc_html($this->getInitials($merchantName)); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="jd-offer-box">
                <div class="jd-offer-box__message">
                    <span class="jd-offer-box__icon" aria-hidden="true">&#10003;</span>
                    <span><?php echo esc_html__('Offer Activated', 'jankx'); ?></span>
                </div>
                <?php if ($code) : ?>
                    <div class="jd-offer-box__code">
                        <code><?php echo esc_html($code); ?></code>
                        <button type="button" class="jd-copy-code jd-btn jd-btn-sm" data-code="<?php echo esc_attr($code); ?>">
                            <?php echo esc_html__('Copy', 'jankx'); ?>
                        </button>
                    </div>
                <?php endif; ?>
                <a class="jd-offer-box__action jd-btn jd-btn-secondary" href="<?php echo esc_url($merchantUrl); ?>" target="_blank" rel="nofollow noopener">
                    <?php printf(esc_html__('Go To %s Website', 'jankx'), esc_html($merchantName)); ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>
            </div>

            <ul class="jd-meta">
                <?php if ($coupon->isVerified()) : ?>
                    <li class="jd-meta__item jd-meta__verified">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>
                        <?php echo esc_html__('Verified', 'jankx'); ?>
                    </li>
                <?php endif; ?>
                <?php if ($coupon->isNewUserOnly()) : ?>
                    <li class="jd-meta__item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                        <?php echo esc_html__('New User', 'jankx'); ?>
                    </li>
                <?php endif; ?>
                <?php if ($coupon->getExpiryTimestamp()) : ?>
                    <li class="jd-meta__item jd-meta__expiry">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php printf(esc_html__('Valid Till: %s', 'jankx'), esc_html(wp_date('M j, Y', $coupon->getExpiryTimestamp()))); ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function renderTerms(Coupon $coupon): string
    {
        $terms = $coupon->getTerms();
        $rows  = $coupon->getDiscountCategories();

        if (empty($terms) && empty($rows)) {
            return '';
        }

        ob_start();
        ?>
        <section class="jd-terms">
            <?php if (!empty($terms)) : ?>
                <h3 class="jd-section-title"><?php echo esc_html__("Terms & Conditions", 'jankx'); ?></h3>
                <ol class="jd-terms__list">
                    <?php foreach ($terms as $term) : ?>
                        <li><?php echo esc_html($term); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if (!empty($rows)) : ?>
                <h3 class="jd-section-title"><?php echo esc_html__('Discount Categories', 'jankx'); ?></h3>
                <table class="jd-discount-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Category', 'jankx'); ?></th>
                            <th><?php echo esc_html__('Discount', 'jankx'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['category']); ?></td>
                                <td><?php printf(esc_html__('Up To %s%% OFF', 'jankx'), esc_html(number_format($row['percent'], 0))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    protected function renderFeedback(Coupon $coupon): string
    {
        $data  = get_post_meta($coupon->getId(), self::FEEDBACK_META, true);
        $data  = is_array($data) ? $data : ['yes' => 0, 'no' => 0];

        ob_start();
        ?>
        <section class="jd-feedback" data-coupon-id="<?php echo esc_attr($coupon->getId()); ?>">
            <h3 class="jd-section-title"><?php echo esc_html__('Did the coupon work?', 'jankx'); ?></h3>
            <div class="jd-feedback__actions">
                <button type="button" class="jd-feedback__btn jd-btn jd-btn-outline" data-answer="yes">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                    <?php echo esc_html__('Yes', 'jankx'); ?>
                    <span class="jd-feedback__count"><?php echo esc_html((int) $data['yes']); ?></span>
                </button>
                <button type="button" class="jd-feedback__btn jd-btn jd-btn-outline" data-answer="no">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3z"></path><path d="M17 22h3a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-3"></path></svg>
                    <?php echo esc_html__('No', 'jankx'); ?>
                    <span class="jd-feedback__count"><?php echo esc_html((int) $data['no']); ?></span>
                </button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    protected function isCouponPage(): bool
    {
        return is_singular(CouponPostType::POST_TYPE);
    }

    protected function getCurrentCoupon(): ?Coupon
    {
        $postId = get_the_ID() ?: get_queried_object_id();
        if (!$postId || get_post_type($postId) !== CouponPostType::POST_TYPE) {
            return null;
        }

        return new Coupon($postId);
    }

    protected function isEditorRender(): bool
    {
        return (defined('REST_REQUEST') && REST_REQUEST && !empty($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false)
            || (is_admin() && !wp_doing_ajax());
    }

    protected function editorPlaceholder(string $label): string
    {
        return sprintf(
            '<div class="jd-editor-placeholder">%s</div>',
            esc_html($label)
        );
    }

    protected function getDiscountLabel(Coupon $coupon): string
    {
        if ($coupon->getType() === Coupon::TYPE_PERCENT) {
            return sprintf(__('Up To %s%% OFF', 'jankx'), number_format($coupon->getAmount(), 0));
        }

        return sprintf(__('%s OFF', 'jankx'), number_format($coupon->getAmount(), 0, ',', '.') . 'đ');
    }

    protected function getInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $words = array_filter((array) $words);

        return strtoupper(
            mb_substr((string) ($words[0] ?? 'J'), 0, 1)
            . (isset($words[1]) ? mb_substr((string) $words[1], 0, 1) : '')
        );
    }

    /* ---------------------------------------------------------------------
     * Feedback AJAX
     * ------------------------------------------------------------------- */

    public function handleFeedback(): void
    {
        check_ajax_referer(self::FEEDBACK_NONCE, 'nonce');

        $postId = (int) ($_POST['post_id'] ?? 0);
        $answer = sanitize_key($_POST['answer'] ?? '');

        if (!$postId || !in_array($answer, ['yes', 'no'], true) || get_post_type($postId) !== CouponPostType::POST_TYPE) {
            wp_send_json_error(['message' => __('Invalid request.', 'jankx')], 400);
        }

        $data = get_post_meta($postId, self::FEEDBACK_META, true);
        $data = is_array($data) ? $data : ['yes' => 0, 'no' => 0];

        $data[$answer] = (int) ($data[$answer] ?? 0) + 1;
        update_post_meta($postId, self::FEEDBACK_META, $data);

        wp_send_json_success([
            'yes' => (int) $data['yes'],
            'no'  => (int) $data['no'],
        ]);
    }

    /* ---------------------------------------------------------------------
     * Static frontend assets (inline only - no external files)
     * ------------------------------------------------------------------- */

    public static function getCss(): string
    {
        return <<<'CSS'
.jd-nav{display:flex;align-items:center;gap:8px;padding:16px 0;max-width:1200px;margin:0 auto}
.jd-nav__spacer{flex:1}
.jd-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:10px;font-size:14px;font-weight:600;line-height:1;padding:12px 16px;cursor:pointer;text-decoration:none;transition:background .15s,box-shadow .15s,transform .15s;font-family:inherit}
.jd-btn:hover{transform:translateY(-1px)}
.jd-btn-ghost{background:#fff;color:#1e293b;border:1px solid #e2e8f0}
.jd-btn-ghost:hover{background:#f1f5f9}
.jd-btn-primary{background:#2563eb;color:#fff}
.jd-btn-primary:hover{background:#1d4ed8}
.jd-btn-secondary{background:#fff;color:#2563eb;border:2px solid #2563eb}
.jd-btn-secondary:hover{background:#eff6ff}
.jd-btn-light{background:#fff;color:#0284c7}
.jd-btn-block{display:flex;width:100%}
.jd-btn-sm{font-size:13px;padding:8px 12px}
.jd-hero{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.jd-hero__top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start}
.jd-hero__headline{flex:1}
.jd-hero__tag{display:inline-flex;align-items:center;gap:6px;background:#7c3aed;color:#fff;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:6px 12px;border-radius:999px}
.jd-hero__tag svg{color:#fbbf24}
.jd-hero__discount{font-size:clamp(1.8rem,4vw,2.6rem);font-weight:800;color:#dc2626;line-height:1.15;margin:14px 0 6px}
.jd-hero__title{font-size:clamp(1rem,2vw,1.25rem);font-weight:600;color:#334155;margin:0}
.jd-hero__logo{width:72px;height:72px;border:1px solid #e2e8f0;border-radius:14px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc;flex-shrink:0}
.jd-hero__logo img{width:100%;height:100%;object-fit:contain}
.jd-hero__logo-fallback{font-weight:800;font-size:22px;color:#2563eb}
.jd-offer-box{margin-top:22px;border:2px dashed #2563eb;border-radius:14px;padding:20px;background:#eff6ff;text-align:center}
.jd-offer-box__message{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:15px;color:#1e3a8a}
.jd-offer-box__icon{width:22px;height:22px;border-radius:50%;background:#16a34a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px}
.jd-offer-box__code{display:flex;align-items:center;justify-content:center;gap:10px;margin:14px 0}
.jd-offer-box__code code{font-size:20px;font-weight:800;letter-spacing:2px;color:#1e3a8a;background:#dbeafe;border-radius:8px;padding:8px 16px}
.jd-offer-box__action{margin-top:4px}
.jd-meta{list-style:none;display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 0;padding:0}
.jd-meta__item{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#475569;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:6px 12px}
.jd-meta__verified svg{color:#16a34a}
.jd-meta__expiry svg{color:#f59e0b}
.jd-telegram{display:flex;align-items:center;gap:16px;margin:16px 0;background:linear-gradient(135deg,#0284c7,#0369a1);border-radius:14px;padding:18px 22px;color:#fff;text-decoration:none;box-shadow:0 2px 6px rgba(3,105,161,.25)}
.jd-telegram__text{flex:1;display:flex;flex-direction:column;gap:2px}
.jd-telegram__text strong{font-size:16px;letter-spacing:.5px}
.jd-telegram__text span{font-size:13px;opacity:.9}
.jd-terms{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin-top:16px}
.jd-section-title{font-size:18px;font-weight:700;color:#1e293b;margin:0 0 14px}
.jd-terms__list{margin:0 0 0 20px;padding:0;color:#475569;font-size:14px;line-height:1.8}
.jd-terms__list li{padding-left:4px}
.jd-discount-table{width:100%;border-collapse:collapse;margin-top:10px;font-size:14px}
.jd-discount-table th,.jd-discount-table td{border:1px solid #e2e8f0;padding:10px 14px;text-align:left}
.jd-discount-table th{background:#f8fafc;font-weight:700;color:#334155}
.jd-discount-table td:last-child{color:#dc2626;font-weight:700;text-align:right}
.jd-feedback{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin-top:16px;text-align:center}
.jd-feedback__actions{display:flex;gap:12px;justify-content:center}
.jd-feedback__btn{background:#fff;color:#1e293b;border:1px solid #e2e8f0}
.jd-feedback__btn:hover{border-color:#2563eb;color:#2563eb}
.jd-feedback__btn.is-active{background:#2563eb;color:#fff;border-color:#2563eb}
.jd-feedback__count{min-width:20px;text-align:center;font-weight:700}
.jd-related{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px}
.jd-sidebar-title{font-size:16px;font-weight:700;color:#1e293b;margin:0 0 16px}
.jd-related__list{display:flex;flex-direction:column;gap:10px}
.jd-related-card{display:flex;gap:12px;align-items:center;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-decoration:none;transition:box-shadow .15s,border-color .15s}
.jd-related-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.08);border-color:#bfdbfe}
.jd-related-card__logo{width:42px;height:42px;border-radius:10px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;text-align:center;overflow:hidden;flex-shrink:0}
.jd-related-card__body{display:flex;flex-direction:column;gap:2px;min-width:0}
.jd-related-card__title{font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.jd-related-card__discount{font-size:12px;color:#dc2626;font-weight:700}
.jd-source-cta{margin-top:16px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;text-align:center}
.jd-source-cta__icon{width:44px;height:44px;border-radius:12px;background:#f8fafc;display:inline-flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#4285f4;margin-bottom:10px}
.jd-source-cta__text{font-size:14px;font-weight:600;color:#334155;margin:0 0 14px}
.jd-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#0f172a;color:#fff;font-size:14px;padding:10px 18px;border-radius:10px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.jd-editor-placeholder{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;color:#64748b;font-size:14px;font-weight:600;padding:48px 24px;text-align:center}
@media (max-width:600px){
.jd-hero__top{flex-direction:column-reverse}
.jd-hero__logo{margin-left:auto}
.jd-offer-box__code{flex-direction:column}
.jd-feedback__actions{flex-direction:column}
}
CSS;
    }

    public static function getJs(): string
    {
        return <<<'JS'
(function(){
  function readData(){ try { return JSON.parse(document.getElementById('jankx-coupon-detail-data').textContent || '{}'); } catch(e){ return {}; } }
  function toast(msg){ var t=document.createElement('div'); t.className='jd-toast'; t.textContent=msg; document.body.appendChild(t); setTimeout(function(){ t.remove(); }, 1800); }
  var data = readData();
  var back = document.querySelector('.jd-nav__back');
  if (back) back.addEventListener('click', function(e){ e.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = window.location.origin; } });
  var share = document.querySelector('.jd-nav__share');
  if (share) share.addEventListener('click', function(){
    var payload = { title: document.title, url: window.location.href };
    if (navigator.share) { navigator.share(payload).catch(function(){}); }
    else if (navigator.clipboard) { navigator.clipboard.writeText(window.location.href).then(function(){ toast('Copied link'); }); }
  });
  document.querySelectorAll('.jd-copy-code').forEach(function(btn){
    btn.addEventListener('click', function(){ var code = btn.getAttribute('data-code') || ''; if (navigator.clipboard) { navigator.clipboard.writeText(code).then(function(){ toast('Copied: ' + code); }); } });
  });
  document.querySelectorAll('.jd-feedback__btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var answer = btn.getAttribute('data-answer');
      var body = new URLSearchParams(); body.append('action', data.action); body.append('nonce', data.nonce); body.append('post_id', data.postId); body.append('answer', answer);
      fetch(data.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res && res.success) {
            document.querySelectorAll('.jd-feedback__btn').forEach(function(b){ b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            document.querySelectorAll('.jd-feedback__btn').forEach(function(b){
              var a = b.getAttribute('data-answer'); var c = b.querySelector('.jd-feedback__count');
              if (c && res.data && typeof res.data[a] !== 'undefined') c.textContent = res.data[a];
            });
          }
        });
    });
  });
})();
JS;
    }
}