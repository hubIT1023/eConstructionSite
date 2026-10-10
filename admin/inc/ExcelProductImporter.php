<?php
/**
 * Excel / Spreadsheet Product Importer Engine
 * Supports HTML-based SpreadsheetML (.xls), CSV (.csv), and XML spreadsheets
 * Maps 3-Tier Taxonomy (Top -> Mid -> End Categories) and inserts into PostgreSQL ecomDB
 */

class ExcelProductImporter {

    private $pdo;
    private $category_cache = [
        'top' => [],
        'mid' => [],
        'end' => []
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadCategoryCache();
    }

    /**
     * Pre-load existing categories into memory cache for high-speed resolution
     */
    private function loadCategoryCache() {
        try {
            // 1. Top categories
            $stmt = $this->pdo->query("SELECT tcat_id, tcat_name FROM tbl_top_category");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = mb_strtolower(trim($row['tcat_name']), 'UTF-8');
                $this->category_cache['top'][$key] = (int)$row['tcat_id'];
            }

            // 2. Mid categories
            $stmt = $this->pdo->query("SELECT mcat_id, mcat_name, tcat_id FROM tbl_mid_category");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = (int)$row['tcat_id'] . '_' . mb_strtolower(trim($row['mcat_name']), 'UTF-8');
                $this->category_cache['mid'][$key] = (int)$row['mcat_id'];
            }

