# System Gap Analysis & Technical Discrepancies

```text
Status: Verified
Last Verified: 2026-09-08
Source: Static Code Inspection
Owner: eConstruction Supply Development Team
```

## Discovered Gaps & Inconsistencies Matrix

| ID | Area | Finding / Discrepancy | Severity | Evidence | Target State Recommendation |
| :--- | :--- | :--- | :---: | :--- | :--- |
| **GAP-SEC-001** | Security / Auth | Passwords use legacy `MD5` hashing in `tbl_user` and `tbl_customer`. | **HIGH** | `admin/login.php` line 18 | Upgrade to PHP `password_hash()` using Argon2id/Bcrypt. |
| **GAP-DB-001** | Database Schema | Some foreign key relationships lack database-level `ON DELETE CASCADE` constraints. | **MEDIUM** | `init-db/01-schema.sql` | Add formal FK DDL constraints in next database migration. |
| **GAP-ORD-001** | Multi-Supplier Orders | Checkout creates a single `tbl_payment.supplier_id` representing primary supplier. | **MEDIUM** | `payment/paypal/payment_process.php` | Create distinct sub-payments per supplier for multi-basket orders. |
| **GAP-INV-001** | Multi-Branch Stock | Inventory is tracked globally per product (`p_qty`) rather than per physical branch depot. | **LOW** | `tbl_product.p_qty` | Introduce `tbl_store_inventory` for multi-depot stock levels. |
