# Database Design & Architecture (`docs/DATABASE.md`)

This document outlines the relational schema, constraints, data types, and portability rules for the **wex-api** database.

---

## 1. Portability Rules (SQLite & MySQL 8+)
- **CI4 Forge & Query Builder only:** All tables, indexes, and foreign keys are created through CodeIgniter 4 Forge migrations.
- **Foreign Keys Enabled:** Configured with `foreignKeys = true` for SQLite3 driver.
- **No ENUM columns:** Statuses and types use `VARCHAR(20..50)` backed by application constants in `Config\Wex`.
- **Booleans:** Represented as `TINYINT(1)` with values `0` and `1`, cast to `bool` in Entities.
- **Integers for Money:** All currencies are whole integers:
  - `price_aed`: whole UAE dirhams.
  - `rate_sdg_snapshot`: whole SDG per 1 AED.
  - `total_sdg`, `subtotal_sdg`, `discount_sdg`, `delivery_fee_sdg`, `fee_sdg`: whole Sudanese pounds.
- **JSON Fields:** Stored as `TEXT` and cast to associative arrays via Entity getters and setters (`specs`, `payload`, `details`).
- **Timestamps:** All stored in UTC format (`DATETIME`), output in ISO 8601 (`YYYY-MM-DDTHH:MM:SSZ`).

---

## 2. Table Relationships (ASCII Diagram)

```
       +-------------------+
       |       users       |
       +---------+---------+
                 |
        +--------+--------+--------+--------+
        |                 |        |        |
        v                 v        v        v
 +---------------+ +-------------+ | +-------------+
 |refresh_tokens | |notifications| | |exchange_rates|
 +---------------+ +-------------+ | +-------------+
                                   |
                                   v
                             +-----------+
                             |   orders  |<------+
                             +-----+-----+       |
                                   |             |
                 +-----------------+-------+     |
                 |                 |       |     |
                 v                 v       v     |
          +-------------+   +------------+ |     |
          | order_items |   |order_events| |     |
          +------+------+   +------------+ |     |
                 |                         |     |
                 |          +--------------+     |
                 |          v                    |
                 |   +--------------+            |
                 |   |payment_proofs|            |
                 |   +--------------+            |
                 |                               |
                 v                               |
          +-------------+                        |
          |  products   |                        |
          +------+------+                        |
                 |                               |
        +--------+--------+                      |
        |                 |                      |
        v                 v                      |
 +--------------+  +-------------------+         |
 |product_images|  |product_accessories|         |
 +--------------+  +-------------------+         |
                                                 |
 +--------------+                                |
 |shipping_zones+--------------------------------+
 +--------------+
```

---

## 3. Tables Specification

### `users`
| Column | Type | Nullable | Description / Default |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| name | VARCHAR(150) | No | Customer or Staff name |
| email | VARCHAR(191) | No | Unique login email |
| phone | VARCHAR(30) | Yes | Sudan normalized (+249...) |
| google_id | VARCHAR(191) | Yes | Unique Google OAuth Sub |
| avatar_url | VARCHAR(255) | Yes | Google profile picture URL |
| role | VARCHAR(20) | No | `customer`, `staff`, `admin` |
| email_verified_at | DATETIME | Yes | Verification timestamp |
| email_notifications | TINYINT(1) | No | Default 1 |
| is_active | TINYINT(1) | No | Default 1 |
| last_login_at | DATETIME | Yes | UTC timestamp |
| created_at | DATETIME | Yes | UTC timestamp |
| updated_at | DATETIME | Yes | UTC timestamp |
| deleted_at | DATETIME | Yes | Soft delete timestamp |

### `refresh_tokens`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| user_id | INT(11) UNSIGNED | No | FK -> users.id (CASCADE) |
| family_id | VARCHAR(64) | No | Token rotation family |
| token_hash | VARCHAR(64) | No | Unique SHA256 of token |
| user_agent | VARCHAR(255) | Yes | Client user agent |
| ip | VARCHAR(45) | Yes | Client IP address |
| expires_at | DATETIME | No | 30 days expiration |
| revoked_at | DATETIME | Yes | Revocation timestamp |
| replaced_by_id | INT(11) UNSIGNED | Yes | Replaced token pointer |
| created_at | DATETIME | Yes | UTC timestamp |

### `otp_codes`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| email | VARCHAR(191) | No | Indexed recipient email |
| code_hash | VARCHAR(64) | No | HMAC-SHA256 hash |
| attempts | INT(5) | No | Wrong attempt count (max 5) |
| expires_at | DATETIME | No | 10 minute expiry |
| consumed_at | DATETIME | Yes | Timestamp when used |
| ip | VARCHAR(45) | Yes | Requesting IP |
| created_at | DATETIME | Yes | UTC timestamp |

