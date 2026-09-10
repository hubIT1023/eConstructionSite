# eConstruction Supply SaaS - Master Documentation Index

```text
Status: Verified
Last Verified: 2026-09-08
Source: Codebase / Database / Configuration
Owner: eConstruction Supply Development Team
```

## Overview & Directory Navigation

Welcome to the authoritative technical, architectural, and business documentation for **eConstruction Supply SaaS**. This documentation reflects the **actual implemented system** running across Docker containers on PostgreSQL 15 and PHP 7.4.

---

## Document Index by Domain

### [00_Master/](./)
- [Codebase_Inventory.md](Codebase_Inventory.md) - Full catalog of all files, routes, controllers, and modules.
- [System_Map.md](System_Map.md) - High-level system architecture and subsystem boundary map.
- [Documentation_Audit_Report.md](Documentation_Audit_Report.md) - Final audit report of discovered entities, tables, and rules.

### [01_Business/](../01_Business/)
- [Business_Overview.md](../01_Business/Business_Overview.md) - Platform purpose, multi-supplier model, and value proposition.
- [Marketplace_Model.md](../01_Business/Marketplace_Model.md) - Public customer shopping and supplier commission model.
- [Supplier_Tenant_Model.md](../01_Business/Supplier_Tenant_Model.md) - Multi-tenant isolation, discounting policies, and catalog ownership.
- [POS_Business_Model.md](../01_Business/POS_Business_Model.md) - Physical branch sales, cashier workflows, and in-store checkout.
- [Customer_Model.md](../01_Business/Customer_Model.md) - B2C retail customers vs. B2B wholesale trade accounts.
- [Order_Model.md](../01_Business/Order_Model.md) - Online orders, supplier sub-orders, and POS sales transactions.
- [Inventory_Model.md](../01_Business/Inventory_Model.md) - Single stock pool, deductions, and inter-store restock transfers.
- [Payment_Model.md](../01_Business/Payment_Model.md) - Payment gateways (Stripe, PayPal, Bank Transfer, Cash/POS).
- [Purchase_Order_Model.md](../01_Business/Purchase_Order_Model.md) - B2B supplier purchase orders and restock requisitions.
- [Delivery_Fulfillment_Model.md](../01_Business/Delivery_Fulfillment_Model.md) - Shipping calculation, delivery statuses, and dispatch.
- [Business_Rules.md](../01_Business/Business_Rules.md) - Numbered business rules (`BR-TENANT-001` through `BR-DELIVERY-001`).
- [Glossary.md](../01_Business/Glossary.md) - Terminology, definitions, and domain vocabulary.

### [02_Users_Roles/](../02_Users_Roles/)
- [User_Roles_Overview.md](../02_Users_Roles/User_Roles_Overview.md) - Authentication pools, session schemas, and role lifecycles.
- [Permission_Matrix.md](../02_Users_Roles/Permission_Matrix.md) - Exhaustive capabilities matrix across all platform roles.
- [Role_SaaS_Admin.md](../02_Users_Roles/Role_SaaS_Admin.md) - Platform Super Administrator capabilities and audit controls.
- [Role_Supplier_Admin.md](../02_Users_Roles/Role_Supplier_Admin.md) - Supplier store owners and store managers.
- [Role_Supplier_Staff.md](../02_Users_Roles/Role_Supplier_Staff.md) - Cashiers, Order Processing Staff, and Store Incharge users.
- [Role_Customer.md](../02_Users_Roles/Role_Customer.md) - Registered public retail and wholesale trade buyers.

### [03_Architecture/](../03_Architecture/)
- [System_Architecture.md](../03_Architecture/System_Architecture.md) - Tiered architecture, container separation, and data flow.
- [Deployment_Architecture.md](../03_Architecture/Deployment_Architecture.md) - Docker Compose, Nginx reverse proxy, and SSL setup.
- [C4/System_Context.md](../03_Architecture/C4/System_Context.md) - Level 1 C4 System Context Diagram.
- [C4/Container_Diagram.md](../03_Architecture/C4/Container_Diagram.md) - Level 2 C4 Container Architecture.
- [C4/Component_Diagrams.md](../03_Architecture/C4/Component_Diagrams.md) - Level 3 C4 Component breakdowns.

