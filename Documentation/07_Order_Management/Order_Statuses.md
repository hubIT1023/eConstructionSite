# Order & Shipping Status Dictionary

```text
Status: Verified
Last Verified: 2026-09-08
Source: Source Code Constants & Database Columns
Owner: eConstruction Supply Development Team
```

## 1. Payment Statuses (`tbl_payment.payment_status`)

| Status Code | Meaning / Definition | Triggering Event |
| :--- | :--- | :--- |
| **`Pending`** | Payment initiated but not yet confirmed by gateway or bank. | Bank wire transfer order placed. |
| **`Completed`** | Payment authorized and captured in full. | Stripe/PayPal capture or POS cash collection. |
| **`Failed`** | Payment rejected or declined by processor. | Gateway credit card decline. |
| **`Refunded`** | Transaction amount returned to customer. | Admin or Supplier processed return. |

---

## 2. Shipping & Delivery Statuses (`tbl_payment.shipping_status`)

| Status Code | Meaning / Definition | Allowed Next Statuses |
| :--- | :--- | :--- |
| **`Pending`** | Order received; waiting for warehouse packing. | `Processing`, `Cancelled` |
| **`Processing`**| Items being picked, packed, or manufactured. | `Shipped`, `Cancelled` |
| **`Shipped`** | Consignment handed over to freight/courier carrier. | `Delivered`, `Returned` |
| **`Delivered`** | Consignment confirmed received at job site. | Terminal State (`Completed`) |
| **`Cancelled`** | Order cancelled prior to dispatch; stock restored. | Terminal State |
