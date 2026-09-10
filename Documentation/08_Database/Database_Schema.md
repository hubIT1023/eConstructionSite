# Comprehensive Database Schema (All 40 Tables)

```text
Status: Verified
Last Verified: 2026-09-08
Source: PostgreSQL 15 DDL & Information Schema
Owner: eConstruction Supply Development Team
```

## 1. Multi-Tenant Core & Staff Tables

### `tbl_supplier` (Tenant Stores)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Unique tenant store identifier |
| `supplier_name` | `varchar(255)` | NO | | | Business trading name |
| `email` | `varchar(255)` | NO | | | Store contact email |
| `phone` | `varchar(50)` | YES | | | Store telephone number |
| `address` | `text` | YES | | | Physical depot address |
| `commission_percentage`| `numeric(5,2)` | YES | 0.00 | | Platform SaaS commission rate |
| `discount_ceiling_percentage`| `numeric(5,2)` | YES | 20.00 | | Maximum authorized store discount ceiling |
| `status` | `smallint` | YES | 1 | | 1 = Active, 0 = Inactive |
| `created_at` | `timestamp` | YES | CURRENT_TIMESTAMP | | Creation timestamp |

### `tbl_supplier_users` (Tenant Staff & RBAC)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Staff user ID |
| `supplier_id` | `integer` | NO | | **FK** | Links to `tbl_supplier.id` |
| `full_name` | `varchar(255)` | NO | | | Employee full name |
| `email` | `varchar(255)` | NO | | | Login email address |
| `phone` | `varchar(50)` | YES | | | Contact phone |
| `password` | `varchar(255)` | NO | | | Password hash (`MD5` legacy) |
| `role` | `varchar(50)` | NO | 'Staff' | | `Admin`, `Manager`, `POS Cashier`, `Store Incharge` |
| `status` | `smallint` | YES | 1 | | 1 = Active, 0 = Suspended |
| `created_at` | `timestamp` | YES | CURRENT_TIMESTAMP | | Registration timestamp |

---

## 2. Product Catalog & Engineering Specifications

### `tbl_product` (Main Product Catalog)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `p_id` | `serial` | NO | nextval() | **PK** | Unique product ID |
| `p_name` | `varchar(255)` | NO | | | Product commercial name |
| `p_old_price` | `numeric(10,2)`| YES | | | List / RRP price |
| `p_current_price`| `numeric(10,2)`| NO | | | Active retail selling price |
| `p_qty` | `integer` | NO | 0 | | Live inventory in stock |
| `p_featured_photo`| `varchar(255)` | YES | | | Primary product image filename |
| `p_description` | `text` | YES | | | Long-form HTML description |
| `p_short_description`| `text` | YES | | | Short excerpt |
| `p_feature` | `text` | YES | | | Bulleted feature list |
| `p_condition` | `text` | YES | | | Warranty / Condition text |
| `p_return_policy`| `text` | YES | | | Return terms |
| `p_total_view` | `integer` | YES | 0 | | Pageview count |
| `p_is_featured` | `smallint` | YES | 0 | | 1 = Featured on homepage |
| `p_is_active` | `smallint` | YES | 1 | | 1 = Visible in catalog |
| `ecat_id` | `integer` | NO | | **FK** | Links to `tbl_end_category.ecat_id` |
| `supplier_id` | `integer` | YES | 1 | **FK** | Owning tenant store |

### `tbl_product_details` (Technical Specifications)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Specification row ID |
| `product_id` | `integer` | NO | | **FK** | Links to `tbl_product.p_id` |
| `spec_name` | `varchar(100)` | NO | | | Property name (e.g., 'Grade', 'Load Rating') |
| `spec_value` | `varchar(255)` | NO | | | Property value (e.g., 'Grade 500E', '25MPa') |

---

## 3. Pricing, Discounts & Volume Tiers

### `tbl_product_tiers` (Volume Quantity Discounts)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Tier row ID |
| `product_id` | `integer` | NO | | **FK** | Links to `tbl_product.p_id` |
| `min_quantity` | `integer` | NO | 1 | | Minimum bulk quantity required |
| `tier_price` | `numeric(10,2)`| NO | | | Unit price when quantity met |
| `tier_discount_percent`| `numeric(5,2)` | YES | 0.00 | | Percentage equivalent discount |

---

## 4. Orders, Payments & Activity Logs

### `tbl_payment` (Master Order Payment Record)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Payment row ID |
| `customer_id` | `integer` | NO | | **FK** | Links to `tbl_customer.cust_id` |
| `customer_name` | `varchar(255)` | NO | | | Name of purchaser |
| `customer_email`| `varchar(255)` | NO | | | Contact email |
| `payment_date` | `varchar(50)` | NO | | | Formatted timestamp string |
| `txnid` | `varchar(255)` | YES | | | Gateway transaction identifier |
| `paid_amount` | `numeric(10,2)`| NO | | | Total amount paid (including freight) |
| `card_number` | `varchar(50)` | YES | | | Masked card reference |
| `card_cvv` | `varchar(10)` | YES | | | Security field (Legacy) |
| `card_month` | `varchar(10)` | YES | | | Expiration month |
| `card_year` | `varchar(10)` | YES | | | Expiration year |
| `bank_transaction_info`| `text` | YES | | | Bank wire transfer reference |
| `payment_method`| `varchar(50)` | NO | | | `Stripe`, `PayPal`, `Bank Transfer`, `POS Cash` |
| `payment_status`| `varchar(50)` | NO | 'Pending' | | `Pending`, `Completed`, `Failed` |
| `shipping_status`| `varchar(50)` | NO | 'Pending' | | `Pending`, `Processing`, `Shipped`, `Delivered` |
| `payment_id` | `varchar(255)` | NO | | | Unique string order identifier |
| `supplier_id` | `integer` | YES | 1 | **FK** | Primary tenant identifier |

### `tbl_order` (Itemized Order Lines)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Line item ID |
| `product_id` | `integer` | NO | | **FK** | Links to `tbl_product.p_id` |
| `product_name` | `varchar(255)` | NO | | | Snapshot product name |
| `size` | `varchar(255)` | YES | | | Selected size variant |
| `color` | `varchar(255)` | YES | | | Selected color variant |
| `quantity` | `integer` | NO | 1 | | Units purchased |
| `unit_price` | `numeric(10,2)`| NO | | | Unit price at time of purchase |
| `payment_id` | `varchar(255)` | NO | | | Ties to `tbl_payment.payment_id` |
| `supplier_id` | `integer` | YES | 1 | **FK** | Scoped supplier store |

### `tbl_order_activity_log` (Immutable Audit Log)
| Column | Type | Nullable | Default | PK/FK | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `serial` | NO | nextval() | **PK** | Log ID |
| `payment_id` | `varchar(255)` | NO | | | Target order reference |
| `action_type` | `varchar(50)` | NO | | | `STATUS_CHANGE`, `PAYMENT_UPDATE`, `RESTOCK` |
| `description` | `text` | NO | | | Human-readable audit description |
| `performed_by` | `varchar(255)` | NO | | | Actor name / User role |
| `ip_address` | `varchar(50)` | YES | | | Origin client IP address |
| `created_at` | `timestamp` | YES | CURRENT_TIMESTAMP | | Timestamp |
