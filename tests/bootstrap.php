<?php
/**
 * Coupon System Extension - PHPUnit bootstrap.
 *
 * Loads:
 *  1. The Composer autoloader (dev deps: phpunit, brain/monkey, mockery).
 *  2. A PSR-4 fallback autoloader for the extension src.
 *  3. A small in-memory WordPress post/meta store (Tests\Support\PostStore)
 *     so get_post/wp_insert_post/get_post_meta/update_post_meta work together.
 *  4. Minimal framework class stubs (WP_Post, WP_Query, WP_Error, WP_REST_*)
 *     and a stub Cart so the session/cart integrations are testable.
 *  5. Brain Monkey aliases for the WP functions used by the extension.
 */

use Brain\Monkey;

if (!defined('JANKX_COUPON_TEST_DIR')) {
    define('JANKX_COUPON_TEST_DIR', __DIR__);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// 1. Composer autoloader (dev dependencies + PSR-4 for src and tests).
$composerAutoload = __DIR__ . '/../libs/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// 2. PSR-4 fallback autoloader for this extension (covers the case where the
//    Composer autoloader is not regenerated yet).
spl_autoload_register(function ($class) {
    $prefix = 'Jankx\\Extensions\\CouponSystem\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 3. WordPress class stubs.
if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public $ID;
        public $post_type;
        public $post_title;
        public $post_excerpt;
        public $post_status;
        public $post_date;
        public $post_name;
        public $post_content;

        public function __construct($data = [])
        {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Query')) {
    /**
     * Minimal WP_Query that filters the in-memory PostStore. It understands
     * the meta_query shape used by the coupon system: single clauses and AND
     * groups with =, LIKE, EXISTS and NOT EXISTS comparisons, plus post_type,
     * post_status, post__not_in, fields (ids), posts_per_page and ordering by
     * post_date. Arrays stored in meta are compared against their PHP
     * serialized form, mirroring how WordPress stores arrays.
     */
    class WP_Query
    {
        public $posts = [];
        public $found_posts = 0;
        public $post_count = 0;
        public $request = '';

        protected $args = [];

        public function __construct($args = [])
        {
            $this->args = $args;

            $matched = [];
            foreach (\Jankx\Extensions\CouponSystem\Tests\Support\PostStore::all() as $id => $post) {
                if (!$this->matches($args, $id, $post)) {
                    continue;
                }
                $matched[$id] = $post;
            }

            $order = strtoupper($args['order'] ?? 'DESC');
            uasort($matched, function ($a, $b) use ($order) {
                $cmp = strcmp((string) $a->post_date, (string) $b->post_date);
                return $order === 'ASC' ? $cmp : -$cmp;
            });

            $this->found_posts = count($matched);

            $perPage = (int) ($args['posts_per_page'] ?? 10);
            if ($perPage > 0) {
                $matched = array_slice($matched, 0, $perPage, true);
            }

            if (($args['fields'] ?? '') === 'ids') {
                $this->posts = array_keys($matched);
            } else {
                $this->posts = array_values($matched);
            }
            $this->post_count = count($this->posts);
        }

        protected function matches(array $args, int $id, \WP_Post $post): bool
        {
            if (isset($args['post_type']) && $args['post_type'] !== 'any' && $post->post_type !== $args['post_type']) {
                return false;
            }

            $status = $args['post_status'] ?? 'publish';
            if (!empty($status) && $status !== 'any' && $post->post_status !== $status) {
                return false;
            }

            if (!empty($args['post__not_in']) && in_array($id, array_map('intval', (array) $args['post__not_in']), true)) {
                return false;
            }

            if (!empty($args['meta_query']) && !$this->matchesMetaQuery($args['meta_query'], $id)) {
                return false;
            }

            return true;
        }

        protected function matchesMetaQuery(array $clauses, int $id): bool
        {
            $relation = strtoupper($clauses['relation'] ?? 'AND');
            $results = [];

            foreach ($clauses as $key => $clause) {
                if ($key === 'relation' || !is_array($clause) || !isset($clause['key'])) {
                    continue;
                }

                $metaKey = $clause['key'];
                $value = $clause['value'] ?? '';
                $compare = strtoupper($clause['compare'] ?? '=');
                $stored = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::meta($id, $metaKey);
                $exists = $stored !== null;

                switch ($compare) {
                    case 'EXISTS':
                        $results[] = $exists;
                        break;
                    case 'NOT EXISTS':
                        $results[] = !$exists;
                        break;
                    case 'LIKE':
                        if (is_array($stored)) {
                            $stored = serialize($stored);
                        }
                        $results[] = is_scalar($stored) && strpos((string) $stored, (string) $value) !== false;
                        break;
                    default:
                        if (is_array($stored)) {
                            $stored = serialize($stored);
                        }
                        $results[] = $stored == $value;
                }
            }

            if ($relation === 'OR') {
                return in_array(true, $results, true);
            }

            return !in_array(false, $results, true);
        }

        public function have_posts()
        {
            return count($this->posts) > 0;
        }

        public function the_post()
        {
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        protected $errors = [];
        protected $codes = [];

        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code) {
                $this->errors[$code] = [$message];
                $this->codes[] = $code;
            }
        }

        public function get_error_code()
        {
            return $this->codes[0] ?? '';
        }

        public function get_error_message()
        {
            $code = $this->get_error_code();

            return $code ? ($this->errors[$code][0] ?? '') : '';
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'PUT, PATCH';
        const DELETABLE = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
        const METHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        protected $params = [];

        public function __construct($method = 'GET', $route = '')
        {
        }

        public function set_param($key, $value)
        {
            $this->params[$key] = $value;
        }

        public function get_param($key)
        {
            return $this->params[$key] ?? null;
        }

        public function get_params()
        {
            return $this->params;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        protected $data;
        protected $status;
        protected $headers;

        public function __construct($data = [], $status = 200, $headers = [])
        {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status()
        {
            return $this->status;
        }

        public function get_headers()
        {
            return $this->headers;
        }
    }
}

// 4. Stub Cart (base-ecommerce integration) so the coupon session key and
//    cart context are testable without loading the real ecommerce extension.
require_once __DIR__ . '/Support/EcommerceStubs.php';

// 5. Helper: Brain Monkey function stubs used across the coupon tests.
    if (!function_exists('coupon_test_stub_wp_functions')) {
        function coupon_test_stub_wp_functions()
        {
            $GLOBALS['__registered_filters'] = [];
            $GLOBALS['__registered_actions'] = [];
            $GLOBALS['__fired_actions'] = [];
            $GLOBALS['__routes'] = [];
            $GLOBALS['__transients'] = [];
            $GLOBALS['__current_user'] = 0;
            $GLOBALS['__users'] = [];
            $GLOBALS['__wp_rand'] = 0;
            $GLOBALS['__wp_insert_post_fail'] = false;

            \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::reset();

            Brain\Monkey\Functions\when('__')->returnArg();
            Brain\Monkey\Functions\when('_x')->returnArg();
            Brain\Monkey\Functions\when('esc_html__')->returnArg();
            Brain\Monkey\Functions\when('esc_html_x')->returnArg();

            Brain\Monkey\Functions\when('add_filter')->alias(function ($tag, $callback, $priority = 10, $accepted = 1) {
                $GLOBALS['__registered_filters'][] = [
                    'tag'      => $tag,
                    'callback' => $callback,
                    'priority' => $priority,
                    'accepted' => $accepted,
                ];

                return true;
            });

            Brain\Monkey\Functions\when('add_action')->alias(function ($tag, $callback, $priority = 10, $accepted = 1) {
                $GLOBALS['__registered_actions'][] = [
                    'tag'      => $tag,
                    'callback' => $callback,
                    'priority' => $priority,
                    'accepted' => $accepted,
                ];

                return true;
            });

            Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
                return $value;
            });

            Brain\Monkey\Functions\when('do_action')->alias(function ($tag, ...$args) {
                $GLOBALS['__fired_actions'][] = ['tag' => $tag, 'args' => $args];

                return null;
            });

            Brain\Monkey\Functions\when('register_rest_route')->alias(function ($namespace, $route, $args = []) {
                $GLOBALS['__routes'][] = ['namespace' => $namespace, 'route' => $route, 'args' => $args];

                return true;
            });

            Brain\Monkey\Functions\when('rest_ensure_response')->alias(function ($response) {
                if ($response instanceof \WP_REST_Response) {
                    return $response;
                }

                return new \WP_REST_Response($response, 200);
            });

            Brain\Monkey\Functions\when('sanitize_text_field')->alias(function ($value) {
                return trim((string) $value);
            });

            Brain\Monkey\Functions\when('sanitize_key')->alias(function ($key) {
                $key = strtolower((string) $key);

                return preg_replace('/[^a-z0-9_\-]/', '', $key);
            });

            Brain\Monkey\Functions\when('wp_rand')->alias(function ($min = 0, $max = 0) {
                return (int) ($GLOBALS['__wp_rand'] ?? 0);
            });

            Brain\Monkey\Functions\when('get_current_user_id')->alias(function () {
                return (int) ($GLOBALS['__current_user'] ?? 0);
            });

            Brain\Monkey\Functions\when('is_user_logged_in')->alias(function () {
                return (int) ($GLOBALS['__current_user'] ?? 0) > 0;
            });

            Brain\Monkey\Functions\when('get_userdata')->alias(function ($id) {
                return $GLOBALS['__users'][$id] ?? null;
            });

            Brain\Monkey\Functions\when('current_time')->alias(function ($type) {
                return '2026-08-10 12:00:00';
            });

            Brain\Monkey\Functions\when('wp_date')->alias(function ($format, $timestamp = null) {
                return date($format, $timestamp ? (int) $timestamp : time());
            });

            Brain\Monkey\Functions\when('get_post')->alias(function ($id = null) {
                return \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::get((int) $id);
            });

            Brain\Monkey\Functions\when('get_post_meta')->alias(function ($id, $key, $single = false) {
                $value = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::meta((int) $id, $key);

                if ($value === null) {
                    return $single ? '' : [];
                }

                return $single ? $value : [$value];
            });

            Brain\Monkey\Functions\when('update_post_meta')->alias(function ($id, $key, $value) {
                \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::updateMeta((int) $id, $key, $value);

                return true;
            });

            Brain\Monkey\Functions\when('get_the_title')->alias(function ($id) {
                $post = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::get((int) $id);

                return $post ? (string) $post->post_title : '';
            });

            Brain\Monkey\Functions\when('get_post_type')->alias(function ($id) {
                $post = \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::get((int) $id);

                return $post ? (string) $post->post_type : false;
            });

            Brain\Monkey\Functions\when('wp_insert_post')->alias(function ($data, $wpError = false) {
                if (!empty($GLOBALS['__wp_insert_post_fail'])) {
                    return new \WP_Error('db_insert_error', 'Could not insert post');
                }

                return \Jankx\Extensions\CouponSystem\Tests\Support\PostStore::insert($data);
            });

            Brain\Monkey\Functions\when('is_wp_error')->alias(function ($thing) {
                return $thing instanceof \WP_Error;
            });

            Brain\Monkey\Functions\when('get_transient')->alias(function ($key) {
                return $GLOBALS['__transients'][$key] ?? false;
            });

            Brain\Monkey\Functions\when('set_transient')->alias(function ($key, $value, $expiration = 0) {
                $GLOBALS['__transients'][$key] = $value;

                return true;
            });

            Brain\Monkey\Functions\when('delete_transient')->alias(function ($key) {
                unset($GLOBALS['__transients'][$key]);

                return true;
            });

            Brain\Monkey\Functions\when('home_url')->justReturn('https://example.com');
        }
    }