### `categories`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| name_ar | VARCHAR(100) | No | Arabic name (لابتوبات) |
| name_en | VARCHAR(100) | Yes | English name (Laptops) |
| slug | VARCHAR(120) | No | Unique slug |
| sort_order | INT(5) | No | Default 0 |
| is_active | TINYINT(1) | No | Default 1 |
| created_at | DATETIME | Yes | UTC timestamp |
| updated_at | DATETIME | Yes | UTC timestamp |

### `brands`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| name | VARCHAR(100) | No | Brand name (Dell, HP, etc.) |
| slug | VARCHAR(120) | No | Unique slug |
| is_active | TINYINT(1) | No | Default 1 |
| created_at | DATETIME | Yes | UTC timestamp |
| updated_at | DATETIME | Yes | UTC timestamp |

### `products`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| type | VARCHAR(30) | No | `laptop`, `phone`, `accessory` |
| category_id | INT(11) UNSIGNED | No | FK -> categories.id (RESTRICT) |
| brand_id | INT(11) UNSIGNED | Yes | FK -> brands.id (SET NULL) |
| name | VARCHAR(191) | No | Product title |
| slug | VARCHAR(191) | No | Unique URL slug |
| short_description | VARCHAR(255) | Yes | Summary subtitle |
| description | TEXT | Yes | Full plain Arabic description |
| condition | VARCHAR(30) | No | `new`, `used_imported` |
| price_aed | INT(11) UNSIGNED | No | Integer Dirham price |
| specs | TEXT | Yes | JSON specs dictionary |
| warranty_text | VARCHAR(255) | Yes | Warranty details |
| stock_quantity | INT(11) | No | Available units |
| is_active | TINYINT(1) | No | Default 1 |
| is_featured | TINYINT(1) | No | Default 0 |
| created_at | DATETIME | Yes | UTC timestamp |
| updated_at | DATETIME | Yes | UTC timestamp |
| deleted_at | DATETIME | Yes | Soft delete timestamp |

### `product_images`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| product_id | INT(11) UNSIGNED | No | FK -> products.id (CASCADE) |
| path | VARCHAR(255) | No | Relative path (uploads/products/...) |
| thumb_path | VARCHAR(255) | Yes | 400px thumbnail path |
| sort_order | INT(5) | No | Display sorting order |
| is_primary | TINYINT(1) | No | 1 if main cover photo |
| created_at | DATETIME | Yes | UTC timestamp |

### `product_accessories`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| product_id | INT(11) UNSIGNED | No | FK -> products.id (laptop/phone) |
| accessory_id | INT(11) UNSIGNED | No | FK -> products.id (accessory) |
| sort_order | INT(5) | No | Sorting order |

### `shipping_zones`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| state_name_ar | VARCHAR(100) | No | Unique Sudan state name |
| fee_sdg | INT(11) UNSIGNED | No | Delivery fee in whole SDG |
| is_active | TINYINT(1) | No | Default 1 |
| created_at | DATETIME | Yes | UTC timestamp |
| updated_at | DATETIME | Yes | UTC timestamp |

### `orders`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| order_number | VARCHAR(30) | No | Unique code (e.g. WX-1042) |
| user_id | INT(11) UNSIGNED | No | FK -> users.id (RESTRICT) |
| status | VARCHAR(30) | No | OrderStateMachine status |
| delivery_type | VARCHAR(20) | No | `pickup` or `delivery` |
| shipping_zone_id | INT(11) UNSIGNED | Yes | FK -> shipping_zones.id (SET NULL) |
| delivery_address | TEXT | Yes | Full physical delivery address |
| delivery_phone | VARCHAR(30) | No | Contact phone for courier |
| customer_note | TEXT | Yes | Customer remarks |
| rate_sdg_snapshot | INT(11) UNSIGNED | No | Exchange rate locked for order |
| app_discount_percent_snapshot | INT(5) UNSIGNED | No | Discount percentage locked |
| subtotal_aed | INT(11) UNSIGNED | No | Items sum in AED |
| subtotal_sdg | INT(11) UNSIGNED | No | Subtotal converted to SDG |
| discount_sdg | INT(11) UNSIGNED | No | App discount amount in SDG |
| delivery_fee_sdg | INT(11) UNSIGNED | No | Shipping fee in SDG |
| total_sdg | INT(11) UNSIGNED | No | Rounded grand total in SDG |
| lock_expires_at | DATETIME | No | Expiration of frozen SDG price |
| courier_name | VARCHAR(100) | Yes | Dispatch courier name |
| courier_phone | VARCHAR(30) | Yes | Courier contact number |
| cancelled_reason | VARCHAR(255) | Yes | Reason if cancelled |
| created_at | DATETIME | Yes | UTC timestamp |
| updated_at | DATETIME | Yes | UTC timestamp |

