# "PO Created" — Read-Only Purchase Order Management for Order Processing Staff

## 1. Overview & Business Objective
The **PO Created** module (`https://econstruction-supply.site/supplier/po-created.php`) is a dedicated, real-time pending-order queue created specifically for **Order Processing Staff**, **Encoders**, **Operators**, and **Supervisors**. 

Located directly beneath **POS Terminal** in the supplier sidebar navigation, it empowers order preparation staff to track, review, search, and reprint customer Purchase Orders that are currently awaiting payment at the Cashier counter.

---

## 2. Definitive Business Workflow

```text
ORDER PROCESSING STAFF
          │
          ▼
     POS Terminal
          │
          ▼
   Select Products
          │
          ▼
  Proceed to Checkout
          │
          ▼
  Send Purchase Order
          │
          ▼
      PO CREATED
          │
          ├── Payment Status = UNPAID / Awaiting for Payment
          ├── View Complete PO Breakdown (READ-ONLY)
          └── Reprint PO Reference Voucher (500mm / 80mm / A4)
          │
          ▼
CUSTOMER GOES TO CASHIER
          │
          ▼
Cashier Receive Order Dashboard
          │
          ▼
Cashier Reviews Order & Confirms with Customer
          │
          ▼
Customer Pays Amount Due
          │
          ▼
Cashier Accepts Payment & Clicks [ PAID ]
          │
          ▼
Database: payment_status = 'Paid'
          │
          ├── Official PAID Tax Receipt Generated
          ├── AUTOMATICALLY REMOVED from "PO Created" List
          └── PERMANENTLY PRESERVED in Paid Orders & Historical Reporting
```

---

## 3. Core Business & Architecture Rules

### Rule 1: Awaiting Payment Queue (PO Created $
eq$ Historical Archive)
- **UNPAID PO**: `payment_status = 'Awaiting for Payment'` $ightarrow$ **VISIBLE** in PO Created.
- **PAID PO**: `payment_status = 'Paid'` $ightarrow$ **NOT VISIBLE** in PO Created.

### Rule 2: Non-Destructive Lifecycle (Zero Record Deletion)
- When a Cashier marks a PO as `Paid`, the order is **NEVER deleted** from `tbl_payment` or `tbl_order`.
- The database record status transitions from `Awaiting for Payment` to `Paid`.
- The record moves smoothly from the pending queue into the store's **Paid Orders** (`supplier/paid-orders.php`), **Sales Reports** (`supplier/sales-report.php`), and customer transaction history.

### Rule 3: Strict Read-Only Policy for Order Processing Staff
- Order Processing Staff can **View** and **Reprint** the PO.
- No editing of quantities, prices, discounts, or customers is allowed from the `PO Created` page.
- No payment collection or status toggles are exposed to Order Processing Staff.

---

## 4. Technical Implementation Details

### Server-Side Data Query
Multi-tenant isolation and strict status filtering are enforced on the server:
```sql
SELECT * FROM tbl_payment 
WHERE supplier_id = :supplier_id 
  AND (payment_status = 'Awaiting for Payment' OR payment_status = 'Pending' OR payment_status = 'UNPAID')
  AND payment_status != 'Paid'
  AND payment_status != 'Completed'
ORDER BY id DESC;
```

### Role-Based Access Control
- **Authorized Roles**: `ORDER_PROCESSING`, `ENCODER`, `OPERATOR`, `SUPERVISOR`, `MANAGER`, `ADMIN`.
- Route barrier configured in `supplier/header.php` preventing unauthorized URL manipulation.
- Multi-tenant validation ensures users cannot access or print another supplier store's purchase orders.

---

## 5. UI Components & Actions

1. **Top Metric Cards**: Real-time count of pending purchase orders, total receivable value awaiting cashier collection, and active tenant role badge.
2. **Period Filter Pills**: Quick toggle between **All Pending POs**, **Today's POs**, **This Week**, and **This Month**.
3. **Server-Side Search**: Search by PO Reference (`payment_id`), customer name, phone number, email, or remarks.
4. **Interactive Action Modals**:
   - **View PO**: Detailed item breakdown with product specifications (thickness, dimensions, color), SKU, unit prices, approved discount tiers, delivery fee, and net grand total.
   - **Reprint PO Voucher**: Generates customer reference voucher with `PAYMENT STATUS: UNPAID` and cashier checkout instructions.
5. **Printer Integration**: Directly reads configured printer settings (`localStorage['pos_printer_settings']`) supporting **500 mm / 50 cm continuous thermal roll**, 80 mm, 58 mm, and standard A4 sheets.

---

## 6. Verification & Test Evidence

| Test Case | Scenario | Expected Result | Status |
| :--- | :--- | :--- | :--- |
| **TEST 1** | Staff creates new PO in POS | PO appears in `PO Created` with status `UNPAID` | **PASSED** |
| **TEST 2** | View PO Details | Full read-only itemized modal opens without edit controls | **PASSED** |
| **TEST 3** | Reprint PO Reference | Dynamic 500mm thermal layout voucher generated with `UNPAID` header | **PASSED** |
| **TEST 4** | Cashier marks PO as `Paid` | Order payment status updated to `Paid` in database | **PASSED** |
| **TEST 5** | PO Disappears from Queue | Paid PO immediately removed from `PO Created` list | **PASSED** |
| **TEST 6** | DB Preservation Check | PO, line items, and payment records remain intact in `tbl_payment` / `tbl_order` | **PASSED** |
| **TEST 7** | Cross-Tenant Isolation | Staff from Store A cannot query or view Store B purchase orders | **PASSED** |
