<?php
require_once __DIR__ . '/inc/config.php';

echo "=== E-CONSTRUCTION SUPPLY: 8x3x5 CANONICAL CATEGORY MIGRATION ===\n\n";

try {
    $pdo->beginTransaction();

    // 1. Definition of the 8x3x5 Canonical Taxonomy
    // 8 Departments, exactly 3 Mid Categories each, exactly 5 End Categories each (120 End Categories total)
    $canonical_taxonomy = [
        'Plumbing & Piping Systems' => [
            'Drainage & Sanitary (DWV)' => [
                'PVC Sanitary Pipes (Orange/Grey)',
                'PVC Sanitary Elbows, Tees & Wyes',
                'PVC P-Traps, Cleanouts & Bushings',
                'Floor Drains, Traps & Gratings',
                'PVC Solvent Cements & Primers'
            ],
            'Potable Water Supply (PPR, PE, GI)' => [
                'PPR Hot & Cold Water Pipes',
                'PPR Heat-Fusion Fittings',
                'PE / HDPE Pipes & Compression Fittings',
                'GI Threaded Pipes & Malleable Fittings',
                'Brass Bushings, Nipples & Adapters'
            ],
            'Valves, Fixtures & Water Control' => [
                'Gate Valves, Ball Valves & Check Valves',
                'Water Faucets, Bibcocks & Hose Bibs',
                'Flexible Supply Hoses & Angle Valves',
                'Stainless Sinks (Lababo) & Bathroom Fixtures',
                'Teflon Thread Tapes & Pipe Clamps'
            ]
        ],
        'Structural & Masonry Materials' => [
            'Cement, Aggregates & Masonry' => [
                'Portland, Pozzolan & Masonry Cement',
                'Concrete Hollow Blocks (CHB) & Pavers',
                'Sand, Gravel & Aggregates',
                'Cementitious Waterproofing Compounds',
                'Concrete Additives & Accelerators'
            ],
            'Reinforcing Steel & Wire Products' => [
                'Deformed Steel Bars (Rebar Grade 33/40)',
                'Plain Round Steel Bars & Dowels',
                'GI Tie Wire (Gauge #16)',
                'Cyclone & Chain Link Mesh',
                'Welded Wire Mesh & Barbed Wire'
            ],
            'Structural Steel & Shapes' => [
                'Angle Bars (Equal & Unequal)',
                'C-Purlins & Steel Channels',
                'Flat Bars & Square Bars',
                'Steel Tubes, Tubulars & GI Pipes',
                'Wide Flange, I-Beams & Shafting'
            ]
        ],
        'Electrical & Lighting Systems' => [
            'Building Wires & Cables' => [
                'THHN / THWN Stranded Copper Wire',
                'Non-Metallic Sheathed Cable (Romex/NM)',
                'Royal Cord & Heavy-Duty Flexible Wire',
                'Flat Cord & Speaker Wire',
                'Telephone & Data Network Cables'
            ],
            'Conduits, Boxes & Fittings' => [
                'PVC Electrical Conduit Pipes & Fittings',
                'Flexible Corrugated Hose Conduits',
                'Metal EMT & IMC Conduits & Clamps',
                'Utility Boxes & Octagonal Junction Boxes',
                'Electrical Tape, Wire Nuts & Connectors'
            ],
            'Devices, Breakers & Lighting' => [
                'Miniature Circuit Breakers (MCB) & Panels',
                'Safety Switches & Knife Switches',
                'Wall Switches (1, 2, 3-Gang) & Dimmers',
                'Convenience Wall Outlets & Sockets',
                'LED Bulbs, Downlights & T8 Tubes'
            ]
        ],
        'Hardware, Fasteners & Adhesives' => [
            'Nails & Construction Screws' => [
                'Common Wire Nails & Finishing Nails',
                'Concrete Steel Nails',
                'Umbrella Roofing Nails',
                'Self-Drilling Tekscrews (Metal & Wood)',
                'Drywall Black Screws & Wood Screws'
            ],
            'Bolts, Anchors & Fastening Hardware' => [
                'Hex Machine Bolts, Studs & Nuts',
                'Masonry Wedge & Expansion Anchors',
                'Plastic Tox Plugs & Wall Anchors',
                'Blind Rivets & Pop Rivets',
                'Threaded Rods & U-Bolts'
            ],
            'Door Hardware & Construction Adhesives' => [
                'Door Hinges, Butt Hinges & Piano Hinges',
                'Cylindrical Locksets & Deadbolts',
                'Padlocks, Hasps & Heavy Latches',
                'Silicone, PU Sealants & Vulcaseal',
                'Contact Cement, Wood Glue & Tile Adhesive'
            ]
        ],
        'Paints, Coatings & Surface Prep' => [
            'Architectural Paints & Coatings' => [
                'Latex Interior & Exterior Paints',
                'Quick Drying Enamels (QDE)',
                'Roof Acrylic & Deck Paints',
                'Elastomeric Waterproofing Paints',
                'Tinting Colors (Acri-Color & Oil Tint)'
            ],
            'Primers, Sealers & Stains' => [
                'Concrete Acrylic Primers & Sealers',
                'Red Oxide & Epoxy Metal Primers',
                'Wood Sanding Sealers & Lacquer Undercoats',
                'Clear Gloss Varnishes & Polyurethane',
                'Oil-Based Wood Stains & Preservatives'
            ],
            'Surface Preparation & Solvents' => [
                'Waterproof Sandpaper Sheets (Grit 60–1000)',
                'Drywall Sanding Mesh & Emery Cloth',
                'Masonry Putty & Patching Paste',
                'Gypsum Joint Compound & Fillers',
                'Paint Thinners, Lacquer Thinner & Acetone'
            ]
        ],
        'Tools, Equipment & Safety' => [
            'Hand Tools & Layout Measuring' => [
                'Hammers, Sledges, Chisels & Crowbars',
                'Handsaws, Hacksaws & Replacement Blades',
                'Wrenches, Pliers, Screwdrivers & Sockets',
                'Masonry Trowels, Floats & Scrapers',
                'Measuring Tapes, Spirit Levels & Plumb Bobs'
            ],
            'Power Tool Accessories & Welding' => [
                'Diamond Concrete & Tile Cutting Wheels',
                'Metal & Stainless Cutting Discs',
                'Grinding Wheels & Flap Sanding Discs',
                'Drill Bits (Masonry, Metal, Wood)',
                'Welding Electrodes (E6011/E6013), Clamps & Masks'
            ],
            'Painting Tools & Jobsite Safety (PPE)' => [
                'Paint Brushes (1" to 4")',
                'Paint Rollers, Trays & Extension Poles',
                'Caulking Guns & Putty Knives',
                'Safety Hard Hats, Goggles & Face Shields',
                'Work Gloves (Cotton, Rubber, Leather) & Vests'
            ]
        ],
        'Roofing & Thermal Insulation' => [
            'Metal Roofing Sheets' => [
                'Corrugated GI Roofing Sheets',
                'Long Span Rib-Type Roofing',
                'Tile-Span Metal Roofing',
                'Pre-Painted Colored Roof Sheets',
                'Translucent & Polycarbonate Skylights'
            ],
            'Plain GI Sheets & Gutters' => [
                'Plain GI Sheets (Gauge 24/26)',
                'Galvanized Flat Coils & Straps',
                'Ridge Rolls & Valley Gutters',
                'End Flashings & Wall Flashings',
                'Downspouts & Gutter Accessories'
            ],
            'Thermal Insulation & Moisture Barriers' => [
                'PE Foam Aluminum Foil Insulation',
                'Bubble Foil Radiant Barriers',
                'Glasswool & Rockwool Insulation Batts',
                'Roof Sealant Tapes & Bituminous Strips',
                'Asphalt Saturated Roofing Felt Paper'
            ]
        ],
        'Lumber, Boards & Drywall Systems' => [
            'Plywood & Sheet Panels' => [
                'Marine Plywood (1/4", 1/2", 3/4")',
                'Ordinary & Interior Plywood',
                'Phenolic & Film-Faced Form Plywood',
                'Particle Boards & MDF Sheets',
                'Hardwood & Melamine Laminated Boards'
            ],
            'Drywall Boards & Metal Framing' => [
                'Fiber Cement Boards (HardieFlex 3.5mm–12mm)',
                'Gypsum Drywall Boards (9mm, 12mm)',
                'Metal Furring Channels & Carrying Channels',
                'Wall Studs & Track Channels',
                'Wall Angles, Main Tees & Cross Tees'
            ],
            'Lumber, Doors & Mouldings' => [
                'Coco Rough Lumber (2x2, 2x3, 2x4)',
                'Good Lumber (S4S Kiln-Dried)',
                'PVC Waterproof Bathroom Doors',
                'Flush & Wood Panel Doors',
                'Louver Jalousie Windows & Wood Mouldings'
            ]
        ]
    ];

    // 2. Build Category Lookup IDs in DB
    $ecat_id_map = []; // "tcat|mcat|ecat" => ecat_id
    $tcat_id_map = []; // "tcat" => tcat_id
    $mcat_id_map = []; // "tcat|mcat" => mcat_id

    echo "Ensuring 8x3x5 canonical taxonomy in database...\n";
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
                $stmt = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
                $stmt->execute([$mcat_name, $tcat_id]);
                $mcat_id = $stmt->fetchColumn();
                echo "    + Added Mid Category: [{$mcat_id}] {$mcat_name}\n";
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

    echo "\nCanonical 8x3x5 structure established successfully (" . count($ecat_id_map) . " End Categories mapped).\n";

    // 3. Intelligent Product Remapping Function
    function resolve_835_ecat($product, $ecat_id_map) {
        $name = strtolower($product['p_name'] . ' ' . ($product['ecat_name'] ?? '') . ' ' . ($product['mcat_name'] ?? '') . ' ' . ($product['tcat_name'] ?? ''));

        // CEMENT & AGGREGATES
        if (strpos($name, 'sand') !== false && strpos($name, 'paper') === false && strpos($name, 'sanding') === false) return $ecat_id_map['Structural & Masonry Materials|Cement, Aggregates & Masonry|Sand, Gravel & Aggregates'];
        if (strpos($name, 'gravel') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement, Aggregates & Masonry|Sand, Gravel & Aggregates'];
        if (strpos($name, 'hollow block') !== false || strpos($name, 'chb') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement, Aggregates & Masonry|Concrete Hollow Blocks (CHB) & Pavers'];
        if (strpos($name, 'sahara') !== false || strpos($name, 'waterproofing') !== false || strpos($name, 'plexibond') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement, Aggregates & Masonry|Cementitious Waterproofing Compounds'];
        if (strpos($name, 'neutralizer') !== false || strpos($name, 'etcher') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement, Aggregates & Masonry|Concrete Additives & Accelerators'];
        if (strpos($name, 'cement') !== false || strpos($name, 'holcim') !== false || strpos($name, 'republic') !== false || strpos($name, 'portland') !== false) return $ecat_id_map['Structural & Masonry Materials|Cement, Aggregates & Masonry|Portland, Pozzolan & Masonry Cement'];

        // REBAR & WIRE
        if (strpos($name, 'tie wire') !== false || strpos($name, 'g.i tie wire') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel & Wire Products|GI Tie Wire (Gauge #16)'];
        if (strpos($name, 'round bar') !== false || strpos($name, 'plain round') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel & Wire Products|Plain Round Steel Bars & Dowels'];
        if (strpos($name, 'cyclone') !== false || strpos($name, 'chainlink') !== false || strpos($name, 'chain link') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel & Wire Products|Cyclone & Chain Link Mesh'];
        if (strpos($name, 'barbed wire') !== false || strpos($name, 'welded wire') !== false || strpos($name, 'hog wire') !== false || strpos($name, 'poultry') !== false || strpos($name, 'expanded') !== false || strpos($name, 'mesh') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel & Wire Products|Welded Wire Mesh & Barbed Wire'];
        if (strpos($name, 'deformed') !== false || strpos($name, 'rebar') !== false || strpos($name, 'steel bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Reinforcing Steel & Wire Products|Deformed Steel Bars (Rebar Grade 33/40)'];

        // STRUCTURAL STEEL & TUBES
        if (strpos($name, 'angle bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Angle Bars (Equal & Unequal)'];
        if (strpos($name, 'c-purlin') !== false || strpos($name, 'purlin') !== false || strpos($name, 'channel') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|C-Purlins & Steel Channels'];
        if (strpos($name, 'flat bar') !== false || strpos($name, 'square bar') !== false || strpos($name, 'squre bar') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Flat Bars & Square Bars'];
        if (strpos($name, 'tubular') !== false || strpos($name, 'gi pipe') !== false || strpos($name, 'stainless tube') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Steel Tubes, Tubulars & GI Pipes'];
        if (strpos($name, 'i-beam') !== false || strpos($name, 'wide flange') !== false || strpos($name, 'shafting') !== false) return $ecat_id_map['Structural & Masonry Materials|Structural Steel & Shapes|Wide Flange, I-Beams & Shafting'];

        // PLYWOOD & LUMBER & BOARDS
        if (strpos($name, 'marine plywood') !== false || strpos($name, 'marine ply') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Panels|Marine Plywood (1/4", 1/2", 3/4")'];
        if (strpos($name, 'phenolic') !== false || strpos($name, 'film faced') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Panels|Phenolic & Film-Faced Form Plywood'];
        if (strpos($name, 'plywood') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Panels|Ordinary & Interior Plywood'];
        if (strpos($name, 'mdf') !== false || strpos($name, 'particle') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Panels|Particle Boards & MDF Sheets'];
        if (strpos($name, 'hardie') !== false || strpos($name, 'fiber cement') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Drywall Boards & Metal Framing|Fiber Cement Boards (HardieFlex 3.5mm–12mm)'];
        if (strpos($name, 'gypsum') !== false || strpos($name, 'drywall board') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Drywall Boards & Metal Framing|Gypsum Drywall Boards (9mm, 12mm)'];
        if (strpos($name, 'furring') !== false || strpos($name, 'carrying') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Drywall Boards & Metal Framing|Metal Furring Channels & Carrying Channels'];
        if (strpos($name, 'stud') !== false || strpos($name, 'track') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Drywall Boards & Metal Framing|Wall Studs & Track Channels'];
        if (strpos($name, 'wall angle') !== false || strpos($name, 'main tee') !== false || strpos($name, 'cross tee') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Drywall Boards & Metal Framing|Wall Angles, Main Tees & Cross Tees'];
        if (strpos($name, 'coco') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber, Doors & Mouldings|Coco Rough Lumber (2x2, 2x3, 2x4)'];
        if (strpos($name, 'lumber') !== false || strpos($name, 'kahoy') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber, Doors & Mouldings|Good Lumber (S4S Kiln-Dried)'];
        if (strpos($name, 'pvc door') !== false || strpos($name, 'bathroom door') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber, Doors & Mouldings|PVC Waterproof Bathroom Doors'];
        if (strpos($name, 'flush door') !== false || strpos($name, 'wood door') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber, Doors & Mouldings|Flush & Wood Panel Doors'];
        if (strpos($name, 'jalousie') !== false || strpos($name, 'window') !== false || strpos($name, 'moulding') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Lumber, Doors & Mouldings|Louver Jalousie Windows & Wood Mouldings'];

        // ROOFING & INSULATION
        if (strpos($name, 'corrogated') !== false || strpos($name, 'corrugated') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal Roofing Sheets|Corrugated GI Roofing Sheets'];
        if (strpos($name, 'rib') !== false || strpos($name, 'long span') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal Roofing Sheets|Long Span Rib-Type Roofing'];
        if (strpos($name, 'tile') !== false && strpos($name, 'roof') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal Roofing Sheets|Tile-Span Metal Roofing'];
        if (strpos($name, 'colored roof') !== false || strpos($name, 'pre-painted') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal Roofing Sheets|Pre-Painted Colored Roof Sheets'];
        if (strpos($name, 'polycarbonate') !== false || strpos($name, 'plastic roofing') !== false || strpos($name, 'skylight') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal Roofing Sheets|Translucent & Polycarbonate Skylights'];
        if (strpos($name, 'plain sheet') !== false || strpos($name, 'plainsheet') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Plain GI Sheets & Gutters|Plain GI Sheets (Gauge 24/26)'];
        if (strpos($name, 'coil') !== false || strpos($name, 'strap') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Plain GI Sheets & Gutters|Galvanized Flat Coils & Straps'];
        if (strpos($name, 'ridge roll') !== false || strpos($name, 'gutter') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Plain GI Sheets & Gutters|Ridge Rolls & Valley Gutters'];
        if (strpos($name, 'flashing') !== false || strpos($name, 'end flashing') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Plain GI Sheets & Gutters|End Flashings & Wall Flashings'];
        if (strpos($name, 'downspout') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Plain GI Sheets & Gutters|Downspouts & Gutter Accessories'];
        if (strpos($name, 'bubble foil') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Thermal Insulation & Moisture Barriers|Bubble Foil Radiant Barriers'];
        if (strpos($name, 'rockwool') !== false || strpos($name, 'glasswool') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Thermal Insulation & Moisture Barriers|Glasswool & Rockwool Insulation Batts'];
        if (strpos($name, 'insulation') !== false || strpos($name, 'pe foam') !== false || strpos($name, 'foam') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Thermal Insulation & Moisture Barriers|PE Foam Aluminum Foil Insulation'];
        if (strpos($name, 'roof seal') !== false || strpos($name, 'flashband') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Thermal Insulation & Moisture Barriers|Roof Sealant Tapes & Bituminous Strips'];

        // PLUMBING & PIPING
        if (strpos($name, 'solvent cement') !== false || strpos($name, 'pvc cement') !== false) return $ecat_id_map['Plumbing & Piping Systems|Drainage & Sanitary (DWV)|PVC Solvent Cements & Primers'];
        if (strpos($name, 'floor drain') !== false || strpos($name, 'drain') !== false) return $ecat_id_map['Plumbing & Piping Systems|Drainage & Sanitary (DWV)|Floor Drains, Traps & Gratings'];
        if (strpos($name, 'p-trap') !== false || strpos($name, 'cleanout') !== false || (strpos($name, 'bushing') !== false && strpos($name, 'pvc') !== false)) return $ecat_id_map['Plumbing & Piping Systems|Drainage & Sanitary (DWV)|PVC P-Traps, Cleanouts & Bushings'];
        if (strpos($name, 'pvc elbow') !== false || strpos($name, 'pvc tee') !== false || strpos($name, 'pvc wye') !== false || strpos($name, 'sanitary tee') !== false || strpos($name, 'sanitary fitting') !== false || strpos($name, 'elbow') !== false) return $ecat_id_map['Plumbing & Piping Systems|Drainage & Sanitary (DWV)|PVC Sanitary Elbows, Tees & Wyes'];
        if (strpos($name, 'sanitary pipe') !== false || strpos($name, 'pvc orange') !== false || strpos($name, 'pvc pipe') !== false || strpos($name, 'pvc blue pipe') !== false) return $ecat_id_map['Plumbing & Piping Systems|Drainage & Sanitary (DWV)|PVC Sanitary Pipes (Orange/Grey)'];
        if (strpos($name, 'ppr pipe') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Supply (PPR, PE, GI)|PPR Hot & Cold Water Pipes'];
        if (strpos($name, 'ppr') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Supply (PPR, PE, GI)|PPR Heat-Fusion Fittings'];
        if (strpos($name, 'hdpe') !== false || strpos($name, 'pe pipe') !== false || strpos($name, 'compression') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Supply (PPR, PE, GI)|PE / HDPE Pipes & Compression Fittings'];
        if (strpos($name, 'gi pipe') !== false && strpos($name, 'plumbing') !== false) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Supply (PPR, PE, GI)|GI Threaded Pipes & Malleable Fittings'];
        if (strpos($name, 'brass') !== false && (strpos($name, 'nipple') !== false || strpos($name, 'adapter') !== false || strpos($name, 'bushing') !== false)) return $ecat_id_map['Plumbing & Piping Systems|Potable Water Supply (PPR, PE, GI)|Brass Bushings, Nipples & Adapters'];
        if (strpos($name, 'valve') !== false || strpos($name, 'gate valve') !== false || strpos($name, 'ball valve') !== false || strpos($name, 'check valve') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Fixtures & Water Control|Gate Valves, Ball Valves & Check Valves'];
        if (strpos($name, 'faucet') !== false || strpos($name, 'bibcock') !== false || strpos($name, 'gripo') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Fixtures & Water Control|Water Faucets, Bibcocks & Hose Bibs'];
        if (strpos($name, 'flexible hose') !== false || strpos($name, 'angle valve') !== false || strpos($name, 'bidet') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Fixtures & Water Control|Flexible Supply Hoses & Angle Valves'];
        if (strpos($name, 'sink') !== false || strpos($name, 'lababo') !== false || strpos($name, 'water closet') !== false || strpos($name, 'toilet') !== false || strpos($name, 'lavatory') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Fixtures & Water Control|Stainless Sinks (Lababo) & Bathroom Fixtures'];
        if (strpos($name, 'teflon') !== false || strpos($name, 'thread tape') !== false || strpos($name, 'pipe clamp') !== false) return $ecat_id_map['Plumbing & Piping Systems|Valves, Fixtures & Water Control|Teflon Thread Tapes & Pipe Clamps'];

        // ELECTRICAL & LIGHTING
        if (strpos($name, 'thhn') !== false || strpos($name, 'thwn') !== false || strpos($name, 'stranded wire') !== false || strpos($name, 'building wire') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|THHN / THWN Stranded Copper Wire'];
        if (strpos($name, 'romex') !== false || strpos($name, 'non-metallic') !== false || strpos($name, 'nm cable') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|Non-Metallic Sheathed Cable (Romex/NM)'];
        if (strpos($name, 'royal cord') !== false || strpos($name, 'heavy duty cord') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|Royal Cord & Heavy-Duty Flexible Wire'];
        if (strpos($name, 'flat cord') !== false || strpos($name, 'speaker wire') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|Flat Cord & Speaker Wire'];
        if (strpos($name, 'telephone') !== false || strpos($name, 'network') !== false || strpos($name, 'lan cable') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|Telephone & Data Network Cables'];
        if (strpos($name, 'conduit pipe') !== false || strpos($name, 'pvc electrical') !== false || strpos($name, 'pvc conduit') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits, Boxes & Fittings|PVC Electrical Conduit Pipes & Fittings'];
        if ((strpos($name, 'flexible hose') !== false && strpos($name, 'electrical') !== false) || strpos($name, 'corrugated conduit') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits, Boxes & Fittings|Flexible Corrugated Hose Conduits'];
        if (strpos($name, 'emt') !== false || strpos($name, 'imc') !== false || strpos($name, 'conduit clamp') !== false || strpos($name, 'locknut') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits, Boxes & Fittings|Metal EMT & IMC Conduits & Clamps'];
        if (strpos($name, 'utility box') !== false || strpos($name, 'octagonal box') !== false || strpos($name, 'junction box') !== false || strpos($name, 'outlet box') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits, Boxes & Fittings|Utility Boxes & Octagonal Junction Boxes'];
        if (strpos($name, 'electrical tape') !== false || strpos($name, 'wire nut') !== false || strpos($name, 'wire connector') !== false) return $ecat_id_map['Electrical & Lighting Systems|Conduits, Boxes & Fittings|Electrical Tape, Wire Nuts & Connectors'];
        if (strpos($name, 'breaker') !== false || strpos($name, 'mcb') !== false || strpos($name, 'panel board') !== false || strpos($name, 'panelboard') !== false) return $ecat_id_map['Electrical & Lighting Systems|Devices, Breakers & Lighting|Miniature Circuit Breakers (MCB) & Panels'];
        if (strpos($name, 'safety switch') !== false || strpos($name, 'knife switch') !== false) return $ecat_id_map['Electrical & Lighting Systems|Devices, Breakers & Lighting|Safety Switches & Knife Switches'];
        if (strpos($name, 'switch') !== false || strpos($name, '1-gang') !== false || strpos($name, '2-gang') !== false || strpos($name, '3-gang') !== false || strpos($name, 'dimmer') !== false) return $ecat_id_map['Electrical & Lighting Systems|Devices, Breakers & Lighting|Wall Switches (1, 2, 3-Gang) & Dimmers'];
        if (strpos($name, 'outlet') !== false || strpos($name, 'socket') !== false || strpos($name, 'receptacle') !== false || strpos($name, 'plug') !== false || strpos($name, 'extension') !== false) return $ecat_id_map['Electrical & Lighting Systems|Devices, Breakers & Lighting|Convenience Wall Outlets & Sockets'];
        if (strpos($name, 'bulb') !== false || strpos($name, 'led') !== false || strpos($name, 'downlight') !== false || strpos($name, 't8') !== false || strpos($name, 'floodlight') !== false || strpos($name, 'lamp') !== false) return $ecat_id_map['Electrical & Lighting Systems|Devices, Breakers & Lighting|LED Bulbs, Downlights & T8 Tubes'];

        // PAINTS & SURFACE PREP
        if (strpos($name, 'sandpaper') !== false || strpos($name, 'lija') !== false || strpos($name, 'abrasive sheet') !== false || strpos($name, 'silicon carbide') !== false || strpos($name, 'grit') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Surface Preparation & Solvents|Waterproof Sandpaper Sheets (Grit 60–1000)'];
        if (strpos($name, 'sanding mesh') !== false || strpos($name, 'emery cloth') !== false || strpos($name, 'sanding sponge') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Surface Preparation & Solvents|Drywall Sanding Mesh & Emery Cloth'];
        if (strpos($name, 'patching') !== false || strpos($name, 'masonry putty') !== false || strpos($name, 'putty paste') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Surface Preparation & Solvents|Masonry Putty & Patching Paste'];
        if (strpos($name, 'joint compound') !== false || strpos($name, 'gypsum putty') !== false || strpos($name, 'wood filler') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Surface Preparation & Solvents|Gypsum Joint Compound & Fillers'];
        if (strpos($name, 'thinner') !== false || strpos($name, 'lacquer thinner') !== false || strpos($name, 'acetone') !== false || strpos($name, 'paint remover') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Surface Preparation & Solvents|Paint Thinners, Lacquer Thinner & Acetone'];
        if (strpos($name, 'primer') !== false || strpos($name, 'sealer') !== false || strpos($name, 'red oxide') !== false || strpos($name, 'epoxy primer') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Primers, Sealers & Stains|Concrete Acrylic Primers & Sealers'];
        if (strpos($name, 'varnish') !== false || strpos($name, 'polyurethane') !== false || strpos($name, 'clear gloss') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Primers, Sealers & Stains|Clear Gloss Varnishes & Polyurethane'];
        if (strpos($name, 'wood stain') !== false || strpos($name, 'oil stain') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Primers, Sealers & Stains|Oil-Based Wood Stains & Preservatives'];
        if (strpos($name, 'tinting') !== false || strpos($name, 'acri-color') !== false || strpos($name, 'colorant') !== false || strpos($name, 'oil tint') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Architectural Paints & Coatings|Tinting Colors (Acri-Color & Oil Tint)'];
        if (strpos($name, 'enamel') !== false || strpos($name, 'qde') !== false || strpos($name, 'quick drying') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Architectural Paints & Coatings|Quick Drying Enamels (QDE)'];
        if (strpos($name, 'roof paint') !== false || strpos($name, 'deck paint') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Architectural Paints & Coatings|Roof Acrylic & Deck Paints'];
        if (strpos($name, 'elastomeric') !== false || strpos($name, 'waterproof paint') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Architectural Paints & Coatings|Elastomeric Waterproofing Paints'];
        if (strpos($name, 'latex') !== false || strpos($name, 'paint') !== false || strpos($name, 'pintura') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Architectural Paints & Coatings|Latex Interior & Exterior Paints'];

        // HARDWARE, FASTENERS & ADHESIVES
        if (strpos($name, 'common nail') !== false || strpos($name, 'finishing nail') !== false || strpos($name, 'pako') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Construction Screws|Common Wire Nails & Finishing Nails'];
        if (strpos($name, 'concrete nail') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Construction Screws|Concrete Steel Nails'];
        if (strpos($name, 'umbrella') !== false || strpos($name, 'roofing nail') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Construction Screws|Umbrella Roofing Nails'];
        if (strpos($name, 'tekscrew') !== false || strpos($name, 'tek screw') !== false || strpos($name, 'self-drilling') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Construction Screws|Self-Drilling Tekscrews (Metal & Wood)'];
        if (strpos($name, 'drywall screw') !== false || strpos($name, 'wood screw') !== false || strpos($name, 'black screw') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Construction Screws|Drywall Black Screws & Wood Screws'];
        if (strpos($name, 'bolt') !== false || strpos($name, 'hex bolt') !== false || strpos($name, 'machine bolt') !== false || strpos($name, 'nut') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Bolts, Anchors & Fastening Hardware|Hex Machine Bolts, Studs & Nuts'];
        if (strpos($name, 'anchor') !== false || strpos($name, 'expansion') !== false || strpos($name, 'wedge anchor') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Bolts, Anchors & Fastening Hardware|Masonry Wedge & Expansion Anchors'];
        if (strpos($name, 'tox') !== false || strpos($name, 'wall plug') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Bolts, Anchors & Fastening Hardware|Plastic Tox Plugs & Wall Anchors'];
        if (strpos($name, 'blind rivet') !== false || strpos($name, 'pop rivet') !== false || strpos($name, 'rivet') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Bolts, Anchors & Fastening Hardware|Blind Rivets & Pop Rivets'];
        if (strpos($name, 'threaded rod') !== false || strpos($name, 'u-bolt') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Bolts, Anchors & Fastening Hardware|Threaded Rods & U-Bolts'];
        if (strpos($name, 'hinge') !== false || strpos($name, 'piano hinge') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door Hardware & Construction Adhesives|Door Hinges, Butt Hinges & Piano Hinges'];
        if (strpos($name, 'lockset') !== false || strpos($name, 'deadbolt') !== false || strpos($name, 'door lock') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door Hardware & Construction Adhesives|Cylindrical Locksets & Deadbolts'];
        if (strpos($name, 'padlock') !== false || strpos($name, 'hasp') !== false || strpos($name, 'latch') !== false || strpos($name, 'barrel bolt') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door Hardware & Construction Adhesives|Padlocks, Hasps & Heavy Latches'];
        if (strpos($name, 'silicone') !== false || strpos($name, 'sealant') !== false || strpos($name, 'vulcaseal') !== false || strpos($name, 'pu sealant') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door Hardware & Construction Adhesives|Silicone, PU Sealants & Vulcaseal'];
        if (strpos($name, 'contact cement') !== false || strpos($name, 'wood glue') !== false || strpos($name, 'tile adhesive') !== false || strpos($name, 'grout') !== false || strpos($name, 'rugby') !== false) return $ecat_id_map['Hardware, Fasteners & Adhesives|Door Hardware & Construction Adhesives|Contact Cement, Wood Glue & Tile Adhesive'];

        // TOOLS, EQUIPMENT & SAFETY
        if (strpos($name, 'hammer') !== false || strpos($name, 'sledge') !== false || strpos($name, 'chisel') !== false || strpos($name, 'crowbar') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Layout Measuring|Hammers, Sledges, Chisels & Crowbars'];
        if (strpos($name, 'handsaw') !== false || strpos($name, 'hacksaw') !== false || strpos($name, 'saw blade') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Layout Measuring|Handsaws, Hacksaws & Replacement Blades'];
        if (strpos($name, 'wrench') !== false || strpos($name, 'pliers') !== false || strpos($name, 'screwdriver') !== false || strpos($name, 'socket') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Layout Measuring|Wrenches, Pliers, Screwdrivers & Sockets'];
        if (strpos($name, 'trowel') !== false || strpos($name, 'float') !== false || strpos($name, 'scraper') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Layout Measuring|Masonry Trowels, Floats & Scrapers'];
        if (strpos($name, 'tape measure') !== false || strpos($name, 'measuring tape') !== false || strpos($name, 'steel tape') !== false || (strpos($name, 'tape') !== false && strpos($name, 'electrical') === false && strpos($name, 'teflon') === false) || strpos($name, 'spirit level') !== false || strpos($name, 'plumb bob') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Layout Measuring|Measuring Tapes, Spirit Levels & Plumb Bobs'];
        if (strpos($name, 'cutting wheel') !== false || strpos($name, 'diamond wheel') !== false || strpos($name, 'diamond disc') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tool Accessories & Welding|Diamond Concrete & Tile Cutting Wheels'];
        if (strpos($name, 'cutting disc') !== false || strpos($name, 'cut off disc') !== false || strpos($name, 'metal cutting') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tool Accessories & Welding|Metal & Stainless Cutting Discs'];
        if (strpos($name, 'grinding wheel') !== false || strpos($name, 'flap disc') !== false || strpos($name, 'grinding disc') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tool Accessories & Welding|Grinding Wheels & Flap Sanding Discs'];
        if (strpos($name, 'drill bit') !== false || strpos($name, 'masonry bit') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tool Accessories & Welding|Drill Bits (Masonry, Metal, Wood)'];
        if (strpos($name, 'welding') !== false || strpos($name, 'electrode') !== false || strpos($name, 'e6011') !== false || strpos($name, 'e6013') !== false || strpos($name, 'welder') !== false) return $ecat_id_map['Tools, Equipment & Safety|Power Tool Accessories & Welding|Welding Electrodes (E6011/E6013), Clamps & Masks'];
        if (strpos($name, 'paint brush') !== false || (strpos($name, 'brush') !== false && strpos($name, 'wire') === false)) return $ecat_id_map['Tools, Equipment & Safety|Painting Tools & Jobsite Safety (PPE)|Paint Brushes (1" to 4")'];
        if (strpos($name, 'roller') !== false || strpos($name, 'tray') !== false) return $ecat_id_map['Tools, Equipment & Safety|Painting Tools & Jobsite Safety (PPE)|Paint Rollers, Trays & Extension Poles'];
        if (strpos($name, 'caulking gun') !== false || strpos($name, 'putty knife') !== false) return $ecat_id_map['Tools, Equipment & Safety|Painting Tools & Jobsite Safety (PPE)|Caulking Guns & Putty Knives'];
        if (strpos($name, 'hard hat') !== false || strpos($name, 'helmet') !== false || strpos($name, 'goggles') !== false || strpos($name, 'face shield') !== false) return $ecat_id_map['Tools, Equipment & Safety|Painting Tools & Jobsite Safety (PPE)|Safety Hard Hats, Goggles & Face Shields'];
        if (strpos($name, 'gloves') !== false || strpos($name, 'vest') !== false || strpos($name, 'safety boot') !== false || strpos($name, 'trapal') !== false || strpos($name, 'rope') !== false || strpos($name, 'wheelbarrow') !== false) return $ecat_id_map['Tools, Equipment & Safety|Painting Tools & Jobsite Safety (PPE)|Work Gloves (Cotton, Rubber, Leather) & Vests'];

        // Fallback default based on Top Category
        if (strpos($name, 'plumb') !== false) return $ecat_id_map['Plumbing & Piping Systems|Drainage & Sanitary (DWV)|PVC Sanitary Pipes (Orange/Grey)'];
        if (strpos($name, 'elect') !== false) return $ecat_id_map['Electrical & Lighting Systems|Building Wires & Cables|THHN / THWN Stranded Copper Wire'];
        if (strpos($name, 'paint') !== false) return $ecat_id_map['Paints, Coatings & Surface Prep|Architectural Paints & Coatings|Latex Interior & Exterior Paints'];
        if (strpos($name, 'roof') !== false) return $ecat_id_map['Roofing & Thermal Insulation|Metal Roofing Sheets|Corrugated GI Roofing Sheets'];
        if (strpos($name, 'tool') !== false) return $ecat_id_map['Tools, Equipment & Safety|Hand Tools & Layout Measuring|Hammers, Sledges, Chisels & Crowbars'];
        if (strpos($name, 'lumber') !== false || strpos($name, 'wood') !== false) return $ecat_id_map['Lumber, Boards & Drywall Systems|Plywood & Sheet Panels|Ordinary & Interior Plywood'];

        return $ecat_id_map['Hardware, Fasteners & Adhesives|Nails & Construction Screws|Common Wire Nails & Finishing Nails'];
    }

    echo "\nRemapping 823 products to 8x3x5 categories...\n";
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
    $update_stmt = $pdo->prepare("UPDATE tbl_product SET ecat_id = ? WHERE p_id = ?");

    foreach ($products as $prod) {
        $target_ecat_id = resolve_835_ecat($prod, $ecat_id_map);
        if (!$target_ecat_id) {
            throw new Exception("Could not map product ID {$prod['p_id']}: {$prod['p_name']}");
        }
        $update_stmt->execute([$target_ecat_id, $prod['p_id']]);
        $remapped_count++;
    }
    echo "  -> Remapped {$remapped_count} / " . count($products) . " products.\n";

    // 4. Prune obsolete empty categories
    echo "\nCleaning up obsolete empty categories...\n";
    $canonical_ecat_ids = array_values($ecat_id_map);
    $canonical_mcat_ids = array_values($mcat_id_map);
    $canonical_tcat_ids = array_values($tcat_id_map);

    // Delete non-canonical end categories with 0 products
    $stmt = $pdo->query("
        DELETE FROM tbl_end_category 
        WHERE ecat_id NOT IN (" . implode(',', $canonical_ecat_ids) . ")
        AND ecat_id NOT IN (SELECT DISTINCT ecat_id FROM tbl_product WHERE ecat_id IS NOT NULL)
    ");
    echo "  -> Deleted obsolete end categories.\n";

    // Delete non-canonical mid categories with 0 end categories
    $stmt = $pdo->query("
        DELETE FROM tbl_mid_category 
        WHERE mcat_id NOT IN (" . implode(',', $canonical_mcat_ids) . ")
        AND mcat_id NOT IN (SELECT DISTINCT mcat_id FROM tbl_end_category)
    ");
    echo "  -> Deleted obsolete mid categories.\n";

    // Resync sequences
    $pdo->exec("SELECT setval('tbl_top_category_tcat_id_seq', (SELECT COALESCE(MAX(tcat_id), 1) FROM tbl_top_category));");
    $pdo->exec("SELECT setval('tbl_mid_category_mcat_id_seq', (SELECT COALESCE(MAX(mcat_id), 1) FROM tbl_mid_category));");
    $pdo->exec("SELECT setval('tbl_end_category_ecat_id_seq', (SELECT COALESCE(MAX(ecat_id), 1) FROM tbl_end_category));");

    $pdo->commit();
    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY! ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
