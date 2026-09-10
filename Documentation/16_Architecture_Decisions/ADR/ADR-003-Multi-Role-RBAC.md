# ADR-003: Supplier Multi-Role RBAC Normalization

## Status
`Accepted`

## Context
Supplier tenants require granular employee roles (Cashiers, Managers, Order Staff) to operate physical builder yards safely.

## Decision
Normalize roles in `tbl_supplier_users` and centralize authorization checks in `supplier_user_helper.php`.
