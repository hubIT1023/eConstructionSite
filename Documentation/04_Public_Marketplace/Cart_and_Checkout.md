# Shopping Cart & Multi-Gateway Checkout Engine

```text
Status: Verified
Last Verified: 2026-09-08
Source: cart.php, checkout.php, payment/
Owner: eConstruction Supply Development Team
```

## 1. Shopping Cart Session Data Structure

The public cart is stored within the PHP session superglobal arrays:

```php
$_SESSION['cart_p_id']          = array(12, 45, 88);       // Product IDs
$_SESSION['cart_size_id']       = array(1, 0, 3);          // Size Variant IDs
$_SESSION['cart_color_id']      = array(2, 0, 0);          // Color Variant IDs
$_SESSION['cart_p_qty']         = array(50, 100, 2);       // Quantities Purchased
$_SESSION['cart_p_current_price']= array(14.50, 8.20, 120); // Unit Prices
```

---

## 2. Checkout Gateway Integrations

1. **Stripe Gateway** (`/payment/stripe/payment_process.php`):
   Processes instant credit card payments via Stripe Elements / API.
2. **PayPal Gateway** (`/payment/paypal/payment_process.php`):
   Redirects customer to PayPal Express Checkout and processes IPN return tokens.
3. **Bank Wire Transfer** (`/payment/bank/payment_process.php`):
   Generates a pending order reference (`tbl_payment.payment_status = 'Pending'`) with bank account deposit instructions.
