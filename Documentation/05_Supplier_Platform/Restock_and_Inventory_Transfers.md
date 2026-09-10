# Restock Requests & Inventory Transfers

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Multi-Store Restock Lifecycle

1. **Requisition Created**: Branch store creates a restock request in `tbl_restock_request` listing required products and quantities.
2. **Dispatch Authorized**: Depot manager reviews stock, generates shipment record in `tbl_restock_shipment`, and marks items as dispatched.
3. **Receipt & Stock Increment**: Receiving store checks physical goods and confirms receipt, automatically incrementing local stock levels.
