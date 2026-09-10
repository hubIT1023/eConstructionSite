# Supplier Onboarding & Tenant Discount Ceilings

```text
Status: Verified
Last Verified: 2026-09-08
Source: /admin/supplier.php & /admin/supplier-edit.php
Owner: eConstruction Supply Development Team
```

## 1. SaaS Tenant Onboarding Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Admin as SaaS Super Administrator
    participant Portal as /admin/supplier-add.php
    participant DB as PostgreSQL (tbl_supplier)
    participant Mail as System Mailer

    Admin->>Portal: Enters Supplier Name, Email, Address, Commission % (e.g. 8.5%)
    Admin->>Portal: Sets Discount Ceiling % (e.g. 25.00%)
    Portal->>DB: INSERT INTO tbl_supplier (status=1)
    Portal->>DB: INSERT INTO tbl_supplier_users (role='Admin')
    Portal->>Mail: Sends Welcome Credentials to Supplier Store Owner
    Portal-->>Admin: Displays 'Supplier Store Successfully Provisioned'
```
