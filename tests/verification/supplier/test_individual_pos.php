<?php
$cmd1 = "php -r \"\\\$supplier_id=2; \\\$_SESSION['supplier_user']=['id'=>1,'supplier_id'=>2,'full_name'=>'Admin','role'=>'ADMIN']; \\\$_GET=['po_success'=>'1','po_id'=>'PO-20260908-AB57C']; chdir('/var/www/html/supplier'); ob_start(); include '/var/www/html/supplier/pos.php'; \\\$html = ob_get_clean(); echo (strpos(\\\$html, 'posPOSuccessModal') !== false ? '[PASS] Modal shown\n' : '[FAIL] Modal missing\n'); echo (strpos(\\\$html, 'closePOSPurchaseOrderModal()') !== false ? '[PASS] Continue button present\n' : '[FAIL] Continue button missing\n'); echo (strpos(\\\$html, 'let cart = [];') !== false ? '[PASS] Cart empty in JS\n' : '[FAIL] Cart not empty\n');\"";

echo "=== CLI TEST 1: PO Creation Success Mode ===\n";
passthru($cmd1);

$cmd2 = "php -r \"\\\$supplier_id=2; \\\$_SESSION['supplier_user']=['id'=>1,'supplier_id'=>2,'full_name'=>'Admin','role'=>'ADMIN']; \\\$_GET=['po_id'=>'PO-20260908-AB57C']; chdir('/var/www/html/supplier'); ob_start(); include '/var/www/html/supplier/pos.php'; \\\$html = ob_get_clean(); echo (strpos(\\\$html, 'PO: PO-20260908-AB57C') !== false ? '[PASS] PO badge displayed\n' : '[FAIL] PO badge missing\n'); echo (strpos(\\\$html, 'Common Nail') !== false ? '[PASS] PO item loaded in cart\n' : '[FAIL] PO item missing\n');\"";

echo "\n=== CLI TEST 2: Existing PO Payment Mode ===\n";
passthru($cmd2);

$cmd3 = "php -r \"\\\$supplier_id=2; \\\$_SESSION['supplier_user']=['id'=>1,'supplier_id'=>2,'full_name'=>'Admin','role'=>'ADMIN']; \\\$_GET=['clear_cart'=>'1']; chdir('/var/www/html/supplier'); ob_start(); include '/var/www/html/supplier/pos.php'; \\\$html = ob_get_clean(); echo (empty(\\\$_SESSION['pos_cart']) ? '[PASS] Session cart cleared\n' : '[FAIL] Session cart not cleared\n'); echo (strpos(\\\$html, 'let cart = [];') !== false ? '[PASS] Cart empty in JS\n' : '[FAIL] Cart not empty\n');\"";

echo "\n=== CLI TEST 3: Clear Cart / New Transaction Route ===\n";
passthru($cmd3);
