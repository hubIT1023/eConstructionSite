# PostgreSQL Database Architecture & Connection Model

```text
Status: Verified
Last Verified: 2026-09-08
Source: PostgreSQL 15 (ecomDB)
Owner: eConstruction Supply Development Team
```

## 1. Database Configuration Overview

- **RDBMS Engine**: PostgreSQL 15 (Running in Docker container `econstructionsite-db`)
- **Database Name**: `ecomDB`
- **User / Schema**: `postgres` / `public`
- **Character Encoding**: `UTF8`
- **Collation**: `en_US.utf8`
- **Connection Adapter**: PHP PDO PostgreSQL (`pgsql:host=db;port=5432;dbname=ecomDB`)

---

## 2. Table Domain Classifications (40 Total Tables)

1. **Multi-Tenant Core & Identity (4 tables)**: `tbl_supplier`, `tbl_supplier_users`, `tbl_user`, `tbl_customer`.
2. **Product Taxonomy (3 tables)**: `tbl_top_category`, `tbl_mid_category`, `tbl_end_category`.
3. **Catalog & Specifications (5 tables)**: `tbl_product`, `tbl_product_details`, `tbl_product_size`, `tbl_product_color`, `tbl_product_photo`.
4. **Pricing & Tiered Discounts (5 tables)**: `tbl_product_tiers`, `tbl_supplier_customer_discounts`, `tbl_supplier_category_discounts`, `tbl_supplier_product_discounts`, `tbl_supplier_tier_discounts`.
5. **Orders & Transactions (4 tables)**: `tbl_order`, `tbl_payment`, `tbl_order_activity_log`, `tbl_supplier_order_items`.
6. **Inventory & Inter-Store Restock (4 tables)**: `tbl_restock_request`, `tbl_restock_request_items`, `tbl_restock_shipment`, `tbl_restock_transfer_history`.
7. **Store Configuration & Settings (4 tables)**: `tbl_settings`, `tbl_language`, `tbl_shipping_cost`, `tbl_country`.
8. **CMS & Marketing Content (11 tables)**: `tbl_slider`, `tbl_service`, `tbl_testimonial`, `tbl_faq`, `tbl_news`, `tbl_page`, etc.
