# AI Architecture Rules & Constraints

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

1. **Subsystem Isolation**: Do not cross-mix Admin (`/admin/`), Supplier (`/supplier/`), and Public Storefront (`/`) session contexts.
2. **Database Scoping**: Always enforce `supplier_id` binding in tenant SQL queries.
3. **No Unilateral Schema Changes**: Do not add, rename, or drop table columns without providing an explicit SQL migration script.
4. **Preserve Legacy Helpers**: Keep helper functions in `/admin/inc/` backwards-compatible.
