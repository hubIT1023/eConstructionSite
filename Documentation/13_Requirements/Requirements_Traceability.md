# Requirements Traceability Matrix (RTM)

```text
Status: Verified
Last Verified: 2026-09-08
Source: Architecture Review
Owner: eConstruction Supply Development Team
```

| Requirement ID | Business Rule | Module | Database Entity | Code Controller | Test Case ID | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: |
| **FR-TENANT-01** | `BR-TENANT-001` | Supplier Portal | `tbl_supplier` | `/supplier/index.php` | `TC-TENANT-01` | Verified |
| **FR-RBAC-01** | `BR-AUTH-002` | Staff RBAC | `tbl_supplier_users` | `/supplier/supplier-users.php`| `TC-RBAC-01` | Verified |
| **FR-POS-01** | `BR-POS-001` | POS Terminal | `tbl_payment`, `tbl_order` | `/supplier/pos.php` | `TC-POS-01` | Verified |
| **FR-POS-02** | `BR-DISCOUNT-002`| POS Discounts | `tbl_supplier.discount_ceiling`| `/supplier/checkout.php` | `TC-POS-02` | Verified |
| **FR-STOCK-01** | `BR-INVENTORY-001`| Stock Engine | `tbl_product.p_qty` | `/checkout.php` | `TC-STOCK-01` | Verified |
| **FR-AUDIT-01** | `BR-ORDER-001` | Order Audit | `tbl_order_activity_log` | `/supplier/order.php` | `TC-AUDIT-01` | Verified |
