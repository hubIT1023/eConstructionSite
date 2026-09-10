# ADR-004: Dual-State POS Cart Persistence

## Status
`Accepted`

## Context
Cashiers frequently navigate between quick product search and full order review without losing selected items or discount tokens.

## Decision
Persist POS cart in session arrays (`$_SESSION['cart_p_id']`) synchronized between `pos.php` and `checkout.php`.
