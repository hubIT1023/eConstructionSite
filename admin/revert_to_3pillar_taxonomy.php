<?php
require_once __DIR__ . '/inc/config.php';

echo "=== E-CONSTRUCTION SUPPLY: REVERT TO 3-PILLAR CATEGORY ARCHITECTURE ===\n\n";

try {
    $pdo->beginTransaction();

    // 1. Definition of the 3-Pillar Architecture
    $taxonomy = [
        'Building Materials' => [
            'Steel & Metal' => [
                'Steel I-Beams',
                'Nails',
                'Rebar & Mesh',
                'Tubular Steel',
                'Round Bars',
                'Angle Bars',
                'C-Purlins',
                'GI Pipes',
                'FLAT BAR',
                'Square Bar',
                'metal stud',
                'Metal Furring',
                'Barbe Wire',
                'Welded Wire (Green)',
                'Hog wire & tire wire',
                'Paint roller',
                'Welding Rod'
            ],
            'Concrete & Cement' => [
                'Portland Cement'
            ],
            'Roofing & Wall' => [
                'Paints',
                'Plywood, Phenolic & Hardiflex',
                'Wood Works',
                'G.I Corrogated',
                'Plastic Roofing',
                'Metal Sheets',
                'Screen Wire',
                'S4S wood',
                'Glue & silicon',
                'Doors'
            ]
        ],
        'Infrastructure & Utilities' => [
            'Plumbing & Pipes' => [
                'PVC Pipes',
                'Pipe Fittings',
                'Valves & Flanges',
                'Lababo'
            ],
            'Electrical & Wiring' => [
                'Copper Cables',
                'Conduits & Fittings',
                'Panel Board',
                'Electrical Devices'
            ]
        ],
        'Tools & Safety' => [
            'Power Tools' => [
                'Drills & Drivers',
                'Steel Tape'
            ],
            'Safety Equipment' => [
                'cutting Disk',
                'Door Knobs'
            ]
        ]
    ];

    // 2. Ensure categories exist in DB
    $ecat_id_map = []; // "tcat|mcat|ecat" => ecat_id
    $tcat_id_map = []; // "tcat" => tcat_id
    $mcat_id_map = []; // "tcat|mcat" => mcat_id

    echo "Ensuring 3-Pillar taxonomy in database...\n";
    foreach ($taxonomy as $tcat_name => $mcat_list) {
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

    // Set other top categories to hidden show_on_menu = 0
    $canonical_tcat_ids = array_values($tcat_id_map);
    $pdo->exec("UPDATE tbl_top_category SET show_on_menu = 0 WHERE tcat_id NOT IN (" . implode(',', $canonical_tcat_ids) . ")");

    echo "\nTaxonomy established (" . count($ecat_id_map) . " End Categories mapped).\n";

    // 3. Product Remapping Logic
    function map_product_to_3pillar($p, $ecat_id_map) {
        $name = strtolower($p['p_name'] . ' ' . ($p['ecat_name'] ?? '') . ' ' . ($p['mcat_name'] ?? '') . ' ' . ($p['tcat_name'] ?? ''));

        // 1. INFRASTRUCTURE & UTILITIES -> PLUMBING
        if (strpos($name, 'lababo') !== false || strpos($name, 'kitchen sink') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Plumbing & Pipes|Lababo'];
        }
        if (strpos($name, 'valve') !== false || strpos($name, 'faucet') !== false || strpos($name, 'bibcock') !== false || strpos($name, 'flange') !== false || strpos($name, 'gripo') !== false || strpos($name, 'check valve') !== false || strpos($name, 'ball valve') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Plumbing & Pipes|Valves & Flanges'];
        }
        if (strpos($name, 'elbow') !== false || strpos($name, 'tee') !== false || strpos($name, 'wye') !== false || strpos($name, 'p-trap') !== false || strpos($name, 'cleanout') !== false || strpos($name, 'coupling') !== false || strpos($name, 'bushing') !== false || strpos($name, 'reducer') !== false || strpos($name, 'solvent cement') !== false || strpos($name, 'teflon') !== false || strpos($name, 'floor drain') !== false || strpos($name, 'pipe fitting') !== false || strpos($name, 'ppr fitting') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Plumbing & Pipes|Pipe Fittings'];
        }
        if (strpos($name, 'pvc') !== false || strpos($name, 'sanitary pipe') !== false || strpos($name, 'orange pipe') !== false || strpos($name, 'blue pipe') !== false || strpos($name, 'ppr pipe') !== false || strpos($name, 'hdpe') !== false || strpos($name, 'pe pipe') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Plumbing & Pipes|PVC Pipes'];
        }

        // 2. INFRASTRUCTURE & UTILITIES -> ELECTRICAL
        if (strpos($name, 'thhn') !== false || strpos($name, 'thwn') !== false || strpos($name, 'stranded wire') !== false || strpos($name, 'copper wire') !== false || strpos($name, 'romex') !== false || strpos($name, 'royal cord') !== false || strpos($name, 'flat cord') !== false || strpos($name, 'cable') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Electrical & Wiring|Copper Cables'];
        }
        if (strpos($name, 'conduit') !== false || strpos($name, 'emt') !== false || strpos($name, 'imc') !== false || strpos($name, 'utility box') !== false || strpos($name, 'octagonal box') !== false || strpos($name, 'junction box') !== false || strpos($name, 'electrical tape') !== false || strpos($name, 'wire nut') !== false || strpos($name, 'conduit clamp') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Electrical & Wiring|Conduits & Fittings'];
        }
        if (strpos($name, 'breaker') !== false || strpos($name, 'mcb') !== false || strpos($name, 'panel board') !== false || strpos($name, 'panelboard') !== false || strpos($name, 'safety switch') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Electrical & Wiring|Panel Board'];
        }
        if (strpos($name, 'switch') !== false || strpos($name, 'outlet') !== false || strpos($name, 'socket') !== false || strpos($name, 'plug') !== false || strpos($name, 'receptacle') !== false || strpos($name, 'bulb') !== false || strpos($name, 'led') !== false || strpos($name, 'downlight') !== false || strpos($name, 'lamp') !== false) {
            return $ecat_id_map['Infrastructure & Utilities|Electrical & Wiring|Electrical Devices'];
        }

        // 3. TOOLS & SAFETY
        if (strpos($name, 'drill') !== false || strpos($name, 'driver') !== false || strpos($name, 'grinder') !== false || strpos($name, 'saw machine') !== false) {
            return $ecat_id_map['Tools & Safety|Power Tools|Drills & Drivers'];
        }
        if (strpos($name, 'tape') !== false || strpos($name, 'measuring tape') !== false || strpos($name, 'steel tape') !== false || strpos($name, 'level') !== false || strpos($name, 'meter') !== false) {
            return $ecat_id_map['Tools & Safety|Power Tools|Steel Tape'];
        }
        if (strpos($name, 'cutting disk') !== false || strpos($name, 'cutting disc') !== false || strpos($name, 'cut off') !== false || strpos($name, 'grinding disc') !== false || strpos($name, 'diamond wheel') !== false || strpos($name, 'diamond disc') !== false || strpos($name, 'blade') !== false) {
            return $ecat_id_map['Tools & Safety|Safety Equipment|cutting Disk'];
        }
        if (strpos($name, 'door knob') !== false || strpos($name, 'lockset') !== false || strpos($name, 'deadbolt') !== false || strpos($name, 'padlock') !== false || strpos($name, 'latch') !== false || strpos($name, 'hinge') !== false) {
            return $ecat_id_map['Tools & Safety|Safety Equipment|Door Knobs'];
        }

        // 4. BUILDING MATERIALS -> CONCRETE & CEMENT
        if (strpos($name, 'cement') !== false || strpos($name, 'holcim') !== false || strpos($name, 'republic') !== false || strpos($name, 'portland') !== false || strpos($name, 'pozzolan') !== false || strpos($name, 'sand') !== false && strpos($name, 'paper') === false || strpos($name, 'gravel') !== false || strpos($name, 'hollow block') !== false || strpos($name, 'chb') !== false) {
            return $ecat_id_map['Building Materials|Concrete & Cement|Portland Cement'];
        }

        // 5. BUILDING MATERIALS -> STEEL & METAL
        if (strpos($name, 'i-beam') !== false || strpos($name, 'wide flange') !== false || strpos($name, 'h-beam') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Steel I-Beams'];
        }
        if (strpos($name, 'welding rod') !== false || strpos($name, 'electrode') !== false || strpos($name, 'e6011') !== false || strpos($name, 'e6013') !== false || strpos($name, 'welder') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Welding Rod'];
        }
        if (strpos($name, 'paint roller') !== false || strpos($name, 'roller') !== false || strpos($name, 'brush') !== false || strpos($name, 'paint brush') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Paint roller'];
        }
        if (strpos($name, 'hog wire') !== false || strpos($name, 'tire wire') !== false || strpos($name, 'tie wire') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Hog wire & tire wire'];
        }
        if (strpos($name, 'welded wire') !== false || strpos($name, 'green wire') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Welded Wire (Green)'];
        }
        if (strpos($name, 'barbe wire') !== false || strpos($name, 'barbed wire') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Barbe Wire'];
        }
        if (strpos($name, 'furring') !== false || strpos($name, 'carrying channel') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Metal Furring'];
        }
        if (strpos($name, 'stud') !== false || strpos($name, 'metal stud') !== false || strpos($name, 'track') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|metal stud'];
        }
        if (strpos($name, 'square bar') !== false || strpos($name, 'squre bar') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Square Bar'];
        }
        if (strpos($name, 'flat bar') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|FLAT BAR'];
        }
        if (strpos($name, 'gi pipe') !== false || strpos($name, 'schedule 40') !== false || strpos($name, 'schedule 20') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|GI Pipes'];
        }
        if (strpos($name, 'c-purlin') !== false || strpos($name, 'purlin') !== false || strpos($name, 'channel') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|C-Purlins'];
        }
        if (strpos($name, 'angle bar') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Angle Bars'];
        }
        if (strpos($name, 'round bar') !== false || strpos($name, 'plain round') !== false || strpos($name, 'shafting') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Round Bars'];
        }
        if (strpos($name, 'tubular') !== false || strpos($name, 'stainless tube') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Tubular Steel'];
        }
        if (strpos($name, 'deformed') !== false || strpos($name, 'rebar') !== false || strpos($name, 'mesh') !== false || strpos($name, 'cyclone') !== false || strpos($name, 'matting') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Rebar & Mesh'];
        }
        if (strpos($name, 'nail') !== false || strpos($name, 'pako') !== false || strpos($name, 'screw') !== false || strpos($name, 'tekscrew') !== false || strpos($name, 'bolt') !== false || strpos($name, 'rivet') !== false || strpos($name, 'tox') !== false) {
            return $ecat_id_map['Building Materials|Steel & Metal|Nails'];
        }

        // 6. BUILDING MATERIALS -> ROOFING & WALL
        if (strpos($name, 'paint') !== false || strpos($name, 'latex') !== false || strpos($name, 'enamel') !== false || strpos($name, 'qde') !== false || strpos($name, 'primer') !== false || strpos($name, 'varnish') !== false || strpos($name, 'thinner') !== false || strpos($name, 'tinting') !== false || strpos($name, 'sandpaper') !== false || strpos($name, 'lija') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Paints'];
        }
        if (strpos($name, 'plywood') !== false || strpos($name, 'phenolic') !== false || strpos($name, 'hardiflex') !== false || strpos($name, 'hardie') !== false || strpos($name, 'fiber cement') !== false || strpos($name, 'gypsum') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Plywood, Phenolic & Hardiflex'];
        }
        if (strpos($name, 's4s') !== false || strpos($name, 'kiln') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|S4S wood'];
        }
        if (strpos($name, 'wood') !== false || strpos($name, 'lumber') !== false || strpos($name, 'coco') !== false || strpos($name, 'kahoy') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Wood Works'];
        }
        if (strpos($name, 'corrogated') !== false || strpos($name, 'corrugated') !== false || strpos($name, 'rib') !== false || strpos($name, 'long span') !== false || strpos($name, 'tile') !== false && strpos($name, 'roof') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|G.I Corrogated'];
        }
        if (strpos($name, 'plastic roofing') !== false || strpos($name, 'polycarbonate') !== false || strpos($name, 'skylight') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Plastic Roofing'];
        }
        if (strpos($name, 'metal sheet') !== false || strpos($name, 'plain sheet') !== false || strpos($name, 'plainsheet') !== false || strpos($name, 'ridge roll') !== false || strpos($name, 'gutter') !== false || strpos($name, 'flashing') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Metal Sheets'];
        }
        if (strpos($name, 'screen') !== false || strpos($name, 'net') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Screen Wire'];
        }
        if (strpos($name, 'glue') !== false || strpos($name, 'silicon') !== false || strpos($name, 'sealant') !== false || strpos($name, 'vulcaseal') !== false || strpos($name, 'adhesive') !== false || strpos($name, 'contact cement') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Glue & silicon'];
        }
        if (strpos($name, 'door') !== false || strpos($name, 'pvc door') !== false || strpos($name, 'flush door') !== false || strpos($name, 'window') !== false || strpos($name, 'jalousie') !== false) {
            return $ecat_id_map['Building Materials|Roofing & Wall|Doors'];
        }

        // Fallback default
        return $ecat_id_map['Building Materials|Steel & Metal|Nails'];
    }

    echo "Remapping all 823 products to 3-pillar categories...\n";
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
        $target_ecat_id = map_product_to_3pillar($prod, $ecat_id_map);
        if (!$target_ecat_id) {
            throw new Exception("Could not map product ID {$prod['p_id']}: {$prod['p_name']}");
        }
        $update_stmt->execute([$target_ecat_id, $prod['p_id']]);
        $remapped_count++;
    }
    echo "  -> Remapped {$remapped_count} / " . count($products) . " products.\n";

    // 4. Cleanup obsolete empty categories
    echo "Cleaning up obsolete categories...\n";
    $canonical_ecat_ids = array_values($ecat_id_map);
    $canonical_mcat_ids = array_values($mcat_id_map);
    $canonical_tcat_ids = array_values($tcat_id_map);

    // Delete non-canonical end categories with 0 products
    $pdo->exec("
        DELETE FROM tbl_end_category 
        WHERE ecat_id NOT IN (" . implode(',', $canonical_ecat_ids) . ")
        AND ecat_id NOT IN (SELECT DISTINCT ecat_id FROM tbl_product WHERE ecat_id IS NOT NULL)
    ");

    // Delete non-canonical mid categories with 0 end categories
    $pdo->exec("
        DELETE FROM tbl_mid_category 
        WHERE mcat_id NOT IN (" . implode(',', $canonical_mcat_ids) . ")
        AND mcat_id NOT IN (SELECT DISTINCT mcat_id FROM tbl_end_category)
    ");

    // Delete non-canonical top categories with 0 mid categories
    $pdo->exec("
        DELETE FROM tbl_top_category 
        WHERE tcat_id NOT IN (" . implode(',', $canonical_tcat_ids) . ")
        AND tcat_id NOT IN (SELECT DISTINCT tcat_id FROM tbl_mid_category)
    ");

    // Resync sequences
    $pdo->exec("SELECT setval('tbl_top_category_tcat_id_seq', (SELECT COALESCE(MAX(tcat_id), 1) FROM tbl_top_category));");
    $pdo->exec("SELECT setval('tbl_mid_category_mcat_id_seq', (SELECT COALESCE(MAX(mcat_id), 1) FROM tbl_mid_category));");
    $pdo->exec("SELECT setval('tbl_end_category_ecat_id_seq', (SELECT COALESCE(MAX(ecat_id), 1) FROM tbl_end_category));");

    $pdo->commit();
    echo "\n=== REVERSION COMPLETED SUCCESSFULLY! ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Reversion failed: " . $e->getMessage() . "\n";
    exit(1);
}
