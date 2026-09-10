# Order Activity Audit Logging Engine

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Audit Log Schema & Logging Events

Stored in `tbl_order_activity_log`:
- `payment_id`: Order transaction reference.
- `action_type`: Event category (`STATUS_CHANGE`, `PAYMENT_CAPTURE`, `DISCOUNT_APPLIED`, `RESTOCK_DISPATCH`).
- `description`: Detailed event narrative.
- `performed_by`: Authenticated user name and role.
- `ip_address`: Client IP address.
- `created_at`: Precise timestamp.
