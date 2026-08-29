-- eConstructionSite Construction Data Migration

-- 1. Add B2B columns to tbl_product if they do not exist
ALTER TABLE tbl_product ADD COLUMN p_moq INTEGER DEFAULT 1;
ALTER TABLE tbl_product ADD COLUMN p_brand VARCHAR(255) DEFAULT 'Generic';
ALTER TABLE tbl_product ADD COLUMN p_specs TEXT;
ALTER TABLE tbl_product ADD COLUMN p_delivery_estimate VARCHAR(255) DEFAULT '3-5 days';
ALTER TABLE tbl_product ADD COLUMN p_pdf VARCHAR(255);
ALTER TABLE tbl_product ADD COLUMN p_sku VARCHAR(100);

-- 2. Clean old catalog data
TRUNCATE tbl_top_category CASCADE;
TRUNCATE tbl_mid_category CASCADE;
TRUNCATE tbl_end_category CASCADE;
TRUNCATE tbl_product CASCADE;
TRUNCATE tbl_product_color CASCADE;
TRUNCATE tbl_product_size CASCADE;
TRUNCATE tbl_product_photo CASCADE;

-- Reset identity sequences
SELECT setval(pg_get_serial_sequence('tbl_top_category', 'tcat_id'), 1, false);
SELECT setval(pg_get_serial_sequence('tbl_mid_category', 'mcat_id'), 1, false);
SELECT setval(pg_get_serial_sequence('tbl_end_category', 'ecat_id'), 1, false);
SELECT setval(pg_get_serial_sequence('tbl_product', 'p_id'), 1, false);

-- 3. Insert B2B categories
-- Top categories
INSERT INTO tbl_top_category (tcat_id, tcat_name, show_on_menu) VALUES
(1, 'Building Materials', 1),
(2, 'Infrastructure & Utilities', 1),
(3, 'Tools & Safety', 1);

-- Mid categories
INSERT INTO tbl_mid_category (mcat_id, mcat_name, tcat_id) VALUES
(1, 'Steel & Metal', 1),
(2, 'Concrete & Cement', 1),
(3, 'Roofing & Wall', 1),
(4, 'Electrical & Wiring', 2),
(5, 'Plumbing & Pipes', 2),
(6, 'Power Tools', 3),
(7, 'Safety Equipment', 3);

-- End categories
INSERT INTO tbl_end_category (ecat_id, ecat_name, mcat_id) VALUES
(1, 'Rebar & Mesh', 1),
(2, 'Steel I-Beams', 1),
(3, 'Portland Cement', 2),
(4, 'Concrete Blocks', 2),
(5, 'Metal Sheets', 3),
(6, 'Roof Shingles', 3),
(7, 'Copper Cables', 4),
(8, 'Conduits & Fittings', 4),
(9, 'PVC Pipes', 5),
(10, 'Valves & Flanges', 5),
(11, 'Drills & Drivers', 6),
(12, 'Helmets & Vests', 7),
(13, 'Goggles & Gloves', 7);

-- Reset category sequences to prevent collision on new additions
SELECT setval(pg_get_serial_sequence('tbl_top_category', 'tcat_id'), COALESCE(max(tcat_id), 1)) FROM tbl_top_category;
SELECT setval(pg_get_serial_sequence('tbl_mid_category', 'mcat_id'), COALESCE(max(mcat_id), 1)) FROM tbl_mid_category;
SELECT setval(pg_get_serial_sequence('tbl_end_category', 'ecat_id'), COALESCE(max(ecat_id), 1)) FROM tbl_end_category;

