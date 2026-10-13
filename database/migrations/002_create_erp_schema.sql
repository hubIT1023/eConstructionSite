-- ==============================================================================
-- 001_create_erp_schema.sql
-- ERP Integration for eConstructionSite
-- Creates dedicated 'erp' schema with strict role permissions
-- Leaves all existing 'public' POS tables intact and untouched.
-- ==============================================================================

-- 1. Create erp schema
CREATE SCHEMA IF NOT EXISTS erp;

-- 2. Create isolated role erp_service_user
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'erp_service_user') THEN
        CREATE ROLE erp_service_user WITH LOGIN PASSWORD 'erp_local_secret';
    END IF;
END
$$;

-- 3. Grant isolated permissions
GRANT USAGE ON SCHEMA public TO erp_service_user;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO erp_service_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO erp_service_user;

GRANT ALL ON SCHEMA erp TO erp_service_user;
GRANT ALL ON ALL TABLES IN SCHEMA erp TO erp_service_user;
GRANT ALL ON ALL SEQUENCES IN SCHEMA erp TO erp_service_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA erp GRANT ALL ON TABLES TO erp_service_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA erp GRANT ALL ON SEQUENCES TO erp_service_user;

-- 4. Create ERP Subscription Entitlements (Controls ERP in Global Admin per tenant)
CREATE TABLE IF NOT EXISTS erp.subscription_entitlements (
    supplier_id INT PRIMARY KEY REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    erp_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    erp_plan VARCHAR(50) NOT NULL DEFAULT 'Standard',
    has_accounting BOOLEAN NOT NULL DEFAULT TRUE,
    has_opex_tracking BOOLEAN NOT NULL DEFAULT TRUE,
    has_sales_incentives BOOLEAN NOT NULL DEFAULT TRUE,
    has_multi_warehouse BOOLEAN NOT NULL DEFAULT FALSE,
    max_warehouses INT NOT NULL DEFAULT 1,
    max_cost_centers INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    valid_until TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. Create Cost Centers (Store branches / Physical locations)
CREATE TABLE IF NOT EXISTS erp.cost_centers (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    location_address TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (supplier_id, code)
);

-- 6. Create ERP Item Profiles (Extends tbl_product without altering it)
CREATE TABLE IF NOT EXISTS erp.item_profiles (
    product_id INT PRIMARY KEY REFERENCES public.tbl_product(p_id) ON DELETE CASCADE,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    cost_valuation_method VARCHAR(30) NOT NULL DEFAULT 'FIFO',
    standard_unit_cost NUMERIC(12, 4) NOT NULL DEFAULT 0.0000,
    reorder_threshold INT NOT NULL DEFAULT 10,
    reorder_quantity INT NOT NULL DEFAULT 50,
    cogs_account_code VARCHAR(20) DEFAULT '5100',
    inventory_account_code VARCHAR(20) DEFAULT '1200',
    revenue_account_code VARCHAR(20) DEFAULT '4100',
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 7. Create Operating Expenses (Rent, Electricity, Salaries, Delivery, Vendor Bills)
CREATE TABLE IF NOT EXISTS erp.operating_expenses (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    cost_center_id INT REFERENCES erp.cost_centers(id) ON DELETE SET NULL,
    category VARCHAR(50) NOT NULL,
    vendor_name VARCHAR(150),
    title VARCHAR(200) NOT NULL,
    amount NUMERIC(12, 2) NOT NULL,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'PAID',
    payment_method VARCHAR(50) DEFAULT 'BANK_TRANSFER',
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 8. Create Payment Processing Fee Rules & Transactions (Bank charges per POS payment)
CREATE TABLE IF NOT EXISTS erp.payment_fee_rules (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    payment_method VARCHAR(100) NOT NULL,
    fixed_fee NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    percentage_fee NUMERIC(6, 4) NOT NULL DEFAULT 0.0000,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (supplier_id, payment_method)
);

CREATE TABLE IF NOT EXISTS erp.payment_fee_transactions (
    id SERIAL PRIMARY KEY,
    payment_id INT NOT NULL REFERENCES public.tbl_payment(id) ON DELETE CASCADE,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    payment_method VARCHAR(100) NOT NULL,
    gross_amount NUMERIC(12, 2) NOT NULL,
    calculated_fee NUMERIC(10, 2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (payment_id)
);

-- 9. Create Sales Staff Incentives & Commissions
CREATE TABLE IF NOT EXISTS erp.sales_incentives (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES public.tbl_order(id) ON DELETE CASCADE,
    payment_id VARCHAR(255) NOT NULL,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    cashier_id INT REFERENCES public.tbl_supplier_user(id) ON DELETE SET NULL,
    cashier_name VARCHAR(255),
    incentive_rate_percent NUMERIC(5, 2) NOT NULL DEFAULT 1.00,
    incentive_amount NUMERIC(10, 2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACCRUED',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (order_id)
);

-- 10. Create Chart of Accounts & General Ledger
CREATE TABLE IF NOT EXISTS erp.chart_of_accounts (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type VARCHAR(30) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (supplier_id, account_code)
);

CREATE TABLE IF NOT EXISTS erp.journal_entries (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    entry_number VARCHAR(50) NOT NULL,
    entry_date DATE NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_reference VARCHAR(100),
    description TEXT,
    total_debit NUMERIC(14, 2) NOT NULL DEFAULT 0.00,
    total_credit NUMERIC(14, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (supplier_id, source_type, source_reference)
);

CREATE TABLE IF NOT EXISTS erp.journal_lines (
    id SERIAL PRIMARY KEY,
    journal_entry_id INT NOT NULL REFERENCES erp.journal_entries(id) ON DELETE CASCADE,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    debit_amount NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    credit_amount NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    line_memo VARCHAR(255)
);

-- 11. Create Pre-Aggregated Daily Store P&L Table (Fast Managerial Reporting)
CREATE TABLE IF NOT EXISTS erp.daily_store_pnl (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    cost_center_id INT REFERENCES erp.cost_centers(id) ON DELETE SET NULL,
    report_date DATE NOT NULL,
    gross_sales NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    discounts_given NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    net_sales NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    landed_cogs NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    gross_profit NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    bank_fees NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    sales_incentives NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    direct_delivery_costs NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    contribution_margin_1 NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    allocated_rent NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    allocated_electricity NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    allocated_base_salaries NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    allocated_other_opex NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    net_operating_profit NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (supplier_id, cost_center_id, report_date)
);

-- 12. Sync Watermarks (For safe, non-intrusive POS order polling)
CREATE TABLE IF NOT EXISTS erp.sync_watermarks (
    source_name VARCHAR(50) PRIMARY KEY,
    last_synced_id INT NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO erp.sync_watermarks (source_name, last_synced_id) 
VALUES ('pos_orders', 0)
ON CONFLICT (source_name) DO NOTHING;
