# Staff Management & Role-Based Access Control

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Staff Management Rules

- Only store users with role `Admin` can create, edit, or deactivate employee accounts.
- Staff accounts are strictly scoped to the creator's `supplier_id`.
- Deactivating a staff account immediately invalidates their active session.