-- 4. Seed construction materials products
INSERT INTO tbl_product (
    p_id, p_name, p_old_price, p_current_price, p_qty, p_featured_photo,
    p_description, p_short_description, p_feature, p_condition, p_return_policy,
    p_total_view, p_is_featured, p_is_active, ecat_id, supplier_id,
    p_moq, p_brand, p_specs, p_delivery_estimate, p_pdf, p_sku
) VALUES
(1, 'Deformed Steel Rebar Grade 60 (12mm x 6m)', '15.00', '12.50', 5000, 'rebar_12mm.jpg', 
'High-grade deformed carbon steel rebar used for reinforce concrete structures such as beams, slabs, and columns.', 
'Premium deformed steel rebar for concrete reinforcement.', 
'High tensile strength, Excellent bonding, ISO Certified', 'New', 'Return within 14 days if unused.', 
12, 1, 1, 1, 1, 100, 'Prime Steel Inc.', 'Material: Carbon Steel\nSize: 12mm diameter\nLength: 6 meters\nGrade: ASTM A615 Grade 60', '3-5 business days', 'spec_steel_rebar.pdf', 'CON-ST-REB-12MM'),

(2, 'Portland Cement Type I/II (50kg Bag)', '9.50', '8.90', 8000, 'portland_cement.jpg', 
'General purpose Portland cement suitable for concrete foundations, civil projects, masonry mortar, and plaster.', 
'High quality Portland cement bag for general building.', 
'ASTM C150 certified, Superior durability, Low hydration heat', 'New', 'Non-returnable once bag is opened.', 
35, 1, 1, 3, 1, 50, 'Cement World', 'Standard: ASTM C150 Type I/II\nWeight: 50 kilograms\nPackaging: Moisture-proof paper bag', '2-4 business days', 'spec_portland_cement.pdf', 'CON-CE-POR-50KG'),

(3, 'Schedule 40 PVC Conduit Pipe (3-inch x 10ft)', '22.00', '18.20', 2000, 'pvc_conduit_3in.jpg', 
'Heavy-duty schedule 40 PVC pipe for plumbing drainage systems and underground electrical wire conduits.', 
'3-inch Schedule 40 PVC pressure pipe.', 
'Corrosion resistant, Low friction loss, Easy installation', 'New', 'Return within 30 days if uncut.', 
18, 1, 1, 9, 1, 20, 'Apex Pipe Corp.', 'Material: PVC (Polyvinyl Chloride)\nDiameter: 3 inches\nLength: 10 feet\nRating: Schedule 40', '3-5 business days', 'spec_pvc_schedule40.pdf', 'CON-PL-PVC-3IN'),

(4, 'Heavy Duty Rotary Hammer Drill 850W', '120.00', '99.00', 150, 'rotary_hammer.jpg', 
'Professional grade 850W rotary hammer drill with variable speed and SDS-Plus chuck for heavy masonry and concrete drilling.', 
'850W SDS-Plus rotary hammer drill for concrete.', 
'3 Modes: Drill, Hammer-Drill, Chisel; Variable speed control; Ergonomic handle', 'New', '1-year manufacturer warranty.', 
42, 1, 1, 11, 1, 2, 'IronForce Tools', 'Power: 850 Watts\nImpact Energy: 3.2 Joules\nSpeed: 0-1100 RPM\nChuck Type: SDS-Plus', '2-3 business days', 'spec_drill_850w.pdf', 'CON-TL-ROT-850W'),

(5, 'Premium Structural Steel I-Beam (HEB 200)', '320.00', '285.00', 80, 'ibeam_heb200.jpg', 
'Heavy structural wide flange steel I-beam HEB 200, designed to withstand high loading pressures in B2B warehouse and residential steel frame designs.', 
'Structural steel H-section I-beam HEB 200.', 
'S275JR Grade steel, Rust-inhibiting primer coated, High structural load capacity', 'New', 'Non-returnable custom cuts.', 
10, 1, 1, 2, 1, 5, 'Prime Steel Inc.', 'Section: HEB 200\nHeight: 200mm\nFlange Width: 200mm\nLength: 12 meters\nStandard: EN 10025-2', '5-7 business days', 'spec_ibeam_heb200.pdf', 'CON-ST-IB-HEB200');

-- Reset product sequences to prevent collision on new additions
SELECT setval(pg_get_serial_sequence('tbl_product', 'p_id'), COALESCE(max(p_id), 1)) FROM tbl_product;
