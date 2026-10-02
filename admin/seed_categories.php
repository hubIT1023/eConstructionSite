<?php
/**
 * Construction Categories Database Seeder
 *
 * Safely inserts industry-standard construction taxonomy into PostgreSQL
 * without modifying, deleting, or overwriting existing categories or products.
 *
 * Performs case-insensitive matching to prevent duplicate categories.
 */

require_once(__DIR__ . '/inc/config.php');

// Define full comprehensive taxonomy
$taxonomy = [
    'Cement & Concrete Materials' => [
        'Cement & Binders' => [
            'Portland Cement',
            'Pozzolan Cement',
            'Hydraulic / Masonry Cement',
            'Mortar & Tile Adhesive Base'
        ],
        'Aggregates & Sand' => [
            'Washed Sand (Fine / Coarse)',
            'Crushed Gravel (3/4, 3/8, G-1)',
            'Base Coarse & Subbase',
            'Boulders & Fill Materials'
        ],
        'Blocks & Bricks' => [
            'Concrete Hollow Blocks (CHB 4", 6", 8")',
            'AAC Lightweight Blocks',
            'Clay & Refractory Bricks',
            'Decorative Glass Blocks'
        ]
    ],
    'Reinforcing Steel & Structural Steel' => [
        'Reinforcing Steel' => [
            'Deformed Bars',
            'Plain Round Bars',
            'GI Tie Wire',
            'Steel Wire Mesh & Matting'
        ],
        'Structural Steel' => [
            'Angle Bars',
            'Flat Bars',
            'Square Bars',
            'C-Purlins & Channels',
            'I-Beams & Wide Flange (H-Beams)',
            'Square & Rectangular Tubular Steel'
        ],
        'Steel Tubes & Pipes' => [
            'Tubular Steel',
            'Stainless Steel Tubes',
            'GI Pipes'
        ],
        'Metal Sheets & Plates' => [
            'Plain GI Sheets',
            'Black Iron (BI) Sheets',
            'Checkered Diamond Plates',
            'Expanded Metal & Perforated Sheets'
        ]
    ],
    'Lumber, Plywood & Boards' => [
        'Sawn & Dressed Lumber' => [
            'Coco Lumber & Rough Framing Wood',
            'S4S Surfaced Lumber (Kiln-Dried)',
            'Wood Studs, Joists & Rafters',
            'Wood Moldings, Baseboards & Trims'
        ],
        'Plywood & Panels' => [
            'Ordinary Plywood',
            'Marine Plywood',
            'Film-Faced Phenolic Boards',
            'Melamine & MDF Boards'
        ],
        'Drywall & Ceiling Boards' => [
            'Fiber Cement Boards (Hardiflex)',
            'Gypsum Wall Boards',
            'Acoustic Ceiling Tiles',
            'PVC Ceiling Panels'
        ],
        'Drywall & Ceiling Metal Framing' => [
            'Metal Studs & Tracks',
            'Double Furring & Wall Angles',
            'Main Tees & Cross Tees'
        ]
    ],
    'Roofing & Insulation' => [
        'Metal Roofing Sheets' => [
            'Corrugated GI Roofing Sheets',
            'Rib-Type Long Span Roofing',
            'Tile-Span Metal Roofing',
            'Curved & Crimped Roofing Sheets'
        ],
        'Polycarbonate & Skylights' => [
            'Solid Polycarbonate Sheets',
            'Corrugated / Twin-Wall Polycarbonate',
            'Fiberglass Skylight Sheets'
        ],
        'Roof Accessories & Gutters' => [
            'Ridge Rolls & Flashings',
            'Spanish Valley Gutters',
            'GI / Pre-painted Box Gutters',
            'PVC Roof Gutters & Downspouts'
        ],
        'Insulation & Underlayment' => [
            'PE Foam Foil Insulation',
            'Double Bubble Foil Insulation',
            'Rockwool / Glasswool Batts',
            'Asphalt Shingles & Roofing Felt'
        ]
    ],
    'Plumbing & Piping Systems' => [
        'Potable Water Supply Pipes' => [
            'PVC Blue Pipes (Series 8/10)',
            'PPR Hot & Cold Pipes',
            'GI Pipes (Schedule 20/40)',
            'PE / HDPE Pipes & Tubing'
        ],
        'Potable Water Fittings' => [
            'PVC Blue Pressure Fittings',
            'PPR Heat-Fusion Fittings',
            'GI Malleable Iron Fittings',
            'Brass Compression & Threaded Fittings'
        ],
        'Drainage & Waste (DWV)' => [
            'PVC Sanitary / Orange Pipes',
            'PVC Sanitary Fittings (Elbows, Tees, Traps)',
            'Cleanouts & Floor Drains',
            'Concrete Drainage Pipes & Catch Basins'
        ],
        'Valves, Faucets & Flow Control' => [
            'Brass / Bronze Ball & Gate Valves',
            'Check Valves & Float Valves',
            'Water Meters',
            'Bibcocks & Sink Faucets'
        ],
        'Water Storage & Pumps' => [
            'Stainless / Polyethylene Water Tanks',
            'Booster & Shallow Well Jet Pumps',
            'Pressure Tanks & Switches'
        ]
    ],
    'Electrical & Lighting' => [
        'Wires & Power Cables' => [
            'THHN / THWN-2 Stranded Copper Wires',
            'Non-Metallic Sheathed Cables (NM / Romex)',
            'Flat Cord / Royal Flexible Cords',
            'Coaxial & Network (Cat6) Cables'
        ],
        'Conduits, Boxes & Raceways' => [
            'PVC Electrical Conduits (Orange/Blue)',
            'EMT / IMC Metallic Conduits',
            'Flexible Metal / Corrugated Plastic Tubing',
            'Utility, Junction & Octagonal Boxes'
        ],
        'Distribution & Circuit Protection' => [
            'Panel Boards & Load Centers',
            'Miniature Circuit Breakers (MCB / Plug-in)',
            'Molded Case Circuit Breakers (MCCB)',
            'Surge Protectors & Manual Transfer Switches'
        ],
        'Wiring Devices & Switches' => [
            'Wall Switches (1-Gang, 2-Gang, 3-Gang)',
            'Convenience Wall Outlets (Duplex / Universal)',
            'Industrial Receptacles & Weatherproof Boxes'
        ],
        'Lighting & Fixtures' => [
            'LED Bulbs & Downlights',
            'LED T8 Tube Lights & Fixtures',
            'LED Outdoor Floodlights & Solar Lights',
            'Emergency Lights & Exit Signs'
        ]
    ],
    'Paints, Coatings & Chemicals' => [
        'Interior & Exterior Paint' => [
            'Latex Paints',
            'Quick Drying Enamels (QDE)',
            'Acrylic Roof Paints',
            'Epoxy Floor & Marine Enamels'
        ],
        'Surface Prep & Primers' => [
            'Concrete Neutralizers & Etchers',
            'Acrylic Concrete Primers',
            'Red Oxide & Zinc Chromate Metal Primers',
            'Paint Thinners, Reducers & Lacquer Solvents'
        ],
        'Waterproofing & Sealants' => [
            'Cementitious Waterproofing Compounds',
            'Liquid Elastomeric Waterproofing',
            'Polyurethane (PU) & Silicone Sealants',
            'Acrylic Gap Fillers & Glazing Compounds'
        ],
        'Tile Grouts & Adhesives' => [
            'Standard & Heavy-Duty Tile Adhesive',
            'Tile Grout (Sanded / Non-Sanded)',
            'Construction Adhesives (Liquid Nails)'
        ]
    ],
    'Hardware & Fasteners' => [
        'Nails' => [
            'Common Nails',
            'Finishing Nails',
            'Concrete Steel Nails',
            'Umbrella / Roofing Nails with Washers'
        ],
        'Screws, Bolts & Anchors' => [
            'Self-Drilling Tekscrews (Metal / Wood)',
            'Gypsum / Drywall Black Screws',
            'Hex Machine Bolts & Nuts',
            'Expansion Shield & Wedge Anchors'
        ],
        'Door & Window Hardware' => [
            'Cylindrical & Tubular Locksets',
            'Butt Hinges & Bearing Hinges',
            'Padlocks, Hasps & Heavy Latches',
            'Hydraulic Door Closers'
        ],
        'Fencing & Meshes' => [
            'Barbed Wire (Gauge #12, #14)',
            'Cyclone / Chainlink Wire Mesh',
            'Hog Wire & Poultry Hex Netting',
            'Expanded Metal Grilles'
        ]
    ],
    'Tools & Safety' => [
        'Hand Tools' => [
            'Hammers, Sledges & Chisels',
            'Masonry Trowels & Floats',
            'Handsaws & Hacksaws',
            'Wrenches, Pliers & Screwdrivers',
            'Measuring Tapes, Plumb Bobs & Levels'
        ],
        'Power Tools' => [
            'Drills & Drivers',
            'Angle Grinders & Cut-off Machines',
            'Rotary Hammer & Impact Drills',
            'Circular Saws & Jigsaws',
            'Demolition Breakers / Jackhammers'
        ],
        'Safety Equipment' => [
            'Helmets & Vests',
            'Goggles & Gloves',
            'High-Visibility Reflective Vests',
            'Steel-Toe Safety Boots',
            'Safety Hard Hats & Bump Caps'
        ],
        'Welding Equipment & Supplies' => [
            'Inverter Arc & MIG Welders',
            'Welding Electrodes (E6011, E6013, E7018)',
            'Cutting Discs & Grinding Wheels',
            'Auto-Darkening Welding Helmets'
        ],
        'Jobsite Equipment' => [
            'Portable Concrete Mixers (1-Bagger)',
            'Concrete Vibrators & Plate Compactors',
            'Steel Scaffolding Sets & Walkway Planks',
            'Heavy-Duty Wheelbarrows'
        ]
    ],
    'Tiles, Flooring & Sanitary Fixtures' => [
        'Tiles & Natural Stone' => [
            'Glazed Ceramic Floor & Wall Tiles',
            'Polished Homogeneous Porcelain Tiles',
            'Anti-Slip Outdoor & Rustic Tiles',
            'Granite Slabs & Mosaics'
        ],
        'Sanitary Bathroom Fixtures' => [
            'Water Closets (Toilets) & Bidet Sprays',
            'Sinks, Lavatories & Pedestals',
            'Stainless Steel Kitchen Sinks',
            'Urinals & Flush Valves'
        ],
        'Resilient Flooring' => [
            'Vinyl Planks & SPC Flooring',
            'Rubber Flooring & Mats'
        ]
    ],
    'Doors, Windows & Glass' => [
        'Doors & Jambs' => [
            'Solid Wood & Flush Panel Doors',
            'PVC & Aluminum Waterproof Doors',
            'Steel Fire & Security Doors',
            'Solid Wood & Metal Door Jambs'
        ],
        'Windows & Glazing' => [
            'Aluminum Sliding & Casement Windows',
            'UPVC Windows',
            'Jalousie Glass Frames & Clips',
            'Clear, Tinted & Frosted Glass Sheets'
        ]
    ]
];

