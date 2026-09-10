# Comprehensive Test Cases Specification

```text
Status: Verified
Last Verified: 2026-09-08
Source: QA Test Suite
Owner: eConstruction Supply Development Team
```

### TC-TENANT-01: Multi-Tenant Data Isolation Test
- **Precondition**: Supplier A (ID 1) and Supplier B (ID 2) have distinct products in `tbl_product`.
- **Test Steps**:
  1. Authenticate as Supplier A staff user.
  2. Navigate to `/supplier/product.php`.
  3. Verify listed product rows belong strictly to `supplier_id = 1`.
  4. Attempt direct GET request to `/supplier/product-edit.php?id=[Supplier B Product ID]`.
- **Expected Result**: HTTP redirect or access denied error; Supplier B product details are never disclosed.
- **Status**: `PASSED`

### TC-POS-02: POS Discount Ceiling Enforcement Test
- **Precondition**: Supplier store has `discount_ceiling_percentage = 20.00%`.
- **Test Steps**:
  1. Login to POS terminal as cashier.
  2. Add item worth $100.00 to cart.
  3. Request manual discount override of 25.00%.
  4. Submit valid Manager PIN.
- **Expected Result**: System rejects discount with error message *"Requested discount (25%) exceeds approved store discount ceiling (20%)"*.
- **Status**: `PASSED`
