# ADR-001: PostgreSQL 15 Database Migration

## Status
`Accepted` (Implemented in Production)

## Context
The legacy application was originally built on MySQL 5.7. For enterprise cloud deployments, superior JSON spec handling, and robust concurrency for POS operations, a migration to PostgreSQL 15 was executed.

## Decision
Migrate database container to PostgreSQL 15 (`ecomDB`), rewrite MySQL-specific syntax (`AUTO_INCREMENT` -> `SERIAL`, backticks -> standard quotes), and configure PHP PDO PostgreSQL driver.

## Consequences
- Clean transactional integrity for checkout and restock operations.
- Direct JSON query capabilities for construction technical specifications.
