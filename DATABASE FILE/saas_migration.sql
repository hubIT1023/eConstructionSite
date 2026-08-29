-- eConstructionSite SaaS Migration Script

-- Create tbl_supplier
CREATE TABLE tbl_supplier (
    supplier_id SERIAL PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    supplier_slug VARCHAR(255) NOT NULL UNIQUE,
    supplier_logo VARCHAR(255),
    supplier_banner VARCHAR(255),
    supplier_description TEXT,
    supplier_address TEXT,
    supplier_email VARCHAR(255) NOT NULL UNIQUE,
    supplier_phone VARCHAR(50),
    supplier_status VARCHAR(50) NOT NULL DEFAULT 'Active', -- Pending, Active, Suspended
    supplier_plan VARCHAR(50) NOT NULL DEFAULT 'Starter',   -- Starter, Professional, Enterprise
    supplier_commission DECIMAL(5,2) NOT NULL DEFAULT 5.00,  -- Percentage commission rate
    supplier_delivery_areas TEXT,
    supplier_certifications TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Platform Supplier
INSERT INTO tbl_supplier (supplier_id, supplier_name, supplier_slug, supplier_logo, supplier_banner, supplier_description, supplier_address, supplier_email, supplier_phone, supplier_status, supplier_plan, supplier_commission)
VALUES (1, 'Platform Supplies Co', 'platform-supplies', 'default_logo.png', 'default_banner.jpg', 'Default marketplace vendor for building materials.', '100 Construction Way, Suite A', 'supplier1@mail.com', '123-456-7890', 'Active', 'Enterprise', 0.00);

-- Reset supplier_id sequence
SELECT setval(pg_get_serial_sequence('tbl_supplier', 'supplier_id'), COALESCE(max(supplier_id), 1)) FROM tbl_supplier;

-- Create tbl_supplier_user
CREATE TABLE tbl_supplier_user (
    id SERIAL PRIMARY KEY,
    supplier_id INTEGER NOT NULL REFERENCES tbl_supplier(supplier_id) ON DELETE CASCADE,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Admin', -- Admin, Employee
    status VARCHAR(50) NOT NULL DEFAULT 'Active'
);

-- Insert Default Supplier User (Password is MD5 of 'Password@123': d00f5d5217896fb7fd601412cb890830)
INSERT INTO tbl_supplier_user (supplier_id, full_name, email, password, role, status)
VALUES (1, 'Platform Supplier Manager', 'supplier@mail.com', 'd00f5d5217896fb7fd601412cb890830', 'Admin', 'Active');

-- Create tbl_quote
CREATE TABLE tbl_quote (
    quote_id SERIAL PRIMARY KEY,
    cust_id INTEGER NOT NULL REFERENCES tbl_customer(cust_id) ON DELETE CASCADE,
    supplier_id INTEGER NOT NULL REFERENCES tbl_supplier(supplier_id) ON DELETE CASCADE,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending', -- Pending, Submitted, Accepted, Declined
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Create tbl_quote_item
CREATE TABLE tbl_quote_item (
    id SERIAL PRIMARY KEY,
    quote_id INTEGER NOT NULL REFERENCES tbl_quote(quote_id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES tbl_product(p_id) ON DELETE CASCADE,
    quantity INTEGER NOT NULL,
    unit_price DECIMAL(10,2)
);

-- Create tbl_message
CREATE TABLE tbl_message (
    id SERIAL PRIMARY KEY,
    sender_type VARCHAR(50) NOT NULL, -- 'Customer', 'Supplier'
    sender_id INTEGER NOT NULL,       -- cust_id or supplier_user_id
    recipient_type VARCHAR(50) NOT NULL, -- 'Customer', 'Supplier'
    recipient_id INTEGER NOT NULL,       -- cust_id or supplier_id
    message_content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Alter existing tables to associate with supplier_id (default to 1)
ALTER TABLE tbl_product ADD COLUMN supplier_id INTEGER REFERENCES tbl_supplier(supplier_id) ON DELETE SET NULL DEFAULT 1;
UPDATE tbl_product SET supplier_id = 1;

ALTER TABLE tbl_order ADD COLUMN supplier_id INTEGER REFERENCES tbl_supplier(supplier_id) ON DELETE SET NULL DEFAULT 1;
UPDATE tbl_order SET supplier_id = 1;

ALTER TABLE tbl_payment ADD COLUMN supplier_id INTEGER REFERENCES tbl_supplier(supplier_id) ON DELETE SET NULL DEFAULT 1;
UPDATE tbl_payment SET supplier_id = 1;

ALTER TABLE tbl_shipping_cost ADD COLUMN supplier_id INTEGER REFERENCES tbl_supplier(supplier_id) ON DELETE CASCADE DEFAULT 1;
UPDATE tbl_shipping_cost SET supplier_id = 1;

ALTER TABLE tbl_rating ADD COLUMN supplier_id INTEGER REFERENCES tbl_supplier(supplier_id) ON DELETE CASCADE DEFAULT 1;
UPDATE tbl_rating SET supplier_id = 1;

ALTER TABLE tbl_subscriber ADD COLUMN supplier_id INTEGER REFERENCES tbl_supplier(supplier_id) ON DELETE CASCADE DEFAULT 1;
UPDATE tbl_subscriber SET supplier_id = 1;
