# Coupon System Extension

Hệ thống mã giảm giá cho website Nobitour (Jankx). Cho phép quản trị viên tạo
**mã master** (toàn hệ thống) dùng chung cho mọi người dùng, người dùng **thu thập**
từ mục tài khoản để nhận **mã slave** cá nhân, và các extension khác tạo mã tự động
thông qua API công khai.

## Kiến trúc

Một mã giảm giá có hai dạng, cùng lưu trong CPT `jankx_coupon`:

| Dạng | Mô tả | Phân biệt bằng |
|------|-------|-----------------|
| **Master** | Mã gốc do admin tạo, chứa toàn bộ quy tắc (số tiền, thời hạn, giới hạn, phạm vi) | Không có meta `_coupon_master_id` |
| **Slave** | Bản sao cá nhân người dùng thu thập được | Có meta `_coupon_master_id` |

Mọi quy tắc đều được **validate qua master**. Nếu master hết hạn / hết lượt / tạm
dừng thì toàn bộ slave sao từ nó tự động vô hiệu. Khi một slave được dùng, bộ đếm
`_coupon_used_count` và danh sách `_coupon_user_usage` của master cũng tăng lên.

Trạng thái:

- Master: `active`, `paused`, `expired`, `exhausted` (`expired`/`exhausted` được
  tính tự động tại thời điểm đọc).
- Slave: `active`, `used`, `expired`, `invalid`.

### Thư mục

```
coupon-system/
├── CouponSystemExtension.php   # Đăng ký hooks, sub-page, REST, assets
├── src/
│   ├── Coupon.php              # Model: quy tắc, validate, discount, collect, markUsed
│   ├── CouponManager.php       # CRUD, lookup, session "coupon đã áp dụng", API công khai
│   ├── PostTypes/CouponPostType.php      # CPT + cột/filter admin
│   ├── Meta/CouponMetaBoxes.php          # Meta box cấu hình mã giảm giá
│   ├── Admin/SettingsPage.php            # Trang cài đặt chung
│   ├── Blocks/AccountTabCouponsBlock.php # Tab "Mã giảm giá" trong my-account
│   ├── Rest/CouponController.php         # REST API cho frontend
│   └── Integration/CheckoutIntegration.php # Bridge vào cart/checkout
├── assets/                      # admin.css/js + account-coupons.css/js
└── blocks/block.json            # Block jankx/account-tab-coupons
```

## Các meta quan trọng (tiền tố `_coupon_`)

| Meta | Master | Slave | Ý nghĩa |
|------|--------|-------|---------|
| `code` | ✔ | ✔ | Mã code duy nhất (viết hoa) |
| `type` | ✔ | – | `percent` hoặc `fixed` |
| `amount` | ✔ | – | Giá trị giảm (đ/vnd hoặc %) |
| `min_order` | ✔ | – | Đơn hàng tối thiểu (0 = không giới hạn) |
| `max_discount` | ✔ | – | Trần giảm cho mã phần trăm (0 = không giới hạn) |
| `valid_from` / `expiry` | ✔ | – | Timestamp hiệu lực / hết hạn |
| `max_uses` / `used_count` | ✔ | – | Tổng lượt dùng tối đa / đã dùng |
| `per_user_limit` | ✔ | – | Giới hạn lượt dùng + số lần thu thập mỗi người |
| `is_global` | ✔ | – | 1 = ai cũng dùng được |
| `is_collectable` | ✔ | – | 1 = cho phép thu thập từ my-account |
| `applies_to` / `apply_values` | ✔ | – | `all` / `product_type` / `product` |
| `user_ids` / `roles` | ✔ | – | Giới hạn người dùng / vai trò được dùng |
| `status` | ✔ | ✔ | Trạng thái thủ công (active/paused) |
| `origin` / `source` | ✔ | ✔ | Ngữ cảnh + nguồn tạo mã (admin, birthday, event...) |
| `master_id` / `user_id` | – | ✔ | Mã gốc / người sở hữu |
| `used_at` / `order_id` | – | ✔ | Thời điểm + đơn hàng đã dùng |

## REST API

Namespace: `jankx/coupon/v1` — tất cả yêu cầu cần cookie auth + header
`X-WP-Nonce: <wp_rest nonce>`.

| Route | Method | Mô tả |
|-------|--------|-------|
| `/coupons` | GET | Nhóm mã của người dùng hiện tại: `collectable`, `mine`, `used`, `unused` |
| `/coupons/{id}/collect` | POST | Thu thập master thành slave cá nhân |
| `/cart/apply` | POST | Áp dụng mã: body `{ "code": "ABC123" }` |
| `/cart/remove` | POST | Gỡ mã đã áp dụng |

