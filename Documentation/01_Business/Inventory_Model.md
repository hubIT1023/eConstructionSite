# Central Inventory Model

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Single Stock Pool Architecture

- Each product record maintains an active quantity field (`p_qty` in `tbl_product`).
- When a sale occurs (online or POS), `p_qty` is decremented atomically.
- Restock shipments increment `p_qty` upon confirmation of physical delivery.
