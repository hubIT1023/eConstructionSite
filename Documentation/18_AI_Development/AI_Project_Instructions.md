# AI Coding Agent Development Instructions

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Master Rules for AI Coding Assistants

1. **Single Source of Truth**: Read the relevant domain documentation before modifying code.
2. **Preserve Tenant Isolation**: Never construct SQL queries on supplier data without binding `supplier_id = ?`.
3. **No Unilateral Code Refactoring**: Modify only files directly requested in user tasks; preserve comments, docstrings, and existing working functions.
4. **PostgreSQL Standards**: Write standard SQL compatible with PostgreSQL 15. Never use MySQL-specific clauses.
5. **Role & Permission Guards**: When creating new supplier pages, always verify permissions using `supplier_user_helper.php`.
6. **Immutable Audit Logging**: Any order state mutation must record an event in `tbl_order_activity_log`.
7. **Verify Deployment**: Always test PHP syntax inside Docker container (`docker exec econstructionsite-web php -l ...`) after modifying code.