### [04_Public_Marketplace/](../04_Public_Marketplace/)
- [Marketplace_Overview.md](../04_Public_Marketplace/Marketplace_Overview.md) - Storefront browsing, multi-supplier products, and cart.
- [Catalog_and_Search.md](../04_Public_Marketplace/Catalog_and_Search.md) - 3-level category hierarchy and search filtering.
- [Cart_and_Checkout.md](../04_Public_Marketplace/Cart_and_Checkout.md) - Cart session handling, delivery calculation, and payment execution.
- [Customer_Portal_and_Tracking.md](../04_Public_Marketplace/Customer_Portal_and_Tracking.md) - Order history, profile updates, and bill downloads.

### [05_Supplier_Platform/](../05_Supplier_Platform/)
- [Supplier_Platform_Overview.md](../05_Supplier_Platform/Supplier_Platform_Overview.md) - Multi-tenant portal, dashboards, and operational controls.
- [Tenant_Onboarding_and_Settings.md](../05_Supplier_Platform/Tenant_Onboarding_and_Settings.md) - Store creation, commissions, and discount ceilings.
- [Catalog_and_Tier_Pricing.md](../05_Supplier_Platform/Catalog_and_Tier_Pricing.md) - Product listings, variants, specifications, and volume tiers.
- [Restock_and_Inventory_Transfers.md](../05_Supplier_Platform/Restock_and_Inventory_Transfers.md) - Multi-branch stock requisition and dispatch workflows.
- [Staff_RBAC_Management.md](../05_Supplier_Platform/Staff_RBAC_Management.md) - User creation, role assignment, and access isolation.

### [06_POS/](../06_POS/)
- [POS_Architecture.md](../06_POS/POS_Architecture.md) - Terminal interface, dual cart persistence, and modal interfaces.
- [POS_Cashier_Workflow.md](../06_POS/POS_Cashier_Workflow.md) - Barcode/product lookup, quantity adjustments, and fast checkout.
- [POS_Discount_Approval_Engine.md](../06_POS/POS_Discount_Approval_Engine.md) - Manual override thresholds, manager approval PINs, and ceilings.
- [POS_Order_Checkout_and_Receipts.md](../06_POS/POS_Order_Checkout_and_Receipts.md) - Payment capture, inventory deduction, and thermal invoice generation.

### [07_Order_Management/](../07_Order_Management/)
- [Order_Architecture.md](../07_Order_Management/Order_Architecture.md) - Unified `tbl_payment` and `tbl_order` structures.
- [Order_Statuses.md](../07_Order_Management/Order_Statuses.md) - Payment and delivery status dictionaries.
- [Order_State_Machine.md](../07_Order_Management/Order_State_Machine.md) - Lifecycle transition rules and permission checks.
- [Order_Activity_Audit_Log.md](../07_Order_Management/Order_Activity_Audit_Log.md) - Immutable chronological timeline of order events.

### [08_Database/](../08_Database/)
- [Database_Architecture.md](../08_Database/Database_Architecture.md) - PostgreSQL 15 configuration, indexing, and connection pools.
- [Database_Schema.md](../08_Database/Database_Schema.md) - Comprehensive dictionary of all 40 database tables.
- [Database_ERD.md](../08_Database/Database_ERD.md) - Visual entity relationship models and logical foreign-key links.

### [09_Inventory/](../09_Inventory/)
- [Inventory_Architecture.md](../09_Inventory/Inventory_Architecture.md) - Stock tracking at `tbl_product.p_qty` and warehouse distribution.
- [Stock_Movements_and_Deductions.md](../09_Inventory/Stock_Movements_and_Deductions.md) - Checkout deductions and cancellation restoration.
- [Restock_Shipments_and_Transfers.md](../09_Inventory/Restock_Shipments_and_Transfers.md) - Inter-store transfer logs and restock tracking.

### [10_Security/](../10_Security/)
- [Authentication.md](../10_Security/Authentication.md) - Session hashes, password verification (`MD5` current state), and token lifetimes.
- [Authorization.md](../10_Security/Authorization.md) - RBAC enforcement routines and route guards.
- [Tenant_Isolation.md](../10_Security/Tenant_Isolation.md) - Multi-tenant data segregation, SQL query scoping, and security boundaries.

