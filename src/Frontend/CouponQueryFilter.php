<?php
namespace Jankx\Extensions\CouponSystem\Frontend;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;

class CouponQueryFilter
{
    protected ?array $liveCouponIds = null;

    public function register(): void
    {
        add_filter('jankx/post-layout/query-args', [$this, 'excludeExpiredCoupons'], 10, 2);
    }

    public function excludeExpiredCoupons(array $args, array $attributes = []): array
    {
        if (!$this->queriesCoupons($args)) {
            return $args;
        }

        $liveIds = $this->getLiveCouponIds();

        $requested = array_filter(array_map('intval', (array) ($args['post__in'] ?? [])));
        if (!empty($requested)) {
            $liveIds = array_values(array_intersect($requested, $liveIds));
        }

        $args['post__in'] = !empty($liveIds) ? $liveIds : [0];

        return $args;
    }

    protected function queriesCoupons(array $args): bool
    {
        $postType = $args['post_type'] ?? null;

        if (is_array($postType)) {
            return in_array(CouponPostType::POST_TYPE, $postType, true);
        }

        return $postType === CouponPostType::POST_TYPE;
    }

    /**
     * Coupons whose effective status is active (dates, limits, master/slave
     * rules resolved by the model), evaluated in PHP because the meta keys
     * exist in two variants (_coupon_x and the legacy _coupon_coupon_x).
     */
    protected function getLiveCouponIds(): array
    {
        if ($this->liveCouponIds !== null) {
            return $this->liveCouponIds;
        }

        $posts = get_posts([
            'post_type'              => CouponPostType::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'update_post_meta_cache' => true,
        ]);

        $liveIds = [];
        foreach ($posts as $post) {
            $coupon = new Coupon($post);
            if ($coupon->getEffectiveStatus() === Coupon::STATUS_ACTIVE) {
                $liveIds[] = (int) $post->ID;
            }
        }

        return $this->liveCouponIds = $liveIds;
    }
}
