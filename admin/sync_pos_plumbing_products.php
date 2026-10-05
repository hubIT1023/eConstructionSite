<?php
/**
 * POS Plumbing Products Sync / Updater
 *
 * Updates or inserts the provided 70 plumbing products matching exact IDs (407 to 612)
 * for Supplier "SAM & INRI CONSTRUCTION SUPPLY" (supplier_id = 2).
 *
 * Synchronizes PostgreSQL sequence upon completion.
 */

require_once(__DIR__ . '/inc/config.php');

$supplierId = 2; // SAM & INRI CONSTRUCTION SUPPLY

// Ensure category structure exists for "Plumbing Supplies" -> "PVC Pipes & Fittings" -> Subcategories
$topName = 'Plumbing Supplies';
$midName = 'PVC Pipes & Fittings';
$endCategories = [
    'PVC Sanitary Pipes',
    'PVC Elbows',
    'PVC Tees & Wyes',
    'PVC Traps, Cleanouts & Caps',
    'PVC Couplings & Unions',
    'PVC Reducers & Adapters',
    'PVC Pipes'
];

// 1. Resolve Top Category
$st = $pdo->prepare("SELECT tcat_id FROM tbl_top_category WHERE LOWER(TRIM(tcat_name)) = LOWER(?)");
$st->execute([$topName]);
$tcat_id = $st->fetchColumn();
if (!$tcat_id) {
    $ins = $pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, 1) RETURNING tcat_id");
    $ins->execute([$topName]);
    $tcat_id = $ins->fetchColumn();
}

// 2. Resolve Mid Category
$st = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE LOWER(TRIM(mcat_name)) = LOWER(?) AND tcat_id = ?");
$st->execute([$midName, $tcat_id]);
$mcat_id = $st->fetchColumn();
if (!$mcat_id) {
    $ins = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
    $ins->execute([$midName, $tcat_id]);
    $mcat_id = $ins->fetchColumn();
}

// 3. Resolve End Categories Map
$ecatMap = [];
foreach ($endCategories as $eName) {
    $st = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE LOWER(TRIM(ecat_name)) = LOWER(?) AND mcat_id = ?");
    $st->execute([$eName, $mcat_id]);
    $ecat_id = $st->fetchColumn();
    if (!$ecat_id) {
        $ins = $pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
        $ins->execute([$eName, $mcat_id]);
        $ecat_id = $ins->fetchColumn();
    }
    $ecatMap[$eName] = (int)$ecat_id;
}

