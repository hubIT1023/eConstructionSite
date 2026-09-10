# ADR-002: Tenant-Level Discount Ceilings

## Status
`Accepted` (Implemented in Production)

## Context
In a multi-supplier construction supply marketplace, unauthorized excessive discounting by tenant cashiers or aggressive supplier pricing could destabilize the marketplace.

## Decision
Introduce `discount_ceiling_percentage` in `tbl_supplier`, managed exclusively by SaaS Admins. Enforce this ceiling in all supplier discount rules and POS override modals.

## Consequences
- Guaranteed margin safety for suppliers and platform operators.
- Transparent supervisor authorization workflow at POS terminals.
