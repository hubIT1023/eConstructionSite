# Order Lifecycle State Machine

```text
Status: Verified
Last Verified: 2026-09-08
Source: /supplier/order.php & /admin/order.php
Owner: eConstruction Supply Development Team
```

```mermaid
stateDiagram-v2
    [*] --> Pending: Customer / POS Places Order
    Pending --> Processing: Staff Confirms & Begins Picking
    Pending --> Cancelled: Customer/Staff Cancels (Restores Stock)
    Processing --> Shipped: Consignment Dispatched with Carrier
    Processing --> Cancelled: Material Unavailable / Cancelled
    Shipped --> Delivered: Jobsite Delivery Confirmed
    Delivered --> [*]: Order Closed
    Cancelled --> [*]: Inventory Restored
```
