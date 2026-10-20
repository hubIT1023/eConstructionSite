<?php
/**
 * ChatGPT / OpenAI Access Guard for Supplier Database, POS, and ERP
 * Managed via Global Admin (/admin/supplier.php and /admin/supplier-edit.php)
 */

if (!function_exists('ensure_chatgpt_access_schema')) {
    function ensure_chatgpt_access_schema($pdo) {
        static $checked = false;
        if ($checked || !$pdo) {
            return;
        }
        $checked = true;
        try {
            $pdo->exec("ALTER TABLE public.tbl_supplier ADD COLUMN IF NOT EXISTS allow_chatgpt_access SMALLINT NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE public.tbl_supplier ADD COLUMN IF NOT EXISTS chatgpt_access_updated_at TIMESTAMP WITHOUT TIME ZONE NULL");
            $pdo->exec("ALTER TABLE public.tbl_settings ADD COLUMN IF NOT EXISTS global_allow_chatgpt SMALLINT NOT NULL DEFAULT 1");
        } catch (Exception $e) {
            // Ignore silently so normal flow never breaks
        }
    }
}

if (!function_exists('is_chatgpt_or_openai_request')) {
    function is_chatgpt_or_openai_request() {
        $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $signatures = array(
            'gptbot',
            'chatgpt-user',
            'oai-searchbot',
            'chatgpt',
            'openai'
        );
        foreach ($signatures as $sig) {
            if ($ua !== '' && strpos($ua, $sig) !== false) {
                return true;
            }
        }

        // Check explicit Custom GPT Action / OpenAI plugin headers
        if (
            !empty($_SERVER['HTTP_X_OPENAI_ACTION']) ||
            !empty($_SERVER['HTTP_X_CHATGPT_INTEGRATION']) ||
            !empty($_SERVER['HTTP_OPENAI_CONVERSATION_ID']) ||
            !empty($_SERVER['HTTP_OPENAI_EPHEMERAL_USER_ID'])
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('get_global_chatgpt_policy')) {
    /**
     * Returns true if per-supplier ChatGPT access is permitted by the global master switch,
     * or false if the Global Admin has enabled the Global Master Block (0).
     */
    function get_global_chatgpt_policy($pdo) {
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->query("SELECT global_allow_chatgpt FROM public.tbl_settings WHERE id = 1 LIMIT 1");
            if ($stmt) {
                $val = $stmt->fetchColumn();
                if ($val !== false && $val !== null) {
                    return ((int)$val === 1);
                }
            }
        } catch (Exception $e) {
            // Default to true (follow per-supplier policy) if column not yet read
        }
        return true;
    }
}

if (!function_exists('is_supplier_chatgpt_allowed')) {
    /**
     * Returns true ONLY if the global master policy is enabled AND this specific supplier
     * has allow_chatgpt_access = 1.
     */
    function is_supplier_chatgpt_allowed($pdo, $supplier_id) {
        $supplier_id = (int)$supplier_id;
        if (!$pdo || $supplier_id <= 0) {
            return false;
        }
        if (!get_global_chatgpt_policy($pdo)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT allow_chatgpt_access FROM public.tbl_supplier WHERE supplier_id = ? LIMIT 1");
            $stmt->execute(array($supplier_id));
            $val = $stmt->fetchColumn();
            return ((int)$val === 1);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('enforce_chatgpt_access_guard')) {
    /**
     * Enforces the Global Admin ChatGPT access policy on Supplier DB, POS, ERP, and Storefront routes.
     * Normal human browser sessions are never interrupted.
     */
    function enforce_chatgpt_access_guard($pdo, $supplier_id = null, $module = 'Supplier Database / POS / ERP') {
        if (!$pdo) {
            return;
        }

        // Resolve supplier_id from session, request, or ERP cookie if not explicitly passed
        if ($supplier_id === null || (int)$supplier_id <= 0) {
            if (!empty($_SESSION['supplier_user']['supplier_id'])) {
                $supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
            } elseif (!empty($_REQUEST['supplier_id'])) {
                $supplier_id = (int)$_REQUEST['supplier_id'];
            } elseif (!empty($_COOKIE['erp_supplier_id'])) {
                $supplier_id = (int)$_COOKIE['erp_supplier_id'];
            } else {
                $supplier_id = 0;
            }
        } else {
            $supplier_id = (int)$supplier_id;
        }

        $global_allowed = get_global_chatgpt_policy($pdo);
        $allowed = false;

        if ($global_allowed && $supplier_id > 0) {
            $allowed = is_supplier_chatgpt_allowed($pdo, $supplier_id);
        } elseif ($global_allowed && $supplier_id === 0) {
            // If no specific supplier is in context:
            // Block ChatGPT on /admin and /supplier (POS/DB) unless a supplier_id with allowed access is specified
            $script_path = strtolower((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['REQUEST_URI'] ?? ''));
            if (strpos($script_path, '/supplier/') !== false || strpos($script_path, '/admin/') !== false || strpos($script_path, 'webpos') !== false) {
                $allowed = false;
            } else {
                // On general public pages, check if at least global policy is enabled
                $allowed = true;
            }
        }

        if (!$allowed) {
            if (!headers_sent()) {
                header('X-Robots-Tag: noai, noimageai');
            }

            if (is_chatgpt_or_openai_request()) {
                if (!headers_sent()) {
                    http_response_code(403);
                    header('Content-Type: application/json; charset=UTF-8');
                }
                echo json_encode(array(
                    'status'  => 'error',
                    'code'    => 403,
                    'error'   => 'ChatGPT_Access_Blocked',
                    'module'  => $module,
                    'message' => 'ChatGPT / OpenAI access to this Supplier Database, POS, and ERP is blocked by Global Admin policy.'
                ));
                exit;
            }
        }
    }
}