## Tích hợp cart / checkout

- Giảm giá được áp dụng qua filter `jankx/ecommerce/cart/discount` (do base-ecommerce
  gọi trong `Cart::getDiscount()`), nên tổng đơn tự giảm.
- Mã đã áp dụng lưu trong transient theo cart key (`jankx_coupon_applied_{cartKey}`).
- Trước khi tạo đơn (`jankx/ecommerce/checkout/validate_customer`) mã được validate lại
  lần cuối; nếu không còn hợp lệ thì checkout báo lỗi, không tạo đơn.
- Khi đơn hoàn tất (`jankx/ecommerce/checkout/completed`) mã được `markUsed()`:
  slave → `used` + `used_at` + `order_id`; master → tăng `used_count` + `user_usage`.
- Block `jankx/cart` hiển thị ô nhập mã giảm giá (chỉ khi extension này hoạt động).

## Tạo mã từ extension khác (API cho bên thứ 3)

Extension khác (mua hàng, sự kiện, sinh nhật...) tạo mã tự động qua `CouponManager`.

```php
use Jankx\Extensions\CouponSystem\CouponManager;
use Jankx\Extensions\CouponSystem\Coupon;

// 1) Tạo master — trả về post ID hoặc WP_Error.
$masterId = CouponManager::get_instance()->create([
    'title'          => 'Khuyến mãi sinh nhật',
    'description'    => 'Giảm 10% cho đơn từ 500.000đ.',
    'type'           => Coupon::TYPE_PERCENT,   // hoặc Coupon::TYPE_FIXED
    'amount'         => 10,
    'min_order'      => 500000,
    'max_discount'   => 200000,
    'expiry'         => date('Y-m-d', strtotime('+30 days')),
    'max_uses'       => 100,
    'per_user_limit' => 1,
    'is_collectable' => true,     // người dùng có thể nhận từ my-account
    'is_global'      => true,     // mọi người đều dùng được
    'applies_to'     => 'all',    // all | product_type | product
    'apply_values'   => [],       // slug loại sản phẩm hoặc product ID
    'origin'         => 'birthday',
    'source'         => 'birthday-campaign',
]);

// 2) Tạo thẳng cho một người dùng (không cần bước thu thập) — trả về Coupon|null.
$coupon = CouponManager::get_instance()->createForUser($userId, [
    'title'       => 'Mã tri ân',
    'description' => 'Giảm 100.000đ cho lần mua tới.',
    'type'        => Coupon::TYPE_FIXED,
    'amount'      => 100000,
    'expiry'      => date('Y-m-d', strtotime('+60 days')),
    'origin'      => 'purchase',
    'source'      => 'order-reward',
]);
```

Lưu ý:
- `code` để trống → tự sinh code duy nhất. Nếu truyền `code` trùng → trả về
  `WP_Error` `duplicate_code`.
- `createForUser` tự đặt `is_collectable=false`, `is_global=false` và thêm người dùng
  vào `user_ids`; mã sẽ xuất hiện trong tab "Của tôi" của người đó.
- Hooks: `jankx/coupon/created`, `jankx/coupon/collected`, `jankx/coupon/applied`,
  `jankx/coupon/removed`, `jankx/coupon/used`.

## My Account — tab "Mã giảm giá"

Tab gồm 4 nhóm:

- **Thu thập** — master collectable còn hiệu lực và chưa đạt `per_user_limit`.
- **Của tôi** — slave + mã trực tiếp đang dùng được.
- **Đã sử dụng** — slave có trạng thái `used`.
- **Không sử dụng** — mã đã thu thập nhưng không dùng (hết hạn, master hết lượt/tạm
  dừng) để tra cứu lại sau.

## Admin

- CPT `jankx_coupon` (menu "Mã giảm giá"): cột Mã / Tiêu đề / Loại / Trạng thái /
  Đã dùng / Chủ sở hữu / Hết hạn; bộ lọc trạng thái + master/slave.
- Meta box "Cấu hình mã giảm giá" ở màn hình sửa: đủ mọi quy tắc. Với slave chỉ cho
  đổi trạng thái, không đổi được số tiền/giới hạn.
- Trang cài đặt `jankx-coupon-settings` (submenu "Mã giảm giá" của Jankx Theme
  Options): bật/tắt hệ thống, giới hạn mặc định, tự sinh mã.
