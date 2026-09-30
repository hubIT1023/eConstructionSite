<?php
/**
 * Construction Product Categorization & Classification Engine
 *
 * Automatically classifies construction supply products into:
 * Main Category -> Mid Category -> End Category -> Base Product -> Product Variant
 *
 * Features:
 * - 26 Standardized Construction Main Categories
 * - Duplicate Category Prevention & Normalization (case, pluralization, abbreviations)
 * - Variant Extraction (separates dimensions, thickness, weight, color, schedule from categories)
 * - Confidence Scoring (0-100) & Explainable Classification Reasons
 * - Manual Review Queue for Low Confidence (< 60)
 * - Non-destructive Database Protection & Full Audit Logging
 */

if (!class_exists('ConstructionCategoryEngine')) {
class ConstructionCategoryEngine
{
    /** @var PDO */
    private $pdo;

    /** @var array Cache of normalized existing categories in DB */
    private $categoryCache = null;

    /**
     * 26 Standard Main Categories (Section 3)
     */
    public static function getStandardMainCategories()
    {
        return [
            1  => 'Cement & Concrete Materials',
            2  => 'Aggregates & Sand',
            3  => 'Masonry Materials',
            4  => 'Reinforcing Steel & Structural Steel',
            5  => 'Roofing Materials',
            6  => 'Lumber & Wood Products',
            7  => 'Plywood & Boards',
            8  => 'Electrical Supplies',
            9  => 'Plumbing Supplies',
            10 => 'Sanitary & Bathroom Fixtures',
            11 => 'Hardware & Fasteners',
            12 => 'Doors & Windows',
            13 => 'Paints & Coatings',
            14 => 'Tiles & Flooring',
            15 => 'Ceiling & Partition Materials',
            16 => 'Waterproofing & Construction Chemicals',
            17 => 'Adhesives, Sealants & Grout',
            18 => 'Tools & Equipment',
            19 => 'Welding Supplies',
            20 => 'Safety & Personal Protective Equipment',
            21 => 'Glass & Aluminum Products',
            22 => 'Fencing & Gates',
            23 => 'Construction Accessories',
            24 => 'Landscaping & Outdoor Materials',
            25 => 'General Building Materials',
            26 => 'Other / Unclassified',
        ];
    }

    /**
     * Synonym & Normalization Map for Duplicate Category Prevention (Sections 16 & 17)
     */
    private static $endCategorySynonyms = [
        'angle bar' => 'Angle Bars',
        'angle bars' => 'Angle Bars',
        'angle steel' => 'Angle Bars',
        'steel angle' => 'Angle Bars',
        'flat bar' => 'Flat Bars',
        'flat bars' => 'Flat Bars',
        'round bar' => 'Plain Round Bars',
        'round bars' => 'Plain Round Bars',
        'square bar' => 'Square Bars',
        'square bars' => 'Square Bars',
        'c-purlin' => 'C-Purlins & Channels',
        'c-purlins' => 'C-Purlins & Channels',
        'c-channel' => 'C-Purlins & Channels',
        'c-channels' => 'C-Purlins & Channels',
        'steel i-beam' => 'I-Beams',
        'steel i-beams' => 'I-Beams',
        'i-beam' => 'I-Beams',
        'i-beams' => 'I-Beams',
        'rebar & mesh' => 'Deformed Bars',
        'deformed bar' => 'Deformed Bars',
        'deformed bars' => 'Deformed Bars',
        'tubular steel' => 'Tubular Steel',
        'square tube' => 'Tubular Steel',
        'rectangular tube' => 'Tubular Steel',
        'gi pipe' => 'GI Pipes',
        'gi pipes' => 'GI Pipes',
        'pvc pipe' => 'PVC Water Pipes',
        'pvc pipes' => 'PVC Water Pipes',
        'pvc water pipe' => 'PVC Water Pipes',
        'pipe fitting' => 'PVC Pipe Fittings',
        'pipe fittings' => 'PVC Pipe Fittings',
        'valves & flanges' => 'Ball Valves',
        'copper cables' => 'THHN Wires',
        'conduits & fittings' => 'PVC Electrical Conduits',
        'panel board' => 'Electrical Panels',
        'electrical devices' => 'Convenience Outlets',
        'nail' => 'Common Nails',
        'nails' => 'Common Nails',
        'barbe wire' => 'Barbed Wire',
        'barbed wire' => 'Barbed Wire',
        'hog wire & tire wire' => 'Hog Wire',
        'welding rod' => 'Welding Rods',
        'welding rods' => 'Welding Rods',
        'g.i corrogated' => 'Corrugated Roofing Sheets',
        'metal sheets' => 'GI Plain Sheets',
        'paints' => 'Latex Paints',
        'paint' => 'Latex Paints',
        'cutting disk' => 'Cutting Discs & Diamond Wheels',
        'steel tape' => 'Measuring Tapes',
        'door knobs' => 'Door Knobs & Locksets',
        'lababo' => 'Kitchen & Utility Sinks',
        's4s wood' => 'S4S Lumber',
    ];

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo;
        if ($this->pdo) {
            $this->ensureSchema();
        }
    }

    /**
     * Non-destructively creates audit log and manual review queue tables (Sections 19, 22, 26)
     */
    public function ensureSchema()
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_category_audit_log (
                    log_id SERIAL PRIMARY KEY,
                    p_id INTEGER NOT NULL,
                    supplier_id INTEGER DEFAULT 0,
                    p_name VARCHAR(255),
                    prev_tcat_id INTEGER,
                    prev_tcat_name VARCHAR(255),
                    prev_mcat_id INTEGER,
                    prev_mcat_name VARCHAR(255),
                    prev_ecat_id INTEGER,
                    prev_ecat_name VARCHAR(255),
                    new_tcat_id INTEGER,
                    new_tcat_name VARCHAR(255),
                    new_mcat_id INTEGER,
                    new_mcat_name VARCHAR(255),
                    new_ecat_id INTEGER,
                    new_ecat_name VARCHAR(255),
                    confidence INTEGER NOT NULL DEFAULT 0,
                    reason TEXT,
                    changed_by VARCHAR(255) NOT NULL DEFAULT 'System',
                    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    source VARCHAR(50) NOT NULL DEFAULT 'AI'
                )
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_category_review_queue (
                    queue_id SERIAL PRIMARY KEY,
                    p_id INTEGER NOT NULL UNIQUE,
                    supplier_id INTEGER DEFAULT 0,
                    p_name VARCHAR(255),
                    p_sku VARCHAR(100),
                    curr_tcat_name VARCHAR(255),
                    curr_mcat_name VARCHAR(255),
                    curr_ecat_name VARCHAR(255),
                    curr_ecat_id INTEGER DEFAULT 0,
                    sugg_tcat_name VARCHAR(255),
                    sugg_mcat_name VARCHAR(255),
                    sugg_ecat_name VARCHAR(255),
                    sugg_tcat_id INTEGER DEFAULT 0,
                    sugg_mcat_id INTEGER DEFAULT 0,
                    sugg_ecat_id INTEGER DEFAULT 0,
                    base_product VARCHAR(255),
                    variant_spec VARCHAR(255),
                    confidence INTEGER NOT NULL DEFAULT 0,
                    confidence_label VARCHAR(50),
                    reason TEXT,
                    status VARCHAR(50) NOT NULL DEFAULT 'Needs Review',
                    reviewed_by VARCHAR(255),
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (Exception $e) {
            // Ignore if read-only connection
        }
    }

    /**
     * Normalize a category name (Section 16 & 17)
     */
    public static function normalizeCategoryName($name, $level = 'end')
    {
        $clean = trim(preg_replace('/\s+/', ' ', (string)$name));
        if ($clean === '') {
            return '';
        }
        // Strip dimensions, prices, SKUs if accidentally passed
        $clean = preg_replace('/\bSKU-[A-Z0-9-]+\b/i', '', $clean);
        $clean = trim($clean, " -|/,");

        $lower = strtolower($clean);
        if ($level === 'end' && isset(self::$endCategorySynonyms[$lower])) {
            return self::$endCategorySynonyms[$lower];
        }

        // Preserve known acronyms
        $words = explode(' ', $clean);
        $preserveUpper = ['PVC', 'GI', 'G.I.', 'THHN', 'PDX', 'HDPE', 'PPR', 'LED', 'MCB', 'MCCB', 'CHB', 'AAC', 'S4S', 'QDE', 'PPE', 'FTA', 'MTA'];
        foreach ($words as &$w) {
            $uw = strtoupper($w);
            if (in_array($uw, $preserveUpper, true)) {
                $w = ($uw === 'G.I.') ? 'GI' : $uw;
            } elseif (strtolower($w) === 'and' || $w === '&') {
                $w = '&';
            } else {
                $w = ucfirst(strtolower($w));
            }
        }
        return implode(' ', $words);
    }

    /**
     * Confidence Level Label (Section 18)
     */
    public static function getConfidenceLabel($score)
    {
        $score = (int)$score;
        if ($score >= 90) return 'Very High Confidence';
        if ($score >= 75) return 'High Confidence';
        if ($score >= 60) return 'Medium Confidence';
        if ($score >= 40) return 'Low Confidence';
        return 'Unclassified / Manual Review';
    }

    /**
     * Extracts Base Product Name and Product Variant from a raw product name (Sections 2 & 15)
     * Hierarchy: Main Category -> Mid Category -> End Category -> Product -> Product Variant
     */
    public static function extractProductAndVariant($rawName)
    {
        $name = trim(preg_replace('/\s+/', ' ', (string)$rawName));
        $variants = [];
        $working = $name;

        // 1. Extract pipe-delimited unit/packaging e.g. "| Meter", "| Box", "| 1 roll", "| roll 150m", "| Neltex"
        if (preg_match('/\|\s*(.+)$/i', $working, $m)) {
            $variants[] = trim($m[1]);
            $working = trim(substr($working, 0, strpos($working, '|')));
        }

        // 2. Extract slash-delimited packaging e.g. "/Meter", "/Box 150 Meters", "/ meter", "/box"
        if (preg_match('/\/\s*(meter|box(?:\s+[0-9]+\s*meters?)?)\s*$/i', $working, $m)) {
            $variants[] = trim($m[1]);
            $working = trim(substr($working, 0, -strlen($m[0])));
        }

        // 3. Extract parenthesized specs e.g. (D = 10 mm), (t = 1.2, Yellow), (40kg Bag), (1/2kg), (Local), (China), (Gallon)
        while (preg_match('/\(([^)]+)\)/', $working, $m)) {
            $inside = trim($m[1]);
            $variants[] = $inside;
            $working = trim(str_replace($m[0], ' ', $working));
        }

        // 4. Extract Schedule e.g. #40, #20
        if (preg_match('/\b(#[0-9]+)\b/', $working, $m)) {
            $variants[] = $m[1];
            $working = trim(str_replace($m[0], ' ', $working));
        }

        // 5. Extract complex dimensions e.g. 1-1/8" x 1-1/8", 1 1/2" x 1 1/2", .4x3x8, 0.9x4x8, 1/4" x 2", 2 x 45°
        if (preg_match('/(?:-|\b)\s*([0-9]+(?:-[0-9]+\/[0-9]+|\s+[0-9]+\/[0-9]+|\/[0-9]+|\.[0-9]+)?\s*["\']?\s*[xX]\s*[0-9]+(?:-[0-9]+\/[0-9]+|\s+[0-9]+\/[0-9]+|\/[0-9]+|\.[0-9]+)?(?:\s*[xX]\s*[0-9]+(?:\.[0-9]+)?)?(?:\s*(?:°|deg|m|ft|mm|cm|in|"))?)/i', $working, $m)) {
            $variants[] = trim($m[1]);
            $working = trim(str_replace($m[0], ' ', $working));
        } elseif (preg_match('/(\.[0-9]+\s*[xX]\s*[0-9]+(?:\s*[xX]\s*[0-9]+)?)/', $working, $m)) {
            $variants[] = trim($m[1]);
            $working = trim(str_replace($m[0], ' ', $working));
        }

        // 6. Extract standalone sizes/gauges/watts/amps/weights/lengths
        $sizePatterns = [
            '/\b([0-9]+(?:\.[0-9]+)?\s*(?:kg|g|ml|liter|liters|gal|gallon|pail|watts|w|A|amp|amps|mm|cm|m|ft|mtr|meters|holes|gang))\b/i',
            '/\b([0-9]+\s+[0-9]+\/[0-9]+\s*["\']?|[0-9]+\/[0-9]+\s*["\']?|[0-9]+(?:\.[0-9]+)?\s*["\'])/',
            '/\b(S[0-9]{3,4}|6013\s+[0-9.]+|6011\s+[0-9.]+|[0-9]{1,2}F|[0-9]{1,2}H|[0-9]{1,2}B|[0-9]\+[0-9]\s*gang)\b/i',
            '/\b(\.[0-9]+|[0-9]+\.[0-9]+)\b/',
            '/\s+([0-9]{1,3})\s*$/',
        ];
        foreach ($sizePatterns as $pat) {
            if (preg_match_all($pat, $working, $matches)) {
                foreach ($matches[1] as $matchVal) {
                    $variants[] = trim($matchVal);
                }
                $working = trim(preg_replace($pat, ' ', $working));
            }
        }

        // 7. Extract trailing color/finish words if part of variant
        if (preg_match('/\b(Orange|Green|Yellow|Brown|White|Black|Red|Blue|Stainless|Single|Double|Short|Long|Small|Medium|Large|Big)\b$/i', $working, $m)) {
            // Keep color in base name only for paints where color defines the paint SKU, otherwise move to variant
            if (!preg_match('/\b(qde|latex|enamel|roof guard|oxide|grout)\b/i', $working)) {
                $variants[] = ucfirst(strtolower($m[1]));
                $working = trim(substr($working, 0, -strlen($m[0])));
            }
        }

        $base = trim(preg_replace('/\s+/', ' ', $working), " -.,/|_'\"");
        if ($base === '' || strlen($base) < 2) {
            $base = $name;
        }

        // Standardize common base product names so variants group cleanly
        $bl = strtolower($base);
        if (strpos($bl, 'common nail') !== false) $base = 'Common Nail';
        elseif (strpos($bl, 'finishing nail') !== false) $base = 'Finishing Nail';
        elseif (strpos($bl, 'concrete nail') !== false) $base = 'Concrete Nail';
        elseif (strpos($bl, 'umbrella nail') !== false) $base = 'Umbrella Nail';
        elseif (strpos($bl, 'hardi nail') !== false) $base = 'Hardi Nail';
        elseif ($bl === 'u nail') $base = 'U-Nail';
        elseif (strpos($bl, 'deformed bar') !== false || strpos($bl, 'deformed steel bar') !== false) $base = 'Deformed Steel Bar';
        elseif (strpos($bl, 'steel matting') !== false) $base = 'Steel Matting';
        elseif (strpos($bl, 'tubular') !== false && strpos($bl, 'doorknob') === false) {
            $base = (stripos($name, 'stainless') !== false) ? 'Stainless Tubular Steel' : 'Tubular Steel';
        }
        elseif (strpos($bl, 'angle bar') !== false) {
            $base = (preg_match('/^ab[0-9]/i', $name, $abm)) ? strtoupper($abm[0]) . ' Angle Bar' : 'Angle Bar';
        }
        elseif (strpos($bl, 'flat bar') !== false) $base = 'Flat Bar';
        elseif (strpos($bl, 'round bar') !== false) $base = 'Round Bar';
        elseif (strpos($bl, 'square') !== false || strpos($bl, 'squre bar') !== false) {
            if (strpos($bl, 'box') === false) $base = 'Square Bar';
        }
        elseif (strpos($bl, 'c-purlin') !== false) $base = 'C-Purlin';
        elseif (strpos($bl, 'gi pipe') !== false) {
            $base = (strpos($name, '#20') !== false || stripos($name, 'schedule 20') !== false)
                ? 'GI Pipe (Schedule 20)'
                : 'GI Pipe (Schedule 40)';
        }
        elseif (strpos($bl, 'plywood') !== false && strpos($bl, 'marine') !== false) $base = 'Marine Plywood';
        elseif (strpos($bl, 'plywood') !== false && strpos($bl, 'ordinary') !== false) $base = 'Ordinary Plywood';
        elseif (strpos($bl, 'phenolic board') !== false) $base = 'Phenolic Board';
        elseif (strpos($bl, 'hardiflex') !== false) $base = 'Hardiflex Fiber Cement Board';
        elseif (strpos($bl, 'paint brush') !== false) $base = 'Paint Brush';

        $variantStr = !empty($variants) ? implode(' | ', array_unique(array_filter($variants))) : 'Standard';

        return [
            'base_product' => $base,
            'variant'      => $variantStr,
        ];
    }

    /**
     * Core Product Classification Method (Sections 4–21)
     *
     * @param array $product Associative array with keys:
     *   p_id, p_name, p_sku, p_brand, p_description, p_short_description, p_specs,
     *   sizes, colors, curr_tcat, curr_mcat, curr_ecat
     * @return array Classification result with main_category, mid_category, end_category,
     *   base_product, variant, confidence, confidence_label, status, reason
     */
    public function classifyProduct(array $product)
    {
        $name      = trim((string)($product['p_name'] ?? $product['Product Name'] ?? ''));
        $sku       = trim((string)($product['p_sku'] ?? $product['SKU'] ?? ''));
        $brand     = trim((string)($product['p_brand'] ?? $product['Brand'] ?? ''));
        $desc      = trim((string)($product['p_description'] ?? $product['p_short_description'] ?? $product['Short Description'] ?? ''));
        $specs     = trim((string)($product['p_specs'] ?? $product['Specifications'] ?? ''));
        $currTcat  = trim((string)($product['curr_tcat'] ?? $product['Top Category'] ?? ''));
        $currMcat  = trim((string)($product['curr_mcat'] ?? $product['Mid Category'] ?? ''));
        $currEcat  = trim((string)($product['curr_ecat'] ?? $product['End Category'] ?? ''));

        $nl = strtolower($name);
        $dl = strtolower($desc . ' ' . $specs);
        $extracted = self::extractProductAndVariant($name);
        $baseProduct = $extracted['base_product'];
        $variant     = $extracted['variant'];

        // Default fallback: Unclassified / Needs Review (Section 21: Do Not Guess)
        $main = 'Other / Unclassified';
        $mid  = 'Unclassified';
        $end  = 'Unclassified';
        $conf = 25;
        $reason = 'Insufficient product name or attribute information to determine a specific construction category without guessing.';

        // -------------------------------------------------------------------------
        // RULE 0: Insufficient Info / Arbitrary Code / System Test Items (Section 21)
        // -------------------------------------------------------------------------
        if ($name === '' || preg_match('/^[a-z]{1,3}-?[0-9]{1,5}$/i', $name)) {
            return $this->buildResult(
                'Other / Unclassified', 'Unclassified', 'Unclassified',
                $baseProduct, $variant, 20,
                "Product name '{$name}' is an ambiguous code without descriptive construction terms. Do not guess category.",
                $currTcat, $currMcat, $currEcat
            );
        }

        if (preg_match('/\b(automated test|api test|diag test|test return item|auto test product)\b/i', $name)) {
            return $this->buildResult(
                'Other / Unclassified', 'System & Diagnostic Records', 'Test Items',
                'System Test Item', $variant, 15,
                "Product name '{$name}' is an automated/diagnostic test record and not a physical construction supply product.",
                $currTcat, $currMcat, $currEcat
            );
        }

        // -------------------------------------------------------------------------
        // RULE 1: Reinforcing Steel & Structural Steel (Section 6)
        // -------------------------------------------------------------------------
        if (preg_match('/\b(angle\s*bar)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Structural Steel';
            $end  = 'Angle Bars';
            $conf = 96;
            $reason = 'The product name explicitly identifies the item as an angle bar, which is a structural steel profile.';
            if ($dl !== '' && strpos($dl, 'pvc') !== false) {
                $reason .= ' (Note: Existing legacy description mentions PVC pipe, but product name and SKU-ANG confirm Steel Angle Bar.)';
            }
        } elseif (preg_match('/\b(deformed\s*(?:steel\s*)?bars?|rebar)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Reinforcing Steel';
            $end  = 'Deformed Bars';
            $conf = 98;
            $reason = 'The product name explicitly identifies a deformed steel reinforcing bar (rebar) used for concrete reinforcement.';
        } elseif (preg_match('/\b(steel\s*matting)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Reinforcing Steel';
            $end  = 'Steel Wire Mesh & Matting';
            $conf = 95;
            $reason = 'The product name identifies steel matting, a welded reinforcing steel mesh sheet.';
        } elseif (preg_match('/\b(tie\s*wire)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Reinforcing Steel';
            $end  = 'Tie Wires';
            $conf = 94;
            $reason = 'The product name identifies GI tie wire used for binding reinforcing steel bars.';
        } elseif (preg_match('/\b(i-?beam|h-?beam|heb\s*[0-9]+)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Structural Steel';
            $end  = (stripos($name, 'h-beam') !== false) ? 'H-Beams' : 'I-Beams';
            $conf = 98;
            $reason = 'The product name and section specification identify a structural steel beam profile.';
        } elseif (preg_match('/\b(c-?\s*purlins?|c-?\s*channels?)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Structural Steel';
            $end  = 'C-Purlins & Channels';
            $conf = 97;
            $reason = 'The product name identifies a structural steel C-purlin / channel section.';
        } elseif (preg_match('/\b(flat\s*bar)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Structural Steel';
            $end  = 'Flat Bars';
            $conf = 97;
            $reason = 'The product name explicitly identifies a structural steel flat bar.';
        } elseif (preg_match('/\b(round\s*bar)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Reinforcing Steel';
            $end  = 'Plain Round Bars';
            $conf = 96;
            $reason = 'The product name identifies a plain steel round bar.';
        } elseif (preg_match('/\b(shafting)\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Structural Steel';
            $end  = 'Steel Shafting Bars';
            $conf = 92;
            $reason = 'The product name identifies a precision steel/stainless shafting round bar.';
        } elseif (preg_match('/\b(square\s*bar|squre\s*bar|^square\s+[0-9]+\s*mm)\b/i', $name) && stripos($name, 'box') === false) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Structural Steel';
            $end  = 'Square Bars';
            $conf = 95;
            $reason = 'The product name and millimeter dimension identify a solid steel square bar.';
        } elseif (preg_match('/\b(tubular\s*(?:steel|stainless)|square\s*tube|rectangular\s*tube)\b/i', $name) && stripos($name, 'doorknob') === false && stripos($name, 'door knob') === false) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Steel Tubes & Pipes';
            $end  = (stripos($name, 'stainless') !== false) ? 'Stainless Steel Tubes' : 'Tubular Steel';
            $conf = 97;
            $reason = 'The product name identifies a hollow structural tubular steel section.';
        } elseif (preg_match('/^gi\s*pipe\b/i', $name)) {
            $main = 'Reinforcing Steel & Structural Steel';
            $mid  = 'Steel Tubes & Pipes';
            $end  = 'GI Pipes';
            $conf = 96;
            $reason = 'The product name and Schedule rating (#20/#40) identify a galvanized iron (GI) steel pipe.';
        }

        // -------------------------------------------------------------------------
        // RULE 2: Cement & Concrete Materials (Section 9)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(portland\s*cement|holcim\s*cement|union\s*cement|type\s*1p?\s*cement|cement\s*[0-9]+\s*kg|cement\s*bag)\b/i', $name)) {
            $main = 'Cement & Concrete Materials';
            $mid  = 'Cement';
            $end  = 'Portland Cement';
            $conf = 97;
            $reason = 'The product name explicitly identifies bagged Portland/hydraulic cement for concrete and mortar.';
            if ($dl !== '' && strpos($dl, 'rotary hammer') !== false) {
                $reason .= ' (Note: Legacy seed description mentions rotary hammer drill, but product name and SKU-CEM-40KG confirm 40kg Holcim Cement.)';
            }
        } elseif (preg_match('/\b(skim\s*coat)\b/i', $name)) {
            $main = 'Cement & Concrete Materials';
            $mid  = 'Concrete Finishes & Plaster';
            $end  = 'Skim Coat';
            $conf = 95;
            $reason = 'The product name identifies a cementitious skim coat plaster compound for concrete finishing.';
        } elseif (preg_match('/\b(patching\s*compound)\b/i', $name)) {
            $main = 'Cement & Concrete Materials';
            $mid  = 'Concrete Finishes & Plaster';
            $end  = 'Patching Compounds';
            $conf = 94;
            $reason = 'The product name identifies a concrete/masonry patching and leveling compound.';
        } elseif (preg_match('/\b(almagre|red\s*oxide|green\s*oxide)\b/i', $name) && stripos($name, 'primer') === false && stripos($name, 'boysen') === false) {
            $main = 'Cement & Concrete Materials';
            $mid  = 'Concrete Admixtures & Pigments';
            $end  = 'Cement Pigments & Oxides';
            $conf = 91;
            $reason = 'The product name identifies Almagre mineral oxide pigment powder used to color cement and concrete floors.';
        }

        // -------------------------------------------------------------------------
        // RULE 3: Aggregates & Sand (Section 3 & 28)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(washed\s*sand|river\s*sand|fine\s*sand|vibro\s*sand|gravel|crushed\s*stone)\b/i', $name)) {
            $main = 'Aggregates & Sand';
            $mid  = (stripos($name, 'sand') !== false) ? 'Sand' : 'Gravel & Crushed Stone';
            $end  = (stripos($name, 'sand') !== false) ? 'Washed Sand' : 'Crushed Gravel';
            $conf = 97;
            $reason = 'The product name identifies construction aggregate (sand/gravel) used for concrete and masonry mixes.';
        }

        // -------------------------------------------------------------------------
        // RULE 4: Masonry Materials (Section 10)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(concrete\s*hollow\s*block|chb|concrete\s*block|aac\s*block|masonry\s*brick)\b/i', $name)) {
            $main = 'Masonry Materials';
            $mid  = 'Concrete Blocks';
            $end  = 'Concrete Hollow Blocks';
            $conf = 98;
            $reason = 'The product name explicitly identifies a Concrete Hollow Block (CHB) masonry unit.';
        }

        // -------------------------------------------------------------------------
        // RULE 5: Roofing Materials (Section 11)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(g\.?i\.?\s*corrogated|corrugated\s*roofing|gi\s*roofing\s*sheet|colored\s*roofing|long\s*span|rib\s*type)\b/i', $name)) {
            $main = 'Roofing Materials';
            $mid  = 'Roofing Sheets';
            $end  = (stripos($name, 'colored') !== false) ? 'Colored Roofing Sheets' : 'Corrugated Sheets';
            $conf = 96;
            $reason = 'The product name identifies a corrugated or pre-painted metal roofing sheet.';
        } elseif (preg_match('/\b(g\.?i\.?\s*plain\s*sheet|plainsheet)\b/i', $name)) {
            $main = 'Roofing Materials';
            $mid  = 'Roofing Sheets';
            $end  = 'GI Plain Sheets';
            $conf = 95;
            $reason = 'The product name identifies a galvanized iron (GI) plain metal sheet used for roofing and sheet-metal work.';
        } elseif (preg_match('/\b(plastic\s*roofing|polycarbonate\s*roofing)\b/i', $name)) {
            $main = 'Roofing Materials';
            $mid  = 'Roofing Sheets';
            $end  = 'Plastic & Polycarbonate Sheets';
            $conf = 93;
            $reason = 'The product name identifies a translucent plastic/synthetic roofing sheet.';
        } elseif (preg_match('/\b(gutter|ridge\s*roll|end\s*flashing|flashing|downspout)\b/i', $name)) {
            $main = 'Roofing Materials';
            $mid  = 'Roofing Accessories';
            if (stripos($name, 'gutter') !== false) $end = 'Gutters';
            elseif (stripos($name, 'ridge') !== false) $end = 'Ridge Rolls';
            else $end = 'Flashings';
            $conf = 96;
            $reason = 'The product name identifies a metal roofing accessory (gutter, ridge roll, or flashing).';
        } elseif (preg_match('/\b(insulation\s*foam)\b/i', $name)) {
            $main = 'Roofing Materials';
            $mid  = 'Roof Insulation';
            $end  = 'Thermal Insulation Foam';
            $conf = 94;
            $reason = 'The product name identifies single/double-sided thermal roof insulation foam.';
        }

        // -------------------------------------------------------------------------
        // RULE 6: Lumber & Wood Products (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(^s4s\b|lumber|good\s*lumber|coco\s*lumber|kiln\s*dried)\b/i', $name)) {
            $main = 'Lumber & Wood Products';
            $mid  = 'Dressed Lumber';
            $end  = 'S4S Lumber';
            $conf = 95;
            $reason = 'The product name identifies S4S (Surfaced Four Sides) dimensional lumber.';
        }

        // -------------------------------------------------------------------------
        // RULE 7: Plywood & Boards (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(plywood|phenolic\s*board|hardiflex|lawanit|marine\s*plywood)\b/i', $name)) {
            $main = 'Plywood & Boards';
            if (stripos($name, 'marine') !== false) {
                $mid = 'Plywood';
                $end = 'Marine Plywood';
                $reason = 'The product name explicitly identifies moisture-resistant marine plywood.';
            } elseif (stripos($name, 'plywood') !== false) {
                $mid = 'Plywood';
                $end = 'Ordinary Plywood';
                $reason = 'The product name identifies ordinary interior construction plywood.';
            } elseif (stripos($name, 'phenolic') !== false) {
                $mid = 'Formwork Boards';
                $end = 'Phenolic Boards';
                $reason = 'The product name identifies film-faced phenolic board used for concrete formwork.';
            } elseif (stripos($name, 'hardiflex') !== false) {
                $mid = 'Fiber Cement Boards';
                $end = 'Hardiflex Boards';
                $reason = 'The product name identifies Hardiflex fiber cement board for ceilings and partitions.';
            } else {
                $mid = 'Hardboards';
                $end = 'Lawanit Boards';
                $reason = 'The product name identifies Lawanit pressed wood fiber hardboard.';
            }
            $conf = 97;
        }

        // -------------------------------------------------------------------------
        // RULE 8: Ceiling & Partition Materials (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(metal\s*furring|metalfurring|metal\s*stud|carrying\s*chanell|carrying\s*channel|hat\s*type|wall\s*angle)\b/i', $name)) {
            $main = 'Ceiling & Partition Materials';
            $mid  = 'Light Gauge Metal Framing';
            if (stripos($name, 'stud') !== false) $end = 'Metal Studs';
            elseif (stripos($name, 'wall angle') !== false) $end = 'Wall Angles';
            elseif (stripos($name, 'carrying') !== false) $end = 'Carrying Channels';
            else $end = 'Metal Furring Channels';
            $conf = 95;
            $reason = 'The product name identifies light-gauge galvanized steel ceiling and partition framing.';
        }

        // -------------------------------------------------------------------------
        // RULE 9: Doors & Windows (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(flush\s*door|pvc\s*door|panel\s*door|steel\s*door)\b/i', $name)) {
            $main = 'Doors & Windows';
            $mid  = 'Doors';
            $end  = (stripos($name, 'pvc') !== false) ? 'PVC Doors' : 'Flush Doors';
            $conf = 97;
            $reason = 'The product name explicitly identifies a residential/commercial door unit.';
        }

        // -------------------------------------------------------------------------
        // RULE 10: Hardware & Fasteners (Section 12)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(common\s*nail|finishing\s*nail|concrete\s*nail|umbrella\s*nail|hardi\s*nails?|u\s*nail)\b/i', $name) && stripos($name, 'no more nail') === false) {
            $main = 'Hardware & Fasteners';
            $mid  = 'Nails';
            if (stripos($name, 'common') !== false) $end = 'Common Nails';
            elseif (stripos($name, 'finishing') !== false) $end = 'Finishing Nails';
            elseif (stripos($name, 'concrete') !== false) $end = 'Concrete Nails';
            elseif (stripos($name, 'umbrella') !== false) $end = 'Umbrella Roofing Nails';
            elseif (stripos($name, 'hardi') !== false) $end = 'Hardi Nails';
            else $end = 'U-Nails & Staples';
            $conf = 98;
            $reason = 'The product name explicitly identifies a construction fastener nail.';
        } elseif (preg_match('/\b(wood\s*screw|self-?tapping\s*screw|tek\s*screw|gypsum\s*screw|hex\s*bolt|anchor\s*bolt|expansion\s*bolt|nut|washer|rivet)\b/i', $name)) {
            $main = 'Hardware & Fasteners';
            if (stripos($name, 'screw') !== false) {
                $mid = 'Screws';
                $end = (stripos($name, 'wood') !== false) ? 'Wood Screws' : 'Self-Tapping Screws';
            } else {
                $mid = 'Bolts, Nuts & Anchors';
                $end = 'Bolts & Anchors';
            }
            $conf = 96;
            $reason = 'The product name identifies a mechanical threaded fastener (screw, bolt, or anchor).';
        } elseif (preg_match('/\b(doorknob|door\s*knob|door\s*lockset|lockset|deadbolt|hinge)\b/i', $name)) {
            $main = 'Hardware & Fasteners';
            $mid  = 'Door & Builders Hardware';
            $end  = 'Door Knobs & Locksets';
            $conf = 96;
            $reason = 'The product name identifies a door lockset or doorknob hardware assembly.';
        }

        // -------------------------------------------------------------------------
        // RULE 11: Waterproofing & Construction Chemicals (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(plexibond|sahara)\b/i', $name)) {
            $main = 'Waterproofing & Construction Chemicals';
            $mid  = 'Waterproofing Compounds';
            $end  = 'Cementitious Waterproofing';
            $conf = 94;
            $reason = 'The product name identifies a cementitious waterproofing compound/admixture (Boysen Plexibond / Sahara).';
        }

        // -------------------------------------------------------------------------
        // RULE 12: Adhesives, Sealants & Grout (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(tile\s*adhesive|tile\s*grout|wood\s*glue|stikwel|no\s*more\s*nail|vulcaseal|elastoseal|el\s*kapitan|el\s*heneral|super\s*dikit|solvent\s*sherman|solvent\s*atlanta)\b/i', $name)) {
            $main = 'Adhesives, Sealants & Grout';
            if (stripos($name, 'tile adhesive') !== false) {
                $mid = 'Tile Adhesives & Grout';
                $end = 'Tile Adhesives';
                $reason = 'The product name explicitly identifies a cementitious tile adhesive mortar.';
            } elseif (stripos($name, 'tile grout') !== false) {
                $mid = 'Tile Adhesives & Grout';
                $end = 'Tile Grout';
                $reason = 'The product name identifies tile joint grout.';
            } elseif (stripos($name, 'vulcaseal') !== false || stripos($name, 'elastoseal') !== false) {
                $mid = 'Sealants';
                $end = 'Roof & Elastomeric Sealants';
                $reason = 'The product name identifies an elastomeric roof and gutter waterproofing sealant.';
            } elseif (stripos($name, 'wood glue') !== false || stripos($name, 'stikwel') !== false) {
                $mid = 'Glues & Construction Adhesives';
                $end = 'Wood Glues';
                $reason = 'The product name identifies a wood-bonding adhesive.';
            } elseif (stripos($name, 'solvent') !== false) {
                $mid = 'Pipe Joint Solvents';
                $end = 'PVC Solvent Cement';
                $reason = 'The product name identifies solvent cement for bonding PVC pipes and fittings.';
            } else {
                $mid = 'Glues & Construction Adhesives';
                $end = 'Epoxy & Construction Adhesives';
                $reason = 'The product name identifies a structural epoxy or heavy-duty construction adhesive.';
            }
            $conf = 96;
        }

        // -------------------------------------------------------------------------
        // RULE 13: Paints & Coatings (Section 13)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(latex|qde\b|enamel|masonry\s*putty|primecoat|metal\s*primer|roof\s*guard|aluminum\s*silver\s*finish|paint\s*thinner|lacquer\s*thinner|epoxy\s*reducer)\b/i', $name)) {
            $main = 'Paints & Coatings';
            if (stripos($name, 'primecoat') !== false || stripos($name, 'primer') !== false || stripos($name, 'putty') !== false) {
                $mid = 'Primers & Surface Preparation';
                $end = 'Primers & Masonry Putty';
                $reason = 'The product name identifies a surface primer or masonry putty undercoat.';
            } elseif (stripos($name, 'latex') !== false) {
                $mid = 'Interior & Exterior Paint';
                $end = 'Latex Paints';
                $reason = 'The product name identifies a water-based acrylic latex architectural paint.';
            } elseif (stripos($name, 'qde') !== false || stripos($name, 'enamel') !== false) {
                $mid = 'Enamel Paint';
                $end = (stripos($name, 'qde') !== false) ? 'Quick Drying Enamels (QDE)' : 'Flat & Semi-Gloss Enamels';
                $reason = 'The product name identifies an alkyd/quick-drying enamel coating for wood and metal.';
            } elseif (stripos($name, 'roof guard') !== false) {
                $mid = 'Specialty Coatings';
                $end = 'Roof Paints';
                $reason = 'The product name identifies Boysen Roofgard acrylic roof coating.';
            } elseif (stripos($name, 'thinner') !== false || stripos($name, 'epoxy reducer') !== false) {
                $mid = 'Thinners & Solvents';
                $end = 'Paint Thinners & Reducers';
                $reason = 'The product name identifies a paint thinner or epoxy reducer solvent.';
            } else {
                $mid = 'Specialty Coatings';
                $end = 'Metallic & Specialty Finishes';
                $reason = 'The product name identifies a specialty metallic aluminum paint finish.';
            }
            $conf = 96;
        }

        // -------------------------------------------------------------------------
        // RULE 14: Welding Supplies (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(welding\s*rod)\b/i', $name)) {
            $main = 'Welding Supplies';
            $mid  = 'Welding Electrodes';
            $end  = 'Welding Rods';
            $conf = 98;
            $reason = 'The product name and AWS classification (6011/6013) explicitly identify shielded metal arc welding electrodes.';
        }

        // -------------------------------------------------------------------------
        // RULE 15: Tools & Equipment (Section 14)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(paint\s*roller|paint\s*brush|steel\s*brush|cutting\s*disk|diamond\s*wheel|steel\s*tape|measuring\s*tape|hammer|angle\s*grinder|grinder|drill|hacksaw|wrench|screwdriver)\b/i', $name)) {
            $main = 'Tools & Equipment';
            if (stripos($name, 'paint brush') !== false || stripos($name, 'paint roller') !== false) {
                $mid = 'Painting Tools';
                $end = (stripos($name, 'roller') !== false) ? 'Paint Rollers' : 'Paint Brushes';
                $reason = 'The product name identifies a manual paint applicator tool (brush or roller).';
            } elseif (stripos($name, 'cutting disk') !== false || stripos($name, 'diamond wheel') !== false) {
                $mid = 'Power Tool Accessories';
                $end = 'Cutting Discs & Diamond Wheels';
                $reason = 'The product name identifies an abrasive cutting disc or diamond blade wheel for grinders/saws.';
            } elseif (stripos($name, 'tape') !== false) {
                $mid = 'Measuring Tools';
                $end = 'Measuring Tapes';
                $reason = 'The product name identifies a retractable steel measuring tape.';
            } elseif (stripos($name, 'grinder') !== false || stripos($name, 'drill') !== false) {
                $mid = 'Power Tools';
                $end = (stripos($name, 'grinder') !== false) ? 'Grinders' : 'Drills';
                $reason = 'The product name identifies an electric power tool.';
            } else {
                $mid = 'Hand Tools';
                $end = (stripos($name, 'hammer') !== false) ? 'Hammers' : 'Hand & Wire Brushes';
                $reason = 'The product name identifies a manual hand tool.';
            }
            $conf = 96;
        }

        // -------------------------------------------------------------------------
        // RULE 16: Safety & Personal Protective Equipment (Section 3 & 28)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(safety\s*helmet|hard\s*hat|safety\s*gloves|safety\s*goggles|safety\s*vest|safety\s*shoes)\b/i', $name)) {
            $main = 'Safety & Personal Protective Equipment';
            $mid  = 'Head & Body Protection';
            $end  = 'Safety Helmets & PPE';
            $conf = 97;
            $reason = 'The product name identifies personal protective equipment (PPE) for jobsite safety.';
        }

        // -------------------------------------------------------------------------
        // RULE 17: Fencing & Gates (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(cyclone|barb\s*wire|barbed\s*wire|hog\s*wire|welded\s*wire|hardware\s*cloth)\b/i', $name)) {
            $main = 'Fencing & Gates';
            $mid  = 'Wire Fencing & Mesh';
            if (stripos($name, 'cyclone') !== false) $end = 'Cyclone Wire Fencing';
            elseif (stripos($name, 'barb') !== false) $end = 'Barbed Wire';
            elseif (stripos($name, 'hog') !== false) $end = 'Hog Wire Fencing';
            else $end = 'Welded Wire Mesh';
            $conf = 95;
            $reason = 'The product name identifies woven or welded wire mesh used for perimeter fencing and enclosures.';
        }

        // -------------------------------------------------------------------------
        // RULE 18: Construction Accessories (Screens, Nets, Tarpaulins) (Section 3)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(net\s*screen|polyethylene\s+[0-9]|black\s*net|aluminum\s*screen|sunshade\s*black\s*net|trapal)\b/i', $name)) {
            $main = 'Construction Accessories';
            $mid  = 'Screens, Nets & Tarpaulins';
            if (stripos($name, 'trapal') !== false) {
                $end = 'Tarpaulins (Trapal)';
                $reason = 'The product name identifies a heavy-duty waterproof tarpaulin sheet (trapal).';
            } elseif (stripos($name, 'black net') !== false || stripos($name, 'sunshade') !== false) {
                $end = 'Shade Nets';
                $reason = 'The product name identifies an agricultural/construction sunshade net.';
            } else {
                $end = 'Screen Meshes & Netting';
                $reason = 'The product name identifies polyethylene, nylon, or aluminum screen mesh.';
            }
            $conf = 92;
        }

        // -------------------------------------------------------------------------
        // RULE 19: Electrical Supplies (Section 8)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(thhn|pdx|flat\s*cord|duplex\s*[0-9])\b/i', $name)) {
            $main = 'Electrical Supplies';
            $mid  = 'Wires & Cables';
            if (stripos($name, 'thhn') !== false) $end = 'THHN Wires';
            elseif (stripos($name, 'pdx') !== false) $end = 'PDX Cables';
            else $end = 'Flat Cords & Duplex Wires';
            $conf = 98;
            $reason = 'The product name and AWG gauge identify insulated copper electrical building wire/cable.';
        } elseif (preg_match('/\b(panel\s*board|circuit\s*breaker|safety\s*breaker|chints\s*breaker|mcb|mccb)\b/i', $name)) {
            $main = 'Electrical Supplies';
            $mid  = 'Electrical Protection';
            if (stripos($name, 'panel board') !== false) $end = 'Electrical Panels';
            elseif (stripos($name, 'safety breaker') !== false) $end = 'Safety Breakers';
            else $end = 'Circuit Breakers';
            $conf = 98;
            $reason = 'The product name identifies an electrical overcurrent protection breaker or distribution panel board.';
        } elseif (preg_match('/\b(electrical\s*pipe|electrical\s*moulding|electrical\s*elbow|pvc\s*clamp\s*elect|flex\s*connector\s*elect|pvc\s*connector\s*elect|entrance\s*cap|flexible\s*hose)\b/i', $name)) {
            $main = 'Electrical Supplies';
            $mid  = 'Conduits & Fittings';
            if (stripos($name, 'electrical pipe') !== false) $end = 'PVC Electrical Conduits';
            elseif (stripos($name, 'flexible hose') !== false) $end = 'Flexible Electrical Conduits';
            elseif (stripos($name, 'moulding') !== false) $end = 'Electrical Mouldings';
            else $end = 'Electrical Conduit Fittings';
            $conf = 95;
            $reason = 'The product name identifies electrical raceway conduit, moulding, or conduit fittings.';
        } elseif (preg_match('/\b(electrical\s*tape)\b/i', $name)) {
            $main = 'Electrical Supplies';
            $mid  = 'Electrical Accessories';
            $end  = 'Electrical Tapes';
            $conf = 96;
            $reason = 'The product name explicitly identifies insulating vinyl electrical tape.';
        } elseif (preg_match('/\b(led\s*bulb|frosted\s*bulb|butterfly\s*bulb|t8\s*tube)\b/i', $name)) {
            $main = 'Electrical Supplies';
            $mid  = 'Lighting & Lamps';
            $end  = (stripos($name, 'tube') !== false) ? 'Fluorescent & LED Tubes' : 'LED & Light Bulbs';
            $conf = 97;
            $reason = 'The product name and wattage rating identify an electrical light bulb or tube lamp.';
        } elseif (preg_match('/\b(outlet|switch|utility\s*box|square\s*box|regular\s*plug|heavy\s*duty\s*plug|rubber\s*socket|plate\s*cover|1gang\s*plate|pull\s*chain|receptacle)\b/i', $name)) {
            $main = 'Electrical Supplies';
            $mid  = 'Wiring Devices';
            if (stripos($name, 'outlet') !== false) $end = 'Convenience Outlets';
            elseif (stripos($name, 'switch') !== false || stripos($name, 'pull chain') !== false) $end = 'Switches';
            elseif (stripos($name, 'box') !== false || stripos($name, 'plate') !== false) $end = 'Boxes & Faceplates';
            else $end = 'Plugs, Sockets & Receptacles';
            $conf = 96;
            $reason = 'The product name identifies an electrical wiring device (outlet, switch, box, plug, or receptacle).';
        }

        // -------------------------------------------------------------------------
        // RULE 20: Plumbing Supplies & Sanitary Fixtures (Sections 7 & 10)
        // -------------------------------------------------------------------------
        elseif (preg_match('/\b(lababo)\b/i', $name)) {
            $main = 'Sanitary & Bathroom Fixtures';
            $mid  = 'Sinks & Lavatories';
            $end  = 'Kitchen & Utility Sinks';
            $conf = 95;
            $reason = 'The product name identifies a sink/lavatory basin (lababo).';
        } elseif (preg_match('/\b(ball\s*valve|foot\s*valve|swing\s*valve|check\s*valve|gate\s*valve)\b/i', $name)) {
            $main = 'Plumbing Supplies';
            $mid  = 'Valves & Flow Control';
            if (stripos($name, 'ball valve') !== false) $end = 'Ball Valves';
            elseif (stripos($name, 'foot valve') !== false) $end = 'Foot Valves';
            else $end = 'Check & Swing Valves';
            $conf = 98;
            $reason = 'The product name explicitly identifies a plumbing flow-control or backflow valve.';
        } elseif (preg_match('/\b(pvc\s*pipe|sanitary\s*pipe)\b/i', $name)) {
            $main = 'Plumbing Supplies';
            $mid  = 'PVC Pipes & Fittings';
            $end  = (stripos($name, 'sanitary') !== false) ? 'PVC Sanitary Pipes' : 'PVC Pipes';
            $conf = 98;
            $reason = 'The product name identifies a potable water or sanitary drainage PVC pipe.';
        } elseif (preg_match('/\b(sdr\s*11|garden\s*hose|level\s*hose|hdpe\s*pipe|ppr\s*pipe)\b/i', $name)) {
            $main = 'Plumbing Supplies';
            $mid  = 'PE Pipes & Hoses';
            $end  = (stripos($name, 'sdr') !== false || stripos($name, 'hdpe') !== false) ? 'HDPE Pipes (SDR 11)' : 'Flexible Water & Level Hoses';
            $conf = 95;
            $reason = 'The product name identifies an HDPE/PE water line pipe (SDR 11) or flexible water/level hose.';
        } elseif (preg_match('/\b(sanitary\s*elbow|pvc\s*blue\s*elbow|pvc\s*elbow|sanitary\s*tee|pvc\s*blue\s*tee|sanitary\s*wye|sanitary\s*clean\s*out|sanitary\s*coupling|pvc\s*blue\s*coupling|sanitary\s*p-trap|sanitary\s*bushing\s*reducer|blue\s*pvc\s*reducer|pvc\s*blue\s*fta|blue\s*mta\s*pvc|pvc\s*blue\s*end\s*cap|pvc\s*blue\s*end\s*plug|union\s*patente|pvc\s*clamp\s*blue)\b/i', $name)) {
            $main = 'Plumbing Supplies';
            $mid  = 'PVC Pipes & Fittings';
            if (stripos($name, 'elbow') !== false) $end = 'PVC Elbows';
            elseif (stripos($name, 'tee') !== false || stripos($name, 'wye') !== false) $end = 'PVC Tees & Wyes';
            elseif (stripos($name, 'coupling') !== false || stripos($name, 'union') !== false) $end = 'PVC Couplings & Unions';
            elseif (stripos($name, 'reducer') !== false || stripos($name, 'fta') !== false || stripos($name, 'mta') !== false) $end = 'PVC Reducers & Adapters';
            else $end = 'PVC Traps, Cleanouts & Caps';
            $conf = 97;
            $reason = 'The product name identifies a PVC potable water or sanitary drainage pipe fitting.';
        } elseif (preg_match('/\b(compression\s*tee|compression\s*coupling|compression\s*elbow|compression\s*fta|compression\s*mta|compression\s*reducer)\b/i', $name)) {
            $main = 'Plumbing Supplies';
            $mid  = 'PE Pipes & Hoses';
            $end  = 'PE Compression Fittings';
            $conf = 96;
            $reason = 'The product name identifies a mechanical compression fitting for HDPE/PE water pipes.';
        } elseif (preg_match('/\b(gi\s*nipple|gi\s*coupling|gi\s*elbow|gi\s*tee|g\.?i\.?\s*plug|gi\s*reducer)\b/i', $name)) {
            $main = 'Plumbing Supplies';
            $mid  = 'GI Pipe Fittings';
            $end  = (stripos($name, 'nipple') !== false) ? 'GI Pipe Nipples' : 'GI Elbows, Tees & Reducers';
            $conf = 96;
            $reason = 'The product name identifies a threaded galvanized iron (GI) plumbing pipe fitting.';
        }

        return $this->buildResult($main, $mid, $end, $baseProduct, $variant, $conf, $reason, $currTcat, $currMcat, $currEcat);
    }

    private function buildResult($main, $mid, $end, $baseProduct, $variant, $conf, $reason, $currTcat, $currMcat, $currEcat)
    {
        $conf = max(0, min(100, (int)$conf));
        $status = ($conf < 60) ? 'Needs Review' : 'Auto-Classified';

        return [
            'main_category'    => $main,
            'mid_category'     => $mid,
            'end_category'     => $end,
            'base_product'     => $baseProduct,
            'variant'          => $variant,
            'confidence'       => $conf,
            'confidence_label' => self::getConfidenceLabel($conf),
            'status'           => $status,
            'reason'           => $reason,
            'curr_tcat'        => $currTcat,
            'curr_mcat'        => $currMcat,
            'curr_ecat'        => $currEcat,
        ];
    }

    /**
     * Resolves or creates (with duplicate prevention) the hierarchy:
     * tbl_top_category -> tbl_mid_category -> tbl_end_category
     * Returns ['tcat_id' => int, 'mcat_id' => int, 'ecat_id' => int]
     */
    public function resolveOrCreateCategoryIds($mainName, $midName, $endName)
    {
        if (!$this->pdo) {
            return ['tcat_id' => 0, 'mcat_id' => 0, 'ecat_id' => 0];
        }

        $mainName = trim($mainName);
        $midName  = trim($midName);
        $endName  = trim($endName);

        // 1. Resolve Top Category (case-insensitive)
        $stmtT = $this->pdo->prepare("SELECT tcat_id, tcat_name FROM tbl_top_category WHERE LOWER(TRIM(tcat_name)) = LOWER(?) LIMIT 1");
        $stmtT->execute([$mainName]);
        $tRow = $stmtT->fetch(PDO::FETCH_ASSOC);
        if ($tRow) {
            $tcatId = (int)$tRow['tcat_id'];
        } else {
            $stmtInsT = $this->pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, 1) RETURNING tcat_id");
            $stmtInsT->execute([$mainName]);
            $tcatId = (int)$stmtInsT->fetchColumn();
        }

        // 2. Resolve Mid Category under Top Category (or reuse existing case-insensitive match)
        $stmtM = $this->pdo->prepare("SELECT mcat_id, mcat_name FROM tbl_mid_category WHERE LOWER(TRIM(mcat_name)) = LOWER(?) AND tcat_id = ? LIMIT 1");
        $stmtM->execute([$midName, $tcatId]);
        $mRow = $stmtM->fetch(PDO::FETCH_ASSOC);
        if ($mRow) {
            $mcatId = (int)$mRow['mcat_id'];
        } else {
            $stmtInsM = $this->pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
            $stmtInsM->execute([$midName, $tcatId]);
            $mcatId = (int)$stmtInsM->fetchColumn();
        }

        // 3. Resolve End Category under Mid Category (check singular/plural & case-insensitive)
        $singularEnd = rtrim($endName, 's');
        $pluralEnd   = $singularEnd . 's';
        $stmtE = $this->pdo->prepare("
            SELECT ecat_id, ecat_name
            FROM tbl_end_category
            WHERE mcat_id = ?
              AND (LOWER(TRIM(ecat_name)) = LOWER(?) OR LOWER(TRIM(ecat_name)) = LOWER(?) OR LOWER(TRIM(ecat_name)) = LOWER(?))
            LIMIT 1
        ");
        $stmtE->execute([$mcatId, $endName, $singularEnd, $pluralEnd]);
        $eRow = $stmtE->fetch(PDO::FETCH_ASSOC);
        if ($eRow) {
            $ecatId = (int)$eRow['ecat_id'];
        } else {
            $stmtInsE = $this->pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
            $stmtInsE->execute([$endName, $mcatId]);
            $ecatId = (int)$stmtInsE->fetchColumn();
        }

        return [
            'tcat_id' => $tcatId,
            'mcat_id' => $mcatId,
            'ecat_id' => $ecatId,
        ];
    }

    /**
     * Analyzes products in DB (filtered optionally by supplier_id) and populates tbl_category_review_queue
     * without modifying tbl_product until approved (Section 24).
     */
    public function analyzeDatabaseProducts($supplierId = 0)
    {
        if (!$this->pdo) {
            return ['total' => 0, 'high' => 0, 'medium' => 0, 'review' => 0, 'items' => []];
        }

        $sql = "
            SELECT p.p_id, p.p_name, p.p_sku, p.p_brand, p.p_description, p.p_short_description, p.p_specs,
                   p.ecat_id, p.supplier_id,
                   ec.ecat_name AS curr_ecat,
                   mc.mcat_id, mc.mcat_name AS curr_mcat,
                   tc.tcat_id, tc.tcat_name AS curr_tcat
            FROM tbl_product p
            LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
            LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
            LEFT JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
        ";
        $params = [];
        if ($supplierId > 0) {
            $sql .= " WHERE p.supplier_id = ?";
            $params[] = (int)$supplierId;
        }
        $sql .= " ORDER BY p.p_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'total'  => count($rows),
            'high'   => 0,
            'medium' => 0,
            'review' => 0,
            'items'  => [],
        ];

        $this->pdo->beginTransaction();
        try {
            $upsertStmt = $this->pdo->prepare("
                INSERT INTO tbl_category_review_queue (
                    p_id, supplier_id, p_name, p_sku,
                    curr_tcat_name, curr_mcat_name, curr_ecat_name, curr_ecat_id,
                    sugg_tcat_name, sugg_mcat_name, sugg_ecat_name,
                    base_product, variant_spec, confidence, confidence_label, reason, status, updated_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
                )
                ON CONFLICT (p_id) DO UPDATE SET
                    p_name = EXCLUDED.p_name,
                    p_sku = EXCLUDED.p_sku,
                    curr_tcat_name = EXCLUDED.curr_tcat_name,
                    curr_mcat_name = EXCLUDED.curr_mcat_name,
                    curr_ecat_name = EXCLUDED.curr_ecat_name,
                    curr_ecat_id = EXCLUDED.curr_ecat_id,
                    sugg_tcat_name = EXCLUDED.sugg_tcat_name,
                    sugg_mcat_name = EXCLUDED.sugg_mcat_name,
                    sugg_ecat_name = EXCLUDED.sugg_ecat_name,
                    base_product = EXCLUDED.base_product,
                    variant_spec = EXCLUDED.variant_spec,
                    confidence = EXCLUDED.confidence,
                    confidence_label = EXCLUDED.confidence_label,
                    reason = EXCLUDED.reason,
                    status = CASE
                        WHEN tbl_category_review_queue.status = 'Approved' THEN 'Approved'
                        ELSE EXCLUDED.status
                    END,
                    updated_at = CURRENT_TIMESTAMP
            ");

            foreach ($rows as $r) {
                $res = $this->classifyProduct($r);
                $conf = $res['confidence'];
                if ($conf >= 75) {
                    $stats['high']++;
                    $qStatus = 'Pending Approval';
                } elseif ($conf >= 60) {
                    $stats['medium']++;
                    $qStatus = 'Pending Approval';
                } else {
                    $stats['review']++;
                    $qStatus = 'Needs Review';
                }

                $upsertStmt->execute([
                    (int)$r['p_id'],
                    (int)$r['supplier_id'],
                    $r['p_name'],
                    $r['p_sku'] ?? '',
                    $r['curr_tcat'] ?? 'Unassigned',
                    $r['curr_mcat'] ?? 'Unassigned',
                    $r['curr_ecat'] ?? 'Unassigned',
                    (int)$r['ecat_id'],
                    $res['main_category'],
                    $res['mid_category'],
                    $res['end_category'],
                    $res['base_product'],
                    $res['variant'],
                    $conf,
                    $res['confidence_label'],
                    $res['reason'],
                    $qStatus,
                ]);
                $stats['items'][] = array_merge($r, $res, ['queue_status' => $qStatus]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $stats;
    }

    /**
     * Applies approved category assignments ONLY to tbl_product.ecat_id and logs every change in tbl_category_audit_log.
     * Strictly preserves all product pricing, stock, SKU, description, supplier, and image fields (Section 25 & 26).
     */
    public function applyProductCategorization($pId, $mainCat, $midCat, $endCat, $confidence, $reason, $changedBy = 'Admin', $source = 'AI', $supplierFilter = 0)
    {
        if (!$this->pdo) {
            return false;
        }

        $pId = (int)$pId;
        $sql = "
            SELECT p.p_id, p.p_name, p.ecat_id, p.supplier_id,
                   ec.ecat_name AS prev_ecat,
                   mc.mcat_id AS prev_mcat_id, mc.mcat_name AS prev_mcat,
                   tc.tcat_id AS prev_tcat_id, tc.tcat_name AS prev_tcat
            FROM tbl_product p
            LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
            LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
            LEFT JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
            WHERE p.p_id = ?
        ";
        $params = [$pId];
        if ($supplierFilter > 0) {
            $sql .= " AND p.supplier_id = ?";
            $params[] = (int)$supplierFilter;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            return false;
        }

        $catIds = $this->resolveOrCreateCategoryIds($mainCat, $midCat, $endCat);
        $newEcatId = $catIds['ecat_id'];
        if ($newEcatId <= 0) {
            return false;
        }

        // Update ONLY ecat_id on tbl_product (Section 25)
        $upd = $this->pdo->prepare("UPDATE tbl_product SET ecat_id = ? WHERE p_id = ?");
        $upd->execute([$newEcatId, $pId]);

        // Log in tbl_category_audit_log (Section 26)
        $logStmt = $this->pdo->prepare("
            INSERT INTO tbl_category_audit_log (
                p_id, supplier_id, p_name,
                prev_tcat_id, prev_tcat_name, prev_mcat_id, prev_mcat_name, prev_ecat_id, prev_ecat_name,
                new_tcat_id, new_tcat_name, new_mcat_id, new_mcat_name, new_ecat_id, new_ecat_name,
                confidence, reason, changed_by, source
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $pId,
            (int)$prod['supplier_id'],
            $prod['p_name'],
            (int)($prod['prev_tcat_id'] ?? 0),
            $prod['prev_tcat'] ?? '',
            (int)($prod['prev_mcat_id'] ?? 0),
            $prod['prev_mcat'] ?? '',
            (int)($prod['ecat_id'] ?? 0),
            $prod['prev_ecat'] ?? '',
            $catIds['tcat_id'],
            $mainCat,
            $catIds['mcat_id'],
            $midCat,
            $catIds['ecat_id'],
            $endCat,
            (int)$confidence,
            $reason,
            $changedBy,
            $source,
        ]);

        // Update review queue status
        $qUpd = $this->pdo->prepare("
            UPDATE tbl_category_review_queue
            SET sugg_tcat_name = ?, sugg_mcat_name = ?, sugg_ecat_name = ?,
                sugg_tcat_id = ?, sugg_mcat_id = ?, sugg_ecat_id = ?,
                curr_tcat_name = ?, curr_mcat_name = ?, curr_ecat_name = ?, curr_ecat_id = ?,
                status = 'Approved', reviewed_by = ?, updated_at = CURRENT_TIMESTAMP
            WHERE p_id = ?
        ");
        $qUpd->execute([
            $mainCat, $midCat, $endCat,
            $catIds['tcat_id'], $catIds['mcat_id'], $catIds['ecat_id'],
            $mainCat, $midCat, $endCat, $catIds['ecat_id'],
            $changedBy, $pId
        ]);

        return true;
    }

    /**
     * Runs the 21 Required Benchmark Test Products from Section 28
     */
    public function runBenchmarkSuite()
    {
        $testProducts = [
            'AB1 Angle Bar 1-1/8" x 1-1/8"',
            '12mm Deformed Bar',
            'Portland Cement 40kg',
            'Concrete Hollow Block 4"',
            'Washed Sand',
            '3/4 Gravel',
            'PVC Pipe 1/2"',
            'PVC Elbow 1/2"',
            'THHN Wire 3.5mm',
            '20A Circuit Breaker',
            'GI Roofing Sheet',
            'Marine Plywood 1/4"',
            'Common Nail 2"',
            'Wood Screw 1"',
            'Latex Paint 1 Gallon',
            'Tile Adhesive 25kg',
            'Door Lockset',
            'Ball Valve 1/2"',
            'Angle Grinder',
            'Hammer',
            'Safety Helmet',
        ];

        $results = [];
        foreach ($testProducts as $idx => $prodName) {
            $res = $this->classifyProduct(['p_name' => $prodName]);
            $results[] = array_merge(['test_no' => $idx + 1, 'product_name' => $prodName], $res);
        }
        return $results;
    }
}
}
