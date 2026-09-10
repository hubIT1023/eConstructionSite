-- ==============================================================================
-- Migration: 001_initial_schema_baseline.sql
-- Description: Baseline marker recording existing PostgreSQL 14 schema setup
-- ==============================================================================

-- Ensure tbl_migrations table exists for version tracking
CREATE TABLE IF NOT EXISTS tbl_migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
