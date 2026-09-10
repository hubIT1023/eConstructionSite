# Order Management Architecture & Lifecycle

```text
Status: Verified
Last Verified: 2026-09-08
Source: /admin/order.php, /supplier/order.php, checkout.php
Owner: eConstruction Supply Development Team
```

## 1. Relational Order Architecture

The order subsystem operates across two primary tables:

1. **`tbl_payment` (Master Transaction Record)**:
   - Contains customer identification (`customer_id`, `customer_name`, `customer_email`).
   - Contains payment details (`payment_id`, `payment_method`, `paid_amount`, `payment_status`).
   - Contains delivery and fulfillment status (`shipping_status`, `payment_date`).
   - For multi-supplier orders, `supplier_id` stores the primary or aggregated tenant.

2. **`tbl_order` (Itemized Order Line Items)**:
   - Contains specific product lines (`product_id`, `product_name`, `size`, `color`, `quantity`, `unit_price`).
   - Tied to master transaction via `payment_id`.
   - Scoped to individual supplier stores via `supplier_id` for tenant-level fulfillment.

```mermaid
flowchart TD
    subgraph MasterRecord["tbl_payment (Master Order)"]
        PaymentID["payment_id (Primary Key / String UUID)"]
        CustInfo["customer_name, email, phone, address"]
        PayMeta["paid_amount, payment_method, payment_status"]
        ShipMeta["shipping_status, payment_date"]
    end

    subgraph LineItems["tbl_order (Line Items)"]
        Item1["Line Item #1 (product_id, qty, unit_price, supplier_id = 1)"]
        Item2["Line Item #2 (product_id, qty, unit_price, supplier_id = 2)"]
    end

    subgraph AuditTrail["tbl_order_activity_log (Audit Timeline)"]
        Log1["Event: Payment Received (Auto Gateway)"]
        Log2["Event: Status Updated to 'Processing' by Staff User #4"]
    end

    MasterRecord --> LineItems
    MasterRecord --> AuditTrail
```
