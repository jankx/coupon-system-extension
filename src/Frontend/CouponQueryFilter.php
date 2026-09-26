<?php
namespace Jankx\Extensions\CouponSystem\Frontend;

use Jankx\Extensions\CouponSystem\Coupon;
use Jankx\Extensions\CouponSystem\PostTypes\CouponPostType;

class CouponQueryFilter
{
    protected ?array $expiredMasterIds = null;

    public function register(): void
    {
        add_filter('jankx/post-layout/query-args', [$this, 'excludeExpiredCoupons'], 10, 2);
    }

    public function excludeExpiredCoupons(array $args, array $attributes = []): array
    {
        if (!$this->queriesCoupons($args)) {
            return $args;
        }

        $now = time();

        $clauses = [
            $this->getLiveExpiryClause($now),
            $this->getLiveValidFromClause($now),
            $this->getLiveStatusClause(),
        ];

        $expiredMasterIds = $this->getExpiredMasterIds();
        if (!empty($expiredMasterIds)) {
            $clauses[] = [
                'relation' => 'OR',
                [
                    'key'     => Coupon::META_PREFIX . 'master_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => Coupon::META_PREFIX . 'master_id',
                    'value'   => $expiredMasterIds,
                    'compare' => 'NOT IN',
                ],
            ];
        }

        $existing = $args['meta_query'] ?? [];

        $metaQuery = ['relation' => 'AND'];
        if (is_array($existing) && !empty($existing)) {
            $metaQuery[] = $existing;
        }
        foreach ($clauses as $clause) {
            $metaQuery[] = $clause;
        }

        $args['meta_query'] = $metaQuery;

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

    protected function getLiveExpiryClause(int $now): array
    {
        return [
            'relation' => 'OR',
            [
                'key'     => Coupon::META_PREFIX . 'expiry',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => Coupon::META_PREFIX . 'expiry',
                'value'   => 0,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => Coupon::META_PREFIX . 'expiry',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ],
        ];
    }

    protected function getLiveValidFromClause(int $now): array
    {
        return [
            'relation' => 'OR',
            [
                'key'     => Coupon::META_PREFIX . 'valid_from',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => Coupon::META_PREFIX . 'valid_from',
                'value'   => 0,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => Coupon::META_PREFIX . 'valid_from',
                'value'   => $now,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ],
        ];
    }

    protected function getLiveStatusClause(): array
    {
        return [
            'relation' => 'OR',
            [
                'key'     => Coupon::META_PREFIX . 'status',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => Coupon::META_PREFIX . 'status',
                'value'   => Coupon::STATUS_EXPIRED,
                'compare' => '!=',
            ],
        ];
    }

    protected function getExpiredMasterIds(): array
    {
        if ($this->expiredMasterIds !== null) {
            return $this->expiredMasterIds;
        }

        $now = time();

        $this->expiredMasterIds = array_map('intval', (array) get_posts([
            'post_type'      => CouponPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'none',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => Coupon::META_PREFIX . 'master_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'relation' => 'OR',
                    [
                        'relation' => 'AND',
                        [
                            'key'     => Coupon::META_PREFIX . 'expiry',
                            'value'   => 0,
                            'compare' => '>',
                            'type'    => 'NUMERIC',
                        ],
                        [
                            'key'     => Coupon::META_PREFIX . 'expiry',
                            'value'   => $now,
                            'compare' => '<',
                            'type'    => 'NUMERIC',
                        ],
                    ],
                    [
                        'key'     => Coupon::META_PREFIX . 'valid_from',
                        'value'   => $now,
                        'compare' => '>',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => Coupon::META_PREFIX . 'status',
                        'value'   => Coupon::STATUS_EXPIRED,
                        'compare' => '=',
                    ],
                ],
            ],
        ]));

        return $this->expiredMasterIds;
    }
}
