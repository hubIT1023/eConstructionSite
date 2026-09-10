# Codebase Inventory & File Directory

```text
Status: Verified
Last Verified: 2026-09-08
Source: Local Codebase & Docker Environment
Owner: eConstruction Supply Development Team
```

## 1. Top-Level Infrastructure & Configurations

| Component | Location | Purpose | Dependencies | Status | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Docker Compose** | `/docker-compose.yml` | Multi-container configuration (PHP Apache + PostgreSQL 15) | Docker Engine | Production | Ports 8080 (Web), 5432 (DB) |
| **PHP Dockerfile** | `/Dockerfile` | PHP 7.4 Apache image build with `pdo_pgsql`, `gd`, `zip` | Debian / Docker | Production | Bind mounts app directory |
| **DB Initializer** | `/init-db/01-schema.sql` | Initial DDL schema creating all 40 PostgreSQL tables | PostgreSQL 15 | Production | Executed on container init |

---

## 2. Public Storefront Subsystem (`/`)

| Component | Location | Purpose | Dependencies | Status | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Storefront Home** | `/index.php` | Marketplace landing page, featured items, category carousels | `header.php`, `footer.php` | Production | Publicly accessible |
| **Header Bar** | `/header.php` | Navigation menu, category dropdowns, cart badge, auth status | `admin/inc/config.php` | Production | Connects PDO database |
| **Footer Bar** | `/footer.php` | Footer links, contact info, newsletter subscription, scripts | Bootstrap, jQuery | Production | Shared across public pages |
| **Product Detail** | `/product.php` | Product specifications, size/color selectors, add-to-cart | `tbl_product`, `tbl_product_details` | Production | Supports construction spec view |
| **Category View** | `/product-category.php`| Filter products by Top, Mid, or End categories | Category hierarchy tables | Production | Supports faceted filtering |
| **Shopping Cart** | `/cart.php` | View cart items, update quantities, calculate subtotals | `$_SESSION['cart_p_id']` | Production | Session-based cart storage |
| **Checkout Portal**| `/checkout.php` | Customer shipping details, shipping cost calc, gateway selection | `tbl_shipping_cost`, `tbl_customer` | Production | Multi-gateway checkout |
| **Customer Auth** | `/login.php`, `/registration.php` | Public user registration, email activation, session login | `tbl_customer` | Production | MD5 password hashing (Legacy) |
| **Customer Portal**| `/dashboard.php`, `/customer-order.php` | View past orders, delivery tracking, download PDF invoice | `tbl_payment`, `tbl_order` | Production | Customer-scoped orders |

---

## 3. Super Admin Subsystem (`/admin/`)

| Component | Location | Purpose | Dependencies | Status | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Master Admin Home** | `/admin/index.php` | Platform KPI metrics (revenue, supplier count, total orders) | `admin/inc/config.php` | Production | Requires `$_SESSION['user']` |
| **Dev Documentation**| `/admin/dev-documentation.php` | Interactive System Documentation, Architecture, ERD & Manual | Bootstrap 3, Prism.js | Production | Master Developer Portal |
| **Supplier Manager** | `/admin/supplier.php` | List, activate/suspend supplier stores, set discount ceilings | `tbl_supplier` | Production | Platform SaaS control |
| **Supplier Create** | `/admin/supplier-add.php` | Register new supplier tenant, company profile & commission % | `tbl_supplier` | Production | Creates primary tenant record |
| **Category Manager** | `/admin/top-category.php`, `mid-category.php`, `end-category.php` | Manage 3-tier product taxonomy tree | Category tables | Production | Global marketplace taxonomy |
| **Product Approval** | `/admin/product.php` | View all marketplace products, approve/edit global catalog | `tbl_product` | Production | SaaS-wide product view |
| **Platform Orders** | `/admin/order.php` | Master audit list of all orders, payment status, dispatch logs | `tbl_payment`, `tbl_order` | Production | SaaS-wide order oversight |

---

## 4. Supplier Multi-Tenant Subsystem (`/supplier/`)

| Component | Location | Purpose | Dependencies | Status | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Tenant Dashboard** | `/supplier/index.php` | Tenant KPIs (daily sales, pending orders, inventory alerts) | `supplier/inc/config.php` | Production | Scoped by `$_SESSION['supplier']['id']` |
| **Staff & RBAC** | `/supplier/supplier-users.php` | Manage staff accounts, role assignment (Admin, Manager, Cashier) | `tbl_supplier_users` | Production | Isolated per `supplier_id` |
| **Catalog Manager** | `/supplier/product.php`, `product-add.php` | Manage tenant products, specifications, inventory levels | `tbl_product`, `tbl_product_details` | Production | Scoped by `supplier_id` |
| **Tier Pricing** | `/supplier/tier-pricing.php` | Configure volume tier discounts & minimum quantity thresholds | `tbl_product_tiers` | Production | Tier-based B2B discounting |
| **Tenant Discounts** | `/supplier/discount-customer.php`, `discount-category.php` | Assign custom discounts to specific customer accounts / categories | `tbl_supplier_*_discounts` | Production | Enforces SaaS discount ceiling |
| **POS Terminal** | `/supplier/pos.php` | Dedicated Point-of-Sale cashier terminal with fast barcode search | jQuery, Session Cart | Production | Real-time POS checkout |
| **POS Checkout** | `/supplier/checkout.php` | POS Review & Finalize Order, apply customer discount, capture pay | `supplier/cart.php` | Production | Supports in-place item edit |
| **Restock Manager** | `/supplier/restock-requests.php` | Branch-to-branch restock requisition, dispatch, and receipt | `tbl_restock_request` | Production | Multi-store inventory sync |
| **Order Processing** | `/supplier/order.php` | Process tenant orders, update statuses (`Completed`, `Cancelled`) | `tbl_payment`, `tbl_order` | Production | Isolated by `supplier_id` |

---

## 5. Shared Core Libraries (`/admin/inc/` & `/supplier/inc/`)

| Component | Location | Purpose | Dependencies | Status | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Database Config** | `/admin/inc/config.php` | PostgreSQL PDO connection, timezone, currency constants | PDO PostgreSQL | Production | Central DB connector |
| **CSRF & Security** | `/admin/inc/CSRF_Protect.php`| Anti-CSRF token generation and validation middleware | PHP Sessions | Production | Attached to forms |
| **Staff Helpers** | `/admin/inc/supplier_user_helper.php` | RBAC functions (`normalize_supplier_role`, `has_pos_access`) | `tbl_supplier_users` | Production | Centralized role auth |
| **Spec Parser** | `/admin/inc/construction_spec_helper.php` | JSON parser for construction specs (material, grade, load) | PHP JSON | Production | Used in storefront & catalog |
| **System Mailer** | `/admin/inc/mail_helper.php` | Transactional email helper via PHP `mail()` or SMTP | PHP mail | Production | Order confirmation & alerts |
