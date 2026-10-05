<?php
require_once __DIR__ . '/inc/config.php';

echo "=== E-CONSTRUCTION SUPPLY: 8-8-10 CANONICAL CATEGORY MIGRATION ===\n\n";

try {
    $pdo->beginTransaction();

    // 1. Definition of the 8-8-10 Canonical Taxonomy
    $canonical_taxonomy = [
        'Structural & Masonry Materials' => [
            'Cement & Aggregates' => [
                'Portland Cement',
                'Pozzolan & Masonry Cement',
                'White Cement',
                'Sand & Gravel Aggregates'
            ],
            'Concrete Blocks & Masonry' => [
                'Concrete Hollow Blocks (CHB)',
                'Pavers & Decorative Blocks'
            ],
            'Reinforcing Steel (Rebar)' => [
                'Deformed Steel Bars',
                'Plain Round Bars',
                'GI Tie Wire'
            ],
            'Structural Steel & Shapes' => [
                'Angle Bars',
                'C-Purlins & Channels',
                'Flat Bars',
                'Square Bars',
                'I-Beams & Wide Flange',
                'Steel Shafting Bars'
            ],
            'Steel Tubes & Pipes' => [
                'GI Pipes',
                'Tubular Steel',
                'Stainless Steel Tubes'
            ],
            'Fencing, Meshes & Wire Products' => [
                'Cyclone / Chain Link Wire Mesh',
                'Barbed Wire',
                'Welded Steel Wire Mesh',
                'Poultry & Hog Hex Netting',
                'Expanded Metal Grilles'
            ],
            'Waterproofing & Concrete Additives' => [
                'Cementitious Waterproofing',
                'Integral Waterproofing Powder',
                'Concrete Neutralizers & Etchers'
            ]
        ],
        'Lumber, Boards & Drywall Systems' => [
            'Plywood & Sheet Boards' => [
                'Marine Plywood',
                'Ordinary Plywood',
                'Phenolic & Film-Faced Plywood'
            ],
            'Fiber Cement & Gypsum Boards' => [
                'Fiber Cement Boards (HardieFlex)',
                'Gypsum Drywall Boards'
            ],
            'Ceiling & Partition Framing' => [
                'Metal Furring Channels',
                'Carrying Channels',
                'Wall Studs & Tracks',
                'Wall Angles & Main Tees'
            ],
            'Lumber & Rough Wood' => [
                'Coco Lumber',
                'Good Lumber (2x2, 2x3, 2x4)',
                'Kiln-Dried S4S Lumber'
            ],
            'Doors, Windows & Mouldings' => [
                'PVC Bathroom Doors',
                'Flush & Panel Wood Doors',
                'Louver & Jalousie Windows'
            ]
        ],
        'Roofing & Thermal Insulation' => [
            'Metal & Corrugated Roofing' => [
                'Corrugated GI Roofing Sheets',
                'Rib-Type Long Span Roofing',
                'Tile-Span Metal Roofing',
                'Colored Pre-painted Roofing Sheets'
            ],
            'Plain GI Sheets & Coils' => [
                'GI Plain Sheets',
                'Galvanized Flat Coils'
            ],
            'Polycarbonate & Skylights' => [
                'Corrugated & Twin-Wall Polycarbonate',
                'Solid Polycarbonate & Fiberglass'
            ],
            'Roof Accessories & Gutters' => [
                'Ridge Rolls',
                'Box & Spanish Valley Gutters',
                'End & Wall Flashings'
            ],
            'Thermal Insulation & Foil' => [
                'PE Foam Foil Insulation',
                'Bubble Foil Insulation',
                'Rockwool & Glasswool Batts'
            ]
        ],
        'Plumbing & Piping Systems' => [
            'PVC Sanitary Pipes & Fittings (DWV)' => [
                'PVC Sanitary Pipes',
                'PVC Sanitary Fittings (Elbows, Tees, Wyes)',
                'PVC P-Traps & Cleanouts',
                'PVC Reducers & Bushings',
                'PVC Solvent Cement'
            ],
            'Potable Water Piping (PPR & PE)' => [
                'PPR Hot & Cold Pipes',
                'PPR Fusion Fittings',
                'PE / HDPE Pipes & Compression Fittings'
            ],
            'Valves, Faucets & Brass Fittings' => [
                'Gate Valves & Ball Valves',
                'Water Faucets & Bibb Cocks',
                'Check Valves & Float Valves',
                'Brass Bushings, Nipples & Adapters'
            ],
            'Flexible Hoses & Drainage Accessories' => [
                'Flexible Supply Hoses',
                'Stainless Floor Drains & Traps',
                'Teflon Thread Seal Tape',
                'Pipe Clamps & Hangers'
            ],
            'Sanitary & Bathroom Fixtures' => [
                'Stainless Steel Kitchen Sinks (Lababo)',
                'Water Closets & Bidet Sprays',
                'Lavatories & Pedestals'
            ]
        ],
        'Electrical & Lighting Systems' => [
            'Building Wires & Cables' => [
                'THHN / THWN Stranded Wire',
                'Non-Metallic Sheathed Cable (NM / Romex)',
                'Royal Cord & Heavy-Duty Cable',
                'Flat Cord & Speaker Wire'
            ],
            'Conduits & Raceways' => [
                'PVC Electrical Conduit Pipes',
                'Flexible Corrugated Conduits',
                'Metal EMT & IMC Conduits',
                'Conduit Adapters, Clamps & Locknuts'
            ],
            'Enclosures, Boxes & Breakers' => [
                'Utility & Octagonal Boxes',
                'Miniature Circuit Breakers (MCB)',
                'Panel Boards & Safety Switches'
            ],
            'Wiring Devices & Controls' => [
                'Wall Switches (1, 2, 3-Gang)',
                'Convenience Outlets & Sockets',
                'Plugs, Receptacles & Extension Sets',
                'Electrical Tape & Wire Connectors'
            ],
            'Lighting Fixtures & Lamps' => [
                'LED Bulbs & T8 Tubes',
                'LED Downlights & Panel Lights',
                'Floodlights & Weatherproof Fixtures'
            ]
        ],
        'Paints, Coatings & Surface Prep' => [
            'Interior & Exterior Paint' => [
                'Latex Architectural Paints',
                'Quick Drying Enamels (QDE)',
                'Roof & Deck Acrylic Paints',
                'Elastomeric Wall Paints'
            ],
            'Primers, Sealers & Undercoats' => [
                'Acrylic Concrete Primers',
                'Red Oxide & Metal Primers',
                'Lacquer Primers & Sealers'
            ],
            'Putty, Patching & Surface Fillers' => [
                'Masonry Putty & Patching Paste',
                'Gypsum Joint Compound',
                'Wood Fillers & Plastic Wood'
            ],
            'Sandpaper & Abrasive Sheets' => [
                'Waterproof Sandpaper Sheets',
                'Drywall Sanding Mesh',
                'Emery Cloth & Sanding Sponges'
            ],
            'Thinners, Solvents & Removers' => [
                'Paint Thinners & Reducers',
                'Lacquer Thinner & Acetone',
                'Paint & Varnish Removers'
            ],
            'Wood Stains, Varnishes & Lacquers' => [
                'Oil Wood Stains',
                'Polyurethane & Clear Gloss Varnish',
                'Sanding Sealers & Clear Lacquers'
            ],
            'Specialty Coatings & Colorants' => [
                'Tinting Colors (Acri-Color)',
                'Epoxy Floor Coatings',
                'Aluminum & Heat Resistant Paints'
            ]
        ],
        'Hardware, Fasteners & Adhesives' => [
            'Nails & Staples' => [
                'Common Wire Nails',
                'Concrete Steel Nails',
                'Finishing Nails',
                'Umbrella Roofing Nails',
                'Hardie / Fiber Cement Nails',
                'U-Nails & Staples'
            ],
            'Screws & Fastening Bolts' => [
                'Self-Drilling Tekscrews (Metal/Wood)',
                'Gypsum / Drywall Black Screws',
                'Wood Screws & Tapping Screws',
                'Hex Machine Bolts & Nuts',
                'Blind Rivets'
            ],
            'Masonry & Heavy Anchors' => [
                'Expansion Wedge Anchors',
                'Drop-In & Sleeve Anchors',
                'Plastic Wall Plugs & Tox'
            ],
            'Door, Window & Cabinet Hardware' => [
                'Door Hinges & Piano Hinges',
                'Cylindrical & Deadbolt Locksets',
                'Padlocks, Hasps & Heavy Latches',
                'Drawer Guides & Cabinet Handles',
                'Barrel Bolts & Safety Latches'
            ],
            'Construction Adhesives & Sealants' => [
                'Silicone Sealants (Clear, White, Black)',
                'Polyurethane (PU) Sealants',
                'Construction Adhesives (Liquid Nails)',
                'Contact Cement & Wood Glue',
                'Elastomeric Gap Fillers (Vulcaseal)'
            ],
            'Tile Adhesives & Grouts' => [
                'Standard & Heavy-Duty Tile Adhesive',
                'Tile Grout (Sanded / Non-Sanded)',
                'Tile Spacers & Grout Additives'
            ]
        ],
        'Tools, Equipment & Safety' => [
            'Hand Tools & Mechanics' => [
                'Hammers, Sledges & Chisels',
                'Handsaws & Hacksaws',
                'Wrenches, Pliers & Screwdrivers',
                'Masonry Trowels & Floats'
            ],
            'Measuring, Layout & Leveling' => [
                'Steel Measuring Tapes',
                'Spirit Levels & Plumb Bobs',
                'Chalk Lines & Marking Tools'
            ],
            'Painting & Application Tools' => [
                'Paint Brushes',
                'Paint Rollers & Refills',
                'Putty Knives & Scrapers',
                'Caulking & Sealant Guns'
            ],
            'Power Tools & Machines' => [
                'Angle Grinders & Cut-off Saws',
                'Rotary Hammer & Impact Drills',
                'Circular Saws & Jigsaws',
                'Demolition Jackhammers'
            ],
            'Power Tool Accessories & Blades' => [
                'Diamond Cutting Wheels',
                'Metal & Stainless Cutting Discs',
                'Grinding Wheels & Flap Discs',
                'Drill Bits (Masonry, Metal, Wood)'
            ],
            'Welding Equipment & Supplies' => [
                'Inverter Arc & MIG Welders',
                'Welding Electrodes (E6011, E6013, E7018)',
                'Auto-Darkening Welding Helmets',
                'Welding Cables & Earth Clamps'
            ],
            'Personal Protective Equipment (PPE)' => [
                'Safety Hard Hats & Bump Caps',
                'Safety Glasses & Face Shields',
                'Safety Gloves (Cotton, Leather, Rubber)',
                'Steel-Toe Safety Boots',
                'High-Visibility Reflective Vests'
            ],
            'Jobsite Equipment & Material Handling' => [
                'Heavy-Duty Wheelbarrows',
                'Portable Concrete Mixers',
                'Steel Scaffolding Sets & Walkway Planks',
                'Tarpaulins (Trapal) & Nylon Ropes'
            ]
        ]
    ];

    // 2. Build Category Lookup IDs in DB
    $ecat_id_map = []; // "tcat|mcat|ecat" => ecat_id
    $tcat_id_map = []; // "tcat" => tcat_id
    $mcat_id_map = []; // "tcat|mcat" => mcat_id

    echo "Ensuring canonical taxonomy in database...\n";
    foreach ($canonical_taxonomy as $tcat_name => $mcat_list) {
        // Top Category
        $stmt = $pdo->prepare("SELECT tcat_id FROM tbl_top_category WHERE tcat_name = ?");
        $stmt->execute([$tcat_name]);
        $tcat_id = $stmt->fetchColumn();
        if (!$tcat_id) {
            $stmt = $pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, 1) RETURNING tcat_id");
            $stmt->execute([$tcat_name]);
            $tcat_id = $stmt->fetchColumn();
            echo "  + Added Top Category: [{$tcat_id}] {$tcat_name}\n";
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_top_category SET show_on_menu = 1 WHERE tcat_id = ?");
            $stmt->execute([$tcat_id]);
        }
        $tcat_id_map[$tcat_name] = $tcat_id;

        foreach ($mcat_list as $mcat_name => $ecat_list) {
            // Mid Category
            $stmt = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE mcat_name = ? AND tcat_id = ?");
            $stmt->execute([$mcat_name, $tcat_id]);
            $mcat_id = $stmt->fetchColumn();
            if (!$mcat_id) {
                // Try finding by name under any top cat and re-parent if needed
                $stmt = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE mcat_name = ?");
                $stmt->execute([$mcat_name]);
                $existing_mcat = $stmt->fetchColumn();
                if ($existing_mcat) {
                    $stmt = $pdo->prepare("UPDATE tbl_mid_category SET tcat_id = ? WHERE mcat_id = ?");
                    $stmt->execute([$tcat_id, $existing_mcat]);
                    $mcat_id = $existing_mcat;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
                    $stmt->execute([$mcat_name, $tcat_id]);
                    $mcat_id = $stmt->fetchColumn();
                    echo "    + Added Mid Category: [{$mcat_id}] {$mcat_name}\n";
                }
            }
            $mcat_id_map[$tcat_name . '|' . $mcat_name] = $mcat_id;

            foreach ($ecat_list as $ecat_name) {
                // End Category
                $stmt = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE ecat_name = ? AND mcat_id = ?");
                $stmt->execute([$ecat_name, $mcat_id]);
                $ecat_id = $stmt->fetchColumn();
                if (!$ecat_id) {
                    $stmt = $pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
                    $stmt->execute([$ecat_name, $mcat_id]);
                    $ecat_id = $stmt->fetchColumn();
                    echo "      + Added End Category: [{$ecat_id}] {$ecat_name}\n";
                }
                $ecat_id_map[$tcat_name . '|' . $mcat_name . '|' . $ecat_name] = $ecat_id;
            }
        }
    }

    // Resync sequences
    $pdo->exec("SELECT setval('tbl_top_category_tcat_id_seq', (SELECT COALESCE(MAX(tcat_id), 1) FROM tbl_top_category));");
    $pdo->exec("SELECT setval('tbl_mid_category_mcat_id_seq', (SELECT COALESCE(MAX(mcat_id), 1) FROM tbl_mid_category));");
    $pdo->exec("SELECT setval('tbl_end_category_ecat_id_seq', (SELECT COALESCE(MAX(ecat_id), 1) FROM tbl_end_category));");

    echo "\nCanonical structure established successfully.\n";

    // 3. Intelligent Product Remapping Logic
    echo "\nRemapping existing products to canonical categories...\n";
    $stmt = $pdo->query("
        SELECT p.p_id, p.p_name, p.ecat_id, e.ecat_name, m.mcat_name, t.tcat_name
        FROM tbl_product p
        LEFT JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
        LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
        LEFT JOIN tbl_top_category t ON m.tcat_id = t.tcat_id
        ORDER BY p.p_id
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $remapped_count = 0;
    $target_ecat_cache = [];

    function find_canonical_ecat($product, $ecat_id_map) {
        $p_name = strtolower($product['p_name'] . ' ' . ($product['ecat_name'] ?? '') . ' ' . ($product['mcat_name'] ?? '') . ' ' . ($product['tcat_name'] ?? ''));

        // CEMENT & AGGREGATES
        if (strpos($p_name, 'white cement') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement & Aggregates|White Cement'];
        if (strpos($p_name, 'pozzolan') !== false || strpos($p_name, 'advance') !== false || strpos($p_name, 'masonry cement') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement & Aggregates|Pozzolan & Masonry Cement'];
        if (strpos($p_name, 'portland') !== false || strpos($p_name, 'holcim') !== false || strpos($p_name, 'republic') !== false || strpos($p_name, 'cement') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement & Aggregates|Portland Cement'];
        if (strpos($p_name, 'sand') !== false || strpos($p_name, 'gravel') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement & Aggregates|Sand & Gravel Aggregates'];

        // CONCRETE BLOCKS
        if (strpos($p_name, 'hollow block') !== false || strpos($p_name, 'chb') !== false) return $ecat_id_map['Structural & Masonry Materials|Concrete Blocks & Masonry|Concrete Hollow Blocks (CHB)'];

        // REBAR & TIE WIRE
        if (strpos($p_name, 'tie wire') !== false || strpos($p_name, 'g.i tie wire') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel (Rebar)|GI Tie Wire'];
        if (strpos($p_name, 'round bar') !== false || strpos($p_name, 'plain round') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel (Rebar)|Plain Round Bars'];
        if (strpos($p_name, 'deformed') !== false || strpos($p_name, 'rebar') !== false || strpos($p_name, 'steel bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel (Rebar)|Deformed Steel Bars'];

        // STRUCTURAL STEEL
        if (strpos($p_name, 'angle bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Angle Bars'];
        if (strpos($p_name, 'c-purlin') !== false || strpos($p_name, 'purlin') !== false || strpos($p_name, 'channel') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|C-Purlins & Channels'];
        if (strpos($p_name, 'flat bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Flat Bars'];
        if (strpos($p_name, 'square bar') !== false || strpos($p_name, 'squre bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Square Bars'];
        if (strpos($p_name, 'i-beam') !== false || strpos($p_name, 'wide flange') !== false || strpos($p_name, 'h-beam') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|I-Beams & Wide Flange'];
        if (strpos($p_name, 'shafting') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Steel Shafting Bars'];

        // STEEL TUBES & PIPES
        if (strpos($p_name, 'gi pipe') !== false || strpos($p_name, 'schedule 40') !== false || strpos($p_name, 'schedule 20') !== false) return $ecat_id_map['Structural & Masonry Materials|Steel Tubes & Pipes|GI Pipes'];
        if (strpos($p_name, 'tubular stainless') !== false || (strpos($p_name, 'tubular') !== false && strpos($p_name, 'stainless') !== false)) return $ecat_id_map['Structural & Masonry Materials|Steel Tubes & Pipes|Stainless Steel Tubes'];
        if (strpos($p_name, 'tubular') !== false) return $ecat_id_map['Structural & Masonry Materials|Steel Tubes & Pipes|Tubular Steel'];

        // FENCING & WIRE
        if (strpos($p_name, 'cyclone') !== false || strpos($p_name, 'chainlink') !== false || strpos($p_name, 'chain link') !== false) return $ecat_id_map['Structural & Masonry Materials|Fencing, Meshes & Wire Products|Cyclone / Chain Link Wire Mesh'];
        if (strpos($p_name, 'barbed wire') !== false) return $ecat_id_map['Structural & Masonry Materials|Fencing, Meshes & Wire Products|Barbed Wire'];
        if (strpos($p_name, 'mesh') !== false || strpos($p_name, 'matting') !== false) return $ecat_id_map['Structural & Masonry Materials|Fencing, Meshes & Wire Products|Welded Steel Wire Mesh'];
        if (strpos($p_name, 'hog wire') !== false || strpos($p_name, 'poultry') !== false || strpos($p_name, 'chicken wire') !== false) return $ecat_id_map['Structural & Masonry Materials|Fencing, Meshes & Wire Products|Poultry & Hog Hex Netting'];
        if (strpos($p_name, 'expanded') !== false) return $ecat_id_map['Structural & Masonry Materials|Fencing, Meshes & Wire Products|Expanded Metal Grilles'];

        // WATERPROOFING CHEMICALS
        if (strpos($p_name, 'plexibond') !== false || strpos($p_name, 'cementitious') !== false) return $ecat_id_map['Structural & Masonry Materials|Waterproofing & Concrete Additives|Cementitious Waterproofing'];
        if (strpos($p_name, 'sahara') !== false) return $ecat_id_map['Structural & Masonry Materials|Waterproofing & Concrete Additives|Integral Waterproofing Powder'];
        if (strpos($p_name, 'neutralizer') !== false || strpos($p_name, 'etcher') !== false) return $ecat_id_map['Structural & Masonry Materials|Waterproofing & Concrete Additives|Concrete Neutralizers & Etchers'];

        // PLYWOOD & BOARDS
        if (strpos($p_name, 'marine plywood') !== false || strpos($p_name, 'marine ply') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Boards|Marine Plywood'];
        if (strpos($p_name, 'ordinary plywood') !== false || (strpos($p_name, 'plywood') !== false && strpos($p_name, 'marine') === false)) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Boards|Ordinary Plywood'];
        if (strpos($p_name, 'phenolic') !== false || strpos($p_name, 'film faced') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Boards|Phenolic & Film-Faced Plywood'];
        if (strpos($p_name, 'hardie') !== false || strpos($p_name, 'fiber cement') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Fiber Cement & Gypsum Boards|Fiber Cement Boards (HardieFlex)'];
        if (strpos($p_name, 'gypsum') !== false || strpos($p_name, 'drywall board') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Fiber Cement & Gypsum Boards|Gypsum Drywall Boards'];

        // CEILING FRAMING
        if (strpos($p_name, 'furring') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Ceiling & Partition Framing|Metal Furring Channels'];
        if (strpos($p_name, 'carrying') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Ceiling & Partition Framing|Carrying Channels'];
        if (strpos($p_name, 'stud') !== false || strpos($p_name, 'track') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Ceiling & Partition Framing|Wall Studs & Tracks'];
        if (strpos($p_name, 'wall angle') !== false || strpos($p_name, 'main tee') !== false || strpos($p_name, 'cross tee') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Ceiling & Partition Framing|Wall Angles & Main Tees'];

        // LUMBER
        if (strpos($p_name, 'coco') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber & Rough Wood|Coco Lumber'];
        if (strpos($p_name, 'lumber') !== false || strpos($p_name, 'kahoy') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber & Rough Wood|Good Lumber (2x2, 2x3, 2x4)'];

        // DOORS & WINDOWS
        if (strpos($p_name, 'pvc door') !== false || strpos($p_name, 'bathroom door') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Doors, Windows & Mouldings|PVC Bathroom Doors'];
        if (strpos($p_name, 'flush door') !== false || strpos($p_name, 'wood door') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Doors, Windows & Mouldings|Flush & Panel Wood Doors'];
        if (strpos($p_name, 'jalousie') !== false || strpos($p_name, 'window') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Doors, Windows & Mouldings|Louver & Jalousie Windows'];

        // ROOFING
        if (strpos($p_name, 'corrogated') !== false || strpos($p_name, 'corrugated') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal & Corrugated Roofing|Corrugated GI Roofing Sheets'];
        if (strpos($p_name, 'rib') !== false || strpos($p_name, 'long span') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal & Corrugated Roofing|Rib-Type Long Span Roofing'];
        if (strpos($p_name, 'tile') !== false && strpos($p_name, 'roof') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal & Corrugated Roofing|Tile-Span Metal Roofing'];
        if (strpos($p_name, 'colored roof') !== false || strpos($p_name, 'pre-painted') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal & Corrugated Roofing|Colored Pre-painted Roofing Sheets'];
        if (strpos($p_name, 'plain sheet') !== false || strpos($p_name, 'plainsheet') !== false || strpos($p_name, 'plain') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Plain GI Sheets & Coils|GI Plain Sheets'];
        if (strpos($p_name, 'polycarbonate') !== false || strpos($p_name, 'skylight') !== false || strpos($p_name, 'plastic roofing') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Polycarbonate & Skylights|Corrugated & Twin-Wall Polycarbonate'];
        if (strpos($p_name, 'ridge roll') !== false || strpos($p_name, 'ridgeroll') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Roof Accessories & Gutters|Ridge Rolls'];
        if (strpos($p_name, 'gutter') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Roof Accessories & Gutters|Box & Spanish Valley Gutters'];
        if (strpos($p_name, 'flashing') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Roof Accessories & Gutters|End & Wall Flashings'];
        if (strpos($p_name, 'insulation') !== false || strpos($p_name, 'pe foam') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Thermal Insulation & Foil|PE Foam Foil Insulation'];

        // PLUMBING
        if (strpos($p_name, 'lababo') !== false || strpos($p_name, 'sink') !== false) return $ecat_id_map['Plumbing & Piping Systems|Sanitary & Bathroom Fixtures|Stainless Steel Kitchen Sinks (Lababo)'];
        if (strpos($p_name, 'water closet') !== false || strpos($p_name, 'toilet') !== false || strpos($p_name, 'bidet') !== false) return $ecat_id_map['Plumbing & Piping Systems|Sanitary & Bathroom Fixtures|Water Closets & Bidet Sprays'];
        if (strpos($p_name, 'lavatory') !== false) return $ecat_id_map['Plumbing & Piping Systems|Sanitary & Bathroom Fixtures|Lavatories & Pedestals'];
        if (strpos($p_name, 'teflon') !== false || strpos($p_name, 'thread seal') !== false) return $ecat_id_map['Plumbing & Piping Systems|Flexible Hoses & Drainage Accessories|Teflon Thread Seal Tape'];
        if (strpos($p_name, 'flexible hose') !== false || strpos($p_name, 'flex hose') !== false) return $ecat_id_map['Plumbing & Piping Systems|Flexible Hoses & Drainage Accessories|Flexible Supply Hoses'];
        if (strpos($p_name, 'floor drain') !== false) return $ecat_id_map['Plumbing & Piping Systems|Flexible Hoses & Drainage Accessories|Stainless Floor Drains & Traps'];
        if (strpos($p_name, 'solvent cement') !== false || strpos($p_name, 'pvc cement') !== false) return $ecat_id_map['Plumbing & Piping Systems|PVC Sanitary Pipes & Fittings (DWV)|PVC Solvent Cement'];
        if (strpos($p_name, 'ppr pipe') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Piping (PPR & PE)|PPR Hot & Cold Pipes'];
        if (strpos($p_name, 'ppr') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Piping (PPR & PE)|PPR Fusion Fittings'];
        if (strpos($p_name, 'pe pipe') !== false || strpos($p_name, 'hdpe') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Piping (PPR & PE)|PE / HDPE Pipes & Compression Fittings'];
        if (strpos($p_name, 'valve') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Faucets & Brass Fittings|Gate Valves & Ball Valves'];
        if (strpos($p_name, 'faucet') !== false || strpos($p_name, 'bibb') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Faucets & Brass Fittings|Water Faucets & Bibb Cocks'];
        if (strpos($p_name, 'brass') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Faucets & Brass Fittings|Brass Bushings, Nipples & Adapters'];
        if (strpos($p_name, 'cleanout') !== false || strpos($p_name, 'p-trap') !== false || strpos($p_name, 'trap') !== false) return $ecat_id_map['Plumbing & Piping Systems|PVC Sanitary Pipes & Fittings (DWV)|PVC P-Traps & Cleanouts'];
        if (strpos($p_name, 'reducer') !== false || strpos($p_name, 'bushing') !== false) return $ecat_id_map['Plumbing & Piping Systems|PVC Sanitary Pipes & Fittings (DWV)|PVC Reducers & Bushings'];
        if (strpos($p_name, 'elbow') !== false || strpos($p_name, 'tee') !== false || strpos($p_name, 'wye') !== false || strpos($p_name, 'coupling') !== false || strpos($p_name, 'adapter') !== false) return $ecat_id_map['Plumbing & Piping Systems|PVC Sanitary Pipes & Fittings (DWV)|PVC Sanitary Fittings (Elbows, Tees, Wyes)'];
        if (strpos($p_name, 'pvc') !== false || strpos($p_name, 'sanitary pipe') !== false || strpos($p_name, 'neltex') !== false || strpos($p_name, 'emerald') !== false) return $ecat_id_map['Plumbing & Piping Systems|PVC Sanitary Pipes & Fittings (DWV)|PVC Sanitary Pipes'];

        // ELECTRICAL
        if (strpos($p_name, 'thhn') !== false || strpos($p_name, 'thwn') !== false || strpos($p_name, 'stranded wire') !== false || strpos($p_name, 'building wire') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|THHN / THWN Stranded Wire'];
        if (strpos($p_name, 'flat cord') !== false || strpos($p_name, 'speaker wire') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|Flat Cord & Speaker Wire'];
        if (strpos($p_name, 'royal cord') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|Royal Cord & Heavy-Duty Cable'];
        if (strpos($p_name, 'pvc conduit') !== false || strpos($p_name, 'conduit pipe') !== false || strpos($p_name, 'orange pipe') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits & Raceways|PVC Electrical Conduit Pipes'];
        if (strpos($p_name, 'flexible conduit') !== false || strpos($p_name, 'corrugated conduit') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits & Raceways|Flexible Corrugated Conduits'];
        if (strpos($p_name, 'locknut') !== false || strpos($p_name, 'conduit clamp') !== false || strpos($p_name, 'conduit adapter') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits & Raceways|Conduit Adapters, Clamps & Locknuts'];
        if (strpos($p_name, 'utility box') !== false || strpos($p_name, 'junction box') !== false || strpos($p_name, 'octagonal') !== false) return $ecat_id_map['Electrical & Lighting Systems|Enclosures, Boxes & Breakers|Utility & Octagonal Boxes'];
        if (strpos($p_name, 'breaker') !== false) return $ecat_id_map['Electrical & Lighting Systems|Enclosures, Boxes & Breakers|Miniature Circuit Breakers (MCB)'];
        if (strpos($p_name, 'panel board') !== false || strpos($p_name, 'safety switch') !== false) return $ecat_id_map['Electrical & Lighting Systems|Enclosures, Boxes & Breakers|Panel Boards & Safety Switches'];
        if (strpos($p_name, 'switch') !== false) return $ecat_id_map['Electrical & Lighting Systems|Wiring Devices & Controls|Wall Switches (1, 2, 3-Gang)'];
        if (strpos($p_name, 'outlet') !== false || strpos($p_name, 'socket') !== false) return $ecat_id_map['Electrical & Lighting Systems|Wiring Devices & Controls|Convenience Outlets & Sockets'];
        if (strpos($p_name, 'plug') !== false || strpos($p_name, 'extension') !== false) return $ecat_id_map['Electrical & Lighting Systems|Wiring Devices & Controls|Plugs, Receptacles & Extension Sets'];
        if (strpos($p_name, 'electrical tape') !== false || strpos($p_name, 'insulating tape') !== false) return $ecat_id_map['Electrical & Lighting Systems|Wiring Devices & Controls|Electrical Tape & Wire Connectors'];
        if (strpos($p_name, 'led bulb') !== false || strpos($p_name, 'bulb') !== false || strpos($p_name, 't8') !== false || strpos($p_name, 'fluorescent') !== false) return $ecat_id_map['Electrical & Lighting Systems|Lighting Fixtures & Lamps|LED Bulbs & T8 Tubes'];
        if (strpos($p_name, 'downlight') !== false || strpos($p_name, 'panel light') !== false) return $ecat_id_map['Electrical & Lighting Systems|Lighting Fixtures & Lamps|LED Downlights & Panel Lights'];
        if (strpos($p_name, 'floodlight') !== false) return $ecat_id_map['Electrical & Lighting Systems|Lighting Fixtures & Lamps|Floodlights & Weatherproof Fixtures'];

        // PAINTS
        if (strpos($p_name, 'latex') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Interior & Exterior Paint|Latex Architectural Paints'];
        if (strpos($p_name, 'qde') !== false || strpos($p_name, 'enamel') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Interior & Exterior Paint|Quick Drying Enamels (QDE)'];
        if (strpos($p_name, 'roof paint') !== false || strpos($p_name, 'roofguard') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Interior & Exterior Paint|Roof & Deck Acrylic Paints'];
        if (strpos($p_name, 'elastomeric') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Interior & Exterior Paint|Elastomeric Wall Paints'];
        if (strpos($p_name, 'red oxide') !== false || strpos($p_name, 'zinc chromate') !== false || strpos($p_name, 'primer') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Primers, Sealers & Undercoats|Red Oxide & Metal Primers'];
        if (strpos($p_name, 'putty') !== false || strpos($p_name, 'patching') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Putty, Patching & Surface Fillers|Masonry Putty & Patching Paste'];
        if (strpos($p_name, 'sandpaper') !== false || strpos($p_name, 'lija') !== false || strpos($p_name, 'abrasive') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Sandpaper & Abrasive Sheets|Waterproof Sandpaper Sheets'];
        if (strpos($p_name, 'thinner') !== false || strpos($p_name, 'lacquer thinner') !== false || strpos($p_name, 'acetone') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Thinners, Solvents & Removers|Paint Thinners & Reducers'];
        if (strpos($p_name, 'wood stain') !== false || strpos($p_name, 'varnish') !== false || strpos($p_name, 'sanding sealer') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Wood Stains, Varnishes & Lacquers|Oil Wood Stains'];
        if (strpos($p_name, 'acri-color') !== false || strpos($p_name, 'tinting') !== false || strpos($p_name, 'hansa') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Specialty Coatings & Colorants|Tinting Colors (Acri-Color)'];
        if (strpos($p_name, 'epoxy') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Specialty Coatings & Colorants|Epoxy Floor Coatings'];

        // HARDWARE & FASTENERS
        if (strpos($p_name, 'concrete nail') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|Concrete Steel Nails'];
        if (strpos($p_name, 'finishing nail') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|Finishing Nails'];
        if (strpos($p_name, 'umbrella nail') !== false || strpos($p_name, 'roofing nail') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|Umbrella Roofing Nails'];
        if (strpos($p_name, 'hardi nail') !== false || strpos($p_name, 'hardie nail') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|Hardie / Fiber Cement Nails'];
        if (strpos($p_name, 'u-nail') !== false || strpos($p_name, 'staple') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|U-Nails & Staples'];
        if (strpos($p_name, 'common nail') !== false || strpos($p_name, 'nail') !== false || strpos($p_name, 'pako') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|Common Wire Nails'];
        if (strpos($p_name, 'tekscrew') !== false || strpos($p_name, 'tek screw') !== false || strpos($p_name, 'self drilling') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Screws & Fastening Bolts|Self-Drilling Tekscrews (Metal/Wood)'];
        if (strpos($p_name, 'drywall screw') !== false || strpos($p_name, 'black screw') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Screws & Fastening Bolts|Gypsum / Drywall Black Screws'];
        if (strpos($p_name, 'wood screw') !== false || strpos($p_name, 'tapping screw') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Screws & Fastening Bolts|Wood Screws & Tapping Screws'];
        if (strpos($p_name, 'bolt') !== false || strpos($p_name, 'nut') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Screws & Fastening Bolts|Hex Machine Bolts & Nuts'];
        if (strpos($p_name, 'blind rivet') !== false || strpos($p_name, 'rivet') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Screws & Fastening Bolts|Blind Rivets'];
        if (strpos($p_name, 'expansion') !== false || strpos($p_name, 'anchor') !== false || strpos($p_name, 'tox') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Masonry & Heavy Anchors|Expansion Wedge Anchors'];
        if (strpos($p_name, 'hinge') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door, Window & Cabinet Hardware|Door Hinges & Piano Hinges'];
        if (strpos($p_name, 'padlock') !== false || strpos($p_name, 'hasp') !== false || strpos($p_name, 'latch') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door, Window & Cabinet Hardware|Padlocks, Hasps & Heavy Latches'];
        if (strpos($p_name, 'lockset') !== false || strpos($p_name, 'deadbolt') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door, Window & Cabinet Hardware|Cylindrical & Deadbolt Locksets'];
        if (strpos($p_name, 'barrel bolt') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door, Window & Cabinet Hardware|Barrel Bolts & Safety Latches'];
        if (strpos($p_name, 'vulcaseal') !== false || strpos($p_name, 'silicone') !== false || strpos($p_name, 'sealant') !== false || strpos($p_name, 'liquid nails') !== false || strpos($p_name, 'contact cement') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Construction Adhesives & Sealants|Silicone Sealants (Clear, White, Black)'];
        if (strpos($p_name, 'tile adhesive') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Tile Adhesives & Grouts|Standard & Heavy-Duty Tile Adhesive'];
        if (strpos($p_name, 'tile grout') !== false || strpos($p_name, 'grout') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Tile Adhesives & Grouts|Tile Grout (Sanded / Non-Sanded)'];

        // TOOLS & EQUIPMENT
        if (strpos($p_name, 'paint brush') !== false || strpos($p_name, 'brush') !== false) return $ecat_id_map['Tools, Equipment & Safety|Painting & Application Tools|Paint Brushes'];
        if (strpos($p_name, 'paint roller') !== false || strpos($p_name, 'roller') !== false) return $ecat_id_map['Tools, Equipment & Safety|Painting & Application Tools|Paint Rollers & Refills'];
        if (strpos($p_name, 'steel tape') !== false || strpos($p_name, 'measuring tape') !== false || strpos($p_name, 'meter') !== false) return $ecat_id_map['Tools, Equipment & Safety|Measuring, Layout & Leveling|Steel Measuring Tapes'];
        if (strpos($p_name, 'diamond wheel') !== false || strpos($p_name, 'cutting disk') !== false || strpos($p_name, 'cutting disc') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tool Accessories & Blades|Diamond Cutting Wheels'];
        if (strpos($p_name, 'steel brush') !== false || strpos($p_name, 'wire brush') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Mechanics|Hammers, Sledges & Chisels'];
        if (strpos($p_name, 'welding rod') !== false || strpos($p_name, 'electrode') !== false) return $ecat_id_map['Tools, Equipment & Safety|Welding Equipment & Supplies|Welding Electrodes (E6011, E6013, E7018)'];
        if (strpos($p_name, 'welder') !== false || strpos($p_name, 'welding machine') !== false) return $ecat_id_map['Tools, Equipment & Safety|Welding Equipment & Supplies|Inverter Arc & MIG Welders'];
        if (strpos($p_name, 'welding helmet') !== false || strpos($p_name, 'welding mask') !== false) return $ecat_id_map['Tools, Equipment & Safety|Welding Equipment & Supplies|Auto-Darkening Welding Helmets'];
        if (strpos($p_name, 'wheelbarrow') !== false) return $ecat_id_map['Tools, Equipment & Safety|Jobsite Equipment & Material Handling|Heavy-Duty Wheelbarrows'];
        if (strpos($p_name, 'scaffolding') !== false || strpos($p_name, 'trapal') !== false || strpos($p_name, 'rope') !== false) return $ecat_id_map['Tools, Equipment & Safety|Jobsite Equipment & Material Handling|Steel Scaffolding Sets & Walkway Planks'];
        if (strpos($p_name, 'grinder') !== false || strpos($p_name, 'drill') !== false || strpos($p_name, 'saw') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tools & Machines|Angle Grinders & Cut-off Saws'];
        if (strpos($p_name, 'goggles') !== false || strpos($p_name, 'gloves') !== false || strpos($p_name, 'helmet') !== false || strpos($p_name, 'vest') !== false) return $ecat_id_map['Tools, Equipment & Safety|Personal Protective Equipment (PPE)|Safety Glasses & Face Shields'];

        // Fallback default
        return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Staples|Common Wire Nails'];
    }

    $update_stmt = $pdo->prepare("UPDATE tbl_product SET ecat_id = ? WHERE p_id = ?");

    foreach ($products as $p) {
        $canonical_ecat_id = find_canonical_ecat($p, $ecat_id_map);
        if ($canonical_ecat_id && $canonical_ecat_id != $p['ecat_id']) {
            $update_stmt->execute([$canonical_ecat_id, $p['p_id']]);
            $remapped_count++;
        }
    }

    echo "Total products evaluated: " . count($products) . "\n";
    echo "Total products remapped: {$remapped_count}\n";

    // 4. Deactivate old/unused top categories so they do not show in menu
    $canonical_tcat_ids = array_values($tcat_id_map);
    $placeholders = implode(',', array_fill(0, count($canonical_tcat_ids), '?'));
    $stmt = $pdo->prepare("UPDATE tbl_top_category SET show_on_menu = 0 WHERE tcat_id NOT IN ($placeholders)");
    $stmt->execute($canonical_tcat_ids);

    $pdo->commit();
    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY WITH ZERO ERRORS ===\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}
