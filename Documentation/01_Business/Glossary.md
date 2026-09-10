# Platform Domain Glossary & Terminology

```text
Status: Verified
Last Verified: 2026-09-08
Source: Domain Standards
Owner: eConstruction Supply Development Team
```

| Term | Definition | Context / Usage |
| :--- | :--- | :--- |
| **SaaS Admin** | Super administrator with global access across all suppliers, categories, and system settings. | Platform Back-office (`/admin/`) |
| **Supplier (Tenant)** | A registered merchant or building supply vendor operating an independent storefront and inventory. | Multi-tenant Subsystem (`/supplier/`) |
| **Supplier User** | An employee account belonging to a specific supplier with an assigned RBAC role. | `tbl_supplier_users` |
| **POS (Point of Sale)** | High-speed sales register interface used by cashiers to process in-person trade counter transactions. | `/supplier/pos.php` |
| **Discount Ceiling** | The maximum percentage discount a supplier store is authorized to grant customers, set by SaaS Admin. | `tbl_supplier.discount_ceiling_percentage` |
| **Volume Tier** | Tiered price reductions activated when an order exceeds specified quantity thresholds. | `tbl_product_tiers` |
| **Restock Request** | Internal supply requisition created by a store branch requesting inventory transfer from a depot. | `tbl_restock_request` |
| **Order Activity Log** | Chronological audit trail tracking who changed an order status, when, and from which IP. | `tbl_order_activity_log` |
| **Top/Mid/End Category**| The 3-level taxonomy hierarchy used to categorize construction hardware and building supplies. | `tbl_top_category`, `tbl_mid_category`, `tbl_end_category` |
