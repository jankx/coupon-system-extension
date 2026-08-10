<?php
namespace Jankx\Extensions\CouponSystem\Tests\Support;

/**
 * In-memory WordPress post/meta store used by the test bootstrap.
 *
 * get_post / wp_insert_post / get_post_meta / update_post_meta are aliased to
 * this store so the coupon model and manager can be tested against a small
 * deterministic "database" instead of a live WordPress install.
 */
class PostStore
{
    /** @var \WP_Post[] id => WP_Post */
    public static $posts = [];

    /** @var array id => [ meta_key => value ] */
    public static $meta = [];

    /** @var int */
    public static $nextId = 1;

    public static function reset(): void
    {
        self::$posts = [];
        self::$meta = [];
        self::$nextId = 1;
    }

    /**
     * @param array $data post fields, may include a `meta_input` array.
     * @return int
     */
    public static function insert(array $data): int
    {
        $id = self::$nextId++;

        $post = new \WP_Post([
            'ID'          => $id,
            'post_type'   => $data['post_type'] ?? 'post',
            'post_status' => $data['post_status'] ?? 'publish',
            'post_title'  => $data['post_title'] ?? '',
            'post_excerpt' => $data['post_excerpt'] ?? '',
            'post_date'   => $data['post_date'] ?? '2026-08-10 00:00:00',
            'post_name'   => $data['post_name'] ?? '',
        ]);

        self::$posts[$id] = $post;

        if (!empty($data['meta_input']) && is_array($data['meta_input'])) {
            foreach ($data['meta_input'] as $key => $value) {
                self::$meta[$id][$key] = $value;
            }
        }

        return $id;
    }

    public static function get(int $id): ?\WP_Post
    {
        return self::$posts[$id] ?? null;
    }

    public static function meta(int $id, string $key)
    {
        return self::$meta[$id][$key] ?? null;
    }

    public static function updateMeta(int $id, string $key, $value): void
    {
        self::$meta[$id][$key] = $value;
    }

    /**
     * @return \WP_Post[]
     */
    public static function all(): array
    {
        return self::$posts;
    }
}
