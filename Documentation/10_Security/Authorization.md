# Authorization Engine & RBAC Enforcement

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## RBAC Helper Functions Reference

- `normalize_supplier_role($role)`: Normalizes legacy string roles into standard constants.
- `is_admin_or_manager_role($role)`: Returns boolean true if user has managerial privileges.
- `is_supplier_approver($role)`: Validates authorization to approve discount overrides.
- `has_pos_access($role)`: Checks if user is permitted to open the POS terminal.
