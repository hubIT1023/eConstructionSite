# REST & AJAX Endpoints Reference

```text
Status: Verified
Last Verified: 2026-09-08
Source: Route Audits
Owner: eConstruction Supply Development Team
```

## Catalog & Category Endpoints

### `GET /admin/get-mid-category.php`
- **Purpose**: Fetch child mid-categories for a selected top category.
- **Authentication**: Admin session required.
- **Parameters**: `id` (integer Top Category ID).
- **Response**: HTML `<option>` elements or JSON list.

### `GET /admin/get-end-category.php`
- **Purpose**: Fetch child end-categories for a selected mid-category.
- **Authentication**: Admin session required.
- **Parameters**: `id` (integer Mid Category ID).
- **Response**: HTML `<option>` elements.

---

## POS & Discount Endpoints

### `POST /supplier/pos-search-products.php`
- **Purpose**: Search catalog products by name, SKU, or barcode for POS cart addition.
- **Authentication**: Supplier Staff session required (`has_pos_access()`).
- **Parameters**: `query` (string), `supplier_id` (inferred from session).
- **Response**: JSON array of matching product objects with price, stock, size/color options.

### `POST /supplier/ajax-pos-discount-approval.php`
- **Purpose**: Verify manager PIN and authorize cashier discount override.
- **Authentication**: Supplier session required.
- **Parameters**: `manager_pin` (string), `discount_percent` (numeric), `order_subtotal` (numeric).
- **Response**: JSON `{"status": "success", "approver_id": 4, "approved_discount": 15.0}` or `{"status": "error", "message": "Invalid PIN or exceeds store ceiling"}`.