echo "=== STARTING CATEGORY SEEDING ===\n";

$topAdded = 0;
$midAdded = 0;
$endAdded = 0;

$topReused = 0;
$midReused = 0;
$endReused = 0;

$pdo->beginTransaction();

try {
    foreach ($taxonomy as $topName => $midGroup) {
        $topName = trim($topName);
        
        // 1. Check or Insert Top Category
        $stmt = $pdo->prepare("SELECT tcat_id FROM tbl_top_category WHERE LOWER(TRIM(tcat_name)) = LOWER(?)");
        $stmt->execute([$topName]);
        $topRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($topRow) {
            $tcat_id = $topRow['tcat_id'];
            $topReused++;
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, 1) RETURNING tcat_id");
            $stmtIns->execute([$topName]);
            $tcat_id = $stmtIns->fetchColumn();
            $topAdded++;
            echo "[+TOP] Created: {$topName} (ID: {$tcat_id})\n";
        }

        foreach ($midGroup as $midName => $endList) {
            $midName = trim($midName);

            // 2. Check or Insert Mid Category under this Top Category
            $stmt = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE LOWER(TRIM(mcat_name)) = LOWER(?) AND tcat_id = ?");
            $stmt->execute([$midName, $tcat_id]);
            $midRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($midRow) {
                $mcat_id = $midRow['mcat_id'];
                $midReused++;
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
                $stmtIns->execute([$midName, $tcat_id]);
                $mcat_id = $stmtIns->fetchColumn();
                $midAdded++;
                echo "  [+MID] Created: {$midName} (ID: {$mcat_id}) -> under [{$topName}]\n";
            }

            foreach ($endList as $endName) {
                $endName = trim($endName);

                // 3. Check or Insert End Category under this Mid Category
                $stmt = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE LOWER(TRIM(ecat_name)) = LOWER(?) AND mcat_id = ?");
                $stmt->execute([$endName, $mcat_id]);
                $endRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($endRow) {
                    $endReused++;
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
                    $stmtIns->execute([$endName, $mcat_id]);
                    $ecat_id = $stmtIns->fetchColumn();
                    $endAdded++;
                    echo "    [+END] Created: {$endName} (ID: {$ecat_id})\n";
                }
            }
        }
    }

    $pdo->commit();
    echo "\n=== SEEDING COMPLETED SUCCESSFULLY ===\n";
    echo "Top Categories:  {$topAdded} added, {$topReused} existing/reused\n";
    echo "Mid Categories:  {$midAdded} added, {$midReused} existing/reused\n";
    echo "End Categories:  {$endAdded} added, {$endReused} existing/reused\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR OCCURRED: " . $e->getMessage() . "\n";
    exit(1);
}
