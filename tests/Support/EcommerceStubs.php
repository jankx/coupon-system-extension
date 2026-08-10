<?php
namespace Jankx\Extensions\Ecommerce\Cart;

/**
 * Stub Cart + CartItem classes so the coupon system's session key and cart
 * context are testable without loading the real base-ecommerce extension.
 */

if (!class_exists('CartItem')) {
    class CartItem
    {
        public $product_id = 0;
        public $quantity = 1;
        public $unit_price = 0.0;

        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }

        public function toArray(): array
        {
            return [
                'product_id' => (int) $this->product_id,
                'quantity'   => (int) $this->quantity,
                'unit_price' => (float) $this->unit_price,
            ];
        }
    }
}

if (!class_exists('Cart')) {
    class Cart
    {
        public static $key = 'guest';
        public static $subtotal = 0.0;
        public static $items = [];

        protected static $instance;

        public static function get_instance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function reset($key = 'guest', $subtotal = 0.0, array $items = []): void
        {
            self::$key = $key;
            self::$subtotal = (float) $subtotal;
            self::$items = $items;
            self::$instance = null;
        }

        public function getCartKey(): string
        {
            return self::$key;
        }

        public function getSubtotal(): float
        {
            return self::$subtotal;
        }

        public function getItems(): array
        {
            return self::$items;
        }

        public function toArray(): array
        {
            return [
                'key'      => $this->getCartKey(),
                'subtotal' => $this->getSubtotal(),
                'items'    => array_map(function ($item) {
                    return $item->toArray();
                }, $this->getItems()),
            ];
        }
    }
}
