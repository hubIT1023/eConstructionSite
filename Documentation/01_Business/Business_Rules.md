# Comprehensive Business Rules Matrix

```text
Status: Verified
Last Verified: 2026-09-08
Source: Codebase & Database Inspection
Owner: eConstruction Supply Development Team
```

## 1. Tenant Data Isolation Rules

### BR-TENANT-001: Strict Tenant Data Isolation
- **Rule**: Supplier staff can only view, edit, or delete records (products, orders, stock, staff accounts, discount rules) belonging to their authenticated `supplier_id`.
- **Applies To**: Supplier Portal, POS Terminal, Product Management, Order Processing.
- **Implementation**: SQL queries must include `WHERE supplier_id = ?` bound to `$_SESSION['supplier']['id']`.
- **Status**: `Verified`

### BR-TENANT-002: Cross-Tenant Order Segmentation
- **Rule**: When a customer checkout contains products from multiple suppliers, the master payment record (`tbl_payment`) is created globally, but itemized sub-orders (`tbl_order`) are partitioned by `supplier_id`.
- **Applies To**: Checkout, Order History, Supplier Fulfillment.
- **Status**: `Verified`

---

## 2. Authentication & Authorization Rules

### BR-AUTH-001: Multi-Pool Authentication Partitioning
- **Rule**: SaaS Admins (`tbl_user`), Supplier Staff (`tbl_supplier_users`), and Public Customers (`tbl_customer`) authenticate against separate tables and distinct session keys (`$_SESSION['user']`, `$_SESSION['supplier']`, `$_SESSION['customer']`).
- **Applies To**: Login Portals, Header Navigation, Access Guards.
- **Status**: `Verified`

### BR-AUTH-002: Supplier Role Hierarchy & RBAC
- **Rule**: Actions within the supplier portal are restricted by normalized roles (`Admin`, `Manager`, `POS Cashier`, `Order Processing Staff`, `Store Incharge`).
- **Applies To**: Staff management, restock approvals, discount approvals, POS access.
- **Status**: `Verified`

---

## 3. Product & Catalog Rules

### BR-PRODUCT-001: 3-Tier Category Hierarchy
- **Rule**: Every product must be associated with a valid Top Category (`tbl_top_category`), Mid Category (`tbl_mid_category`), and End Category (`tbl_end_category`).
- **Applies To**: Storefront Navigation, Admin Taxonomy, Product Creation.
- **Status**: `Verified`

### BR-PRODUCT-002: Construction Specification Storage
- **Rule**: Technical engineering specifications (material grade, load bearing, dimensions, compliance standards) are stored in structured key-value pairs inside `tbl_product_details`.
- **Applies To**: Product Page, POS Product Modal, Catalog Search.
- **Status**: `Verified`

---

## 4. Inventory Rules

### BR-INVENTORY-001: Single Stock Pool Synchronization
- **Rule**: Both online marketplace checkouts and in-store POS transactions deduct inventory from the same central stock balance (`tbl_product.p_qty`).
- **Applies To**: Public Checkout, POS Finalize Sale, Restock Receipt.
- **Status**: `Verified`

### BR-INVENTORY-002: Stock Depletion Prevention
- **Rule**: An order line item cannot exceed the currently available `p_qty` unless the store explicitly enables backorders.
- **Applies To**: Cart Addition, POS Quantity Update.
- **Status**: `Verified`

---

## 5. Pricing & Discount Rules

### BR-DISCOUNT-001: SaaS Discount Ceiling Enforcement
- **Rule**: No supplier discount rule or POS manual discount override may exceed the store's approved `discount_ceiling_percentage` defined in `tbl_supplier`.
- **Applies To**: Supplier Discount Forms, POS Discount Approval Engine.
- **Status**: `Verified`

### BR-DISCOUNT-002: POS Manager Override Authorization
- **Rule**: When a POS cashier requests a line-item or order discount greater than the cashier threshold (default 5%), a Manager/Admin PIN or credential check is mandatory.
- **Applies To**: POS Terminal Checkout.
- **Status**: `Verified`

---

## 6. Order & Payment Rules

### BR-ORDER-001: Immutable Order Activity Logging
- **Rule**: Every lifecycle status change (Pending -> Completed -> Delivered -> Cancelled) must append an immutable chronological entry to `tbl_order_activity_log`.
- **Applies To**: Admin Order Manager, Supplier Order Manager, POS Checkout.
- **Status**: `Verified`

### BR-PAYMENT-001: Payment Gateway Transaction Capture
- **Rule**: Orders placed via Stripe, PayPal, or Bank Transfer must record the external gateway reference, currency, and amount in `tbl_payment` before triggering fulfillment.
- **Applies To**: Public Checkout, Webhook Processing.
- **Status**: `Verified`
