# POS Discount Approval & Ceiling Verification Engine

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Discount Verification Workflow

1. **Cashier Request**: If a customer requests a discount exceeding standard cashier authority, the cashier enters the discount rate.
2. **Supervisor Modal**: System opens an approval modal prompting for Manager/Admin credentials or PIN.
3. **Ceiling Check**: System queries `tbl_supplier.discount_ceiling_percentage`. If the requested discount exceeds this ceiling, it is rejected regardless of manager approval.