### [11_API/](../11_API/)
- [API_Overview.md](../11_API/API_Overview.md) - Internal JSON/AJAX API conventions and authentication headers.
- [Endpoints_Reference.md](../11_API/Endpoints_Reference.md) - Complete catalog of all AJAX actions, inputs, outputs, and status codes.

### [12_UI_UX/](../12_UI_UX/)
- [Navigation_and_Layouts.md](../12_UI_UX/Navigation_and_Layouts.md) - AdminLTE layout, storefront theme, and responsive breakpoints.
- [User_Flows.md](../12_UI_UX/User_Flows.md) - Step-by-step UI journeys for buyers, suppliers, cashiers, and admins.

### [13_Requirements/](../13_Requirements/)
- [Requirements_Traceability.md](../13_Requirements/Requirements_Traceability.md) - Full mapping from Functional Requirements to Code, DB, and Tests.

### [14_Testing/](../14_Testing/)
- [Testing_Strategy.md](../14_Testing/Testing_Strategy.md) - Test methodologies, automated CLI scripts, and QA procedures.
- [Test_Cases.md](../14_Testing/Test_Cases.md) - Step-by-step test cases for security, POS, discounts, orders, and restock.

### [15_Analysis_and_Gaps/](../15_Analysis_and_Gaps/)
- [System_Gap_Analysis.md](../15_Analysis_and_Gaps/System_Gap_Analysis.md) - Current vs. Target state discrepancy matrix.
- [Security_Findings.md](../15_Analysis_and_Gaps/Security_Findings.md) - Security vulnerability audit and mitigation roadmap.
- [Technical_Debt.md](../15_Analysis_and_Gaps/Technical_Debt.md) - Code quality, architectural debt, and refactoring priorities.

### [16_Architecture_Decisions/](../16_Architecture_Decisions/)
- [ADR-001-PostgreSQL-Migration.md](../16_Architecture_Decisions/ADR/ADR-001-PostgreSQL-Migration.md) - PostgreSQL 15 port from MySQL.
- [ADR-002-Tenant-Discount-Ceilings.md](../16_Architecture_Decisions/ADR/ADR-002-Tenant-Discount-Ceilings.md) - Store-level discount capping engine.
- [ADR-003-Multi-Role-RBAC.md](../16_Architecture_Decisions/ADR/ADR-003-Multi-Role-RBAC.md) - Normalized role structure in `tbl_supplier_users`.
- [ADR-004-POS-Cart-Persistence.md](../16_Architecture_Decisions/ADR/ADR-004-POS-Cart-Persistence.md) - Dual-state session-to-checkout synchronization.
- [ADR-005-Activity-Logging.md](../16_Architecture_Decisions/ADR/ADR-005-Activity-Logging.md) - Immutable chronological order audit logging.

### [17_Change_Management/](../17_Change_Management/)
- [CHANGELOG.md](../17_Change_Management/CHANGELOG.md) - Historical record of architectural releases and updates.
- [Release_Notes.md](../17_Change_Management/Release_Notes.md) - Detailed deployment notes and environment migration steps.

### [18_AI_Development/](../18_AI_Development/)
- [AI_Project_Instructions.md](../18_AI_Development/AI_Project_Instructions.md) - Master prompt and behavioral guidelines for AI coding agents.
- [AI_Architecture_Rules.md](../18_AI_Development/AI_Architecture_Rules.md) - Structural boundaries, isolation rules, and architectural constraints.
- [AI_Database_Rules.md](../18_AI_Development/AI_Database_Rules.md) - PostgreSQL PDO standards, query construction, and migration rules.
- [AI_Security_Rules.md](../18_AI_Development/AI_Security_Rules.md) - CSRF, SQL parameter binding, and role authorization validation.
- [AI_Coding_Rules.md](../18_AI_Development/AI_Coding_Rules.md) - Coding style, naming conventions, and file organization.
- [AI_Testing_Rules.md](../18_AI_Development/AI_Testing_Rules.md) - Verification requirements before closing tasks.
- [AI_Task_Template.md](../18_AI_Development/AI_Task_Template.md) - Structured template for framing developer tasks.
