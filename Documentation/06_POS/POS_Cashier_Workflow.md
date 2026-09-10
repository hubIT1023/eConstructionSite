# POS Cashier Workflow & Operational Guide

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Step-by-Step Cashier Workflow

1. **Product Selection**: Cashier types product name or scans barcode in search input.
2. **Variant Confirmation**: Selects available size/color from dropdown and enters quantity.
3. **Add to Current Sale**: Item is appended to the active POS cart table with live subtotal calculation.
4. **Checkout Transition**: Cashier clicks *Proceed to Checkout*, leading to `/supplier/checkout.php`.
5. **In-Place Item Adjustments**: Quantities can be revised or items deleted directly in the checkout card.
6. **Payment Capture & Receipt**: Cashier records payment method, submits order, and prints thermal invoice.
