# System Architecture Map & Component Boundaries

```text
Status: Verified
Last Verified: 2026-09-08
Source: Architectural Inspection
Owner: eConstruction Supply Development Team
```

```mermaid
flowchart TD
    subgraph Public_Clients["External Clients & End Users"]
        Customer["Public Customer / B2B Contractor"]
        Browser["Modern Web Browser / Mobile View"]
    end

    subgraph Edge_Tier["Edge & Web Server (Docker)"]
        Apache["Apache 2.4 + PHP 7.4 Engine\n(Container: econstructionsite-web)"]
        Router["Virtual URL Routing & Auth Guards"]
    end

    subgraph App_Subsystems["Core Application Subsystems"]
        subgraph Storefront["1. Public Marketplace Subsystem"]
            Catalog["Product Catalog & Faceted Search"]
            Cart["Session Shopping Cart Engine"]
            PublicCheckout["Multi-Gateway Checkout (Stripe/PayPal/Bank)"]
            CustPortal["Customer Order History & Tracking"]
        end

        subgraph SuperAdmin["2. SaaS Super Admin Subsystem (/admin/)"]
            TenantMgmt["Supplier Tenant Onboarding & Ceilings"]
            TaxonomyMgmt["Global 3-Level Category Taxonomy"]
            GlobalAudits["Global Transaction & Activity Audit Logs"]
            DevDoc["Developer Documentation & System Portal"]
        end

        subgraph SupplierTenant["3. Supplier Tenant Subsystem (/supplier/)"]
            StaffRBAC["Staff User Accounts & Role Permissions"]
            StoreCatalog["Store Catalog & Construction Specs"]
            DiscountRules["Customer/Category Tier Discount Engine"]
            RestockEngine["Inter-Store Restock & Transfer Workflow"]
            TenantOrders["Supplier Order Processing & Dispatch"]
        end

        subgraph POSSubsystem["4. Supplier Point-of-Sale (/supplier/pos.php)"]
            CashierUI["Fast Barcode/Product Catalog Terminal"]
            DiscountApproval["Discount Override & PIN Approval Modal"]
            POSCheckout["POS Checkout, Order Item Edit & Thermal Invoice"]
        end
    end

    subgraph Data_Tier["Data Persistence Tier (Docker)"]
        Postgres[("PostgreSQL 15 Database (ecomDB)\n40 Production Tables")]
    end

    Customer --> Browser
    Browser --> Apache
    Apache --> Router
    Router --> Storefront
    Router --> SuperAdmin
    Router --> SupplierTenant
    Router --> POSSubsystem

    Storefront --> Postgres
    SuperAdmin --> Postgres
    SupplierTenant --> Postgres
    POSSubsystem --> Postgres
```

---

## Subsystem Boundary Definitions

1. **Public Marketplace**:
   - Primary domain for unauthenticated visitors and authenticated retail/wholesale customers.
   - Allows multi-supplier basket aggregation, calculating itemized shipping fees, and triggering third-party payment gateways.
2. **SaaS Super Administration**:
   - Restricted back-office portal accessible exclusively by platform administrators (`tbl_user`).
   - Configures platform commissions, store discount limits (ceilings), category trees, and monitors system-wide health.
3. **Supplier Multi-Tenant Platform**:
   - Private back-office portal partitioned strictly by `supplier_id`.
   - Accessible by registered tenant staff (`tbl_supplier_users`) with fine-grained RBAC roles.
4. **Supplier Point-of-Sale (POS)**:
   - Optimized high-speed desktop/tablet sales terminal designed for counter sales in brick-and-mortar builder yards.
   - Synchronizes directly with the store's central inventory pool and enforces supervisor discount authorizations.