            // 3. End categories
            $stmt = $this->pdo->query("SELECT ecat_id, ecat_name, mcat_id FROM tbl_end_category");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = (int)$row['mcat_id'] . '_' . mb_strtolower(trim($row['ecat_name']), 'UTF-8');
                $this->category_cache['end'][$key] = (int)$row['ecat_id'];
            }
        } catch (Exception $e) {}
    }

    /**
     * Parse spreadsheet file into normalized product associative array
     */
    public function parseSpreadsheet($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("Spreadsheet file not found: " . htmlspecialchars($filePath));
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $content = file_get_contents($filePath);

        // Check if file is HTML/SpreadsheetML table format (.xls export format)
        if (strpos($content, '<table') !== false || strpos($content, '<html') !== false || $ext === 'xls') {
            return $this->parseHtmlSpreadsheet($content);
        }

        // Standard CSV fallback
        return $this->parseCsvSpreadsheet($filePath);
    }

    /**
     * Parse HTML/SpreadsheetML table
     */
    private function parseHtmlSpreadsheet($htmlContent) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            throw new Exception("No product data table found inside spreadsheet.");
        }

        $table = $tables->item(0);
        $headers = [];
        $rows = [];

        $thList = $table->getElementsByTagName('th');
        foreach ($thList as $th) {
            $headers[] = trim($th->textContent);
        }

        $trList = $table->getElementsByTagName('tr');
        foreach ($trList as $tr) {
            $tdList = $tr->getElementsByTagName('td');
            if ($tdList->length === 0) continue; // Skip header row

            $rawRow = [];
            foreach ($tdList as $idx => $td) {
                $colName = $headers[$idx] ?? "Col_$idx";
                $rawRow[$colName] = trim($td->textContent);
            }
            
            $normalized = $this->normalizeProductRow($rawRow);
            if (!empty($normalized['name'])) {
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * Parse CSV format
     */
    private function parseCsvSpreadsheet($filePath) {
        $rows = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // Auto-detect delimiter (comma, semicolon, tab)
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiters = [",", ";", "\t", "|"];
            $delim = ",";
            $maxCount = 0;
            foreach ($delimiters as $d) {
                $cnt = count(str_getcsv($firstLine, $d));
                if ($cnt > $maxCount) {
                    $maxCount = $cnt;
                    $delim = $d;
                }
            }

            $headers = [];
            $lineNum = 0;
            while (($data = fgetcsv($handle, 10000, $delim)) !== FALSE) {
                $lineNum++;
                if ($lineNum === 1) {
                    $headers = array_map('trim', $data);
                    continue;
                }
                if (empty($data) || (count($data) === 1 && empty($data[0]))) continue;

                $rawRow = [];
                foreach ($data as $idx => $val) {
                    $colName = $headers[$idx] ?? "Col_$idx";
                    $rawRow[$colName] = trim($val);
                }
                $normalized = $this->normalizeProductRow($rawRow);
                if (!empty($normalized['name'])) {
                    $rows[] = $normalized;
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Normalize flexible spreadsheet column headers into standard product model
     */
    private function normalizeProductRow($raw) {
        $normalized = [
            'original_id' => null,
            'name' => '',
            'sku' => '',
            'top_category' => 'Building Materials',
            'mid_category' => 'General',
            'end_category' => 'General Supplies',
            'capital_cost' => 0.0,
            'markup' => 20.0,
            'new_price' => 0.0,
            'current_price' => 0.0,
            'stock' => 10,
            'incoming_stock' => 0,
            'alert_level' => 10,
            'is_featured' => 0,
            'is_active' => 1,
            'raw' => $raw
        ];

        foreach ($raw as $key => $val) {
            $k = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $key));

            // Product ID
            if (in_array($k, ['productid', 'id', 'pid'])) {
                $normalized['original_id'] = is_numeric($val) ? (int)$val : null;
            }
            // Product Name
            elseif (in_array($k, ['productname', 'name', 'itemname', 'product', 'item', 'description'])) {
                $normalized['name'] = trim($val);
            }
            // SKU
            elseif (in_array($k, ['sku', 'itemcode', 'barcode', 'code', 'productsku'])) {
                $cleanSku = trim($val);
                $normalized['sku'] = ($cleanSku === 'N/A' || $cleanSku === '-') ? '' : $cleanSku;
            }
            // Top Category
            elseif (in_array($k, ['topcategory', 'toplevelcategory', 'tcat', 'tcategory', 'department'])) {
                if (!empty(trim($val))) $normalized['top_category'] = trim($val);
            }
            // Mid Category
            elseif (in_array($k, ['midcategory', 'midlevelcategory', 'mcat', 'mcategory', 'category', 'subcategory'])) {
                if (!empty(trim($val))) $normalized['mid_category'] = trim($val);
            }
            // End Category
            elseif (in_array($k, ['endcategory', 'endlevelcategory', 'ecat', 'ecategory', 'subsubcategory', 'grouptype'])) {
                if (!empty(trim($val))) $normalized['end_category'] = trim($val);
            }
            // Capital Cost / Acquisition Price
            elseif (in_array($k, ['capitalcostphp', 'capitalcost', 'capitalprice', 'capital', 'costprice', 'cost', 'unitcost', 'wholesale'])) {
                $normalized['capital_cost'] = $this->sanitizeDecimal($val);
            }
            // Markup
            elseif (in_array($k, ['markupphp', 'markup', 'markuppercent', 'margin'])) {
                $normalized['markup'] = $this->sanitizeDecimal($val);
            }
            // New Price / Promo Price
            elseif (in_array($k, ['newpricephp', 'newprice', 'promoprice', 'discountprice'])) {
                $normalized['new_price'] = $this->sanitizeDecimal($val);
            }
            // Current Price / SRP
            elseif (in_array($k, ['currentpricephp', 'currentprice', 'price', 'srp', 'sellingprice', 'unitprice'])) {
                $normalized['current_price'] = $this->sanitizeDecimal($val);
            }
            // Stock Quantity
            elseif (in_array($k, ['stockq', 'stock', 'qty', 'quantity', 'stockquantity', 'inventory', 'onhand'])) {
                $normalized['stock'] = (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
            }
            // Incoming Stock
            elseif (in_array($k, ['incomingnq', 'incoming', 'incomingstock', 'pendingorders', 'nq'])) {
                $normalized['incoming_stock'] = (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
            }
            // Safety / Alert Level
            elseif (in_array($k, ['alertlevels', 'alertlevel', 'alertqty', 'reorderlevel', 'safetystock', 'slevel'])) {
                $normalized['alert_level'] = (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
            }
            // Featured
            elseif (in_array($k, ['featured', 'isfeatured'])) {
                $normalized['is_featured'] = (stripos($val, 'yes') !== false || $val === '1') ? 1 : 0;
            }
            // Active Status
            elseif (in_array($k, ['activestatus', 'status', 'isactive', 'active'])) {
                $normalized['is_active'] = (stripos($val, 'inactive') !== false || $val === '0') ? 0 : 1;
            }
        }

        // Logical Price & Cost Calculation Fallback
        if ($normalized['current_price'] <= 0 && $normalized['capital_cost'] > 0) {
            $markup_factor = ($normalized['markup'] > 0) ? (1 + ($normalized['markup'] / 100)) : 1.20;
            $normalized['current_price'] = round($normalized['capital_cost'] * $markup_factor, 2);
        } elseif ($normalized['capital_cost'] <= 0 && $normalized['current_price'] > 0) {
            $normalized['capital_cost'] = round($normalized['current_price'] / 1.20, 2);
            if ($normalized['markup'] <= 0) $normalized['markup'] = 20.0;
        }

        if ($normalized['alert_level'] <= 0) {
            $normalized['alert_level'] = 10;
        }

        return $normalized;
    }

    /**
     * Convert currency / number strings into clean numeric float
     */
    private function sanitizeDecimal($val) {
        if (is_numeric($val)) return (float)$val;
        // Strip ₱, $, &#8369;, commas, spaces
        $clean = preg_replace('/[^0-9\.\-]/', '', str_replace(['&#8369;', '₱', '$', ','], '', (string)$val));
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    /**
     * Resolve or create Top -> Mid -> End Category hierarchy in PostgreSQL
     */
    public function resolveCategoryHierarchy($topName, $midName, $endName) {
        $topName = trim($topName) ?: 'Building Materials';
        $midName = trim($midName) ?: 'General';
        $endName = trim($endName) ?: 'General Supplies';

        // 1. Resolve Top Category
        $topKey = mb_strtolower($topName, 'UTF-8');
        if (!isset($this->category_cache['top'][$topKey])) {
            $stmt = $this->pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, 1) RETURNING tcat_id");
            $stmt->execute([$topName]);
            $newTcatId = (int)$stmt->fetchColumn();
            $this->category_cache['top'][$topKey] = $newTcatId;
        }
        $tcatId = $this->category_cache['top'][$topKey];

        // 2. Resolve Mid Category
        $midKey = $tcatId . '_' . mb_strtolower($midName, 'UTF-8');
        if (!isset($this->category_cache['mid'][$midKey])) {
            $stmt = $this->pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
            $stmt->execute([$midName, $tcatId]);
            $newMcatId = (int)$stmt->fetchColumn();
            $this->category_cache['mid'][$midKey] = $newMcatId;
        }
        $mcatId = $this->category_cache['mid'][$midKey];

        // 3. Resolve End Category
        $endKey = $mcatId . '_' . mb_strtolower($endName, 'UTF-8');
        if (!isset($this->category_cache['end'][$endKey])) {
            $stmt = $this->pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
            $stmt->execute([$endName, $mcatId]);
            $newEcatId = (int)$stmt->fetchColumn();
            $this->category_cache['end'][$endKey] = $newEcatId;
        }
        $ecatId = $this->category_cache['end'][$endKey];

        return $ecatId;
    }

    /**
     * Execute batch import into PostgreSQL with full transactional safety
     *
     * @param int $supplierId Target tenant supplier ID
     * @param string $filePath Full path to spreadsheet file
     * @param string $mode 'append_update' or 'fresh_seed'
     * @return array Execution metrics summary
     */
    public function importCatalog($supplierId, $filePath, $mode = 'append_update') {
        $supplierId = (int)$supplierId;
        if ($supplierId <= 0) {
            throw new Exception("Invalid target Supplier/Customer ID specified.");
        }

        // Verify supplier exists
        $stmt_supp = $this->pdo->prepare("SELECT supplier_id, supplier_name FROM tbl_supplier WHERE supplier_id = ?");
        $stmt_supp->execute([$supplierId]);
        $supplier = $stmt_supp->fetch(PDO::FETCH_ASSOC);
        if (!$supplier) {
            throw new Exception("Supplier tenant account not found in database (ID: $supplierId).");
        }

        $rows = $this->parseSpreadsheet($filePath);
        if (empty($rows)) {
            throw new Exception("No valid product rows could be parsed from the provided spreadsheet.");
        }

        $insertedCount = 0;
        $updatedCount = 0;
        $totalStock = 0;
        $totalValuation = 0.0;
        $categoriesCreated = 0;

        $this->pdo->beginTransaction();

        try {
            // If Fresh Seed Mode: Cleanly remove previous products for THIS SUPPLIER ONLY
            if ($mode === 'fresh_seed') {
                $stmt_get_pids = $this->pdo->prepare("SELECT p_id FROM tbl_product WHERE supplier_id = ?");
                $stmt_get_pids->execute([$supplierId]);
                $existingPids = $stmt_get_pids->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($existingPids)) {
                    $inPlaceholders = implode(',', array_fill(0, count($existingPids), '?'));
                    $stmt_del_sizes = $this->pdo->prepare("DELETE FROM tbl_product_size WHERE p_id IN ($inPlaceholders)");
                    $stmt_del_sizes->execute($existingPids);
                }

                $stmt_del_prod = $this->pdo->prepare("DELETE FROM tbl_product WHERE supplier_id = ?");
                $stmt_del_prod->execute([$supplierId]);
            }

            // Prepared statements for insertion and updates
            $stmt_find_existing = $this->pdo->prepare("SELECT p_id FROM tbl_product WHERE supplier_id = ? AND ( (p_sku != '' AND p_sku IS NOT NULL AND p_sku = ?) OR (p_name = ? AND ecat_id = ?) ) LIMIT 1");
            
            $stmt_update = $this->pdo->prepare("
                UPDATE tbl_product SET
                    p_name = ?,
                    p_old_price = ?,
                    p_current_price = ?,
                    p_capital_price = ?,
                    p_markup = ?,
                    p_qty = ?,
                    p_new_price = ?,
                    p_new_qty = ?,
                    p_s_level = ?,
                    p_is_featured = ?,
                    p_is_active = ?,
                    ecat_id = ?,
                    p_sku = ?,
                    p_brand = ?,
                    p_specs = ?,
                    p_delivery_estimate = ?,
                    p_moq = 1
                WHERE p_id = ? AND supplier_id = ?
            ");

            $stmt_insert = $this->pdo->prepare("
                INSERT INTO tbl_product (
                    p_name,
                    p_old_price,
                    p_current_price,
                    p_capital_price,
                    p_markup,
                    p_qty,
                    p_featured_photo,
                    p_description,
                    p_short_description,
                    p_feature,
                    p_condition,
                    p_return_policy,
                    p_total_view,
                    p_is_featured,
                    p_is_active,
                    ecat_id,
                    supplier_id,
                    p_sku,
                    p_new_price,
                    p_new_qty,
                    p_s_level,
                    p_moq,
                    p_brand,
                    p_specs,
                    p_delivery_estimate
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING p_id
            ");

            $stmt_insert_size = $this->pdo->prepare("INSERT INTO tbl_product_size (size_id, p_id) VALUES (?, ?)");

            foreach ($rows as $item) {
                $ecatId = $this->resolveCategoryHierarchy($item['top_category'], $item['mid_category'], $item['end_category']);
                
                $p_name          = $item['name'];
                $p_sku           = $item['sku'];
                $p_current_price = number_format($item['current_price'], 2, '.', '');
                $p_old_price     = $p_current_price;
                $p_capital_price = number_format($item['capital_cost'], 2, '.', '');
                $p_markup        = number_format($item['markup'], 2, '.', '');
                $p_new_price     = ($item['new_price'] > 0) ? number_format($item['new_price'], 2, '.', '') : '';
                $p_qty           = $item['stock'];
                $p_new_qty       = $item['incoming_stock'];
                $p_s_level       = $item['alert_level'];
                $p_is_featured   = $item['is_featured'];
                $p_is_active     = $item['is_active'];
                $p_photo         = 'no-image.jpg';
                $p_desc          = $p_name . ' - Professional construction & industrial supply.';
                $p_short_desc    = $p_name;
                $p_feature       = 'Durable and certified quality.';
                $p_condition     = 'Brand New';
                $p_return_policy = 'Standard 7-day store replacement & return policy.';
                $p_brand         = 'Standard';
                $p_specs         = $p_name;
                $p_delivery      = 'Same-day POS / 1-3 Days Delivery';

                $totalStock += $p_qty;
                $totalValuation += ($item['current_price'] * $p_qty);

                $targetPid = null;

                if ($mode === 'append_update') {
                    $stmt_find_existing->execute([$supplierId, $p_sku, $p_name, $ecatId]);
                    $targetPid = $stmt_find_existing->fetchColumn();
                }

                if ($targetPid) {
                    // Update existing
                    $stmt_update->execute([
                        $p_name,
                        $p_old_price,
                        $p_current_price,
                        $p_capital_price,
                        $p_markup,
                        $p_qty,
                        $p_new_price,
                        $p_new_qty,
                        $p_s_level,
                        $p_is_featured,
                        $p_is_active,
                        $ecatId,
                        $p_sku,
                        $p_brand,
                        $p_specs,
                        $p_delivery,
                        $targetPid,
                        $supplierId
                    ]);
                    $updatedCount++;
                } else {
                    // Insert new product
                    $stmt_insert->execute([
                        $p_name,
                        $p_old_price,
                        $p_current_price,
                        $p_capital_price,
                        $p_markup,
                        $p_qty,
                        $p_photo,
                        $p_desc,
                        $p_short_desc,
                        $p_feature,
                        $p_condition,
                        $p_return_policy,
                        0, // p_total_view
                        $p_is_featured,
                        $p_is_active,
                        $ecatId,
                        $supplierId,
                        $p_sku,
                        $p_new_price,
                        $p_new_qty,
                        $p_s_level,
                        1, // p_moq
                        $p_brand,
                        $p_specs,
                        $p_delivery
                    ]);
                    $newPid = (int)$stmt_insert->fetchColumn();

                    // Insert default size tracking entry in tbl_product_size
                    try {
                        $stmt_insert_size->execute([0, $newPid]);
                    } catch (Exception $e_size) {}

                    $insertedCount++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'mode' => $mode,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplier['supplier_name'],
                'total_processed' => count($rows),
                'inserted_count' => $insertedCount,
                'updated_count' => $updatedCount,
                'total_stock' => $totalStock,
                'total_valuation' => $totalValuation,
                'categories_cached' => count($this->category_cache['end'])
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Database transaction rolled back. Error: " . $e->getMessage());
        }
    }
}
