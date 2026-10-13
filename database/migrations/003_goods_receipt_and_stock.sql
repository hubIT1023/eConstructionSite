-- ==============================================================================
-- 003_goods_receipt_and_stock.sql
-- Inward Goods Receipt (GRN) & Stock Replenishment for Tenant Admin
-- Safe, audited stock receiving using SECURITY DEFINER procedure.
-- ==============================================================================

-- 1. Create Goods Receipt Notes Header Table
CREATE TABLE IF NOT EXISTS erp.goods_receipt_notes (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES public.tbl_supplier(supplier_id) ON DELETE CASCADE,
    vendor_name VARCHAR(150) NOT NULL,
    reference_number VARCHAR(100),
    receipt_date DATE NOT NULL DEFAULT CURRENT_DATE,
    freight_handling_cost NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    total_materials_cost NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    total_landed_cost NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. Create Goods Receipt Items Detail Table
CREATE TABLE IF NOT EXISTS erp.goods_receipt_items (
    id SERIAL PRIMARY KEY,
    grn_id INT NOT NULL REFERENCES erp.goods_receipt_notes(id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES public.tbl_product(p_id) ON DELETE CASCADE,
    product_name VARCHAR(255) NOT NULL,
    quantity_received INT NOT NULL CHECK (quantity_received > 0),
    unit_purchase_price NUMERIC(12, 4) NOT NULL,
    landed_unit_cost NUMERIC(12, 4) NOT NULL,
    subtotal NUMERIC(12, 2) NOT NULL
);

-- Grant access on new tables to erp_service_user
GRANT ALL ON erp.goods_receipt_notes TO erp_service_user;
GRANT ALL ON erp.goods_receipt_items TO erp_service_user;
GRANT ALL ON ALL SEQUENCES IN SCHEMA erp TO erp_service_user;

-- 3. Secure Stock Replenishment Procedure (SECURITY DEFINER)
-- Atomically increments POS tbl_product.p_qty and updates capital price,
-- while guaranteeing that erp_service_user CANNOT delete or modify other product fields!
CREATE OR REPLACE FUNCTION erp.receive_inventory_stock(
    p_supplier_id INT,
    p_product_id INT,
    p_quantity_received INT,
    p_landed_unit_cost NUMERIC(12, 4)
)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
BEGIN
    -- Verify product belongs to this supplier
    IF NOT EXISTS (SELECT 1 FROM public.tbl_product WHERE p_id = p_product_id AND supplier_id = p_supplier_id) THEN
        RAISE EXCEPTION 'Product ID % does not belong to Supplier ID %', p_product_id, p_supplier_id;
    END IF;

    -- Update POS stock and landed capital price atomically
    UPDATE public.tbl_product
    SET 
        p_qty = p_qty + p_quantity_received,
        p_capital_price = TRIM(TO_CHAR(p_landed_unit_cost, '9999999990.00'))
    WHERE p_id = p_product_id AND supplier_id = p_supplier_id;

    -- Also update or insert into erp.item_profiles
    INSERT INTO erp.item_profiles (product_id, supplier_id, standard_unit_cost, updated_at)
    VALUES (p_product_id, p_supplier_id, p_landed_unit_cost, NOW())
    ON CONFLICT (product_id) 
    DO UPDATE SET 
        standard_unit_cost = EXCLUDED.standard_unit_cost,
        updated_at = NOW();
END;
$$;

-- Grant execution rights to erp_service_user
GRANT EXECUTE ON FUNCTION erp.receive_inventory_stock(INT, INT, INT, NUMERIC) TO erp_service_user;