### `order_items`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| order_id | INT(11) UNSIGNED | No | FK -> orders.id (CASCADE) |
| product_id | INT(11) UNSIGNED | No | FK -> products.id (RESTRICT) |
| product_name_snapshot | VARCHAR(191) | No | Name at purchase |
| product_type_snapshot | VARCHAR(30) | No | Type at purchase |
| unit_price_aed | INT(11) UNSIGNED | No | Integer AED unit price (Admin only) |
| quantity | INT(5) UNSIGNED | No | Quantity ordered |
| line_total_aed | INT(11) UNSIGNED | No | unit_price_aed * quantity (Admin only) |
| unit_price_sdg | INT(11) UNSIGNED | No | Snapshotted unit price in SDG (Customer visible) |
| line_total_sdg | INT(11) UNSIGNED | No | unit_price_sdg * quantity (Customer visible) |

### `order_events`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| order_id | INT(11) UNSIGNED | No | FK -> orders.id (CASCADE) |
| from_status | VARCHAR(30) | Yes | Previous status |
| to_status | VARCHAR(30) | No | New status |
| actor_user_id | INT(11) UNSIGNED | Yes | FK -> users.id (NULL = system) |
| note | TEXT | Yes | Note visible to customer |
| created_at | DATETIME | Yes | UTC timestamp |

### `payment_proofs`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| order_id | INT(11) UNSIGNED | No | FK -> orders.id (CASCADE) |
| file_path | VARCHAR(255) | No | Secure path (writable/uploads/proofs/...) |
| sender_name | VARCHAR(150) | Yes | Name of sender in Bankak |
| reference_number | VARCHAR(100) | Yes | Bank transaction reference |
| status | VARCHAR(30) | No | `pending`, `approved`, `rejected` |
| review_note | TEXT | Yes | Rejection reason or staff note |
| reviewed_by | INT(11) UNSIGNED | Yes | FK -> users.id (SET NULL) |
| reviewed_at | DATETIME | Yes | UTC review timestamp |
| created_at | DATETIME | Yes | Upload timestamp |

### `notifications`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| user_id | INT(11) UNSIGNED | No | FK -> users.id (CASCADE) |
| type | VARCHAR(50) | No | Notification type identifier |
| title | VARCHAR(191) | No | Arabic title |
| body | TEXT | No | Arabic body text |
| order_id | INT(11) UNSIGNED | Yes | FK -> orders.id (SET NULL) |
| read_at | DATETIME | Yes | Read timestamp |
| created_at | DATETIME | Yes | Dispatch timestamp |

### `email_outbox`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| user_id | INT(11) UNSIGNED | Yes | FK -> users.id (SET NULL) |
| to_email | VARCHAR(191) | No | Destination email |
| subject | VARCHAR(255) | No | Email subject line |
| template | VARCHAR(100) | No | Template identifier in Views/emails |
| payload | TEXT | Yes | JSON template parameters |
| status | VARCHAR(20) | No | `pending`, `sent`, `failed` |
| attempts | INT(5) | No | Delivery attempts made (max 5) |
| last_error | TEXT | Yes | Last error message if failed |
| available_at | DATETIME | Yes | Backoff delay timestamp |
| sent_at | DATETIME | Yes | Success timestamp |
| created_at | DATETIME | Yes | Creation timestamp |

### `settings`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| key | VARCHAR(100) | No | Unique setting key |
| value | TEXT | Yes | Setting string/integer value |
| updated_at | DATETIME | Yes | UTC timestamp |

### `exchange_rates`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| rate_sdg | INT(11) UNSIGNED | No | SDG value per 1 AED |
| changed_by | INT(11) UNSIGNED | Yes | FK -> users.id (SET NULL) |
| created_at | DATETIME | Yes | Audit timestamp |

### `audit_logs`
| Column | Type | Nullable | Description |
|---|---|---|---|
| id | INT(11) UNSIGNED AUTO_INCREMENT | No | Primary Key |
| user_id | INT(11) UNSIGNED | Yes | FK -> users.id (SET NULL) |
| action | VARCHAR(50) | No | Action type (rate_change, etc.) |
| entity | VARCHAR(50) | No | Target entity (order, setting, etc.) |
| entity_id | INT(11) UNSIGNED | Yes | Target entity ID |
| details | TEXT | Yes | JSON representation of changes |
| ip | VARCHAR(45) | Yes | Actor IP address |
| created_at | DATETIME | Yes | Audit timestamp |