// Dataset definitions
$products = [
    ['id' => 407, 'name' => 'Sanitary Pipe Ord. S500 2', 'cp' => 141, 'ca' => 121, 'mu' => 20, 'q' => 1, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 408, 'name' => 'Sanitary Pipe Ord. S500 3', 'cp' => 249, 'ca' => 229, 'mu' => 20, 'q' => 96, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 409, 'name' => 'Sanitary Pipe Ord. S500 4', 'cp' => 331, 'ca' => 311, 'mu' => 20, 'q' => 49, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 410, 'name' => 'Sanitary Pipe Ord. S900 2', 'cp' => 185, 'ca' => 165, 'mu' => 20, 'q' => 44, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 411, 'name' => 'Sanitary Pipe Ord. S900 3', 'cp' => 369, 'ca' => 349, 'mu' => 20, 'q' => 45, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 412, 'name' => 'Sanitary Pipe Ord. S900 4', 'cp' => 490, 'ca' => 470, 'mu' => 20, 'q' => 46, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 413, 'name' => 'Sanitary Pipe Atlanta S600 2', 'cp' => 522, 'ca' => 502, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 414, 'name' => 'Sanitary Pipe Atlanta S600 3', 'cp' => 729, 'ca' => 709, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 415, 'name' => 'Sanitary Pipe Atlanta S600 4', 'cp' => 1245, 'ca' => 1225, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 416, 'name' => 'Sanitary Pipe Atlanta S1000 2', 'cp' => 658, 'ca' => 638, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 417, 'name' => 'Sanitary Pipe Atlanta S1000 3', 'cp' => 1248, 'ca' => 1228, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 418, 'name' => 'Sanitary Pipe Atlanta S1000 4', 'cp' => 1662, 'ca' => 1642, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 419, 'name' => 'Sanitary Pipe Lamtex S1000 3', 'cp' => 764, 'ca' => 744, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 420, 'name' => 'Sanitary Pipe Lamtex S1000 4', 'cp' => 1198, 'ca' => 1178, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 421, 'name' => 'Sanitary Pipe Lamtex S1000 6', 'cp' => 1490, 'ca' => 1470, 'mu' => 20, 'q' => 10, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 422, 'name' => 'Sanitary Pipe Lamtex S600 2', 'cp' => 271, 'ca' => 251, 'mu' => 20, 'q' => 41, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 423, 'name' => 'Sanitary Pipe Lamtex S600 3', 'cp' => 484, 'ca' => 464, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 424, 'name' => 'Sanitary Pipe Lamtex S600 4', 'cp' => 647, 'ca' => 627, 'mu' => 20, 'q' => 10, 's' => 10, 'ecat' => 'PVC Sanitary Pipes'],
    ['id' => 425, 'name' => 'Sanitary  Elbow Ord. 2 x 45°', 'cp' => 33, 'ca' => 13, 'mu' => 20, 'q' => 49, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 426, 'name' => 'Sanitary  Elbow Ord. 3 x 45°', 'cp' => 44, 'ca' => 24, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 427, 'name' => 'Sanitary  Elbow Ord. 4 x 45°', 'cp' => 73, 'ca' => 53, 'mu' => 20, 'q' => 48, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 428, 'name' => 'Sanitary  Elbow Atlanta 2 x 45°', 'cp' => 45, 'ca' => 25, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 429, 'name' => 'Sanitary  Elbow Atlanta 3 x 45°', 'cp' => 109, 'ca' => 89, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 430, 'name' => 'Sanitary  Elbow Atlanta 4 x 45°', 'cp' => 115, 'ca' => 95, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 431, 'name' => 'Sanitary  Elbow Ord. 2 x 90°', 'cp' => 37, 'ca' => 17, 'mu' => 20, 'q' => 22, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 432, 'name' => 'Sanitary  Elbow Ord. 3 x 90°', 'cp' => 65, 'ca' => 45, 'mu' => 20, 'q' => 41, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 433, 'name' => 'Sanitary  Elbow Ord. 4 x 90°', 'cp' => 100, 'ca' => 80, 'mu' => 20, 'q' => 48, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 434, 'name' => 'Sanitary  Elbow Atlanta 2 x 90°', 'cp' => 69, 'ca' => 49, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 435, 'name' => 'Sanitary  Elbow Atlanta 3 x 90°', 'cp' => 127, 'ca' => 107, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 436, 'name' => 'Sanitary  Elbow Atlanta 4 x 90°', 'cp' => 151, 'ca' => 131, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 437, 'name' => 'Sanitary Tee Ord. 2', 'cp' => 56, 'ca' => 36, 'mu' => 20, 'q' => 49, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 438, 'name' => 'Sanitary Tee Ord. 3', 'cp' => 82, 'ca' => 62, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 439, 'name' => 'Sanitary Tee Ord. 4', 'cp' => 167, 'ca' => 147, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 440, 'name' => 'Sanitary Tee Ord. 2 x 3', 'cp' => 61, 'ca' => 41, 'mu' => 20, 'q' => 48, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 441, 'name' => 'Sanitary Tee Ord. 2 x 4', 'cp' => 86, 'ca' => 66, 'mu' => 20, 'q' => 49, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 442, 'name' => 'Sanitary Tee Ord. 3 x 4', 'cp' => 99, 'ca' => 79, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 443, 'name' => 'Sanitary Tee Atlanta 2', 'cp' => 77, 'ca' => 57, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 444, 'name' => 'Sanitary Tee Atlanta 3', 'cp' => 156, 'ca' => 136, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 445, 'name' => 'Sanitary Tee Atlanta 4', 'cp' => 250, 'ca' => 230, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 446, 'name' => 'Sanitary Tee Atlanta 2 x 3', 'cp' => 192, 'ca' => 172, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 447, 'name' => 'Sanitary Tee Atlanta 2 x 4', 'cp' => 287, 'ca' => 267, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 448, 'name' => 'Sanitary Tee Atlanta 3 x 4', 'cp' => 464, 'ca' => 444, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 449, 'name' => 'Sanitary WYE Ord. 2', 'cp' => 46, 'ca' => 26, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 451, 'name' => 'Sanitary WYE Ord. 3', 'cp' => 77, 'ca' => 57, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 452, 'name' => 'Sanitary WYE Ord. 4', 'cp' => 119, 'ca' => 99, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 453, 'name' => 'Sanitary WYE Ord. 2 x 3', 'cp' => 70, 'ca' => 50, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 454, 'name' => 'Sanitary WYE Ord. 2 x 4', 'cp' => 94, 'ca' => 74, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 455, 'name' => 'Sanitary WYE Ord. 3 x 4', 'cp' => 108, 'ca' => 88, 'mu' => 20, 'q' => 49, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 456, 'name' => 'Sanitary WYE Atlanta 2', 'cp' => 73, 'ca' => 53, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 457, 'name' => 'Sanitary WYE Atlanta 3', 'cp' => 133, 'ca' => 113, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 458, 'name' => 'Sanitary WYE Atlanta 4', 'cp' => 282, 'ca' => 262, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 459, 'name' => 'Sanitary WYE Atlanta 2 x 3', 'cp' => 173, 'ca' => 153, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 460, 'name' => 'Sanitary WYE Atlanta 2 x 4', 'cp' => 200, 'ca' => 180, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 461, 'name' => 'Sanitary WYE Atlanta 3 x 4', 'cp' => 283, 'ca' => 263, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 476, 'name' => 'Sanitary  Clean Out Ord. 2', 'cp' => 33, 'ca' => 13, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 477, 'name' => 'Sanitary Clean Out Ord. 3', 'cp' => 48, 'ca' => 28, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 478, 'name' => 'Sanitary Clean Out Ord. 4', 'cp' => 60, 'ca' => 40, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 479, 'name' => 'Sanitary Clean Out Atlanta 2', 'cp' => 43, 'ca' => 23, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 480, 'name' => 'Sanitary Clean Out Atlanta 3', 'cp' => 94, 'ca' => 74, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 481, 'name' => 'Sanitary Clean Out Atlanta 4', 'cp' => 149, 'ca' => 129, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 482, 'name' => 'Sanitary Coupling Ord.  2', 'cp' => 23, 'ca' => 3, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 483, 'name' => 'Sanitary Coupling Ord.  3', 'cp' => 27, 'ca' => 7, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 484, 'name' => 'Sanitary Coupling Ord.  4', 'cp' => 48, 'ca' => 28, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 485, 'name' => 'Sanitary Coupling Atlanta 2', 'cp' => 29, 'ca' => 9, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 486, 'name' => 'Sanitary Coupling Atlanta 3', 'cp' => 56, 'ca' => 36, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 487, 'name' => 'Sanitary Coupling Atlanta 4', 'cp' => 102, 'ca' => 82, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 488, 'name' => 'Sanitary P-Trap Atlanta 2', 'cp' => 198, 'ca' => 178, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 489, 'name' => 'Sanitary P-Trap Atlanta 3', 'cp' => 253, 'ca' => 233, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 490, 'name' => 'Sanitary P-Trap Atlanta 3 (Large)', 'cp' => 475, 'ca' => 455, 'mu' => 20, 'q' => 55, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 491, 'name' => 'Sanitary P-Trap Ord. 2', 'cp' => 80, 'ca' => 60, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 492, 'name' => 'Sanitary P-Trap Ord. 3', 'cp' => 163, 'ca' => 143, 'mu' => 20, 'q' => 49, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 493, 'name' => 'Sanitary P-Trap Ord. 4', 'cp' => 245, 'ca' => 225, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 494, 'name' => 'Sanitary Bushing Reducer Ord. 2 x 3', 'cp' => 31, 'ca' => 11, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 495, 'name' => 'Sanitary Bushing Reducer Ord. 2 x 4', 'cp' => 44, 'ca' => 24, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 496, 'name' => 'Sanitary Bushing Reducer Ord. 3 x 4', 'cp' => 48, 'ca' => 28, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 497, 'name' => 'Sanitary Bushing Reducer Atlanta 2 x 3', 'cp' => 51, 'ca' => 31, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 498, 'name' => 'Sanitary Bushing Reducer Atlanta 2 x 4', 'cp' => 79, 'ca' => 59, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 499, 'name' => 'Sanitary Bushing Reducer Atlanta 3 x 4', 'cp' => 92, 'ca' => 72, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 500, 'name' => 'Sanitary WYE Atlanta 6 x 6', 'cp' => 1483, 'ca' => 1463, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 501, 'name' => 'Sanitary Bushing Reducer Atlanta 4 x 6', 'cp' => 476, 'ca' => 456, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 502, 'name' => 'PVC Pipe Blue 1/2', 'cp' => 90, 'ca' => 70, 'mu' => 20, 'q' => 40, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 503, 'name' => 'PVC Pipe Blue 3/4', 'cp' => 125, 'ca' => 105, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 504, 'name' => 'PVC Pipe Blue 1', 'cp' => 164, 'ca' => 144, 'mu' => 20, 'q' => 46, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 505, 'name' => 'PVC Pipe Blue 1 1/4', 'cp' => 230, 'ca' => 210, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 506, 'name' => 'PVC Pipe Blue 1 1/2', 'cp' => 306, 'ca' => 286, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 507, 'name' => 'PVC Pipe Blue Atlanta 2', 'cp' => 1068, 'ca' => 1048, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 508, 'name' => 'PVC Pipe Blue Atlanta 1/2', 'cp' => 179, 'ca' => 159, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 509, 'name' => 'PVC Pipe Blue Atlanta 3/4', 'cp' => 185, 'ca' => 165, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Pipes'],
    ['id' => 526, 'name' => 'PVC Blue Elbow 1/2', 'cp' => 10, 'ca' => 5, 'mu' => 5, 'q' => 98, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 527, 'name' => 'PVC Blue Elbow 3/4', 'cp' => 12, 'ca' => 2, 'mu' => 10, 'q' => 100, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 528, 'name' => 'PVC Blue Elbow 1', 'cp' => 21, 'ca' => 1, 'mu' => 20, 'q' => 100, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 529, 'name' => 'PVC Blue Elbow 1 1/4', 'cp' => 28, 'ca' => 8, 'mu' => 20, 'q' => 100, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 530, 'name' => 'PVC Blue Elbow 1 1/2', 'cp' => 44, 'ca' => 24, 'mu' => 20, 'q' => 100, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 531, 'name' => 'PVC Blue Tee 1/2', 'cp' => 12, 'ca' => 2, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 532, 'name' => 'PVC Blue Tee 3/4', 'cp' => 14, 'ca' => 4, 'mu' => 10, 'q' => 47, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 533, 'name' => 'PVC Blue Tee 1', 'cp' => 27, 'ca' => 7, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 534, 'name' => 'PVC Blue Tee 1 1/4', 'cp' => 41, 'ca' => 21, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 535, 'name' => 'PVC Blue Tee 1 1/2', 'cp' => 50, 'ca' => 30, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 536, 'name' => 'PVC Blue Coupling 1/2', 'cp' => 8, 'ca' => 3, 'mu' => 5, 'q' => 47, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 537, 'name' => 'PVC Blue Coupling 3/4', 'cp' => 10, 'ca' => 5, 'mu' => 5, 'q' => 47, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 538, 'name' => 'PVC Blue Coupling 1', 'cp' => 15, 'ca' => 5, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 539, 'name' => 'PVC Blue Coupling 1 1/4', 'cp' => 18, 'ca' => 8, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 540, 'name' => 'PVC Blue Coupling 1 1/2', 'cp' => 22, 'ca' => 2, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 546, 'name' => 'PVC Blue FTA 1/2', 'cp' => 12, 'ca' => 2, 'mu' => 10, 'q' => 47, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 547, 'name' => 'PVC Blue FTA 3/4', 'cp' => 13, 'ca' => 3, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 548, 'name' => 'PVC Blue FTA 1', 'cp' => 21, 'ca' => 1, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 549, 'name' => 'PVC Blue FTA 1 1/4', 'cp' => 26, 'ca' => 6, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 550, 'name' => 'PVC Blue FTA 1 1/2', 'cp' => 39, 'ca' => 19, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 551, 'name' => 'PVC Blue End Cap 1/2', 'cp' => 7, 'ca' => 2, 'mu' => 5, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 552, 'name' => 'PVC Blue End Cap 3/4', 'cp' => 11, 'ca' => 1, 'mu' => 10, 'q' => 49, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 553, 'name' => 'PVC Blue End Cap 1', 'cp' => 16, 'ca' => 6, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 554, 'name' => 'PVC Blue End Cap 1 1/4', 'cp' => 31, 'ca' => 11, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 555, 'name' => 'PVC Blue End Plug 1/2', 'cp' => 17, 'ca' => 7, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 556, 'name' => 'PVC Blue End Plug 3/4', 'cp' => 23, 'ca' => 3, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 557, 'name' => 'PVC Blue Tee Threaded 1/2', 'cp' => 15, 'ca' => 5, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 558, 'name' => 'PVC Blue Tee Threaded 3/4', 'cp' => 22, 'ca' => 2, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Tees & Wyes'],
    ['id' => 559, 'name' => 'PVC Blue Elbow Threaded 1/2', 'cp' => 16, 'ca' => 6, 'mu' => 10, 'q' => 49, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 560, 'name' => 'PVC Blue Elbow Threaded 3/4', 'cp' => 18, 'ca' => 8, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 561, 'name' => 'PVC Blue Elbow Threaded 1', 'cp' => 25, 'ca' => 5, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Elbows'],
    ['id' => 573, 'name' => 'Blue PVC Reducer 3/4 x 1/2', 'cp' => 13, 'ca' => 3, 'mu' => 10, 'q' => 49, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 574, 'name' => 'Blue PVC Reducer 3/4 x 1', 'cp' => 17, 'ca' => 7, 'mu' => 10, 'q' => 49, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 581, 'name' => 'Blue MTA PVC 1/2', 'cp' => 12, 'ca' => 2, 'mu' => 10, 'q' => 49, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 582, 'name' => 'Blue MTA PVC 3/4', 'cp' => 13, 'ca' => 3, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 583, 'name' => 'Blue MTA PVC 1', 'cp' => 21, 'ca' => 1, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 584, 'name' => 'Blue MTA PVC 1 1/4', 'cp' => 26, 'ca' => 6, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 585, 'name' => 'Blue MTA PVC 1 1/2', 'cp' => 39, 'ca' => 19, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 586, 'name' => 'Blue PVC Reducer 1/2 x 1', 'cp' => 17, 'ca' => 7, 'mu' => 10, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 587, 'name' => 'Blue PVC Reducer 1 1/4 x 1', 'cp' => 45, 'ca' => 25, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 588, 'name' => 'Blue PVC Reducer 1 1/4 x 1/2', 'cp' => 41, 'ca' => 21, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 589, 'name' => 'Blue PVC Reducer 3/4 x 1 1/4', 'cp' => 45, 'ca' => 25, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 590, 'name' => 'Blue PVC Reducer 3/4 x 1 1/2', 'cp' => 45, 'ca' => 25, 'mu' => 20, 'q' => 25, 's' => 10, 'ecat' => 'PVC Reducers & Adapters'],
    ['id' => 591, 'name' => 'Union Patente 1/2', 'cp' => 76, 'ca' => 56, 'mu' => 20, 'q' => 47, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 592, 'name' => 'Union Patente 3/4', 'cp' => 88, 'ca' => 68, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 594, 'name' => 'Union Patente 1', 'cp' => 108, 'ca' => 88, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 595, 'name' => 'Union Patente 1 1/4', 'cp' => 162, 'ca' => 142, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 596, 'name' => 'Union Patente 1 1/2', 'cp' => 284, 'ca' => 264, 'mu' => 20, 'q' => 50, 's' => 10, 'ecat' => 'PVC Couplings & Unions'],
    ['id' => 610, 'name' => 'PVC Clamp BLUE 1/2', 'cp' => 2, 'ca' => 1, 'mu' => 1, 'q' => 100, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 611, 'name' => 'PVC Clamp BLUE 3/4', 'cp' => 3, 'ca' => 1, 'mu' => 2, 'q' => 100, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
    ['id' => 612, 'name' => 'PVC Clamp BLUE 1', 'cp' => 4, 'ca' => 2, 'mu' => 2, 'q' => 100, 's' => 10, 'ecat' => 'PVC Traps, Cleanouts & Caps'],
];

echo "=== STARTING POS PLUMBING PRODUCTS SYNC ===\n";
echo "Target Supplier ID: {$supplierId}\n";
echo "Total Items to Sync: " . count($products) . "\n\n";

$inserted = 0;
$updated = 0;

$pdo->beginTransaction();

try {
    $checkStmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE p_id = ?");
    
    $updateStmt = $pdo->prepare("
        UPDATE tbl_product 
        SET p_name = ?, 
            p_current_price = ?, 
            p_old_price = 0, 
            p_capital_price = ?, 
            p_markup = ?, 
            p_new_price = ?,
            p_qty = ?, 
            p_s_level = ?, 
            p_moq = 1, 
            p_is_active = 1, 
            p_is_featured = 0, 
            p_delivery_estimate = '3-5 days', 
            p_featured_photo = 'general_products.png', 
            supplier_id = ?, 
            ecat_id = ?
        WHERE p_id = ?
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO tbl_product (
            p_id, p_name, p_old_price, p_current_price, p_qty, p_featured_photo,
            p_description, p_short_description, p_feature, p_condition, p_return_policy,
            p_total_view, p_is_featured, p_is_active, ecat_id, supplier_id,
            p_moq, p_brand, p_specs, p_delivery_estimate, p_pdf, p_sku,
            p_new_price, p_new_qty, p_s_level, p_capital_price, p_markup
        ) VALUES (
            ?, ?, 0, ?, ?, 'general_products.png',
            '', '', '', '', '',
            1, 0, 1, ?, ?,
            1, 'Generic', '', '3-5 days', '', '',
            ?, 0, ?, ?, ?
        )
    ");

    foreach ($products as $p) {
        $pId = (int)$p['id'];
        $pName = trim($p['name']);
        $pCurrentPrice = number_format((float)$p['cp'], 2, '.', '');
        $pCapitalPrice = number_format((float)$p['ca'], 2, '.', '');
        $pMarkup = number_format((float)$p['mu'], 2, '.', '');
        $pNewPrice = $pCurrentPrice;
        $pQty = (int)$p['q'];
        $pSLevel = (int)$p['s'];
        $ecatName = $p['ecat'];
        $ecatId = $ecatMap[$ecatName] ?? 0;

        $checkStmt->execute([$pId]);
        $exists = $checkStmt->fetchColumn();

        if ($exists) {
            $updateStmt->execute([
                $pName, $pCurrentPrice, $pCapitalPrice, $pMarkup, $pNewPrice,
                $pQty, $pSLevel, $supplierId, $ecatId, $pId
            ]);
            $updated++;
            echo "[UPDATED] p_id: {$pId} -> {$pName} (₱{$pCurrentPrice}, Qty: {$pQty}, ecat_id: {$ecatId})\n";
        } else {
            $insertStmt->execute([
                $pId, $pName, $pCurrentPrice, $pQty,
                $ecatId, $supplierId,
                $pNewPrice, $pSLevel, $pCapitalPrice, $pMarkup
            ]);
            $inserted++;
            echo "[INSERTED] p_id: {$pId} -> {$pName} (₱{$pCurrentPrice}, Qty: {$pQty}, ecat_id: {$ecatId})\n";
        }
    }

    // Synchronize PostgreSQL Sequence to MAX(p_id)
    $pdo->exec("SELECT setval('tbl_product_p_id_seq', (SELECT MAX(p_id) FROM tbl_product))");

    $pdo->commit();

    echo "\n=== POS SYNC COMPLETED SUCCESSFULLY ===\n";
    echo "Total Processed: " . count($products) . "\n";
    echo "Inserted: {$inserted}\n";
    echo "Updated:  {$updated}\n";

    $maxId = $pdo->query("SELECT MAX(p_id) FROM tbl_product")->fetchColumn();
    $seqVal = $pdo->query("SELECT last_value FROM tbl_product_p_id_seq")->fetchColumn();
    echo "New Max Product ID in DB: {$maxId}\n";
    echo "Current Sequence Value:   {$seqVal}\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
