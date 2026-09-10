# ADR-005: Immutable Order Activity Logging

## Status
`Accepted`

## Context
Auditing order lifecycle transitions is essential for dispute resolution in B2B building material fulfillment.

## Decision
Create `tbl_order_activity_log` to record chronological audit entries for all status changes and discount approvals.
