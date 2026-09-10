# Point of Sale (POS) Architecture & Workflow

```text
Status: Verified
Last Verified: 2026-09-08
Source: /supplier/pos.php & /supplier/checkout.php
Owner: eConstruction Supply Development Team
```

## 1. POS Subsystem Overview

The Supplier POS system ([`/supplier/pos.php`](file:///D:/projects/eConstructionSite/supplier/pos.php)) is a specialized high-speed interface engineered for counter sales in brick-and-mortar builder yards.

```mermaid
flowchart TD
    Start([Cashier Opens POS]) --> Search[Search Product / Scan Barcode]
    Search --> Select[Select Size / Color / Quantity]
    Select --> AddCart[Add to Current POS Sale]
    AddCart --> CheckDiscount{Discount Requested?}
    
    CheckDiscount -->|No| Review[Review Order Items & Subtotal]
    CheckDiscount -->|Yes - <= Threshold| ApplyDirect[Apply Direct Line/Order Discount]
    CheckDiscount -->|Yes - > Threshold| ModalPIN[Trigger Supervisor PIN Approval Modal]
    
    ModalPIN --> ApproverCheck{Supervisor Valid & <= Ceiling?}
    ApproverCheck -->|Reject| Review
    ApproverCheck -->|Approve| ApplyDiscount[Apply Approved Discount]
    
    ApplyDirect --> Review
    ApplyDiscount --> Review
    Review --> CheckoutBtn[Click 'Checkout / Order Finalize']
    CheckoutBtn --> CheckoutPage[/supplier/checkout.php]
    
    CheckoutPage --> EditItems{Modify Qty / Remove Item?}
    EditItems -->|Yes| UpdateCart[Update Cart Session in Place]
    EditItems -->|No| PayMethod[Select Payment: Cash / Card / Account]
    
    UpdateCart --> PayMethod
    PayMethod --> SubmitPay[Submit Final Payment]
    SubmitPay --> DeductStock[Atomic Deduction from tbl_product.p_qty]
    DeductStock --> CreateOrder[Insert tbl_payment & tbl_order]
    CreateOrder --> PrintInvoice[Generate Printable Thermal Invoice]
    PrintInvoice --> End([Transaction Complete])
```

---

## 2. Key POS Architectural Features

1. **Dual Cart Persistence**:
   The POS cart is persisted in `$_SESSION['cart_p_id']`. When navigating between POS search ([`pos.php`](file:///D:/projects/eConstructionSite/supplier/pos.php)) and Order Finalization ([`checkout.php`](file:///D:/projects/eConstructionSite/supplier/checkout.php)), cart state is fully preserved.
2. **In-Place Item Editing**:
   The POS checkout card allows cashiers to adjust quantities or remove line items directly on the checkout screen without losing selected customer or discount settings.
3. **Supervisor PIN Authorization**:
   Discounts exceeding cashier limits trigger an AJAX verification check against active Managers/Admins in `tbl_supplier_users`.
