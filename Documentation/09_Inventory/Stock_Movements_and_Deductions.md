# Stock Movements & Atomic Deductions

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Atomic Inventory Execution

During checkout, inventory is decremented via parameterized SQL:
```sql
UPDATE tbl_product SET p_qty = p_qty - :purchased_qty WHERE p_id = :p_id AND p_qty >= :purchased_qty;
```
