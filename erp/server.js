const net = require('net');
const http = require('http');
const https = require('https');
const url = require('url');
const fs = require('fs');
const { Pool } = require('pg');
const path = require('path');
const XLSX = require('xlsx');
require('dotenv').config({ path: path.join(__dirname, '.env.erp') });

const port = process.env.PORT || 4000;

// PostgreSQL Connection Pool (Strictly erp_service_user)
const pool = new Pool({
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '54321', 10),
  database: process.env.DB_NAME || 'ecomDB',
  user: process.env.DB_USER || 'erp_service_user',
  password: process.env.DB_PASS || 'erp_local_secret',
  max: 10,
  idleTimeoutMillis: 30000,
});

pool.on('error', (err) => {
  console.error('[ERP DB Pool Error]:', err.message);
});

async function ensureStorageColumns() {
  try {
    const client = await pool.connect();
    try {
      await client.query(`
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS storage_a character varying(50) DEFAULT '0';
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS storage_b character varying(50) DEFAULT '0';
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS storage_c character varying(50) DEFAULT '0';
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS cost_storage_a character varying(50) DEFAULT '0.00';
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS cost_storage_b character varying(50) DEFAULT '0.00';
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS cost_storage_c character varying(50) DEFAULT '0.00';
        ALTER TABLE public.tbl_product ADD COLUMN IF NOT EXISTS cost_storage_nq character varying(50) DEFAULT '0.00';
      `);
      console.log('[ERP Migration]: Ensured storage and cost columns in tbl_product');
    } finally {
      client.release();
    }
  } catch (err) {
    if (!err.message.includes('must be owner')) {
      console.error('[ERP Migration Error]:', err.message);
    }
  }
}
ensureStorageColumns();

// Helpers
function sendJson(res, statusCode, data) {
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization'
  });
  res.end(JSON.stringify(data, null, 2));
}

function getRequestBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', chunk => { body += chunk.toString(); });
    req.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch (err) {
        reject(err);
      }
    });
    req.on('error', reject);
  });
}

// --------------------------------------------------------------------------
// 1. INGESTION WORKER: Non-Intrusively Syncs POS Orders & Payments into ERP
// --------------------------------------------------------------------------
async function runPosSyncWorker() {
  const client = await pool.connect();
  const syncResults = { syncedPayments: 0, syncedOrders: 0, syncedFees: 0, errors: [] };

  try {
    await client.query('BEGIN');

    const wmRes = await client.query("SELECT last_synced_id FROM erp.sync_watermarks WHERE source_name = 'pos_orders'");
    const lastSyncedId = wmRes.rows.length > 0 ? wmRes.rows[0].last_synced_id : 0;

    const newPayments = await client.query(
      `SELECT id, payment_id, supplier_id, payment_date, paid_amount, payment_method, cashier_id, cashier_name
       FROM public.tbl_payment
       WHERE id > $1
       ORDER BY id ASC
       LIMIT 300`,
      [lastSyncedId]
    );

    let maxPaymentId = lastSyncedId;

    for (const p of newPayments.rows) {
      maxPaymentId = Math.max(maxPaymentId, p.id);
      syncResults.syncedPayments++;

      let feePercent = 0.00;
      let fixedFee = 0.00;
      const method = (p.payment_method || '').toLowerCase();

      if (method.includes('card') || method.includes('credit') || method.includes('debit')) {
        feePercent = 0.025;
        fixedFee = 15.00;
      } else if (method.includes('gcash') || method.includes('wallet') || method.includes('maya')) {
        feePercent = 0.015;
      } else if (method.includes('bank') || method.includes('transfer')) {
        fixedFee = 25.00;
      }

      const grossAmt = parseFloat(p.paid_amount || 0);
      const calculatedFee = (grossAmt * feePercent) + fixedFee;

      await client.query(
        `INSERT INTO erp.payment_fee_transactions 
         (payment_id, supplier_id, payment_method, gross_amount, calculated_fee)
         VALUES ($1, $2, $3, $4, $5)
         ON CONFLICT (payment_id) DO UPDATE SET calculated_fee = EXCLUDED.calculated_fee`,
        [p.id, p.supplier_id || 1, p.payment_method || 'Unknown', grossAmt, calculatedFee]
      );
      syncResults.syncedFees++;

      const orderLines = await client.query(
        `SELECT o.id, o.product_id, o.quantity, o.unit_price, o.discount_amount, p.p_capital_price, p.p_current_price
         FROM public.tbl_order o
         LEFT JOIN public.tbl_product p ON o.product_id = p.p_id
         WHERE o.payment_id = $1`,
        [p.payment_id]
      );

      for (const line of orderLines.rows) {
        syncResults.syncedOrders++;
        const lineTotal = parseFloat(line.unit_price || 0) * parseFloat(line.quantity || 1);
        const incentiveRate = 0.01;
        const incentiveAmt = lineTotal * incentiveRate;

        await client.query(
          `INSERT INTO erp.sales_incentives
           (order_id, payment_id, supplier_id, cashier_id, cashier_name, incentive_rate_percent, incentive_amount)
           VALUES ($1, $2, $3, $4, $5, $6, $7)
           ON CONFLICT (order_id) DO NOTHING`,
          [line.id, p.payment_id, p.supplier_id || 1, p.cashier_id, p.cashier_name || 'Counter Cashier', 1.00, incentiveAmt]
        );
      }
    }

    if (maxPaymentId > lastSyncedId) {
      await client.query(
        `UPDATE erp.sync_watermarks 
         SET last_synced_id = $1, last_synced_at = NOW() 
         WHERE source_name = 'pos_orders'`,
        [maxPaymentId]
      );
    }

    await client.query('COMMIT');
    return syncResults;
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('[Sync Worker Error]:', err.message);
    throw err;
  } finally {
    client.release();
  }
}

// --------------------------------------------------------------------------
// 2. HTTP SERVER & API ROUTES FOR TENANT ADMIN
// --------------------------------------------------------------------------
function isChatGptOrOpenAiRequest(req) {
  const ua = String(req.headers['user-agent'] || '').toLowerCase();
  if (ua.includes('gptbot') || ua.includes('chatgpt-user') || ua.includes('oai-searchbot') || ua.includes('chatgpt') || ua.includes('openai')) {
    return true;
  }
  if (req.headers['x-openai-action'] || req.headers['x-chatgpt-integration'] || req.headers['openai-conversation-id']) {
    return true;
  }
  return false;
}

async function isErpChatGptAllowed(supplierId) {
  const client = await pool.connect();
  try {
    const gRes = await client.query('SELECT global_allow_chatgpt FROM public.tbl_settings WHERE id = 1 LIMIT 1');
    if (gRes.rows.length > 0 && Number(gRes.rows[0].global_allow_chatgpt) === 0) {
      return false;
    }
    if (supplierId && Number(supplierId) > 0) {
      const sRes = await client.query('SELECT allow_chatgpt_access FROM public.tbl_supplier WHERE supplier_id = $1 LIMIT 1', [Number(supplierId)]);
      return sRes.rows.length > 0 && Number(sRes.rows[0].allow_chatgpt_access) === 1;
    }
    return false;
  } catch (e) {
    return false;
  } finally {
    client.release();
  }
}

const requestHandler = async (req, res) => {
  const parsedUrl = url.parse(req.url, true);
  let pathname = parsedUrl.pathname;
  if (pathname === '/erp') {
    pathname = '/';
  } else if (pathname.startsWith('/erp/')) {
    pathname = pathname.substring(4);
  }
  const method = req.method;

  // Auto-redirect direct public port 4000 visits to clean https://econstruction-supply.site/erp
  const hostHeader = req.headers.host || '';
  const isDirectPort4000 = hostHeader.includes(':4000') && !hostHeader.startsWith('localhost') && !hostHeader.startsWith('127.0.0.1');
  if (isDirectPort4000 && (pathname === '/' || pathname === '/index.html')) {
    const redirectUrl = 'https://econstruction-supply.site/erp' + (parsedUrl.search || '');
    res.writeHead(302, { 'Location': redirectUrl });
    return res.end();
  }

  if (method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    });
    return res.end();
  }

  try {
    // Enforce Global Admin ChatGPT / OpenAI Access Policy for ERP
    if (isChatGptOrOpenAiRequest(req)) {
      const qSupId = parsedUrl.query.supplier_id || (req.headers.cookie && (req.headers.cookie.match(/erp_supplier_id=(\d+)/) || [])[1]) || 0;
      const allowed = await isErpChatGptAllowed(qSupId);
      if (!allowed) {
        res.setHeader('X-Robots-Tag', 'noai, noimageai');
        return sendJson(res, 403, {
          status: 'error',
          code: 403,
          error: 'ChatGPT_Access_Blocked',
          module: 'Supplier ERP',
          message: 'ChatGPT / OpenAI access to this Supplier ERP and Database is blocked by Global Admin policy.'
        });
      }
    }

    // Health Check
    if (pathname === '/api/health' && (method === 'GET' || method === 'HEAD')) {
      if (method === 'HEAD') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        return res.end();
      }
      return sendJson(res, 200, { status: 'UP', service: 'eConstructionSite ERP Tenant Hub', port });
    }

    // Static file handler for product photos & uploads
    if (pathname.startsWith('/assets/uploads/')) {
      const safeRelPath = pathname.replace(/^\/+/, '');
      const filePath = path.join(__dirname, '..', safeRelPath);
      if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
        const ext = path.extname(filePath).toLowerCase();
        const mimeTypes = {
          '.jpg': 'image/jpeg',
          '.jpeg': 'image/jpeg',
          '.png': 'image/png',
          '.gif': 'image/gif',
          '.webp': 'image/webp',
          '.svg': 'image/svg+xml'
        };
        const contentType = mimeTypes[ext] || 'application/octet-stream';
        res.writeHead(200, { 'Content-Type': contentType, 'Cache-Control': 'public, max-age=86400' });
        return fs.createReadStream(filePath).pipe(res);
      } else {
        const placeholderPath = path.join(__dirname, '..', 'assets', 'uploads', 'product_photos', 'general_products.png');
        if (fs.existsSync(placeholderPath)) {
          res.writeHead(200, { 'Content-Type': 'image/png' });
          return fs.createReadStream(placeholderPath).pipe(res);
        }
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        return res.end('Image Not Found');
      }
    }

    // 1. Tenants List (for Tenant Switcher)
    if (pathname === '/api/tenants' && method === 'GET') {
      const client = await pool.connect();
      try {
        const q = await client.query('SELECT supplier_id, supplier_name, supplier_plan, supplier_status FROM public.tbl_supplier ORDER BY supplier_id ASC');
        return sendJson(res, 200, q.rows);
      } finally {
        client.release();
      }
    }

    // 2. Products List (for Stock Receiving & Store Catalog)
    if (pathname === '/api/products' && method === 'GET') {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '1', 10);
      const client = await pool.connect();
      try {
        const q = await client.query(
          `SELECT p.p_id, p.p_name, p.p_sku, p.p_featured_photo, p.ecat_id, p.p_qty, p.p_new_qty, p.p_s_level, 
                  p.p_capital_price, p.p_markup, p.p_current_price, p.p_new_price,
                  COALESCE(p.storage_a, '0') AS storage_a,
                  COALESCE(p.cost_storage_a, '0.00') AS cost_storage_a,
                  COALESCE(p.storage_b, '0') AS storage_b,
                  COALESCE(p.cost_storage_b, '0.00') AS cost_storage_b,
                  COALESCE(p.storage_c, '0') AS storage_c,
                  COALESCE(p.cost_storage_c, '0.00') AS cost_storage_c,
                  COALESCE(p.cost_storage_nq, '0.00') AS cost_storage_nq,
                  ec.ecat_name
           FROM public.tbl_product p
           LEFT JOIN public.tbl_end_category ec ON p.ecat_id = ec.ecat_id
           WHERE p.supplier_id = $1 
           ORDER BY ec.ecat_name ASC NULLS LAST, p.p_name ASC`,
          [supplierId]
        );
        return sendJson(res, 200, q.rows);
      } finally {
        client.release();
      }
    }

    // 2b. Inline Product Update (for Store Catalog & Available Stock inline editing across all columns)
    if (pathname === '/api/products/update' && (method === 'POST' || method === 'PUT')) {
      const body = await getRequestBody(req);
      const productId = parseInt(body.p_id, 10);
      if (!productId) {
        return sendJson(res, 400, { error: 'Product ID is required.' });
      }

      const client = await pool.connect();
      try {
        const fields = [];
        const values = [];
        let idx = 1;

        if (body.p_name !== undefined && String(body.p_name).trim()) {
          fields.push(`p_name = $${idx++}`);
          values.push(String(body.p_name).trim());
        }
        if (body.p_sku !== undefined) {
          fields.push(`p_sku = $${idx++}`);
          values.push(String(body.p_sku).trim());
        }
        if (body.p_featured_photo !== undefined) {
          fields.push(`p_featured_photo = $${idx++}`);
          values.push(String(body.p_featured_photo).trim());
        }
        if (body.ecat_id !== undefined && !isNaN(parseInt(body.ecat_id, 10)) && parseInt(body.ecat_id, 10) > 0) {
          fields.push(`ecat_id = $${idx++}`);
          values.push(parseInt(body.ecat_id, 10));
        }
        if (body.storage_a !== undefined) {
          fields.push(`storage_a = $${idx++}`);
          values.push(String(body.storage_a || '0').trim());
        }
        if (body.storage_b !== undefined) {
          fields.push(`storage_b = $${idx++}`);
          values.push(String(body.storage_b || '0').trim());
        }
        if (body.storage_c !== undefined) {
          fields.push(`storage_c = $${idx++}`);
          values.push(String(body.storage_c || '0').trim());
        }
        if (body.cost_storage_a !== undefined && !isNaN(parseFloat(body.cost_storage_a))) {
          fields.push(`cost_storage_a = $${idx++}`);
          values.push(parseFloat(body.cost_storage_a).toFixed(2));
        }
        if (body.cost_storage_b !== undefined && !isNaN(parseFloat(body.cost_storage_b))) {
          fields.push(`cost_storage_b = $${idx++}`);
          values.push(parseFloat(body.cost_storage_b).toFixed(2));
        }
        if (body.cost_storage_c !== undefined && !isNaN(parseFloat(body.cost_storage_c))) {
          fields.push(`cost_storage_c = $${idx++}`);
          values.push(parseFloat(body.cost_storage_c).toFixed(2));
        }
        if (body.cost_storage_nq !== undefined && !isNaN(parseFloat(body.cost_storage_nq))) {
          fields.push(`cost_storage_nq = $${idx++}`);
          values.push(parseFloat(body.cost_storage_nq).toFixed(2));
        }
        if (body.p_qty !== undefined && !isNaN(parseInt(body.p_qty, 10))) {
          fields.push(`p_qty = $${idx++}`);
          values.push(parseInt(body.p_qty, 10));
        }
        if (body.p_new_qty !== undefined && !isNaN(parseInt(body.p_new_qty, 10))) {
          fields.push(`p_new_qty = $${idx++}`);
          values.push(parseInt(body.p_new_qty, 10));
        }
        if (body.p_s_level !== undefined && !isNaN(parseInt(body.p_s_level, 10))) {
          fields.push(`p_s_level = $${idx++}`);
          values.push(parseInt(body.p_s_level, 10));
        }
        if (body.p_capital_price !== undefined && !isNaN(parseFloat(body.p_capital_price))) {
          const cap = parseFloat(body.p_capital_price);
          fields.push(`p_capital_price = $${idx++}`);
          values.push(cap.toFixed(2));
        }
        if (body.p_markup !== undefined && !isNaN(parseFloat(body.p_markup))) {
          fields.push(`p_markup = $${idx++}`);
          values.push(String(body.p_markup));
        }
        if (body.p_current_price !== undefined && !isNaN(parseFloat(body.p_current_price))) {
          const price = parseFloat(body.p_current_price);
          fields.push(`p_current_price = $${idx++}`);
          values.push(price.toFixed(2));
          fields.push(`p_new_price = $${idx++}`);
          values.push(price.toFixed(2));
        }

        if (fields.length === 0) {
          return sendJson(res, 400, { error: 'No editable fields provided.' });
        }

        values.push(productId);
        const updateSql = `UPDATE public.tbl_product SET ${fields.join(', ')} WHERE p_id = $${idx} RETURNING p_id, p_name, p_sku, p_featured_photo, ecat_id, p_qty, p_new_qty, p_s_level, p_capital_price, p_markup, p_current_price, storage_a, storage_b, storage_c, cost_storage_a, cost_storage_b, cost_storage_c, cost_storage_nq`;
        const result = await client.query(updateSql, values);

        if (result.rows.length === 0) {
          return sendJson(res, 404, { error: 'Product not found.' });
        }

        return sendJson(res, 200, {
          success: true,
          message: 'Product updated successfully.',
          product: result.rows[0]
        });
      } catch (err) {
        console.error('[Product Update Error]:', err);
        return sendJson(res, 500, { error: err.message });
      } finally {
        client.release();
      }
    }

    // 2c. Upload or Assign Product Photo
    if (pathname === '/api/products/upload-photo' && method === 'POST') {
      const body = await getRequestBody(req);
      const productId = parseInt(body.p_id, 10);
      if (!productId) {
        return sendJson(res, 400, { error: 'Product ID required' });
      }

      let photoFilename = body.filename ? path.basename(body.filename) : null;
      if (body.image_base64) {
        const matches = body.image_base64.match(/^data:image\/([a-zA-Z0-9]+);base64,(.+)$/);
        let ext = '.jpg';
        let base64Data = body.image_base64;
        if (matches) {
          ext = '.' + matches[1].replace('jpeg', 'jpg');
          base64Data = matches[2];
        }
        if (!photoFilename) {
          photoFilename = `product-photo-${productId}-${Date.now()}${ext}`;
        }
        const uploadsDir = path.join(__dirname, '..', 'assets', 'uploads');
        const targetPath = path.join(uploadsDir, photoFilename);
        fs.writeFileSync(targetPath, Buffer.from(base64Data, 'base64'));
      } else if (body.photo_path) {
        photoFilename = body.photo_path.trim();
      }

      if (!photoFilename) {
        return sendJson(res, 400, { error: 'No photo provided' });
      }

      const client = await pool.connect();
      try {
        await client.query('UPDATE public.tbl_product SET p_featured_photo = $1 WHERE p_id = $2', [photoFilename, productId]);
        return sendJson(res, 200, { success: true, p_featured_photo: photoFilename });
      } finally {
        client.release();
      }
    }

    // 2d. Category List for Store Catalog dropdown
    if (pathname === '/api/categories' && method === 'GET') {
      const client = await pool.connect();
      try {
        const q = await client.query('SELECT ecat_id, ecat_name FROM public.tbl_end_category ORDER BY ecat_name ASC');
        return sendJson(res, 200, q.rows);
      } finally {
        client.release();
      }
    }

    // 2e. Batch Import / Update Products from Excel / Spreadsheet (One-Time Excel to SQL)
    if (pathname === '/api/products/batch-import-excel' && method === 'POST') {
      const body = await getRequestBody(req);
      const supplierId = parseInt(body.supplier_id || '2', 10);
      const rows = body.products || [];
      const updateExisting = body.update_existing !== false;
      const insertNew = body.insert_new !== false;

      if (!rows || rows.length === 0) {
        return sendJson(res, 400, { error: 'No product rows provided for Excel import.' });
      }

      const client = await pool.connect();
      try {
        await client.query('BEGIN');

        // Preload existing categories for lookup by name if ecat_id is missing or name is provided
        const catRes = await client.query('SELECT ecat_id, ecat_name FROM public.tbl_end_category');
        const catMap = new Map();
        catRes.rows.forEach(c => {
          catMap.set(String(c.ecat_id), c.ecat_id);
          catMap.set(c.ecat_name.toLowerCase().trim(), c.ecat_id);
        });

        // Preload supplier\'s existing products for fast ID / SKU / Name matching
        const existingRes = await client.query(
          'SELECT p_id, p_sku, LOWER(p_name) as lower_name FROM public.tbl_product WHERE supplier_id = $1',
          [supplierId]
        );
        const existingById = new Set(existingRes.rows.map(r => r.p_id));
        const existingBySku = new Map();
        const existingByName = new Map();
        existingRes.rows.forEach(r => {
          if (r.p_sku) existingBySku.set(r.p_sku.trim().toLowerCase(), r.p_id);
          if (r.lower_name) existingByName.set(r.lower_name.trim(), r.p_id);
        });

        let updatedCount = 0;
        let insertedCount = 0;
        const errors = [];

        for (let i = 0; i < rows.length; i++) {
          const row = rows[i];

          // Normalize values with complete alignment for Sam & Inri inventory headers & standard headers
          let rawPid = row.p_id || row.id || row.ID || row['Product ID'] || row.code || row.Code || row['Item Code'] || row.item_code || '';
          let pid = parseInt(String(rawPid).replace(/[^0-9]/g, ''), 10);
          if (isNaN(pid)) pid = 0;

          const p_name = String(
            row.p_name || row.name || row['Product Name'] || row.Title || row.title || 
            row.description || row.Description || row.desc || row.Desc || row['Item Description'] || ''
          ).trim();
          if (!p_name && !pid) {
            continue;
          }

          const p_sku = String(row.p_sku || row.sku || row.SKU || row['Item Code'] || row.item_code || (pid > 0 ? '' : (row.code || ''))).trim();

          // Resolve ecat_id
          let ecat_id = 1;
          const rawCat = row.ecat_id || row.category || row.Category || row['Category Name'] || '';
          if (rawCat) {
            const cleanCatStr = String(rawCat).trim().toLowerCase();
            if (catMap.has(cleanCatStr)) {
              ecat_id = catMap.get(cleanCatStr);
            } else {
              const numericCat = parseInt(cleanCatStr, 10);
              if (!isNaN(numericCat) && catMap.has(String(numericCat))) {
                ecat_id = numericCat;
              }
            }
          }

          const p_qty = parseInt(String(
            row.p_qty || row.qty || row.Stock || row.stock || row['Available Stock'] || row['Available Stock (Q)'] || row.quantity || row.Quantity || 0
          ).replace(/[^0-9-]/g, ''), 10) || 0;

          const p_new_qty = parseInt(String(
            row.p_new_qty || row.new_qty || row['New Batch'] || row['New Batch (NQ)'] || row.incoming || 0
          ).replace(/[^0-9-]/g, ''), 10) || 0;

          const p_s_level = parseInt(String(
            row.p_s_level || row.s_level || row['Safety Level'] || row['Safety Level (S)'] || 
            row.minimum_stock || row.min_stock || row['Minimum Stock'] || 10
          ).replace(/[^0-9]/g, ''), 10) || 10;

          const p_capital_price = parseFloat(String(
            row.p_capital_price || row.capital_price || row.capital_cost || row['Capital Cost'] || 
            row['Landed Cost'] || row['Cost Price'] || row.cost || 0
          ).replace(/[^0-9.-]/g, '')) || 0;

          let p_markup = parseFloat(String(
            row.p_markup || row.markup || row['Markup'] || row['Markup (PHP)'] || row['Markup (₱)'] || 0
          ).replace(/[^0-9.-]/g, '')) || 0;

          const markup_percent = parseFloat(String(
            row.markup_percent || row['Markup Percent'] || row['Markup %'] || row.markup_percentage || 0
          ).replace(/[^0-9.-]/g, '')) || 0;

          let p_current_price = parseFloat(String(
            row.p_current_price || row.current_price || row.price || row.selling_price || 
            row['Selling Price'] || row['Retail Price'] || row['Current Price'] || row.srp || 0
          ).replace(/[^0-9.-]/g, '')) || 0;

          // Auto-calculate markup or retail price
          if (p_current_price > 0 && p_capital_price > 0 && p_markup === 0) {
            p_markup = p_current_price - p_capital_price;
          } else if (p_current_price === 0 && p_capital_price > 0 && markup_percent > 0) {
            p_markup = p_capital_price * (markup_percent / 100);
            p_current_price = p_capital_price + p_markup;
          } else if (p_current_price === 0 && p_capital_price > 0 && p_markup > 0) {
            p_current_price = p_capital_price + p_markup;
          } else if (p_markup === 0 && p_capital_price > 0 && markup_percent > 0) {
            p_markup = p_capital_price * (markup_percent / 100);
          }

          const unit = String(row.unit || row.Unit || row.uom || '').trim();
          const supplierName = String(row.supplier || row.Supplier || row.vendor || row.Vendor || '').trim();
          const shortDesc = unit ? ('Unit: ' + unit) : '';
          const featureDesc = supplierName ? ('Supplier: ' + supplierName) : '';

          const p_featured_photo = String(row.p_featured_photo || row.photo || row.Photo || row.Image || 'product_photos/general_products.png').trim() || 'product_photos/general_products.png';

          // Match strategy:
          // 1. By ID if exists in active store
          // 2. By SKU if exists in active store
          // 3. By Name if exists in active store
          let targetPid = 0;
          if (pid > 0 && existingById.has(pid)) {
            targetPid = pid;
          } else if (p_sku && existingBySku.has(p_sku.toLowerCase())) {
            targetPid = existingBySku.get(p_sku.toLowerCase());
          } else if (p_name && existingByName.has(p_name.toLowerCase())) {
            targetPid = existingByName.get(p_name.toLowerCase());
          }

          if (targetPid > 0) {
            if (updateExisting) {
              await client.query(
                `UPDATE public.tbl_product 
                 SET p_name = COALESCE(NULLIF($1, ''), p_name),
                     p_sku = COALESCE(NULLIF($2, ''), p_sku),
                     ecat_id = $3,
                     p_qty = $4,
                     p_new_qty = $5,
                     p_s_level = $6,
                     p_capital_price = $7,
                     p_markup = $8,
                     p_current_price = $9,
                     p_new_price = $9,
                     p_featured_photo = COALESCE(NULLIF($10, ''), p_featured_photo),
                     p_short_description = CASE WHEN $13 <> '' AND (p_short_description IS NULL OR p_short_description = '') THEN $13 ELSE p_short_description END,
                     p_feature = CASE WHEN $14 <> '' AND (p_feature IS NULL OR p_feature = '') THEN $14 ELSE p_feature END
                 WHERE p_id = $11 AND supplier_id = $12`,
                [
                  p_name,
                  p_sku,
                  ecat_id,
                  p_qty,
                  p_new_qty,
                  p_s_level,
                  p_capital_price.toFixed(2),
                  p_markup.toFixed(2),
                  p_current_price.toFixed(2),
                  p_featured_photo,
                  targetPid,
                  supplierId,
                  shortDesc,
                  featureDesc
                ]
              );
              updatedCount++;
            }
          } else if (insertNew && p_name) {
            const insertSql = `
              INSERT INTO public.tbl_product (
                supplier_id, p_name, p_old_price, p_current_price, p_new_price,
                p_qty, p_new_qty, p_s_level, p_capital_price, p_markup,
                p_featured_photo, p_description, p_short_description, p_feature,
                p_condition, p_return_policy, p_total_view, p_is_featured,
                p_is_active, ecat_id, p_sku
              ) VALUES (
                $1, $2, $3, $4, $4,
                $5, $6, $7, $8, $9,
                $10, $11, $12, $13,
                $14, $15, 0, 0,
                1, $16, $17
              ) RETURNING p_id
            `;
            const insRes = await client.query(insertSql, [
              supplierId,
              p_name,
              p_current_price.toFixed(2),
              p_current_price.toFixed(2),
              p_qty,
              p_new_qty,
              p_s_level,
              p_capital_price.toFixed(2),
              p_markup.toFixed(2),
              p_featured_photo,
              '', shortDesc, featureDesc, '', '',
              ecat_id,
              p_sku
            ]);
            const newId = insRes.rows[0].p_id;
            existingById.add(newId);
            if (p_sku) existingBySku.set(p_sku.toLowerCase(), newId);
            if (p_name) existingByName.set(p_name.toLowerCase(), newId);
            insertedCount++;
          }
        }

        await client.query('COMMIT');

        return sendJson(res, 200, {
          success: true,
          message: `Successfully processed ${updatedCount + insertedCount} products from Excel (${updatedCount} updated, ${insertedCount} inserted).`,
          updated_count: updatedCount,
          inserted_count: insertedCount,
          total_rows: rows.length,
          errors
        });
      } catch (err) {
        await client.query('ROLLBACK');
        console.error('[Excel Batch Import Error]:', err);
        return sendJson(res, 500, { error: 'Failed to import Excel data into PostgreSQL: ' + err.message });
      } finally {
        client.release();
      }
    }

    // 2f. Download Blank Excel Template for Products (Standard & Sam & Inri formats)
    if (pathname === '/api/products/template-excel' && (method === 'GET' || method === 'HEAD')) {
      const isSamInri = (parsedUrl.query.format === 'sam_inri' || parsedUrl.query.format === 'saminri');
      
      const templateData = isSamInri ? [
        {
          "code": 1163,
          "description": "7/8 PowerNails ( pack)",
          "unit": "pc",
          "stock": 50,
          "minimum_stock": 0,
          "supplier": "Tool Mate",
          "supplier_contact": "",
          "capital_cost": 18.00,
          "markup_percent": 111.11,
          "selling_price": 38.00
        },
        {
          "code": 254,
          "description": "Angle Bar - 1/4 x 1\" orange",
          "unit": "pc",
          "stock": 280,
          "minimum_stock": 25,
          "supplier": "Evergold",
          "supplier_contact": "",
          "capital_cost": 420.00,
          "markup_percent": 30.00,
          "selling_price": 546.00
        },
        {
          "code": "",
          "description": "Sample New Item (Leave code blank to auto-insert new item)",
          "unit": "pc",
          "stock": 100,
          "minimum_stock": 10,
          "supplier": "General Supplies",
          "supplier_contact": "",
          "capital_cost": 50.00,
          "markup_percent": 20.00,
          "selling_price": 60.00
        }
      ] : [
        {
          "ID": 56,
          "SKU": "AB-ORANGE-01",
          "Product Name": "Angle Bar - 1/4 x 2\" orange",
          "Category": "Angle Bars",
          "Available Stock (Q)": 50,
          "New Batch (NQ)": 0,
          "Safety Level (S)": 25,
          "Landed Cost": 875.00,
          "Markup": 306.00,
          "Retail Price": 1181.00,
          "Photo": "product-featured-56.jpg"
        },
        {
          "ID": "",
          "SKU": "NEW-ITEM-001",
          "Product Name": "Sample New Product (Leave ID blank for new items)",
          "Category": "Common Nails",
          "Available Stock (Q)": 100,
          "New Batch (NQ)": 20,
          "Safety Level (S)": 15,
          "Landed Cost": 45.00,
          "Markup": 15.00,
          "Retail Price": 60.00,
          "Photo": "product_photos/general_products.png"
        }
      ];

      const ws = XLSX.utils.json_to_sheet(templateData);
      ws['!cols'] = isSamInri ? [
        { wch: 10 },
        { wch: 38 },
        { wch: 8 },
        { wch: 10 },
        { wch: 14 },
        { wch: 18 },
        { wch: 16 },
        { wch: 14 },
        { wch: 16 },
        { wch: 14 }
      ] : [
        { wch: 8 },
        { wch: 16 },
        { wch: 38 },
        { wch: 22 },
        { wch: 18 },
        { wch: 16 },
        { wch: 16 },
        { wch: 14 },
        { wch: 12 },
        { wch: 14 },
        { wch: 28 }
      ];

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, isSamInri ? "Sam_Inri_Inventory" : "Product_Catalog");
      const buffer = XLSX.write(wb, { type: 'buffer', bookType: isSamInri ? 'biff8' : 'xlsx' });

      res.writeHead(200, {
        'Content-Type': isSamInri ? 'application/vnd.ms-excel' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition': 'attachment; filename="' + (isSamInri ? 'Sam_Inri_Inventory_Template.xls' : 'eConstruction_Product_Template.xlsx') + '"',
        'Content-Length': buffer.length
      });
      return res.end(buffer);
    }

    // 2g. Export Active Store Catalog to Excel
    if (pathname === '/api/products/export-excel' && (method === 'GET' || method === 'HEAD')) {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '2', 10);
      const client = await pool.connect();
      try {
        const q = await client.query(
          `SELECT p.p_id, p.p_sku, p.p_name, ec.ecat_name, p.p_qty, p.p_new_qty, p.p_s_level, 
                  p.p_capital_price, p.p_markup, p.p_current_price, p.p_featured_photo
           FROM public.tbl_product p
           LEFT JOIN public.tbl_end_category ec ON p.ecat_id = ec.ecat_id
           WHERE p.supplier_id = $1 
           ORDER BY p.p_id ASC`,
          [supplierId]
        );

        const rows = q.rows.map(r => ({
          "ID": r.p_id,
          "SKU": r.p_sku || '',
          "Product Name": r.p_name || '',
          "Category": r.ecat_name || 'General',
          "Available Stock (Q)": parseInt(r.p_qty || 0, 10),
          "New Batch (NQ)": parseInt(r.p_new_qty || 0, 10),
          "Safety Level (S)": parseInt(r.p_s_level || 10, 10),
          "Landed Cost": parseFloat(r.p_capital_price || 0),
          "Markup": parseFloat(r.p_markup || 0),
          "Retail Price": parseFloat(r.p_current_price || 0),
          "Photo": r.p_featured_photo || 'product_photos/general_products.png'
        }));

        const ws = XLSX.utils.json_to_sheet(rows);
        ws['!cols'] = [
          { wch: 8 },
          { wch: 18 },
          { wch: 40 },
          { wch: 24 },
          { wch: 18 },
          { wch: 16 },
          { wch: 16 },
          { wch: 14 },
          { wch: 12 },
          { wch: 14 },
          { wch: 30 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Store_Catalog");
        const buffer = XLSX.write(wb, { type: 'buffer', bookType: 'xlsx' });

        res.writeHead(200, {
          'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'Content-Disposition': `attachment; filename="Store_Catalog_Supplier_${supplierId}.xlsx"`,
          'Content-Length': buffer.length
        });
        return res.end(buffer);
      } finally {
        client.release();
      }
    }

    // 3. Receive Inward Materials (GRN) -> Atomically updates POS stock & Landed Cost
    if (pathname === '/api/materials/receive' && method === 'POST') {
      const body = await getRequestBody(req);
      const supplierId = parseInt(body.supplier_id || '1', 10);
      const vendorName = body.vendor_name || 'Vendor';
      const refNumber = body.reference_number || 'DR-' + Date.now();
      const receiptDate = body.receipt_date || new Date().toISOString().split('T')[0];
      const freightCost = parseFloat(body.freight_handling_cost || 0);
      const items = body.items || [];

      if (!items || items.length === 0) {
        return sendJson(res, 400, { error: 'No items provided for receiving' });
      }

      const client = await pool.connect();
      try {
        await client.query('BEGIN');

        let totalMaterialsCost = 0;
        for (const it of items) {
          totalMaterialsCost += (parseInt(it.quantity_received, 10) * parseFloat(it.unit_purchase_price));
        }
        const totalLandedCost = totalMaterialsCost + freightCost;

        // A. Insert GRN Header
        const grnRes = await client.query(
          `INSERT INTO erp.goods_receipt_notes 
           (supplier_id, vendor_name, reference_number, receipt_date, freight_handling_cost, total_materials_cost, total_landed_cost, notes)
           VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
           RETURNING id`,
          [supplierId, vendorName, refNumber, receiptDate, freightCost, totalMaterialsCost, totalLandedCost, body.notes || '']
        );
        const grnId = grnRes.rows[0].id;

        // B. Process Items and update POS Stock via SECURITY DEFINER procedure
        for (const it of items) {
          const qty = parseInt(it.quantity_received, 10);
          const unitPrice = parseFloat(it.unit_purchase_price);
          const itemSubtotal = qty * unitPrice;
          
          // Allocate freight proportionally by value
          const freightShare = totalMaterialsCost > 0 ? (itemSubtotal / totalMaterialsCost) * freightCost : 0;
          const landedUnitCost = unitPrice + (freightShare / qty);

          // Insert GRN Item
          await client.query(
            `INSERT INTO erp.goods_receipt_items 
             (grn_id, product_id, product_name, quantity_received, unit_purchase_price, landed_unit_cost, subtotal)
             VALUES ($1, $2, $3, $4, $5, $6, $7)`,
            [grnId, it.product_id, it.product_name, qty, unitPrice, landedUnitCost, itemSubtotal]
          );

          // Update POS tbl_product.p_qty and capital price atomically
          await client.query(
            `SELECT erp.receive_inventory_stock($1, $2, $3, $4)`,
            [supplierId, it.product_id, qty, landedUnitCost]
          );
        }

        await client.query('COMMIT');
        return sendJson(res, 201, {
          success: true,
          message: `Stock received successfully! Updated ${items.length} product(s) in active POS catalog.`,
          grn_id: grnId,
          total_landed_cost: totalLandedCost
        });
      } catch (err) {
        await client.query('ROLLBACK');
        console.error('[Receive Stock Error]:', err);
        return sendJson(res, 500, { error: err.message });
      } finally {
        client.release();
      }
    }

    // 4. Inward Materials Receiving History
    if (pathname === '/api/materials/history' && method === 'GET') {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '1', 10);
      const client = await pool.connect();
      try {
        const q = await client.query(
          `SELECT g.id, g.vendor_name, g.reference_number, g.receipt_date, g.freight_handling_cost, g.total_materials_cost, g.total_landed_cost, g.created_at,
                  COUNT(i.id) as item_count
           FROM erp.goods_receipt_notes g
           LEFT JOIN erp.goods_receipt_items i ON g.id = i.grn_id
           WHERE g.supplier_id = $1
           GROUP BY g.id
           ORDER BY g.created_at DESC
           LIMIT 20`,
          [supplierId]
        );
        return sendJson(res, 200, q.rows);
      } finally {
        client.release();
      }
    }

    // 5. Staff Payroll & Accrued POS Commissions (with Editable Base Salary, Deductibles & Bonus/Commission)
    if (pathname === '/api/payroll/staff' && method === 'GET') {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '1', 10);
      const client = await pool.connect();
      try {
        await client.query(`
          CREATE TABLE IF NOT EXISTS erp.staff_compensation (
            staff_id INT PRIMARY KEY,
            supplier_id INT NOT NULL,
            base_salary NUMERIC(12,2) NOT NULL DEFAULT 15000.00,
            bonus_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            commission_rate_pct NUMERIC(5,2) NOT NULL DEFAULT 1.00,
            tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00,
            sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            updated_at TIMESTAMP DEFAULT NOW()
          );
          ALTER TABLE erp.staff_compensation ADD COLUMN IF NOT EXISTS tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_compensation ADD COLUMN IF NOT EXISTS sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_compensation ADD COLUMN IF NOT EXISTS advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00;
        `);

        // Staff from tbl_supplier_user joined with editable erp.staff_compensation
        const staffRes = await client.query(
          `SELECT 
             u.id,
             u.full_name,
             u.email,
             u.role,
             u.status,
             COALESCE(
               sc.base_salary,
               CASE 
                 WHEN UPPER(u.role) IN ('ADMIN', 'MANAGER') THEN 25000.00
                 WHEN UPPER(u.role) = 'SUPERVISOR' THEN 18000.00
                 ELSE 15000.00
               END
             ) AS base_salary,
             COALESCE(sc.bonus_commission, 0.00) AS bonus_commission,
             COALESCE(sc.commission_rate_pct, 1.00) AS commission_rate_pct,
             COALESCE(sc.tax_pct, 0.00) AS tax_pct,
             COALESCE(sc.sss_contribution, 0.00) AS sss_contribution,
             COALESCE(sc.advance_payment, 0.00) AS advance_payment,
             COALESCE((
               SELECT SUM(si.incentive_amount)
               FROM erp.sales_incentives si
               WHERE si.supplier_id = u.supplier_id
                 AND si.status = 'ACCRUED'
                 AND (si.cashier_id = u.id OR LOWER(si.cashier_name) = LOWER(u.full_name))
             ), 0.00) AS pos_commission
           FROM public.tbl_supplier_user u
           LEFT JOIN erp.staff_compensation sc ON u.id = sc.staff_id
           WHERE u.supplier_id = $1
           ORDER BY u.id ASC`,
          [supplierId]
        );

        // Group accrued commissions from POS sales
        const incRes = await client.query(
          `SELECT 
             COALESCE(cashier_name, 'Store Cashier') as cashier_name,
             COUNT(*) as order_count,
             COALESCE(SUM(incentive_amount), 0) as total_incentive
           FROM erp.sales_incentives
           WHERE supplier_id = $1 AND status = 'ACCRUED'
           GROUP BY cashier_name`,
          [supplierId]
        );

        // Payroll Payment Calendar Ledger (tracks per-employee paid dates & salary+deductions+bonus/comm breakdown)
        await client.query(`
          CREATE TABLE IF NOT EXISTS erp.staff_payroll_ledger (
            id SERIAL PRIMARY KEY,
            supplier_id INT NOT NULL,
            staff_id INT NOT NULL,
            employee_name VARCHAR(150) NOT NULL,
            pay_date DATE NOT NULL,
            base_salary NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            bonus_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            pos_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00,
            tax_amount NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            total_paid NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'PAID',
            updated_at TIMESTAMP DEFAULT NOW(),
            UNIQUE (supplier_id, staff_id, pay_date)
          );
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS tax_amount NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00;
        `);

        const ledgerRes = await client.query(
          `SELECT id, supplier_id, staff_id, employee_name, pay_date::text AS pay_date,
                  base_salary, bonus_commission, pos_commission,
                  tax_pct, tax_amount, sss_contribution, advance_payment,
                  total_paid, status
           FROM erp.staff_payroll_ledger
           WHERE supplier_id = $1
           ORDER BY pay_date DESC, staff_id ASC`,
          [supplierId]
        );

        return sendJson(res, 200, {
          staff: staffRes.rows,
          commissions: incRes.rows,
          payroll_ledger: ledgerRes.rows
        });
      } finally {
        client.release();
      }
    }

    // 5B. Save / Update Editable Staff Salary, Deductibles & Bonus/Commission
    if (pathname === '/api/payroll/staff/update' && method === 'POST') {
      const body = await getRequestBody(req);
      const supplierId = parseInt(body.supplier_id || '1', 10);
      const updates = Array.isArray(body.staff_updates)
        ? body.staff_updates
        : [{
            staff_id: body.staff_id,
            base_salary: body.base_salary,
            bonus_commission: body.bonus_commission,
            commission_rate_pct: body.commission_rate_pct,
            tax_pct: body.tax_pct,
            sss_contribution: body.sss_contribution,
            advance_payment: body.advance_payment
          }];

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await client.query(`
          CREATE TABLE IF NOT EXISTS erp.staff_compensation (
            staff_id INT PRIMARY KEY,
            supplier_id INT NOT NULL,
            base_salary NUMERIC(12,2) NOT NULL DEFAULT 15000.00,
            bonus_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            commission_rate_pct NUMERIC(5,2) NOT NULL DEFAULT 1.00,
            tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00,
            sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            updated_at TIMESTAMP DEFAULT NOW()
          );
          ALTER TABLE erp.staff_compensation ADD COLUMN IF NOT EXISTS tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_compensation ADD COLUMN IF NOT EXISTS sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_compensation ADD COLUMN IF NOT EXISTS advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00;
        `);

        for (const item of updates) {
          const staffId = parseInt(item.staff_id, 10);
          if (!staffId) continue;
          const baseSalary = Math.max(0, parseFloat(item.base_salary || 0));
          const bonusComm = Math.max(0, parseFloat(item.bonus_commission || 0));
          const commRate = Math.max(0, parseFloat(item.commission_rate_pct ?? 1.0));
          const taxPct = Math.max(0, Math.min(100, parseFloat(item.tax_pct || 0)));
          const sssContrib = Math.max(0, parseFloat(item.sss_contribution || 0));
          const advancePay = Math.max(0, parseFloat(item.advance_payment || 0));

          await client.query(
            `INSERT INTO erp.staff_compensation
               (staff_id, supplier_id, base_salary, bonus_commission, commission_rate_pct, tax_pct, sss_contribution, advance_payment, updated_at)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, NOW())
             ON CONFLICT (staff_id) DO UPDATE SET
               supplier_id = EXCLUDED.supplier_id,
               base_salary = EXCLUDED.base_salary,
               bonus_commission = EXCLUDED.bonus_commission,
               commission_rate_pct = EXCLUDED.commission_rate_pct,
               tax_pct = EXCLUDED.tax_pct,
               sss_contribution = EXCLUDED.sss_contribution,
               advance_payment = EXCLUDED.advance_payment,
               updated_at = NOW()`,
            [staffId, supplierId, baseSalary, bonusComm, commRate, taxPct, sssContrib, advancePay]
          );
        }

        await client.query('COMMIT');
        return sendJson(res, 200, {
          success: true,
          message: `Saved salary, deductions (Tax %, SSS, Advance), and bonus/commission for ${updates.length} staff member(s).`
        });
      } catch (err) {
        await client.query('ROLLBACK');
        return sendJson(res, 500, { error: err.message });
      } finally {
        client.release();
      }
    }

    // 5C. Toggle / Mark Staff Paid Status on Horizontal Calendar Date
    if (pathname === '/api/payroll/calendar/toggle' && method === 'POST') {
      const body = await getRequestBody(req);
      const supplierId = parseInt(body.supplier_id || '1', 10);
      const staffId = parseInt(body.staff_id || '0', 10);
      const employeeName = body.employee_name || 'Staff Member';
      const payDate = body.pay_date || new Date().toISOString().split('T')[0];
      const baseSalary = Math.max(0, parseFloat(body.base_salary || 0));
      const bonusCommission = Math.max(0, parseFloat(body.bonus_commission || 0));
      const posCommission = Math.max(0, parseFloat(body.pos_commission || 0));
      const taxPct = Math.max(0, Math.min(100, parseFloat(body.tax_pct || 0)));
      const taxAmount = Math.round(baseSalary * (taxPct / 100) * 100) / 100;
      const sssContribution = Math.max(0, parseFloat(body.sss_contribution || 0));
      const advancePayment = Math.max(0, parseFloat(body.advance_payment || 0));
      const totalDeductions = taxAmount + sssContribution + advancePayment;
      const totalPaid = Math.max(0, (baseSalary - totalDeductions) + bonusCommission + posCommission);
      const desiredStatus = (body.status || 'PAID').toUpperCase();

      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await client.query(`
          CREATE TABLE IF NOT EXISTS erp.staff_payroll_ledger (
            id SERIAL PRIMARY KEY,
            supplier_id INT NOT NULL,
            staff_id INT NOT NULL,
            employee_name VARCHAR(150) NOT NULL,
            pay_date DATE NOT NULL,
            base_salary NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            bonus_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            pos_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00,
            tax_amount NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            total_paid NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'PAID',
            updated_at TIMESTAMP DEFAULT NOW(),
            UNIQUE (supplier_id, staff_id, pay_date)
          );
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS tax_amount NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00;
        `);

        if (desiredStatus === 'UNPAID') {
          await client.query(
            `DELETE FROM erp.staff_payroll_ledger WHERE supplier_id = $1 AND staff_id = $2 AND pay_date = $3::date`,
            [supplierId, staffId, payDate]
          );
        } else {
          await client.query(
            `INSERT INTO erp.staff_payroll_ledger
             (supplier_id, staff_id, employee_name, pay_date, base_salary, bonus_commission, pos_commission, tax_pct, tax_amount, sss_contribution, advance_payment, total_paid, status, updated_at)
             VALUES ($1, $2, $3, $4::date, $5, $6, $7, $8, $9, $10, $11, $12, 'PAID', NOW())
             ON CONFLICT (supplier_id, staff_id, pay_date) DO UPDATE SET
               employee_name = EXCLUDED.employee_name,
               base_salary = EXCLUDED.base_salary,
               bonus_commission = EXCLUDED.bonus_commission,
               pos_commission = EXCLUDED.pos_commission,
               tax_pct = EXCLUDED.tax_pct,
               tax_amount = EXCLUDED.tax_amount,
               sss_contribution = EXCLUDED.sss_contribution,
               advance_payment = EXCLUDED.advance_payment,
               total_paid = EXCLUDED.total_paid,
               status = 'PAID',
               updated_at = NOW()`,
            [supplierId, staffId, employeeName, payDate, baseSalary, bonusCommission, posCommission, taxPct, taxAmount, sssContribution, advancePayment, totalPaid]
          );
        }

        await client.query('COMMIT');
        return sendJson(res, 200, {
          success: true,
          status: desiredStatus,
          pay_date: payDate,
          message: desiredStatus === 'PAID'
            ? `Marked ${employeeName} as PAID on ${payDate} (Net Payout: ₱${totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2 })}).`
            : `Cleared PAID status for ${employeeName} on ${payDate}.`
        });
      } catch (err) {
        await client.query('ROLLBACK');
        return sendJson(res, 500, { error: err.message });
      } finally {
        client.release();
      }
    }

    // 6. Disburse Payroll & Settle Commissions
    if (pathname === '/api/payroll/disburse' && method === 'POST') {
      const body = await getRequestBody(req);
      const supplierId = parseInt(body.supplier_id || '1', 10);
      const staffId = body.staff_id ? parseInt(body.staff_id, 10) : null;
      const employeeName = body.employee_name || 'Store Staff';
      const baseSalary = parseFloat(body.base_salary || 0);
      const bonusCommission = parseFloat(body.bonus_commission || 0);
      const commissionAmount = parseFloat(body.commission_amount || 0);
      const taxPct = parseFloat(body.tax_pct || 0);
      const taxAmount = body.tax_amount !== undefined ? parseFloat(body.tax_amount) : Math.round(baseSalary * (taxPct / 100) * 100) / 100;
      const sssContribution = parseFloat(body.sss_contribution || 0);
      const advancePayment = parseFloat(body.advance_payment || 0);
      const totalDeductions = taxAmount + sssContribution + advancePayment;
      const totalPayout = Math.max(0, (baseSalary - totalDeductions) + bonusCommission + commissionAmount);
      const payDate = body.pay_date || new Date().toISOString().split('T')[0];
      const periodStart = body.period_start || payDate;
      const periodEnd = body.period_end || payDate;
      const staffBreakdowns = Array.isArray(body.staff_items) ? body.staff_items : [];

      const client = await pool.connect();
      try {
        await client.query('BEGIN');

        await client.query(`
          CREATE TABLE IF NOT EXISTS erp.staff_payroll_ledger (
            id SERIAL PRIMARY KEY,
            supplier_id INT NOT NULL,
            staff_id INT NOT NULL,
            employee_name VARCHAR(150) NOT NULL,
            pay_date DATE NOT NULL,
            base_salary NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            bonus_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            pos_commission NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00,
            tax_amount NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            total_paid NUMERIC(12,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'PAID',
            updated_at TIMESTAMP DEFAULT NOW(),
            UNIQUE (supplier_id, staff_id, pay_date)
          );
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS tax_pct NUMERIC(5,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS tax_amount NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS sss_contribution NUMERIC(12,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE erp.staff_payroll_ledger ADD COLUMN IF NOT EXISTS advance_payment NUMERIC(12,2) NOT NULL DEFAULT 0.00;
        `);

        // Guard against double payment for a single staff member on the same pay_date
        if (staffId) {
          const dupCheck = await client.query(
            `SELECT id, total_paid FROM erp.staff_payroll_ledger
             WHERE supplier_id = $1 AND staff_id = $2 AND pay_date = $3::date AND status = 'PAID'`,
            [supplierId, staffId, payDate]
          );
          if (dupCheck.rows.length > 0) {
            await client.query('ROLLBACK');
            return sendJson(res, 409, {
              success: false,
              already_paid: true,
              error: `${employeeName} is already marked PAID on ${payDate}. Double payment prevented.`
            });
          }
        }

        // A. Log Payroll Outflow in OPEX
        await client.query(
          `INSERT INTO erp.operating_expenses 
           (supplier_id, category, vendor_name, title, amount, billing_period_start, billing_period_end, payment_status, payment_method, notes)
           VALUES ($1, 'BASE_SALARY', $2, $3, $4, $5, $6, 'PAID', 'CASH', $7)`,
          [
            supplierId,
            employeeName,
            `Salary, Bonus & Commission Disbursal for ${employeeName}`,
            totalPayout,
            periodStart,
            periodEnd,
            `Base: ₱${baseSalary.toFixed(2)} - Ded(Tax: ₱${taxAmount.toFixed(2)}, SSS: ₱${sssContribution.toFixed(2)}, Adv: ₱${advancePayment.toFixed(2)}) + Bonus/Comm: ₱${bonusCommission.toFixed(2)} + POS Incentive: ₱${commissionAmount.toFixed(2)}`
          ]
        );

        // B. Record Paid Status in Horizontal Payroll Calendar Ledger
        if (staffId) {
          await client.query(
            `INSERT INTO erp.staff_payroll_ledger
             (supplier_id, staff_id, employee_name, pay_date, base_salary, bonus_commission, pos_commission, tax_pct, tax_amount, sss_contribution, advance_payment, total_paid, status, updated_at)
             VALUES ($1, $2, $3, $4::date, $5, $6, $7, $8, $9, $10, $11, $12, 'PAID', NOW())
             ON CONFLICT (supplier_id, staff_id, pay_date) DO UPDATE SET
               base_salary = EXCLUDED.base_salary,
               bonus_commission = EXCLUDED.bonus_commission,
               pos_commission = EXCLUDED.pos_commission,
               tax_pct = EXCLUDED.tax_pct,
               tax_amount = EXCLUDED.tax_amount,
               sss_contribution = EXCLUDED.sss_contribution,
               advance_payment = EXCLUDED.advance_payment,
               total_paid = EXCLUDED.total_paid,
               status = 'PAID',
               updated_at = NOW()`,
            [supplierId, staffId, employeeName, payDate, baseSalary, bonusCommission, commissionAmount, taxPct, taxAmount, sssContribution, advancePayment, totalPayout]
          );
        } else if (staffBreakdowns.length > 0) {
          for (const st of staffBreakdowns) {
            const sBase = parseFloat(st.base_salary || 0);
            const sBonus = parseFloat(st.bonus_commission || 0);
            const sPos = parseFloat(st.pos_commission || 0);
            const sTaxPct = parseFloat(st.tax_pct || 0);
            const sTaxAmt = Math.round(sBase * (sTaxPct / 100) * 100) / 100;
            const sSss = parseFloat(st.sss_contribution || 0);
            const sAdv = parseFloat(st.advance_payment || 0);
            const sTot = Math.max(0, (sBase - (sTaxAmt + sSss + sAdv)) + sBonus + sPos);
            await client.query(
              `INSERT INTO erp.staff_payroll_ledger
               (supplier_id, staff_id, employee_name, pay_date, base_salary, bonus_commission, pos_commission, tax_pct, tax_amount, sss_contribution, advance_payment, total_paid, status, updated_at)
               VALUES ($1, $2, $3, $4::date, $5, $6, $7, $8, $9, $10, $11, $12, 'PAID', NOW())
               ON CONFLICT (supplier_id, staff_id, pay_date) DO UPDATE SET
                 base_salary = EXCLUDED.base_salary,
                 bonus_commission = EXCLUDED.bonus_commission,
                 pos_commission = EXCLUDED.pos_commission,
                 tax_pct = EXCLUDED.tax_pct,
                 tax_amount = EXCLUDED.tax_amount,
                 sss_contribution = EXCLUDED.sss_contribution,
                 advance_payment = EXCLUDED.advance_payment,
                 total_paid = EXCLUDED.total_paid,
                 status = 'PAID',
                 updated_at = NOW()`,
              [supplierId, parseInt(st.staff_id, 10), st.employee_name || 'Staff', payDate, sBase, sBonus, sPos, sTaxPct, sTaxAmt, sSss, sAdv, sTot]
            );
          }
        }

        // C. Mark accrued incentives as DISBURSED
        if (staffId) {
          await client.query(
            `UPDATE erp.sales_incentives 
             SET status = 'DISBURSED' 
             WHERE supplier_id = $1 AND status = 'ACCRUED' AND (cashier_id = $2 OR LOWER(cashier_name) = LOWER($3))`,
            [supplierId, staffId, employeeName]
          );
        } else {
          await client.query(
            `UPDATE erp.sales_incentives 
             SET status = 'DISBURSED' 
             WHERE supplier_id = $1 AND status = 'ACCRUED'`,
            [supplierId]
          );
        }

        await client.query('COMMIT');
        return sendJson(res, 200, {
          success: true,
          message: `Net Payroll of ₱${totalPayout.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} disbursed to ${employeeName} for ${payDate}.`
        });
      } catch (err) {
        await client.query('ROLLBACK');
        return sendJson(res, 500, { error: err.message });
      } finally {
        client.release();
      }
    }

    // 7. Operating Expenses List & Create
    if (pathname === '/api/expenses' && method === 'GET') {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '1', 10);
      const client = await pool.connect();
      try {
        const expRes = await client.query(
          `SELECT * FROM erp.operating_expenses WHERE supplier_id = $1 ORDER BY billing_period_start DESC LIMIT 50`,
          [supplierId]
        );
        return sendJson(res, 200, expRes.rows);
      } finally {
        client.release();
      }
    }

    if (pathname === '/api/expenses' && method === 'POST') {
      const body = await getRequestBody(req);
      const client = await pool.connect();
      try {
        const ins = await client.query(
          `INSERT INTO erp.operating_expenses 
           (supplier_id, category, vendor_name, title, amount, billing_period_start, billing_period_end, payment_status, payment_method, reference_number, notes)
           VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
           RETURNING *`,
          [
            body.supplier_id || 1,
            body.category || 'MISC_OPEX',
            body.vendor_name || 'Vendor',
            body.title,
            parseFloat(body.amount),
            body.billing_period_start,
            body.billing_period_end,
            body.payment_status || 'PAID',
            body.payment_method || 'BANK_TRANSFER',
            body.reference_number || '',
            body.notes || ''
          ]
        );
        return sendJson(res, 201, { success: true, expense: ins.rows[0] });
      } finally {
        client.release();
      }
    }

    // 8. Profitability Waterfall (Supports period=daily|weekly|monthly|quarterly)
    if (pathname === '/api/reports/waterfall' && method === 'GET') {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '1', 10);
      const rawPeriod = String(parsedUrl.query.period || 'daily').toLowerCase();
      const periodConfig = {
        daily:     { days: 1,   label: 'Daily',     opexFactor: 1 / 30 },
        weekly:    { days: 7,   label: 'Weekly',    opexFactor: 7 / 30 },
        monthly:   { days: 30,  label: 'Monthly',   opexFactor: 1.0 },
        quarterly: { days: 90,  label: 'Quarterly', opexFactor: 3.0 },
        yearly:    { days: 365, label: 'Yearly',    opexFactor: 12.0 }
      };
      const periodKey = periodConfig[rawPeriod] ? rawPeriod : 'daily';
      const cfg = periodConfig[periodKey];

      const client = await pool.connect();
      try {
        // Anchor to latest active POS trading date for this supplier (or CURRENT_DATE if none)
        const anchorRes = await client.query(
          `SELECT COALESCE(MAX(SUBSTRING(payment_date, 1, 10)::date), CURRENT_DATE)::text AS anchor_date,
                  (COALESCE(MAX(SUBSTRING(payment_date, 1, 10)::date), CURRENT_DATE) - ($2::int - 1))::text AS start_date
           FROM public.tbl_payment 
           WHERE supplier_id = $1
             AND payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
             AND paid_amount > 0`,
          [supplierId, cfg.days]
        );
        const anchorDate = anchorRes.rows[0].anchor_date;
        const startDate = anchorRes.rows[0].start_date;

        const salesRes = await client.query(
          `SELECT COUNT(DISTINCT p.id) as total_tx_count, COALESCE(SUM(p.paid_amount), 0) as gross_revenue
           FROM public.tbl_payment p
           WHERE p.supplier_id = $1
             AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
             AND SUBSTRING(p.payment_date, 1, 10)::date BETWEEN $2::date AND $3::date`,
          [supplierId, startDate, anchorDate]
        );
        const feesRes = await client.query(
          `SELECT COALESCE(SUM(f.calculated_fee), 0) as total_bank_fees
           FROM erp.payment_fee_transactions f
           JOIN public.tbl_payment p ON f.payment_id = p.id
           WHERE f.supplier_id = $1
             AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
             AND SUBSTRING(p.payment_date, 1, 10)::date BETWEEN $2::date AND $3::date`,
          [supplierId, startDate, anchorDate]
        );
        const incRes = await client.query(
          `SELECT COALESCE(SUM(s.incentive_amount), 0) as total_incentives
           FROM erp.sales_incentives s
           JOIN public.tbl_payment p ON s.payment_id = p.payment_id
           WHERE s.supplier_id = $1
             AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
             AND p.paid_amount > 0
             AND SUBSTRING(p.payment_date, 1, 10)::date BETWEEN $2::date AND $3::date`,
          [supplierId, startDate, anchorDate]
        );
        const opexRes = await client.query(
          `SELECT category, COALESCE(SUM(amount), 0) as total_amount
           FROM erp.operating_expenses WHERE supplier_id = $1 GROUP BY category`,
          [supplierId]
        );
        const cogsRes = await client.query(
          `SELECT COALESCE(SUM(
             CAST(NULLIF(o.quantity, '') AS NUMERIC) * 
             COALESCE(NULLIF(CAST(NULLIF(pr.p_capital_price, '') AS NUMERIC), 0), CAST(NULLIF(o.unit_price, '') AS NUMERIC) * 0.70)
           ), 0) as total_cogs
           FROM public.tbl_order o
           JOIN public.tbl_payment p ON o.payment_id = p.payment_id
           LEFT JOIN public.tbl_product pr ON o.product_id = pr.p_id
           WHERE p.supplier_id = $1
             AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
             AND p.paid_amount > 0
             AND SUBSTRING(p.payment_date, 1, 10)::date BETWEEN $2::date AND $3::date`,
          [supplierId, startDate, anchorDate]
        );

        const grossRevenue = parseFloat(salesRes.rows[0].gross_revenue || 0);
        const totalCogs = parseFloat(cogsRes.rows[0].total_cogs || 0);
        const grossMargin = grossRevenue - totalCogs;
        const bankFees = parseFloat(feesRes.rows[0].total_bank_fees || 0);
        const staffIncentives = parseFloat(incRes.rows[0].total_incentives || 0);
        const contributionMargin1 = grossMargin - (bankFees + staffIncentives);

        let totalOpex = 0;
        const opexBreakdown = {};
        for (const row of opexRes.rows) {
          const rawAmt = parseFloat(row.total_amount);
          const scaledAmt = Math.round(rawAmt * cfg.opexFactor * 100) / 100;
          opexBreakdown[row.category] = scaledAmt;
          totalOpex += scaledAmt;
        }

        const netOperatingProfit = contributionMargin1 - totalOpex;

        return sendJson(res, 200, {
          supplier_id: supplierId,
          period: periodKey,
          period_label: cfg.label,
          date_range: { start_date: startDate, end_date: anchorDate, days: cfg.days },
          metrics: {
            period: periodKey,
            period_label: cfg.label,
            start_date: startDate,
            end_date: anchorDate,
            transaction_count: parseInt(salesRes.rows[0].total_tx_count, 10),
            gross_revenue: grossRevenue,
            landed_cogs: totalCogs,
            gross_margin: grossMargin,
            gross_margin_percentage: grossRevenue > 0 ? ((grossMargin / grossRevenue) * 100).toFixed(2) + '%' : '0%',
            bank_processing_fees: bankFees,
            sales_staff_incentives: staffIncentives,
            contribution_margin_1: contributionMargin1,
            contribution_margin_1_percentage: grossRevenue > 0 ? ((contributionMargin1 / grossRevenue) * 100).toFixed(2) + '%' : '0%',
            total_operating_expenses: totalOpex,
            opex_breakdown: opexBreakdown,
            net_operating_profit: netOperatingProfit,
            net_profit_margin: grossRevenue > 0 ? ((netOperatingProfit / grossRevenue) * 100).toFixed(2) + '%' : '0%'
          }
        });
      } finally {
        client.release();
      }
    }

    // 9. Sync POS trigger
    if (pathname === '/api/sync/run' && method === 'POST') {
      const results = await runPosSyncWorker();
      return sendJson(res, 200, { success: true, results });
    }

    // 10. Tenant Admin Dashboard HTML
    if (pathname === '/' || pathname === '/index.html') {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      return res.end(getTenantDashboardHtml());
    }

    return sendJson(res, 404, { error: 'Not Found' });
  } catch (err) {
    console.error('[Server Error]:', err);
    return sendJson(res, 500, { error: err.message });
  }
};

const httpServer = http.createServer(requestHandler);

// SSL Certificate Detection: Production Let's Encrypt, or Local Development Fallback
const certCandidates = [
  {
    name: "Production (Let's Encrypt)",
    cert: process.env.SSL_CERT_PATH || '/etc/letsencrypt/live/econstruction-supply.site/fullchain.pem',
    key: process.env.SSL_KEY_PATH || '/etc/letsencrypt/live/econstruction-supply.site/privkey.pem'
  },
  {
    name: "Local Dev (Mounted /etc/letsencrypt/dev)",
    cert: '/etc/letsencrypt/dev/fullchain.pem',
    key: '/etc/letsencrypt/dev/privkey.pem'
  },
  {
    name: "Local Dev (Project Root ssl/dev)",
    cert: path.join(__dirname, '../ssl/dev/fullchain.pem'),
    key: path.join(__dirname, '../ssl/dev/privkey.pem')
  },
  {
    name: "Local Dev (ERP Subdir ssl/dev)",
    cert: path.join(__dirname, 'ssl/dev/fullchain.pem'),
    key: path.join(__dirname, 'ssl/dev/privkey.pem')
  }
];

let httpsServer = null;
for (const cand of certCandidates) {
  try {
    if (fs.existsSync(cand.cert) && fs.existsSync(cand.key)) {
      httpsServer = https.createServer({
        cert: fs.readFileSync(cand.cert),
        key: fs.readFileSync(cand.key)
      }, requestHandler);
      console.log(`[ERP SSL]: Loaded ${cand.name} certificates successfully from ${cand.cert}`);
      break;
    }
  } catch (sslErr) {
    console.warn(`[ERP SSL Warning]: Failed loading certs from ${cand.cert}:`, sslErr.message);
  }
}

// Dual HTTP/HTTPS Connection Multiplexer on port 4000
const tcpServer = net.createServer((socket) => {
  socket.once('data', (buffer) => {
    socket.pause();
    socket.unshift(buffer);
    // 0x16 (22) is TLS Handshake record type in SSLv3/TLS1.x
    if (buffer[0] === 22) {
      if (httpsServer) {
        httpsServer.emit('connection', socket);
      } else {
        // Plain HTTP only mode, but client attempted TLS handshake
        // Destroy socket cleanly to prevent HTTP parser from throwing "Invalid method encountered"
        socket.destroy();
        return;
      }
    } else {
      httpServer.emit('connection', socket);
    }
    process.nextTick(() => socket.resume());
  });

  socket.on('error', (err) => {
    if (err.code !== 'ECONNRESET' && err.code !== 'EPIPE') {
      console.warn('[ERP Socket Error]:', err.message);
    }
  });
});

// Full-featured Tenant Admin Dashboard (Matching POS AdminLTE Layout on Port 9090)
function getTenantDashboardHtml() {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Supplier Admin Panel (ERP) - eConstruction Supply</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background-color: #ecf0f5;
      font-family: "Roboto", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-size: 13px;
      color: #333;
      overflow-x: hidden;
    }

    /* AdminLTE Top Header (#131921) - Identical to Port 9090 */
    .main-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 50px;
      background-color: #131921;
      z-index: 1030;
      display: flex;
      align-items: center;
      box-shadow: 0 1px 4px rgba(0,0,0,0.25);
    }
    .main-header .logo {
      width: 230px;
      height: 50px;
      background-color: #131921;
      color: #fff;
      display: flex;
      align-items: center;
      padding: 0 15px;
      font-size: 20px;
      font-weight: 400;
      text-decoration: none;
      border-right: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
      transition: width 0.2s ease;
    }
    .main-header .logo:hover { color: #fff; background-color: #131921; }
    body.sidebar-collapse .main-header .logo {
      width: 50px;
      padding: 0;
      justify-content: center;
    }
    body.sidebar-collapse .main-header .logo .logo-lg { display: none; }
    .main-header .logo .logo-mini { display: none; font-weight: 700; font-size: 16px; color: #34d399; }
    body.sidebar-collapse .main-header .logo .logo-mini { display: inline; }

    .main-header .navbar-custom {
      flex: 1;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 15px 0 0;
    }
    .sidebar-toggle-top {
      color: #fff;
      padding: 15px;
      font-size: 16px;
      text-decoration: none;
      cursor: pointer;
    }
    .sidebar-toggle-top:hover { background-color: rgba(255,255,255,0.08); color: #fff; }
    .panel-title-top {
      color: #fff;
      font-size: 18px;
      line-height: 50px;
      padding-left: 5px;
      font-weight: 400;
    }

    /* Upper-Right POS | ERP Slider Switch */
    .mode-switch-pill {
      display: inline-flex;
      align-items: center;
      background: #0b0f17;
      border: 1px solid #334155;
      border-radius: 999px;
      padding: 3px;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
    }
    .mode-switch-pill a {
      padding: 4px 14px;
      font-size: 12px;
      font-weight: 800;
      border-radius: 999px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      line-height: 1.2;
      transition: all 0.2s ease;
    }
    .mode-switch-pill a.pos-inactive {
      color: #94a3b8;
      background: transparent;
    }
    .mode-switch-pill a.pos-inactive:hover {
      color: #ffffff;
    }
    .mode-switch-pill a.erp-active {
      color: #ffffff;
      background: linear-gradient(135deg, #059669, #047857);
      border: 1px solid #34d399;
      box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }

    /* AdminLTE Left Sidebar (#222d32) - Identical to Port 9090 */
    .main-sidebar {
      position: fixed;
      top: 50px;
      left: 0;
      bottom: 0;
      width: 230px;
      background-color: #222d32;
      z-index: 1020;
      overflow-y: auto;
      transition: width 0.2s ease;
    }
    body.sidebar-collapse .main-sidebar { width: 50px; overflow-x: hidden; }
    .sidebar-collapse-header {
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      background: rgba(0,0,0,0.15);
    }
    body.sidebar-collapse .sidebar-collapse-header .sidebar-menu-title { display: none !important; }
    body.sidebar-collapse .sidebar-collapse-header { justify-content: center !important; padding: 8px 0 !important; }

    .sidebar-menu {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .sidebar-menu > li.header {
      color: #4b646f;
      background: #1a2226;
      padding: 10px 25px 10px 15px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.6px;
      text-transform: uppercase;
    }
    body.sidebar-collapse .sidebar-menu > li.header { display: none; }
    .sidebar-menu > li > a {
      display: flex;
      align-items: center;
      padding: 12px 15px;
      color: #b8c7ce;
      text-decoration: none;
      font-size: 13px;
      border-left: 3px solid transparent;
      cursor: pointer;
      transition: background 0.15s ease, color 0.15s ease;
    }
    .sidebar-menu > li > a > i.fa {
      width: 22px;
      font-size: 14px;
      margin-right: 6px;
      text-align: center;
      flex-shrink: 0;
    }
    .sidebar-menu > li:hover > a,
    .sidebar-menu > li.active > a {
      color: #ffffff;
      background-color: #4c4c4c;
      border-left-color: #10b981;
    }
    body.sidebar-collapse .sidebar-menu > li > a > span { display: none; }

    /* Content Wrapper */
    .content-wrapper {
      margin-left: 230px;
      padding-top: 50px;
      min-height: 100vh;
      background-color: #ecf0f5;
      transition: margin-left 0.2s ease;
    }
    body.sidebar-collapse .content-wrapper { margin-left: 50px; }

    .content-header-bar {
      padding: 15px 20px 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      border-bottom: 1px solid #d2d6de;
      background: #fff;
    }
    .content-header-bar h1 {
      margin: 0;
      font-size: 21px;
      font-weight: 700;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .content-body { padding: 20px; }
    .card {
      border: 1px solid #e2e8f0;
      border-top: 3px solid #4c4c4c;
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      margin-bottom: 20px;
      background: #fff;
    }
    .waterfall-row {
      display: flex;
      justify-content: space-between;
      padding: 11px 15px;
      border-bottom: 1px solid #f1f5f9;
      font-size: 13.5px;
    }
    .waterfall-row.highlight {
      background-color: #f8fafc;
      font-weight: 700;
      border-left: 4px solid #0284c7;
    }
    .negative { color: #dc3545; }
    .positive { color: #16a34a; }

    /* KPI Info Boxes matching AdminLTE */
    .kpi-box {
      background: #fff;
      border-radius: 6px;
      border-left: 4px solid #0284c7;
      padding: 14px 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      margin-bottom: 18px;
    }
    .kpi-box .kpi-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      color: #64748b;
      letter-spacing: 0.4px;
    }
    .kpi-box .kpi-val {
      font-size: 22px;
      font-weight: 800;
      margin-top: 4px;
      color: #0f172a;
    }

    /* Warehouse Stock Paired Column Groups */
    .wh-col-group-a {
      background-color: #e6e0d1 !important;
    }
    .wh-col-group-b {
      background-color: #c2d0ed !important;
    }
    .wh-col-group-c {
      background-color: #d1cfcd !important;
    }
    .wh-col-group-nq {
      background-color: #d1e6d6 !important;
    }
  </style>
</head>
<body>

  <!-- TOP HEADER BAR (#131921) - IDENTICAL TO PORT 9090 -->
  <header class="main-header">
    <a id="logoReturnLink" href="http://localhost:9090/supplier/index.php" class="logo" title="eConstruct Hub">
      <span class="logo-mini">eCH</span>
      <span class="logo-lg">eConstruct Hub</span>
    </a>

    <nav class="navbar-custom">
      <div class="d-flex align-items-center">
        <a class="sidebar-toggle-top" onclick="toggleSidebarCollapse()" title="Toggle navigation">
          <i class="fa fa-bars"></i>
        </a>
        <span class="panel-title-top">
          <i class="fa fa-shield" style="color: #38bdf8; margin-right: 4px;"></i> Supplier Admin Panel
          <span class="badge" style="background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.4); font-size: 11px; margin-left: 6px; vertical-align: middle;">ERP Financial &amp; Inventory Hub</span>
        </span>
      </div>

      <!-- Right Navbar Controls: Active Store Badge + Sync + POS | ERP Slider Switch + Admin Profile -->
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
          <span id="activeTenantBadge" class="badge bg-dark border border-secondary text-white d-none d-sm-inline-flex align-items-center gap-1 px-2 py-1" style="font-size: 11.5px; font-weight: 600;">
            <i class="fa fa-building-o text-info"></i>
            <span id="activeTenantName">Active Store</span>
          </span>
          <button id="btnSync" class="btn btn-outline-light btn-sm" style="font-size: 12px; padding: 3px 10px;" onclick="triggerSync()">
            <i class="fa fa-refresh"></i> Sync POS
          </button>
        </div>

        <!-- UPPER-RIGHT SLIDER SWITCH (POS | ERP) - FOR TENANT/SUPPLIER ADMIN ONLY -->
        <div class="mode-switch-pill" title="Switch between POS (localhost:9090) and ERP (localhost:4000)">
          <a id="posReturnLink" href="http://localhost:9090/supplier/index.php" class="pos-inactive">
            <i class="fa fa-calculator" style="color: #60a5fa;"></i> POS
          </a>
          <a href="#erp" class="erp-active">
            <i class="fa fa-line-chart"></i> ERP
          </a>
        </div>

        <!-- Admin User Profile Display -->
        <div class="d-flex align-items-center text-white" style="font-size: 13px; padding-left: 4px;">
          <i class="fa fa-user" style="margin-right: 6px;"></i>
          <span id="adminUserName" class="d-none d-md-inline">Supplier Admin</span>
          <span id="adminUserRole" class="badge" style="background-color: #0284c7; font-size: 10px; margin-left: 6px;">Store Admin</span>
        </div>
      </div>
    </nav>
  </header>

  <!-- LEFT SIDEBAR (#222d32) - IDENTICAL ADMINLTE DESIGN TO PORT 9090 -->
  <aside class="main-sidebar">
    <div class="sidebar-collapse-header">
      <span class="sidebar-menu-title" style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">ERP Navigation</span>
      <a href="javascript:void(0)" onclick="toggleSidebarCollapse()" title="Collapse / Expand Sidebar" style="color: #94a3b8; padding: 2px 6px; border-radius: 4px;">
        <i class="fa fa-angle-double-left" id="sidebarCollapseIcon" style="font-size: 14px;"></i>
      </a>
    </div>

    <ul class="sidebar-menu" id="mainTabs" role="tablist">
      <li class="header">FINANCIALS &amp; OPEX (ERP)</li>
      <li class="active" id="nav-li-pnl">
        <a id="tab-pnl-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-pnl', 'tab-pnl', 'P&L Waterfall')">
          <i class="fa fa-line-chart" style="color: #34d399;"></i> <span>P&amp;L Waterfall</span>
        </a>
      </li>
      <li id="nav-li-opex">
        <a id="tab-opex-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-opex', 'tab-opex', 'Store Bills & OPEX Tracker')">
          <i class="fa fa-bolt" style="color: #fbbf24;"></i> <span>Store Bills &amp; OPEX Tracker</span>
        </a>
      </li>
      <li id="nav-li-payroll">
        <a id="tab-payroll-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-payroll', 'tab-payroll', 'Staff Payroll & Commission')">
          <i class="fa fa-users" style="color: #60a5fa;"></i> <span>Staff Payroll &amp; Commission</span>
        </a>
      </li>
      <li id="nav-li-ledger">
        <a id="tab-ledger-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-ledger', 'tab-ledger', 'General Ledger (Double-Entry)')">
          <i class="fa fa-book" style="color: #a78bfa;"></i> <span>General Ledger</span>
        </a>
      </li>

      <li class="header">INVENTORY &amp; PROCUREMENT</li>
      <li id="nav-li-catalog">
        <a id="tab-catalog-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-catalog', 'tab-catalog', 'Catalog & Available Stock')">
          <i class="fa fa-cubes" style="color: #38bdf8;"></i> <span>Catalog &amp; Available Stock</span>
        </a>
      </li>

      <li class="header">WAREHOUSE</li>
      <li id="nav-li-warehouse">
        <a id="tab-warehouse-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-warehouse', 'tab-warehouse', 'Warehouse Stock')">
          <i class="fa fa-building-o" style="color: #fb923c;"></i> <span>Warehouse Stock</span>
        </a>
      </li>
      <li id="nav-li-materials">
        <a id="tab-materials-link" href="javascript:void(0)" onclick="activateSidebarItem('nav-li-materials', 'tab-materials', 'Receive Materials (GRN)')">
          <i class="fa fa-truck" style="color: #f472b6;"></i> <span>Receive Materials (GRN)</span>
        </a>
      </li>

      <li class="header">POS TERMINAL SWITCH</li>
      <li>
        <a id="sidebarPosLink" href="http://localhost:9090/supplier/index.php">
          <i class="fa fa-calculator" style="color: #60a5fa;"></i> <span style="color: #93c5fd; font-weight: 700;">Switch to POS Admin</span>
        </a>
      </li>
    </ul>
  </aside>

  <!-- MAIN CONTENT AREA -->
  <div class="content-wrapper">
    <div class="content-header-bar">
      <h1>
        <i class="fa fa-arrow-circle-o-right" style="color: #10b981;"></i>
        <span id="pageHeaderTitle">P&amp;L Waterfall</span>
      </h1>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success" style="padding: 6px 12px; font-size: 11px;">
          <i class="fa fa-check-circle"></i> Live PostgreSQL Sync Active (Port 4000)
        </span>
      </div>
    </div>

    <div class="content-body">
      <div class="tab-content" id="mainTabsContent">

        <!-- TAB 1: P&L WATERFALL -->
        <div class="tab-pane fade show active" id="tab-pnl">
          <!-- Top KPI Strip (Financial Waterfall Only) -->
          <div class="row">
            <div class="col-md-3 col-sm-6">
              <div class="kpi-box" style="border-left-color: #10b981;">
                <div class="kpi-label">Gross POS Revenue (<span class="kpiPeriodLabel">Daily</span>)</div>
                <div class="kpi-val text-success" id="kpiGross">₱0.00</div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6">
              <div class="kpi-box" style="border-left-color: #f59e0b;">
                <div class="kpi-label">True Landed COGS (<span class="kpiPeriodLabel">Daily</span>)</div>
                <div class="kpi-val text-danger" id="kpiCogs">-₱0.00</div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6">
              <div class="kpi-box" style="border-left-color: #6366f1;">
                <div class="kpi-label">Store OPEX &amp; Bills (<span class="kpiPeriodLabel">Daily</span>)</div>
                <div class="kpi-val text-danger" id="kpiOpex">-₱0.00</div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6">
              <div class="kpi-box" style="border-left-color: #0284c7;">
                <div class="kpi-label">Net Operating Profit (<span class="kpiPeriodLabel">Daily</span>)</div>
                <div class="kpi-val" id="kpiNet">₱0.00</div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-7">
              <div class="card p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                  <div>
                    <h5 class="mb-1 fw-bold"><i class="bi bi-cash-stack text-success"></i> P&amp;L Waterfall</h5>
                    <p class="text-muted small mb-1">Live reconciliation connecting POS orders on Port 9090 against landed inventory costs and store overhead.</p>
                    <span id="wfPeriodSummary" class="badge bg-light text-secondary border" style="font-size: 11px;">Period: Daily</span>
                  </div>

                  <!-- Daily / Weekly / Monthly / Quarterly / Yearly Period Selector -->
                  <div class="btn-group btn-group-sm" role="group" aria-label="P&L Waterfall Period">
                    <button type="button" id="btnPeriod-daily" class="btn btn-primary fw-bold" onclick="setWaterfallPeriod('daily')">Daily</button>
                    <button type="button" id="btnPeriod-weekly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('weekly')">Weekly</button>
                    <button type="button" id="btnPeriod-monthly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('monthly')">Monthly</button>
                    <button type="button" id="btnPeriod-quarterly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('quarterly')">Quarterly</button>
                    <button type="button" id="btnPeriod-yearly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('yearly')">Yearly</button>
                  </div>
                </div>

                <div class="border rounded mt-2">
                  <div class="waterfall-row highlight">
                    <span>Gross POS Revenue (<span class="kpiPeriodLabel">Daily</span>)</span>
                    <span id="wfGross" class="positive">₱0.00</span>
                  </div>
                  <div class="waterfall-row negative">
                    <span><i class="bi bi-dash"></i> True Landed Product Cost (COGS)</span>
                    <span id="wfCogs">-₱0.00</span>
                  </div>
                  <div class="waterfall-row highlight bg-light">
                    <span>Gross Margin (Margin 1)</span>
                    <span id="wfGrossMargin">₱0.00 (<span id="wfGrossMarginPct">0%</span>)</span>
                  </div>
                  <div class="waterfall-row negative">
                    <span><i class="bi bi-dash"></i> Credit Card &amp; E-Wallet Processing Fees</span>
                    <span id="wfFees">-₱0.00</span>
                  </div>
                  <div class="waterfall-row negative">
                    <span><i class="bi bi-dash"></i> Cashier Sales Commission (1%)</span>
                    <span id="wfIncentives">-₱0.00</span>
                  </div>
                  <div class="waterfall-row highlight bg-light">
                    <span>Contribution Margin 1</span>
                    <span id="wfCm1">₱0.00 (<span id="wfCm1Pct">0%</span>)</span>
                  </div>
                  <div class="waterfall-row negative">
                    <span><i class="bi bi-dash"></i> Operating Expenses (<span class="kpiPeriodLabel">Daily</span> Amortized Rent, Power, Wages)</span>
                    <span id="wfOpex">-₱0.00</span>
                  </div>
                  <div class="waterfall-row highlight" style="font-size: 1.15rem; background: #eff6ff;">
                    <span>Net Operating Profit (<span class="kpiPeriodLabel">Daily</span>)</span>
                    <span id="wfNetProfit">₱0.00 (<span id="wfNetProfitPct">0%</span>)</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="card p-4">
                <h5 class="fw-bold">Store Overhead Summary (<span class="kpiPeriodLabel">Daily</span>)</h5>
                <p class="text-muted small">Amortized breakdown of recorded store bills absorbed during the selected <strong class="kpiPeriodLabel">Daily</strong> period.</p>
                <div id="opexSummaryList" class="list-group list-group-flush small">
                  <div class="text-muted text-center py-3">Loading summary...</div>
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION 2: VISUAL WATERFALL BAR CHART & BREAK-EVEN ANALYTICS -->
          <div class="row mt-4">
            <!-- Stepped Waterfall Bar Chart Card -->
            <div class="col-12 mb-4">
              <div class="card p-4 shadow-sm border">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <div>
                    <h5 class="fw-bold mb-1"><i class="fa fa-bar-chart text-success"></i> Visual Stepped Waterfall Bar Chart (<span class="kpiPeriodLabel">Daily</span>)</h5>
                    <p class="text-muted small mb-0">Step-by-step financial bridge tracking inflows, cost deductions, and net margins across the selected period.</p>
                  </div>
                  <div class="d-flex align-items-center gap-3 small fw-bold">
                    <span class="d-flex align-items-center gap-1"><span style="width: 12px; height: 12px; background: #10b981; display: inline-block; border-radius: 2px;"></span> Revenue</span>
                    <span class="d-flex align-items-center gap-1"><span style="width: 12px; height: 12px; background: #ef4444; display: inline-block; border-radius: 2px;"></span> Deductions (COGS/OPEX)</span>
                    <span class="d-flex align-items-center gap-1"><span style="width: 12px; height: 12px; background: #3b82f6; display: inline-block; border-radius: 2px;"></span> Margin Pillars</span>
                  </div>
                </div>

                <div class="border rounded p-3 bg-light overflow-auto">
                  <svg id="waterfallSvg" style="min-width: 760px; width: 100%; height: 320px;" viewBox="0 0 960 320">
                    <!-- Rendered dynamically by JavaScript -->
                  </svg>
                </div>
              </div>
            </div>

            <!-- Periodic Scale Comparison Bar Chart -->
            <div class="col-lg-6 mb-4">
              <div class="card p-4 shadow-sm border h-100">
                <h5 class="fw-bold mb-1"><i class="fa fa-columns text-primary"></i> Periodic Scale Comparison (Bars)</h5>
                <p class="text-muted small mb-3">Gross POS Revenue vs True Landed COGS vs Store OPEX across periods.</p>

                <div class="d-flex align-items-end justify-content-around pt-4 pb-2 border-bottom" style="height: 220px;">
                  <!-- Daily -->
                  <div class="text-center flex-fill">
                    <div class="small fw-bold text-muted mb-1">Daily</div>
                    <div class="d-flex align-items-end justify-content-center gap-1" style="height: 140px;">
                      <div id="barScaleDailyRev" style="width: 14px; height: 20%; background: #10b981; border-radius: 3px 3px 0 0;" title="Revenue"></div>
                      <div id="barScaleDailyCogs" style="width: 14px; height: 23%; background: #ef4444; border-radius: 3px 3px 0 0;" title="COGS"></div>
                      <div id="barScaleDailyOpex" style="width: 14px; height: 8%; background: #a855f7; border-radius: 3px 3px 0 0;" title="OPEX"></div>
                    </div>
                    <div id="lblScaleDaily" class="small fw-bold text-dark mt-2" style="font-size: 11px;">--</div>
                  </div>

                  <!-- Weekly -->
                  <div class="text-center flex-fill">
                    <div class="small fw-bold text-muted mb-1">Weekly</div>
                    <div class="d-flex align-items-end justify-content-center gap-1" style="height: 140px;">
                      <div id="barScaleWeeklyRev" style="width: 14px; height: 35%; background: #10b981; border-radius: 3px 3px 0 0;" title="Revenue"></div>
                      <div id="barScaleWeeklyCogs" style="width: 14px; height: 40%; background: #ef4444; border-radius: 3px 3px 0 0;" title="COGS"></div>
                      <div id="barScaleWeeklyOpex" style="width: 14px; height: 15%; background: #a855f7; border-radius: 3px 3px 0 0;" title="OPEX"></div>
                    </div>
                    <div id="lblScaleWeekly" class="small fw-bold text-dark mt-2" style="font-size: 11px;">--</div>
                  </div>

                  <!-- Monthly -->
                  <div class="text-center flex-fill">
                    <div class="small fw-bold text-muted mb-1">Monthly</div>
                    <div class="d-flex align-items-end justify-content-center gap-1" style="height: 140px;">
                      <div id="barScaleMonthlyRev" style="width: 14px; height: 55%; background: #10b981; border-radius: 3px 3px 0 0;" title="Revenue"></div>
                      <div id="barScaleMonthlyCogs" style="width: 14px; height: 63%; background: #ef4444; border-radius: 3px 3px 0 0;" title="COGS"></div>
                      <div id="barScaleMonthlyOpex" style="width: 14px; height: 25%; background: #a855f7; border-radius: 3px 3px 0 0;" title="OPEX"></div>
                    </div>
                    <div id="lblScaleMonthly" class="small fw-bold text-dark mt-2" style="font-size: 11px;">--</div>
                  </div>

                  <!-- Quarterly -->
                  <div class="text-center flex-fill">
                    <div class="small fw-bold text-muted mb-1">Quarterly</div>
                    <div class="d-flex align-items-end justify-content-center gap-1" style="height: 140px;">
                      <div id="barScaleQuarterlyRev" style="width: 14px; height: 75%; background: #10b981; border-radius: 3px 3px 0 0;" title="Revenue"></div>
                      <div id="barScaleQuarterlyCogs" style="width: 14px; height: 86%; background: #ef4444; border-radius: 3px 3px 0 0;" title="COGS"></div>
                      <div id="barScaleQuarterlyOpex" style="width: 14px; height: 35%; background: #a855f7; border-radius: 3px 3px 0 0;" title="OPEX"></div>
                    </div>
                    <div id="lblScaleQuarterly" class="small fw-bold text-dark mt-2" style="font-size: 11px;">--</div>
                  </div>

                  <!-- Yearly -->
                  <div class="text-center flex-fill">
                    <div class="small fw-bold text-muted mb-1">Yearly</div>
                    <div class="d-flex align-items-end justify-content-center gap-1" style="height: 140px;">
                      <div id="barScaleYearlyRev" style="width: 14px; height: 95%; background: #10b981; border-radius: 3px 3px 0 0;" title="Revenue"></div>
                      <div id="barScaleYearlyCogs" style="width: 14px; height: 100%; background: #ef4444; border-radius: 3px 3px 0 0;" title="COGS"></div>
                      <div id="barScaleYearlyOpex" style="width: 14px; height: 45%; background: #a855f7; border-radius: 3px 3px 0 0;" title="OPEX"></div>
                    </div>
                    <div id="lblScaleYearly" class="small fw-bold text-dark mt-2" style="font-size: 11px;">--</div>
                  </div>
                </div>

                <div class="d-flex justify-content-center gap-4 mt-2 small text-muted">
                  <span><span style="display:inline-block; width:10px; height:10px; background:#10b981;"></span> Revenue</span>
                  <span><span style="display:inline-block; width:10px; height:10px; background:#ef4444;"></span> COGS</span>
                  <span><span style="display:inline-block; width:10px; height:10px; background:#a855f7;"></span> Store OPEX</span>
                </div>
              </div>
            </div>

            <!-- Predictive Break-Even Simulator Card -->
            <div class="col-lg-6 mb-4">
              <div class="card p-4 shadow-sm border h-100" style="background: linear-gradient(145deg, #ffffff 0%, #f0f7ff 100%);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h5 class="fw-bold mb-0"><i class="fa fa-calculator text-primary"></i> Predictive Break-Even Simulator</h5>
                  <span class="badge bg-primary">Forecasting</span>
                </div>
                <p class="text-muted small mb-3">Model required sales and order frequency to cover operating expenses and achieve profitability.</p>

                <div class="mb-3">
                  <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>Average Target Markup over Landed COGS:</span>
                    <span id="erpSimMarkupVal" class="text-primary font-monospace">25.0%</span>
                  </div>
                  <input type="range" id="erpSimMarkupInput" min="5" max="60" step="1" value="25" class="form-range" oninput="runErpBreakEvenSimulation()">
                  <div class="d-flex justify-content-between text-muted" style="font-size: 10px;">
                    <span>5% (Commodity)</span>
                    <span>25% (Standard)</span>
                    <span>60% (High-Margin)</span>
                  </div>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>Monthly Store OPEX (Rent, Power, Wages):</span>
                    <span id="erpSimOpexVal" class="text-primary font-monospace">₱20,000.00</span>
                  </div>
                  <input type="range" id="erpSimOpexInput" min="5000" max="60000" step="1000" value="20000" class="form-range" oninput="runErpBreakEvenSimulation()">
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>Average Order Basket Value:</span>
                    <span id="erpSimBasketVal" class="text-success font-monospace">₱1,200.00</span>
                  </div>
                  <input type="range" id="erpSimBasketInput" min="300" max="5000" step="100" value="1200" class="form-range" oninput="runErpBreakEvenSimulation()">
                </div>

                <div class="p-3 bg-white border rounded shadow-sm">
                  <div class="small fw-bold text-muted text-uppercase mb-2">Required Target to Break-Even</div>
                  <div class="row text-center g-2">
                    <div class="col-6">
                      <div class="p-2 bg-light rounded border">
                        <div class="text-muted" style="font-size: 11px;">Monthly Break-Even Sales</div>
                        <div id="erpSimMonthlyBe" class="fw-bold text-primary" style="font-size: 16px;">₱100,000.00</div>
                        <div id="erpSimDailyBe" class="text-muted" style="font-size: 10px;">₱3,333.33 / day</div>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="p-2 bg-light rounded border">
                        <div class="text-muted" style="font-size: 11px;">Orders Needed per Day</div>
                        <div id="erpSimDailyOrders" class="fw-bold text-success" style="font-size: 16px;">3 Orders / Day</div>
                        <div class="text-muted" style="font-size: 10px;">at avg basket ticket</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actionable Playbook: 4 Strategies -->
            <div class="col-12 mb-3">
              <div class="card p-4 shadow-sm border">
                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                  <i class="fa fa-line-chart text-success fa-lg"></i>
                  <div>
                    <h5 class="fw-bold mb-0">Actionable Growth Playbook: How to Reverse Margin Losses &amp; Increase Sales</h5>
                    <p class="text-muted small mb-0">Proven retail principles designed specifically for construction and hardware suppliers.</p>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-light h-100">
                      <div class="badge bg-primary mb-2">1. Price Floor Guard</div>
                      <h6 class="fw-bold mb-1" style="font-size: 13px;">Enforce POS Minimum Markup</h6>
                      <p class="text-muted small mb-0">Never allow catalog items to be rung up below landed capital cost. Prevents accidental negative gross margins (-₱533 daily).</p>
                    </div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-light h-100">
                      <div class="badge bg-success mb-2">2. Margin Bundling</div>
                      <h6 class="fw-bold mb-1" style="font-size: 13px;">Bundle Low-Margin Staples</h6>
                      <p class="text-muted small mb-0">Pair thin-margin cement and rebar (5-8%) with high-margin sundries (tie wires, waterproofing, trowels at 40-50% margin).</p>
                    </div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-light h-100">
                      <div class="badge bg-warning text-dark mb-2">3. Credit Terms Fee</div>
                      <h6 class="fw-bold mb-1" style="font-size: 13px;">Automated 2.5% - 5.0% Terms Markup</h6>
                      <p class="text-muted small mb-0">Standardize carrying markup on 15/30-day contractor credit orders to eliminate working capital drag and finance receivables.</p>
                    </div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-light h-100">
                      <div class="badge bg-info text-dark mb-2">4. Contractor Accounts</div>
                      <h6 class="fw-bold mb-1" style="font-size: 13px;">3 Active Contractor Retainers</h6>
                      <p class="text-muted small mb-0">Securing 3 sub-contractors spending ₱15k weekly guarantees ₱180k monthly sales, comfortably surpassing the ₱100k break-even line.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: RECEIVE INWARD MATERIALS (GRN) -->
        <div class="tab-pane fade" id="tab-materials">
          <div class="row">
            <div class="col-lg-6">
              <div class="card p-4">
                <h5 class="mb-1 fw-bold"><i class="bi bi-box-arrow-in-down text-primary"></i> Receive Incoming Delivery (GRN)</h5>
                <p class="text-muted small">Increments active POS register stock and recalculates true landed unit cost.</p>
                
                <form id="grnForm" onsubmit="submitGrn(event)">
                  <div class="row g-2 mb-2">
                    <div class="col-6">
                      <label class="form-label small fw-bold">Supplier / Vendor</label>
                      <input type="text" id="grnVendor" class="form-control form-control-sm" placeholder="e.g. Holcim Cement Corp" required>
                    </div>
                    <div class="col-6">
                      <label class="form-label small fw-bold">Delivery Receipt (DR) #</label>
                      <input type="text" id="grnRef" class="form-control form-control-sm" placeholder="e.g. DR-98412" required>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="form-label small fw-bold">Select Product to Replenish</label>
                    <select id="grnProduct" class="form-select form-select-sm" required onchange="updateProductCurrentStock()">
                      <option value="">Select Catalog Item...</option>
                    </select>
                    <small class="text-muted" id="currentStockNote">Current Stock: --</small>
                  </div>

                  <div class="row g-2 mb-2">
                    <div class="col-6">
                      <label class="form-label small fw-bold">Quantity Received</label>
                      <input type="number" id="grnQty" class="form-control form-control-sm" min="1" placeholder="e.g. 100" required>
                    </div>
                    <div class="col-6">
                      <label class="form-label small fw-bold">Invoice Price per Unit (₱)</label>
                      <input type="number" id="grnPrice" class="form-control form-control-sm" step="0.01" placeholder="e.g. 210.00" required>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label small fw-bold">Inbound Trucking / Freight Fee (₱)</label>
                    <input type="number" id="grnFreight" class="form-control form-control-sm" step="0.01" value="0.00">
                    <small class="text-muted">Pro-rated into unit landed cost so POS COGS is accurate.</small>
                  </div>

                  <button type="submit" id="btnSubmitGrn" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-check-circle"></i> Confirm Receipt &amp; Update POS Stock
                  </button>
                </form>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="card p-4">
                <h5 class="fw-bold">Recent Materials Received</h5>
                <p class="text-muted small">Log of delivered stock and warehouse replenishment.</p>
                <div class="table-responsive">
                  <table class="table table-sm table-hover small">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>DR #</th>
                        <th>Items</th>
                        <th>Landed Cost</th>
                      </tr>
                    </thead>
                    <tbody id="grnHistoryTable">
                      <tr><td colspan="5" class="text-center text-muted">Loading history...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 3: POWER BILL, RENT & OPEX -->
        <div class="tab-pane fade" id="tab-opex">
          <div class="card p-4">
            <!-- Header Bar with Aligned Expense Category Quick-Log Buttons -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom">
              <div>
                <h5 class="mb-0 fw-bold"><i class="bi bi-receipt text-warning"></i> Store Operating Expenses &amp; Bills</h5>
                <p class="text-muted small mb-0">Select an expense category button to log a store bill or amortize operating costs into P&amp;L.</p>
              </div>
              <!-- Expense Category Buttons Aligned with Store Operating Expenses & Bills -->
              <div class="d-flex align-items-center flex-wrap gap-1" role="toolbar" aria-label="Expense Category Quick Log Buttons">
                <span class="text-muted small fw-bold me-1 d-none d-xl-inline" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px;">
                  <i class="fa fa-plus-circle text-primary"></i> Log Bill:
                </span>
                <button type="button" class="btn btn-sm fw-bold" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; font-size: 12px;" onclick="openExpenseModal('ELECTRICITY')" title="Log Electricity / Power Bill">
                  <i class="fa fa-bolt"></i> Electricity
                </button>
                <button type="button" class="btn btn-sm fw-bold" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 12px;" onclick="openExpenseModal('RENT')" title="Log Store &amp; Warehouse Rent">
                  <i class="fa fa-building-o"></i> Rent
                </button>
                <button type="button" class="btn btn-sm fw-bold" style="background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; font-size: 12px;" onclick="openExpenseModal('WATER')" title="Log Water Utility Bill">
                  <i class="fa fa-tint"></i> Water
                </button>
                <button type="button" class="btn btn-sm fw-bold" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; font-size: 12px;" onclick="openExpenseModal('DELIVERY_LOGISTICS')" title="Log Delivery &amp; Courier Logistics">
                  <i class="fa fa-truck"></i> Logistics
                </button>
                <button type="button" class="btn btn-sm fw-bold" style="background: #f3e8ff; color: #6d28d9; border: 1px solid #ddd6fe; font-size: 12px;" onclick="openExpenseModal('MAINTENANCE')" title="Log Store Repairs &amp; Maintenance">
                  <i class="fa fa-wrench"></i> Maintenance
                </button>
                <button type="button" class="btn btn-sm fw-bold" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-size: 12px;" onclick="openExpenseModal('MISC_OPEX')" title="Log Miscellaneous Store Expense">
                  <i class="fa fa-cube"></i> Misc OPEX
                </button>
              </div>
            </div>

            <!-- Interactive Category Filter & Live Totals Nav Bar -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 rounded border" style="background: #f8fafc;">
              <div class="d-flex align-items-center flex-wrap gap-1" id="opexCategoryFilterBar">
                <button type="button" class="btn btn-dark btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="ALL" onclick="filterExpensesByCategory('ALL')">
                  All Categories <span class="badge bg-secondary ms-1" id="opexCount-ALL">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="ELECTRICITY" onclick="filterExpensesByCategory('ELECTRICITY')">
                  <i class="fa fa-bolt text-warning"></i> Electricity <span class="badge bg-light text-dark border ms-1" id="opexCount-ELECTRICITY">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="RENT" onclick="filterExpensesByCategory('RENT')">
                  <i class="fa fa-building-o text-primary"></i> Rent <span class="badge bg-light text-dark border ms-1" id="opexCount-RENT">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="WATER" onclick="filterExpensesByCategory('WATER')">
                  <i class="fa fa-tint text-info"></i> Water <span class="badge bg-light text-dark border ms-1" id="opexCount-WATER">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="DELIVERY_LOGISTICS" onclick="filterExpensesByCategory('DELIVERY_LOGISTICS')">
                  <i class="fa fa-truck text-success"></i> Logistics <span class="badge bg-light text-dark border ms-1" id="opexCount-DELIVERY_LOGISTICS">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="MAINTENANCE" onclick="filterExpensesByCategory('MAINTENANCE')">
                  <i class="fa fa-wrench" style="color:#6d28d9;"></i> Maintenance <span class="badge bg-light text-dark border ms-1" id="opexCount-MAINTENANCE">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="MISC_OPEX" onclick="filterExpensesByCategory('MISC_OPEX')">
                  <i class="fa fa-cube text-secondary"></i> Misc <span class="badge bg-light text-dark border ms-1" id="opexCount-MISC_OPEX">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fw-bold" style="font-size: 11.5px;" data-cat="PAYROLL" onclick="filterExpensesByCategory('PAYROLL')">
                  <i class="fa fa-users text-success"></i> Payroll <span class="badge bg-light text-dark border ms-1" id="opexCount-PAYROLL">0</span>
                </button>
              </div>
              <div class="text-end small">
                <span class="text-muted">Filtered Total:</span>
                <strong class="text-danger ms-1" style="font-size: 13.5px;" id="opexFilteredTotal">₱0.00</strong>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-hover small align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Category</th>
                    <th>Title / Payee</th>
                    <th>Billing Period</th>
                    <th>Daily Burn</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="expenseTable">
                  <tr><td colspan="6" class="text-center text-muted">Loading expenses...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 4: STAFF PAYROLL & COMMISSION -->
        <div class="tab-pane fade" id="tab-payroll">
          <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-person-badge text-success"></i> Staff Payroll &amp; Commission</h5>
                <p class="text-muted small mb-0">Edit individual staff base salaries, salary deductibles (Tax %, SSS contribution, Advance payment), and bonus/commission amounts.</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 215px;" title="Pay Date for Disbursement">
                  <span class="input-group-text fw-bold bg-light">Pay Date</span>
                  <input type="date" id="payrollPayDate" class="form-control form-control-sm" onchange="syncStaffRowPayButtons()">
                </div>
                <button class="btn btn-primary btn-sm fw-bold" id="btnSaveAllStaff" onclick="saveAllStaffCompensation()">
                  <i class="fa fa-floppy-o"></i> Save All Salaries &amp; Deductions
                </button>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-9">
                <h6 class="fw-bold">Store Staff Compensation &amp; Bonus/Commission</h6>
                <div class="table-responsive">
                  <table class="table table-sm table-hover small align-middle">
                    <thead class="table-light">
                      <tr>
                        <th style="min-width: 145px;">Staff Member</th>
                        <th>Role</th>
                        <th style="min-width: 125px;">Base Salary (₱)</th>
                        <th style="min-width: 105px;">Tax (%)<br><small class="text-danger fw-normal" style="font-size:10px;">Deductible</small></th>
                        <th style="min-width: 118px;">SSS (₱)<br><small class="text-danger fw-normal" style="font-size:10px;">Contribution</small></th>
                        <th style="min-width: 125px;">Advance Pay (₱)<br><small class="text-danger fw-normal" style="font-size:10px;">Before Payday</small></th>
                        <th style="min-width: 125px;">Bonus / Comm (₱)</th>
                        <th>POS Comm.</th>
                        <th style="min-width: 115px;">Net Payout</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="staffTable">
                      <tr><td colspan="10" class="text-center text-muted">Loading staff...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="col-lg-3">
                <h6 class="fw-bold">Payroll &amp; Commission Summary</h6>
                <div class="border rounded p-3 bg-light">
                  <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span class="text-muted">Total Base Salaries:</span>
                    <strong id="sumBaseSalaries">₱0.00</strong>
                  </div>
                  <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span class="text-muted">Less: Tax Deductions:</span>
                    <strong class="text-danger" id="sumTaxDed">-₱0.00</strong>
                  </div>
                  <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span class="text-muted">Less: SSS Contributions:</span>
                    <strong class="text-danger" id="sumSssDed">-₱0.00</strong>
                  </div>
                  <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span class="text-muted">Less: Advance Payments:</span>
                    <strong class="text-danger" id="sumAdvanceDed">-₱0.00</strong>
                  </div>
                  <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span class="text-muted">Total Bonus / Commission:</span>
                    <strong class="text-primary" id="sumBonusComm">₱0.00</strong>
                  </div>
                  <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span class="text-muted">Accrued POS Commissions:</span>
                    <strong class="text-success" id="sumPosComm">₱0.00</strong>
                  </div>
                  <div class="d-flex justify-content-between py-2 border-bottom mb-2" style="font-size: 14px;">
                    <span class="fw-bold">Grand Net Payroll:</span>
                    <strong class="text-dark" id="sumGrandPayroll">₱0.00</strong>
                  </div>

                  <div class="small fw-bold text-secondary mb-1">Accrued POS Sales Breakdown:</div>
                  <div id="commissionBox" class="mb-2">
                    <p class="text-muted small">Loading accrued commissions...</p>
                  </div>
                  <button class="btn btn-success btn-sm w-100 mt-2 fw-bold" id="btnDisburseAllPayroll" onclick="disbursePayroll(this)">
                    <i class="bi bi-cash"></i> Disburse All Wages &amp; Settle Commissions
                  </button>
                </div>
              </div>
            </div>

            <!-- HORIZONTAL PAYROLL & COMMISSION PAYMENT CALENDAR (CURRENT DATE TO PREVIOUS) -->
            <hr class="my-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h6 class="mb-1 fw-bold">
                  <i class="fa fa-calendar-check-o text-primary me-1"></i>
                  Staff Salary &amp; Bonus/Commission Payment Calendar (Current Date to Previous)
                </h6>
                <p class="text-muted small mb-0">
                  Follows the <strong>current calendar month</strong> ordered from <strong>current date to previous</strong>. Click any cell to mark <strong>PAID</strong> or <strong>UNPAID</strong> (locked with spinner to prevent double payment).
                </p>
              </div>
              <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="input-group input-group-sm" style="width: 185px;" title="Calendar Month">
                  <span class="input-group-text bg-light fw-bold"><i class="fa fa-calendar"></i></span>
                  <input type="month" id="payrollCalMonth" class="form-control form-control-sm fw-bold" onchange="renderPayrollCalendar()">
                </div>
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" id="btnCalMode-current" class="btn btn-dark fw-bold" onclick="setPayrollCalMode('current')">Current Date &rarr; Previous</button>
                  <button type="button" id="btnCalMode-schedule" class="btn btn-outline-dark fw-bold" onclick="setPayrollCalMode('schedule')">Cutoffs &amp; Paid Dates</button>
                  <button type="button" id="btnCalMode-alldays" class="btn btn-outline-dark fw-bold" onclick="setPayrollCalMode('alldays')">All Month Days (31&ndash;1)</button>
                </div>
              </div>
            </div>

            <!-- Legend -->
            <div class="d-flex align-items-center gap-3 small mb-2">
              <span><span class="badge bg-success"><i class="fa fa-check-circle"></i> PAID</span> Salary + Bonus/Commission Disbursed</span>
              <span><span class="badge bg-light text-secondary border"><i class="fa fa-circle-o"></i> Unpaid</span> Click cell to mark Paid</span>
              <span><span class="badge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #93c5fd;">Cutoff Date</span> Standard 15th / End-of-Month Payday</span>
            </div>

            <div class="table-responsive border rounded" style="max-height: 440px;">
              <table class="table table-sm table-bordered small align-middle mb-0" id="payrollCalendarTable" style="min-width: 100%;">
                <thead id="payrollCalendarHead" class="table-light" style="position: sticky; top: 0; z-index: 5;">
                  <tr><th class="text-center text-muted py-3">Loading horizontal payment calendar...</th></tr>
                </thead>
                <tbody id="payrollCalendarBody">
                  <tr><td class="text-center text-muted py-3">Loading staff payment records...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 5: CATALOG & AVAILABLE STOCK -->
        <div class="tab-pane fade" id="tab-catalog">
          <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h5 class="mb-0 fw-bold"><i class="fa fa-cubes text-info"></i> Store Catalog &amp; Available Stock</h5>
                <p class="text-muted small mb-0">Inline inventory management with live PostgreSQL sync. Edit stock, safety thresholds, and prices directly in table cells.</p>
              </div>
              <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm" role="group" id="catalogFilterGroup">
                  <button type="button" id="catBtnFilterCritical" class="btn btn-danger active fw-bold" onclick="setCatalogFilter('critical')">
                    <i class="fa fa-exclamation-triangle"></i> Critical Only (<span id="catCountCritical">0</span>)
                  </button>
                  <button type="button" id="catBtnFilterLow" class="btn btn-outline-warning fw-bold text-dark" onclick="setCatalogFilter('low')">
                    <i class="fa fa-bell-o"></i> Low Stock (<span id="catCountLow">0</span>)
                  </button>
                  <button type="button" id="catBtnFilterAll" class="btn btn-outline-secondary fw-bold" onclick="setCatalogFilter('all')">
                    <i class="fa fa-list"></i> All Items (<span id="catCountAll">0</span>)
                  </button>
                </div>
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text bg-light text-muted"><i class="fa fa-search"></i></span>
                  <input type="text" id="catalogSearchInput" class="form-control" placeholder="Search product, SKU..." oninput="onCatalogSearchInput()">
                </div>
                <button class="btn btn-outline-success btn-sm text-nowrap fw-bold" onclick="openExcelUploadModal()" title="Upload and update products via Excel spreadsheet">
                  <i class="fa fa-file-excel-o text-success me-1"></i> Upload Excel
                </button>
                <button class="btn btn-primary btn-sm text-nowrap" onclick="document.getElementById('tab-materials-link').click()">
                  <i class="fa fa-truck"></i> Receive Delivery (GRN)
                </button>
              </div>
            </div>

            <!-- Notice Banner for Critical Status View -->
            <div class="alert alert-danger py-2 px-3 d-flex align-items-center justify-content-between mb-3 small" id="catalogCriticalAlert">
              <div>
                <i class="fa fa-exclamation-circle text-danger me-1"></i>
                <strong id="catalogFilterLabel">Displaying Critical Status Items:</strong>
                <span id="catalogFilterDesc" class="text-muted">Showing items at or below 50% safety stock or completely out of stock. Type directly into input boxes to edit inline; press Enter or click Save to apply updates.</span>
              </div>
              <span class="badge bg-danger ms-2 text-nowrap" id="criticalBadgeNotice">Critical Filter Active</span>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-hover small align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="width: 60px;">ID</th>
                    <th class="text-center" style="width: 65px;">Image</th>
                    <th style="min-width: 100px;">SKU</th>
                    <th style="min-width: 180px;">Product Name</th>
                    <th style="min-width: 140px;">Category</th>
                    <th class="text-center" style="min-width: 105px;">Available Stock (Q)</th>
                    <th class="text-center" style="min-width: 95px;">New Batch (NQ)</th>
                    <th class="text-center" style="min-width: 95px;">Safety Level (S)</th>
                    <th class="text-end" style="min-width: 115px;">Landed Cost</th>
                    <th class="text-end" style="min-width: 115px;">Markup (₱)</th>
                    <th class="text-end" style="min-width: 115px;">Retail Price</th>
                    <th class="text-center" style="min-width: 110px;">Status</th>
                    <th class="text-center" style="min-width: 140px;">Action</th>
                  </tr>
                </thead>
                <tbody id="catalogStockTable">
                  <tr><td colspan="13" class="text-center text-muted">Loading catalog...</td></tr>
                </tbody>
              </table>
            </div>

            <!-- Catalog Pagination & Page Size Toolbar -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top small" id="catalogPaginationBar">
              <div class="d-flex align-items-center gap-2 text-muted">
                <span>Show</span>
                <select id="catalogPageSize" class="form-select form-select-sm" style="width: auto; min-width: 75px;" onchange="onCatalogPageSizeChange()">
                  <option value="15">15</option>
                  <option value="25" selected>25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="all">All</option>
                </select>
                <span>items per page</span>
                <span class="mx-2 text-secondary">|</span>
                <span id="catalogPaginationInfo" class="fw-bold text-dark">Showing 0 of 0 products</span>
              </div>
              <nav aria-label="Catalog pagination">
                <ul class="pagination pagination-sm mb-0" id="catalogPaginationControls">
                  <!-- Generated dynamically in JS -->
                </ul>
              </nav>
            </div>

            <!-- Modal for Editing / Uploading Product Photo -->
            <div class="modal fade" id="productPhotoModal" tabindex="-1" aria-labelledby="productPhotoModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow border-0">
                  <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold" id="productPhotoModalLabel"><i class="fa fa-camera me-1"></i> Edit Product Photo</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body p-3">
                    <div class="text-center mb-3">
                      <img id="photoModalPreview" src="/assets/uploads/product_photos/general_products.png" class="rounded border shadow-sm" style="width: 120px; height: 120px; object-fit: cover;" onerror="this.onerror=null;this.src=&apos;/assets/uploads/product_photos/general_products.png&apos;;">
                      <div class="small fw-bold mt-2" id="photoModalTitle">Product #</div>
                    </div>
                    <input type="hidden" id="photoModalPid" value="">
                    
                    <div class="mb-3">
                      <label class="form-label small fw-bold text-muted mb-1"><i class="fa fa-upload me-1"></i> Upload Image File</label>
                      <input type="file" id="photoModalFileInput" class="form-control form-control-sm" accept="image/*" onchange="previewSelectedPhotoFile(event)">
                      <div class="form-text small" style="font-size: 11px;">Uploads from local device directly into store inventory assets.</div>
                    </div>

                    <div class="mb-2">
                      <label class="form-label small fw-bold text-muted mb-1"><i class="fa fa-link me-1"></i> Or Enter Image Path / Filename</label>
                      <input type="text" id="photoModalPathInput" class="form-control form-control-sm" placeholder="e.g. rotary_hammer.jpg or product-featured-56.jpg" oninput="previewEnteredPhotoPath()">
                      <div class="form-text small" style="font-size: 11px;">Relative to <code>assets/uploads/</code>.</div>
                    </div>
                  </div>
                  <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnSavePhotoModal" class="btn btn-primary btn-sm" onclick="saveProductPhotoFromModal()"><i class="fa fa-check"></i> Apply Photo</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal for Excel Product List Upload & Batch SQL Update -->
            <div class="modal fade" id="excelUploadModal" tabindex="-1" aria-labelledby="excelUploadModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content shadow border-0">
                  <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title fw-bold" id="excelUploadModalLabel">
                      <i class="fa fa-file-excel-o me-2"></i> Update Product List via Excel (Excel &rarr; SQL)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body p-4">
                    <!-- Helper and Template Downloads -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-3 border-bottom">
                      <div>
                        <div class="fw-bold small text-dark"><i class="fa fa-info-circle text-primary me-1"></i> Fast One-Time PostgreSQL Catalog Sync</div>
                        <div class="text-muted small" style="font-size: 11.5px;">
                          Upload your product spreadsheet (supports <strong>Sam & Inri Inventory format</strong>: <code>code</code>, <code>description</code>, <code>stock</code>, <code>minimum_stock</code>, <code>capital_cost</code>, <code>markup_percent</code>, <code>selling_price</code>, <code>unit</code>, <code>supplier</code>). Matches existing products by <strong>Code / ID</strong>, <strong>SKU</strong>, or <strong>Name</strong> and updates stock, costs, and markups atomically.
                        </div>
                      </div>
                      <div class="d-flex gap-2 flex-wrap">
                        <a href="/api/products/template-excel?format=sam_inri" class="btn btn-outline-success btn-sm text-nowrap" download="Sam_Inri_Inventory_Template.xls" title="Download Sam & Inri Inventory Excel template (.xls)">
                          <i class="fa fa-file-excel-o me-1"></i> Sam & Inri Format (.xls)
                        </a>
                        <a href="/api/products/template-excel" class="btn btn-outline-primary btn-sm text-nowrap" download title="Download clean standard Excel format">
                          <i class="fa fa-download me-1"></i> Standard Template
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="downloadCurrentCatalogExcel()" title="Export current catalog to Excel for editing">
                          <i class="fa fa-table text-secondary me-1"></i> Export Catalog
                        </button>
                      </div>
                    </div>

                    <!-- File Drop / Select Area -->
                    <div class="p-4 border-2 border-dashed rounded text-center mb-3 bg-light" id="excelDropZone" style="border: 2px dashed #cbd5e1; cursor: pointer;" onclick="document.getElementById('excelProductFileInput').click()">
                      <i class="fa fa-cloud-upload fa-3x text-success mb-2 d-block"></i>
                      <h6 class="fw-bold mb-1">Click to browse or drop your Excel file here</h6>
                      <p class="text-muted small mb-0">Supports <strong>.xlsx</strong>, <strong>.xls</strong>, or <strong>.csv</strong></p>
                      <input type="file" id="excelProductFileInput" accept=".xlsx, .xls, .csv" style="display:none;" onchange="handleExcelFileSelect(event)">
                    </div>

                    <!-- Parse Status & Preview Section (initially hidden) -->
                    <div id="excelPreviewSection" style="display:none;">
                      <!-- Quick Stats Badges -->
                      <div class="row g-2 mb-3 text-center">
                        <div class="col-md-4">
                          <div class="p-2 border rounded bg-white">
                            <span class="text-muted small d-block">Total Rows Parsed</span>
                            <strong class="fs-5 text-dark" id="excelStatTotal">0</strong>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="p-2 border rounded bg-white">
                            <span class="text-muted small d-block">Matched to Update (SQL)</span>
                            <strong class="fs-5 text-success" id="excelStatUpdate">0</strong>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="p-2 border rounded bg-white">
                            <span class="text-muted small d-block">New Items to Insert</span>
                            <strong class="fs-5 text-primary" id="excelStatInsert">0</strong>
                          </div>
                        </div>
                      </div>

                      <!-- Options Checkboxes -->
                      <div class="d-flex align-items-center gap-3 mb-2 small bg-light p-2 rounded border">
                        <div class="form-check form-check-inline mb-0">
                          <input class="form-check-input" type="checkbox" id="chkExcelUpdateExisting" checked>
                          <label class="form-check-label fw-bold" for="chkExcelUpdateExisting">Update existing products (ID / SKU / Name)</label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                          <input class="form-check-input" type="checkbox" id="chkExcelInsertNew" checked>
                          <label class="form-check-label fw-bold" for="chkExcelInsertNew">Insert new items not found in catalog</label>
                        </div>
                      </div>

                      <!-- Preview Table -->
                      <div class="table-responsive border rounded mb-2" style="max-height: 220px;">
                        <table class="table table-sm table-striped small align-middle mb-0">
                          <thead class="table-light small">
                            <tr>
                              <th>#</th>
                              <th>ID</th>
                              <th>SKU</th>
                              <th>Product Name</th>
                              <th>Category</th>
                              <th class="text-center">Stock (Q)</th>
                              <th class="text-end">Cost</th>
                              <th class="text-end">Markup</th>
                              <th class="text-end">Retail</th>
                              <th>Action</th>
                            </tr>
                          </thead>
                          <tbody id="excelPreviewTableBody">
                            <!-- Populated on file parse -->
                          </tbody>
                        </table>
                      </div>
                      <div class="text-muted small mb-0 fst-italic" id="excelPreviewNote" style="font-size: 11px;">
                        * Displaying first preview rows. Ready to execute one-time update to PostgreSQL.
                      </div>
                    </div>

                    <!-- Progress / Result Alert -->
                    <div id="excelImportResultAlert" class="mt-3" style="display:none;"></div>
                  </div>
                  <div class="modal-footer py-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="btnExecuteExcelImport" class="btn btn-success btn-sm fw-bold px-3" onclick="executeBatchExcelImport()" disabled>
                      <i class="fa fa-database me-1"></i> Apply Updates to PostgreSQL (1-Click)
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: WAREHOUSE STOCK -->
        <div class="tab-pane fade" id="tab-warehouse">
          <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h5 class="mb-0 fw-bold"><i class="fa fa-building-o text-warning"></i> Warehouse Stock</h5>
                <p class="text-muted small mb-0">Warehouse inventory &amp; reserve replenishment management with live PostgreSQL sync. Edit warehouse stock, safety thresholds, and prices directly in table cells.</p>
              </div>
              <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm" role="group" id="warehouseFilterGroup">
                  <button type="button" id="whBtnFilterCritical" class="btn btn-danger active fw-bold" onclick="setWarehouseFilter('critical')">
                    <i class="fa fa-exclamation-triangle"></i> Critical Only (<span id="whCountCritical">0</span>)
                  </button>
                  <button type="button" id="whBtnFilterLow" class="btn btn-outline-warning fw-bold text-dark" onclick="setWarehouseFilter('low')">
                    <i class="fa fa-bell-o"></i> Low Stock (<span id="whCountLow">0</span>)
                  </button>
                  <button type="button" id="whBtnFilterAll" class="btn btn-outline-secondary fw-bold" onclick="setWarehouseFilter('all')">
                    <i class="fa fa-list"></i> All Items (<span id="whCountAll">0</span>)
                  </button>
                </div>
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text bg-light text-muted"><i class="fa fa-search"></i></span>
                  <input type="text" id="warehouseSearchInput" class="form-control" placeholder="Search warehouse, SKU..." oninput="onWarehouseSearchInput()">
                </div>
                <button class="btn btn-outline-success btn-sm text-nowrap fw-bold" onclick="openExcelUploadModal()" title="Upload and update products via Excel spreadsheet">
                  <i class="fa fa-file-excel-o text-success me-1"></i> Upload Excel
                </button>
                <button class="btn btn-primary btn-sm text-nowrap" onclick="document.getElementById('tab-materials-link').click()">
                  <i class="fa fa-truck"></i> Receive Delivery (GRN)
                </button>
              </div>
            </div>

            <!-- Notice Banner for Critical Status View -->
            <div class="alert alert-danger py-2 px-3 d-flex align-items-center justify-content-between mb-3 small" id="warehouseCriticalAlert">
              <div>
                <i class="fa fa-exclamation-circle text-danger me-1"></i>
                <strong id="warehouseFilterLabel">Displaying Critical Status Items:</strong>
                <span id="warehouseFilterDesc" class="text-muted">Showing warehouse items at or below 50% safety stock or completely out of stock. Type directly into input boxes to edit inline; press Enter or click Save to apply updates.</span>
              </div>
              <span class="badge bg-danger ms-2 text-nowrap" id="warehouseCriticalBadgeNotice">Critical Filter Active</span>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-hover small align-middle">
                <thead class="table-light">
                  <tr>
                    <th class="text-center" style="width: 65px;">Image</th>
                    <th style="min-width: 100px;">SKU</th>
                    <th style="min-width: 180px;">Product Name</th>
                    <th class="text-center wh-col-group-a" style="min-width: 90px; background-color: #e6e0d1 !important; color: #3f3928;">Storage_A</th>
                    <th class="text-end wh-col-group-a" style="min-width: 110px; background-color: #e6e0d1 !important; color: #3f3928;">Landed Cost(A)</th>
                    <th class="text-center wh-col-group-b" style="min-width: 90px; background-color: #c2d0ed !important; color: #1e3a5f;">Storage_B</th>
                    <th class="text-end wh-col-group-b" style="min-width: 110px; background-color: #c2d0ed !important; color: #1e3a5f;">Landed Cost(B)</th>
                    <th class="text-center wh-col-group-c" style="min-width: 90px; background-color: #d1cfcd !important; color: #2e2c2a;">Storage_C</th>
                    <th class="text-end wh-col-group-c" style="min-width: 110px; background-color: #d1cfcd !important; color: #2e2c2a;">Landed Cost(C)</th>
                    <th class="text-center wh-col-group-nq" style="min-width: 95px; background-color: #d1e6d6 !important; color: #1e4620;">Reserve Batch (NQ)</th>
                    <th class="text-end wh-col-group-nq" style="min-width: 110px; background-color: #d1e6d6 !important; color: #1e4620;">Landed Cost(NQ)</th>
                    <th class="text-center" style="min-width: 105px;">Store Stock(Q)</th>
                    <th class="text-end" style="min-width: 115px;">Landed Cost</th>
                    <th class="text-center" style="min-width: 110px;">Status</th>
                    <th class="text-center" style="min-width: 140px;">Action</th>
                  </tr>
                </thead>
                <tbody id="warehouseStockTable">
                  <tr><td colspan="15" class="text-center text-muted">Loading warehouse stock...</td></tr>
                </tbody>
              </table>
            </div>

            <!-- Warehouse Pagination & Page Size Toolbar -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top small" id="warehousePaginationBar">
              <div class="d-flex align-items-center gap-2 text-muted">
                <span>Show</span>
                <select id="warehousePageSize" class="form-select form-select-sm" style="width: auto; min-width: 75px;" onchange="onWarehousePageSizeChange()">
                  <option value="15">15</option>
                  <option value="25" selected>25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="all">All</option>
                </select>
                <span>items per page</span>
                <span class="mx-2 text-secondary">|</span>
                <span id="warehousePaginationInfo" class="fw-bold text-dark">Showing 0 of 0 products</span>
              </div>
              <nav aria-label="Warehouse pagination">
                <ul class="pagination pagination-sm mb-0" id="warehousePaginationControls">
                  <!-- Generated dynamically in JS -->
                </ul>
              </nav>
            </div>
          </div>
        </div>

        <!-- TAB 6: GENERAL LEDGER (DOUBLE-ENTRY SUMMARY) -->
        <div class="tab-pane fade" id="tab-ledger">
          <div class="card p-4">
            <h5 class="mb-1 fw-bold"><i class="fa fa-book text-primary"></i> Tenant Double-Entry General Ledger Summary</h5>
            <p class="text-muted small">Automated trial balance reflecting POS sales, landed inventory receipts, payment gateway fees, and store OPEX.</p>
            <div class="table-responsive">
              <table class="table table-sm table-bordered small">
                <thead class="table-light">
                  <tr>
                    <th>Account Code</th>
                    <th>Account Title</th>
                    <th>Classification</th>
                    <th class="text-end">Debit (₱)</th>
                    <th class="text-end">Credit (₱)</th>
                  </tr>
                </thead>
                <tbody id="ledgerSummaryTable">
                  <tr><td colspan="5" class="text-center text-muted">Loading general ledger...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Expense Modal -->
  <div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 10px; overflow: hidden;">
        <div class="modal-header py-2 px-3 text-white" id="expModalHeader" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
          <h6 class="modal-title fw-bold mb-0" id="expModalTitle">
            <i class="fa fa-bolt me-1"></i> Log Store Bill — Electricity / Power
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
          <form id="expForm">
            <input type="hidden" id="expCategory" value="ELECTRICITY">
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold mb-0">Expense Category</label>
                <span class="badge bg-primary" id="expCategoryActiveBadge" style="font-size: 10.5px;">Electricity / Power</span>
              </div>
              <div class="d-flex flex-wrap gap-1 p-1 rounded border" style="background: #f8fafc;" id="expModalCategoryButtons">
                <button type="button" class="btn btn-sm py-1 px-2 fw-bold" style="font-size: 11px;" data-exp-cat="ELECTRICITY" onclick="selectExpenseCategory('ELECTRICITY')">
                  <i class="fa fa-bolt"></i> Electricity
                </button>
                <button type="button" class="btn btn-sm py-1 px-2 fw-bold" style="font-size: 11px;" data-exp-cat="RENT" onclick="selectExpenseCategory('RENT')">
                  <i class="fa fa-building-o"></i> Rent
                </button>
                <button type="button" class="btn btn-sm py-1 px-2 fw-bold" style="font-size: 11px;" data-exp-cat="WATER" onclick="selectExpenseCategory('WATER')">
                  <i class="fa fa-tint"></i> Water
                </button>
                <button type="button" class="btn btn-sm py-1 px-2 fw-bold" style="font-size: 11px;" data-exp-cat="DELIVERY_LOGISTICS" onclick="selectExpenseCategory('DELIVERY_LOGISTICS')">
                  <i class="fa fa-truck"></i> Logistics
                </button>
                <button type="button" class="btn btn-sm py-1 px-2 fw-bold" style="font-size: 11px;" data-exp-cat="MAINTENANCE" onclick="selectExpenseCategory('MAINTENANCE')">
                  <i class="fa fa-wrench"></i> Maintenance
                </button>
                <button type="button" class="btn btn-sm py-1 px-2 fw-bold" style="font-size: 11px;" data-exp-cat="MISC_OPEX" onclick="selectExpenseCategory('MISC_OPEX')">
                  <i class="fa fa-cube"></i> Misc OPEX
                </button>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold">Title / Description</label>
              <input type="text" class="form-control form-control-sm" id="expTitle" placeholder="e.g. October 2026 Electric Bill" required>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold">Vendor / Biller</label>
              <input type="text" class="form-control form-control-sm" id="expVendor" placeholder="e.g. Meralco Commercial Accounts">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold">Amount (₱)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text fw-bold">₱</span>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm fw-bold" id="expAmount" placeholder="0.00" required>
              </div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label small fw-bold">Period Start</label>
                <input type="date" class="form-control form-control-sm" id="expStart" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold">Period End</label>
                <input type="date" class="form-control form-control-sm" id="expEnd" required>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer py-2 px-3 bg-light">
          <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-sm btn-primary fw-bold px-3" onclick="submitExpense()">
            <i class="fa fa-check-circle me-1"></i> Save Store Bill
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Official Employee Payslip & Payroll Confirmation Modal -->
  <div class="modal fade" id="payrollConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 680px;">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
        <div class="modal-header py-2 px-3 text-white" id="pcmHeader" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
          <div class="d-flex align-items-center gap-2">
            <h6 class="modal-title fw-bold mb-0" id="pcmTitle">
              <i class="fa fa-file-text-o me-1"></i> Official Employee Payslip &amp; Payment Voucher
            </h6>
          </div>
          <button type="button" class="btn-close btn-close-white" id="pcmCloseIcon" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3" style="background: #f1f5f9;">
          <!-- Printable Payslip Card Preview -->
          <div class="border rounded bg-white p-3 shadow-sm" id="pcmPayslipPreviewCard" style="border-color: #cbd5e1 !important;">
            <!-- Payslip Top Store Header -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
              <div>
                <div class="fw-bold text-dark" style="font-size: 15px; letter-spacing: 0.3px;" id="pcmStoreName">eConstruction Store ERP</div>
                <div class="text-uppercase fw-bold text-primary" style="font-size: 10.5px; letter-spacing: 0.8px;">Official Salary &amp; Commission Payslip</div>
              </div>
              <div class="text-end">
                <div class="small fw-bold text-dark" style="font-size: 11.5px;">Slip No: <span class="text-primary" id="pcmSlipNo">PS-20261013-001</span></div>
                <div class="text-muted" style="font-size: 10.5px;">Pay Date: <strong class="text-dark" id="pcmPayDateFormatted">Oct 13, 2026</strong> (<span id="pcmPayDateIso">2026-10-13</span>)</div>
              </div>
            </div>

            <!-- Employee Information Bar -->
            <div class="row g-2 align-items-center p-2 mb-2 rounded border mx-0" style="background: #f8fafc;">
              <div class="col-sm-7 d-flex align-items-center gap-2">
                <div id="pcmInitials" class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width: 38px; height: 38px; background: #0f172a; font-size: 13px;">
                  ST
                </div>
                <div>
                  <div class="fw-bold text-dark" style="font-size: 13.5px;" id="pcmStaffName">Staff Name</div>
                  <div class="text-muted" style="font-size: 11px;">
                    <span id="pcmStaffCode">EMP-001</span> &bull; <span id="pcmStaffEmail">staff@store.com</span>
                  </div>
                </div>
              </div>
              <div class="col-sm-5 text-sm-end">
                <div class="mb-1">
                  <span class="badge bg-info text-dark" style="font-size: 10px;" id="pcmStaffRole">Staff</span>
                  <span id="pcmStatusTransition" class="badge bg-success" style="font-size: 10px;">Unpaid &rarr; PAID</span>
                </div>
                <div class="text-muted" style="font-size: 10px;">Currency: <strong>PHP (₱)</strong></div>
              </div>
            </div>

            <!-- Side-by-Side Earnings & Deductions Table -->
            <div class="row g-2 mb-2" id="pcmBreakdownBox">
              <!-- Left Column: Earnings -->
              <div class="col-md-6">
                <div class="border rounded h-100 d-flex flex-column justify-content-between" style="background: #ffffff;">
                  <div>
                    <div class="px-2 py-1 border-bottom fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 0.5px; background: #f0fdf4; color: #166534;">
                      <i class="fa fa-plus-circle me-1"></i> Earnings &amp; Incentives
                    </div>
                    <div class="p-2">
                      <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <span class="text-secondary">Basic / Base Salary</span>
                        <strong class="text-dark" id="pcmBaseSalary">₱0.00</strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <span class="text-secondary">Bonus / Incentive</span>
                        <strong class="text-primary" id="pcmBonusComm">+₱0.00</strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-1 small" id="pcmPosCommRow">
                        <span class="text-secondary">Accrued POS Commission</span>
                        <strong class="text-success" id="pcmPosComm">+₱0.00</strong>
                      </div>
                    </div>
                  </div>
                  <div class="px-2 py-1 border-top d-flex justify-content-between align-items-center" style="background: #f8fafc; font-size: 11.5px;">
                    <span class="fw-bold text-dark">Gross Earnings</span>
                    <strong class="text-dark" id="pcmGrossEarnings">₱0.00</strong>
                  </div>
                </div>
              </div>

              <!-- Right Column: Deductions (from Base Salary) -->
              <div class="col-md-6">
                <div class="border rounded h-100 d-flex flex-column justify-content-between" style="background: #ffffff;">
                  <div>
                    <div class="px-2 py-1 border-bottom fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 0.5px; background: #fef2f2; color: #991b1b;">
                      <i class="fa fa-minus-circle me-1"></i> Salary Deductions
                    </div>
                    <div class="p-2">
                      <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <span class="text-secondary">Withholding Tax (<span id="pcmTaxPctLabel">0.00%</span>)</span>
                        <strong class="text-danger" id="pcmTaxAmt">-₱0.00</strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <span class="text-secondary">SSS Contribution</span>
                        <strong class="text-danger" id="pcmSssAmt">-₱0.00</strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-1 small">
                        <span class="text-secondary">Cash Advance / Vale</span>
                        <strong class="text-danger" id="pcmAdvanceAmt">-₱0.00</strong>
                      </div>
                    </div>
                  </div>
                  <div class="px-2 py-1 border-top d-flex justify-content-between align-items-center" style="background: #fef2f2; font-size: 11.5px;">
                    <span class="fw-bold text-danger">Total Deductions</span>
                    <strong class="text-danger" id="pcmTotalDeductions">-₱0.00</strong>
                  </div>
                </div>
              </div>
            </div>

            <!-- Net Take-Home Pay Banner -->
            <div class="p-2 rounded border d-flex flex-wrap justify-content-between align-items-center" style="background: #ecfdf5; border-color: #86efac !important;">
              <div>
                <div class="fw-bold text-dark text-uppercase" style="font-size: 12px; letter-spacing: 0.4px;" id="pcmTotalLabel">Net Take-Home Pay (Net Payout)</div>
                <div class="text-muted" style="font-size: 10.5px;" id="pcmNetFormulaSub">
                  Net Base Salary: <strong class="text-dark" id="pcmNetBaseSalary">₱0.00</strong> + Bonus/Comm: <strong class="text-primary" id="pcmTotalBonusPos">₱0.00</strong>
                </div>
              </div>
              <div class="text-end">
                <strong class="text-success" style="font-size: 19px; letter-spacing: 0.3px;" id="pcmTotalPayout">₱0.00</strong>
              </div>
            </div>

            <!-- Signature Preview Row -->
            <div class="row g-3 mt-2 pt-2 border-top text-center" style="font-size: 10.5px; color: #64748b;">
              <div class="col-6">
                <div class="border-bottom mx-3 mb-1" style="height: 18px;"></div>
                <div>Authorized Employer Signatory</div>
              </div>
              <div class="col-6">
                <div class="border-bottom mx-3 mb-1" style="height: 18px;"></div>
                <div>Employee Signature / Date Received</div>
              </div>
            </div>
          </div>

          <div class="small text-muted mt-2 mb-0 px-1" id="pcmNote" style="font-size: 11.5px;">
            Confirming will mark this employee as <strong>PAID</strong> on the payment calendar and print their official payslip.
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-1">
          <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3" id="pcmCancelBtn" data-bs-dismiss="modal">
            Cancel
          </button>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-dark fw-bold px-2" id="pcmPrintOnlyBtn" onclick="printCurrentModalPayslip()">
              <i class="fa fa-print me-1"></i> Print Payslip Only
            </button>
            <button type="button" class="btn btn-sm btn-outline-success fw-bold px-2" id="pcmConfirmBtn" onclick="executePayrollModalConfirm(false)">
              <i class="fa fa-check-circle me-1"></i> Mark PAID Only
            </button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-3" id="pcmConfirmPrintBtn" onclick="executePayrollModalConfirm(true)">
              <i class="fa fa-print me-1"></i> Confirm PAID &amp; Print Payslip
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden Iframe for Zero-Popup-Blocker Official Payslip Printing -->
  <iframe id="payslipPrintIframe" style="position: fixed; right: 0; bottom: 0; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

  <!-- Floating Toast Notification Container -->
  <div id="erpToastContainer" style="position: fixed; top: 62px; right: 18px; z-index: 2050; display: flex; flex-direction: column; gap: 8px; max-width: 380px;"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Automatic API Base Resolution: routes to /erp/api/... when accessed behind /erp reverse proxy
    const _nativeFetch = window.fetch;
    window.fetch = function(input, init) {
      if (typeof input === 'string' && input.startsWith('/api/')) {
        const prefix = window.location.pathname.startsWith('/erp') ? '/erp' : '';
        input = prefix + input;
      }
      return _nativeFetch.call(this, input, init);
    };

    let currentSupplierId = 1;
    let currentSupplierName = 'eConstruction Store ERP';
    let currentWaterfallPeriod = 'daily';
    let productsList = [];
    let expenseModal;
    let payrollConfirmModal;
    let pendingPayrollConfirmAction = null;

    function getErpCookie(name) {
      const pairs = String(document.cookie || '').split(';');
      for (const p of pairs) {
        const idx = p.indexOf('=');
        if (idx > -1) {
          const k = p.slice(0, idx).trim();
          const v = p.slice(idx + 1).trim();
          if (k === name) {
            try { return decodeURIComponent(v.split('+').join(' ')); } catch (e) { return v; }
          }
        }
      }
      return '';
    }

    function toggleSidebarCollapse() {
      document.body.classList.toggle('sidebar-collapse');
      const icon = document.getElementById('sidebarCollapseIcon');
      if (icon) {
        icon.className = document.body.classList.contains('sidebar-collapse')
          ? 'fa fa-angle-double-right'
          : 'fa fa-angle-double-left';
      }
    }

    function activateSidebarItem(liId, tabPaneId, titleText) {
      document.querySelectorAll('#mainTabs > li').forEach(li => {
        if (!li.classList.contains('header')) li.classList.remove('active');
      });
      const activeLi = document.getElementById(liId);
      if (activeLi) activeLi.classList.add('active');

      document.querySelectorAll('#mainTabsContent > .tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
      });
      const activePane = document.getElementById(tabPaneId);
      if (activePane) activePane.classList.add('show', 'active');

      const titleEl = document.getElementById('pageHeaderTitle');
      if (titleEl && titleText) titleEl.textContent = titleText;
    }

    function setWaterfallPeriod(period) {
      currentWaterfallPeriod = period;
      ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'].forEach(p => {
        const btn = document.getElementById('btnPeriod-' + p);
        if (btn) {
          btn.className = (p === period)
            ? 'btn btn-primary fw-bold'
            : 'btn btn-outline-primary fw-bold';
        }
      });
      loadWaterfall();
    }

    function jumpToGrnForProduct(productId) {
      const link = document.getElementById('tab-materials-link');
      if (link) link.click();
      const sel = document.getElementById('grnProduct');
      if (sel) {
        sel.value = String(productId);
        updateProductCurrentStock();
      }
    }

    document.addEventListener('DOMContentLoaded', async () => {
      expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
      payrollConfirmModal = new bootstrap.Modal(document.getElementById('payrollConfirmModal'));
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('expStart').value = today;
      document.getElementById('expEnd').value = today;

      const params = new URLSearchParams(window.location.search);
      const urlSupplierId = params.get('supplier_id');
      const cookieSupplierId = getErpCookie('erp_supplier_id');
      const storedSupplierId = localStorage.getItem('erp_supplier_id');

      if (urlSupplierId && parseInt(urlSupplierId, 10) > 0) {
        currentSupplierId = parseInt(urlSupplierId, 10);
        localStorage.setItem('erp_supplier_id', String(currentSupplierId));
      } else if (cookieSupplierId && parseInt(cookieSupplierId, 10) > 0) {
        currentSupplierId = parseInt(cookieSupplierId, 10);
        localStorage.setItem('erp_supplier_id', String(currentSupplierId));
      } else if (storedSupplierId && parseInt(storedSupplierId, 10) > 0) {
        currentSupplierId = parseInt(storedSupplierId, 10);
      }

      const resolvedUserName = params.get('user_name') || getErpCookie('erp_user_name') || localStorage.getItem('erp_user_name') || '';
      if (resolvedUserName) {
        document.getElementById('adminUserName').textContent = resolvedUserName;
        localStorage.setItem('erp_user_name', resolvedUserName);
      }

      const resolvedRole = params.get('role') || getErpCookie('erp_user_role') || localStorage.getItem('erp_user_role') || '';
      if (resolvedRole) {
        document.getElementById('adminUserRole').textContent = resolvedRole;
        localStorage.setItem('erp_user_role', resolvedRole);
      }

      const cookieStoreName = getErpCookie('erp_supplier_name');
      if (cookieStoreName) {
        currentSupplierName = cookieStoreName;
        const nameEl = document.getElementById('activeTenantName');
        if (nameEl) nameEl.textContent = currentSupplierName;
      }

      // Sanitize URL query parameters immediately to prevent exposing ?supplier_id=... in browser address bar
      if (window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
      }

      const isLocalHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
      const posReturnTarget = isLocalHost
        ? 'http://localhost:9090/supplier/index.php'
        : (window.location.protocol + '//' + window.location.hostname + '/supplier/index.php');
      const posLink = document.getElementById('posReturnLink');
      if (posLink) posLink.href = posReturnTarget;
      const logoLink = document.getElementById('logoReturnLink');
      if (logoLink) logoLink.href = posReturnTarget;
      const sidebarPosLink = document.getElementById('sidebarPosLink');
      if (sidebarPosLink) sidebarPosLink.href = posReturnTarget;

      await loadTenants();
      await loadAllData();
    });

    async function loadTenants() {
      try {
        const res = await fetch('/api/tenants');
        const list = await res.json();
        const matched = (list || []).find(t => Number(t.supplier_id) === Number(currentSupplierId));
        if (matched) {
          currentSupplierName = matched.supplier_name;
          const nameEl = document.getElementById('activeTenantName');
          if (nameEl) nameEl.textContent = matched.supplier_name;
        }
      } catch (e) {}
    }

    async function loadAllData() {
      await loadWaterfall();
      await loadProducts();
      await loadGrnHistory();
      await loadExpenses();
      await loadPayroll();
    }

    async function loadWaterfall() {
      try {
        const res = await fetch('/api/reports/waterfall?supplier_id=' + currentSupplierId + '&period=' + currentWaterfallPeriod);
        const data = await res.json();
        const m = data.metrics;
        const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const periodLabel = m.period_label || 'Daily';
        document.querySelectorAll('.kpiPeriodLabel').forEach(el => {
          el.textContent = periodLabel;
        });
        const summaryBadge = document.getElementById('wfPeriodSummary');
        if (summaryBadge) {
          const rangeText = (m.start_date === m.end_date)
            ? m.end_date
            : (m.start_date + ' to ' + m.end_date);
          summaryBadge.textContent = 'Period: ' + periodLabel + ' (' + rangeText + ' • ' + (m.transaction_count || 0) + ' POS Orders)';
        }

        document.getElementById('wfGross').textContent = '₱' + fmt(m.gross_revenue);
        document.getElementById('wfCogs').textContent = '-₱' + fmt(m.landed_cogs);
        document.getElementById('wfGrossMargin').textContent = '₱' + fmt(m.gross_margin);
        document.getElementById('wfGrossMarginPct').textContent = m.gross_margin_percentage;
        document.getElementById('wfFees').textContent = '-₱' + fmt(m.bank_processing_fees);
        document.getElementById('wfIncentives').textContent = '-₱' + fmt(m.sales_staff_incentives);
        document.getElementById('wfCm1').textContent = '₱' + fmt(m.contribution_margin_1);
        document.getElementById('wfCm1Pct').textContent = m.contribution_margin_1_percentage;
        document.getElementById('wfOpex').textContent = '-₱' + fmt(m.total_operating_expenses);
        
        const netEl = document.getElementById('wfNetProfit');
        netEl.textContent = '₱' + fmt(m.net_operating_profit);
        document.getElementById('wfNetProfitPct').textContent = m.net_profit_margin;
        netEl.className = m.net_operating_profit >= 0 ? 'positive' : 'negative';

        // Update Top KPI Strip
        document.getElementById('kpiGross').textContent = '₱' + fmt(m.gross_revenue);
        document.getElementById('kpiCogs').textContent = '-₱' + fmt(m.landed_cogs);
        document.getElementById('kpiOpex').textContent = '-₱' + fmt(m.total_operating_expenses);
        const kpiNetEl = document.getElementById('kpiNet');
        kpiNetEl.textContent = '₱' + fmt(m.net_operating_profit);
        kpiNetEl.className = 'kpi-val ' + (m.net_operating_profit >= 0 ? 'text-primary' : 'text-danger');

        // Summary list
        const box = document.getElementById('opexSummaryList');
        let html = '';
        for (const [k, v] of Object.entries(m.opex_breakdown)) {
          html += '<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">' +
            '<span>' + k + '</span><span class="badge bg-light text-dark border">₱' + fmt(v) + '</span>' +
          '</div>';
        }
        box.innerHTML = html || '<div class="text-muted text-center py-3">No expenses recorded yet.</div>';

        // Populate Double-Entry General Ledger Table
        const ledgerBody = document.getElementById('ledgerSummaryTable');
        if (ledgerBody) {
          const cashNet = Math.max(0, (m.gross_revenue || 0) - (m.bank_processing_fees || 0));
          const totalDebits = cashNet + (m.landed_cogs || 0) + (m.bank_processing_fees || 0) + (m.sales_staff_incentives || 0) + (m.total_operating_expenses || 0);
          const totalCredits = (m.gross_revenue || 0) + (m.landed_cogs || 0) + (m.sales_staff_incentives || 0) + (m.total_operating_expenses || 0);
          ledgerBody.innerHTML =
            '<tr><td><code>1010</code></td><td>Cash &amp; POS Settlement Clearing</td><td><span class="badge bg-success">Asset</span></td><td class="text-end">₱' + fmt(cashNet) + '</td><td class="text-end">—</td></tr>' +
            '<tr><td><code>4000</code></td><td>POS Merchandise Sales Revenue</td><td><span class="badge bg-primary">Revenue</span></td><td class="text-end">—</td><td class="text-end">₱' + fmt(m.gross_revenue) + '</td></tr>' +
            '<tr><td><code>5010</code></td><td>Cost of Goods Sold (Landed Inventory)</td><td><span class="badge bg-warning text-dark">COGS</span></td><td class="text-end">₱' + fmt(m.landed_cogs) + '</td><td class="text-end">—</td></tr>' +
            '<tr><td><code>1200</code></td><td>Merchandise Inventory Asset</td><td><span class="badge bg-info text-dark">Asset</span></td><td class="text-end">—</td><td class="text-end">₱' + fmt(m.landed_cogs) + '</td></tr>' +
            '<tr><td><code>6050</code></td><td>Payment Gateway &amp; Card Merchant Fees</td><td><span class="badge bg-secondary">Expense</span></td><td class="text-end">₱' + fmt(m.bank_processing_fees) + '</td><td class="text-end">—</td></tr>' +
            '<tr><td><code>6120</code></td><td>Cashier Sales Commission Expense</td><td><span class="badge bg-secondary">Expense</span></td><td class="text-end">₱' + fmt(m.sales_staff_incentives) + '</td><td class="text-end">₱' + fmt(m.sales_staff_incentives) + '</td></tr>' +
            '<tr><td><code>6200</code></td><td>Store Operating Expenses (Power, Rent, Wages)</td><td><span class="badge bg-secondary">Expense</span></td><td class="text-end">₱' + fmt(m.total_operating_expenses) + '</td><td class="text-end">₱' + fmt(m.total_operating_expenses) + '</td></tr>' +
            '<tr class="table-light fw-bold"><td colspan="3">Total Trial Balance Activity</td><td class="text-end text-success">₱' + fmt(totalDebits) + '</td><td class="text-end text-success">₱' + fmt(totalCredits) + '</td></tr>';
        }

        // Render Stepped Waterfall Bar Chart & Periodic Comparison Scale
        renderWaterfallSvg(m);
        updateScaleComparisonBars(m);
        runErpBreakEvenSimulation();
      } catch (err) {
        console.error(err);
      }
    }

    function renderWaterfallSvg(m) {
      const svg = document.getElementById('waterfallSvg');
      if (!svg) return;
      svg.innerHTML = '';

      const gross = parseFloat(m.gross_revenue || 0);
      const cogs = parseFloat(m.landed_cogs || 0);
      const grossMargin = parseFloat(m.gross_margin || 0);
      const fees = parseFloat(m.bank_processing_fees || 0);
      const comm = parseFloat(m.sales_staff_incentives || 0);
      const cm1 = parseFloat(m.contribution_margin_1 || 0);
      const opex = parseFloat(m.total_operating_expenses || 0);
      const net = parseFloat(m.net_operating_profit || 0);

      const fmt = (n) => '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      // Financial steps sequence
      const steps = [
        { label: 'Gross Revenue', val: gross, type: 'pillar', color: '#10b981' },
        { label: 'Landed COGS', val: -cogs, type: 'step', color: '#ef4444' },
        { label: 'Gross Margin', val: grossMargin, type: 'subtotal', color: grossMargin >= 0 ? '#3b82f6' : '#f59e0b' },
        { label: 'Processing Fees', val: -fees, type: 'step', color: '#94a3b8' },
        { label: 'Commissions', val: -comm, type: 'step', color: '#94a3b8' },
        { label: 'Contribution M1', val: cm1, type: 'subtotal', color: cm1 >= 0 ? '#3b82f6' : '#f59e0b' },
        { label: 'Store OPEX', val: -opex, type: 'step', color: '#a855f7' },
        { label: 'Net Profit', val: net, type: 'pillar', color: net >= 0 ? '#10b981' : '#ef4444' }
      ];

      const width = 960;
      const height = 320;
      const paddingX = 40;
      const paddingBottom = 60;
      const paddingTop = 30;
      const usableHeight = height - paddingTop - paddingBottom;
      const colWidth = 72;
      const gap = (width - paddingX * 2 - colWidth * steps.length) / (steps.length - 1);

      const maxVal = Math.max(gross * 1.15, 100);
      const minVal = Math.min(-cogs * 0.4, net * 1.2, -opex, -100);
      const range = Math.max(1, maxVal - minVal);

      const getY = (val) => paddingTop + usableHeight * (1 - (val - minVal) / range);
      const zeroY = getY(0);

      // Draw zero axis line
      const zeroLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      zeroLine.setAttribute('x1', paddingX - 10);
      zeroLine.setAttribute('y1', zeroY);
      zeroLine.setAttribute('x2', width - paddingX + 10);
      zeroLine.setAttribute('y2', zeroY);
      zeroLine.setAttribute('stroke', '#cbd5e1');
      zeroLine.setAttribute('stroke-width', '1.5');
      svg.appendChild(zeroLine);

      let runningTotal = 0;

      steps.forEach((s, i) => {
        const x = paddingX + i * (colWidth + gap);
        let startVal = runningTotal;
        let endVal = runningTotal;

        if (s.type === 'step') {
          endVal = runningTotal + s.val;
          runningTotal = endVal;
        } else {
          startVal = 0;
          endVal = s.val;
          runningTotal = s.val;
        }

        const y1 = getY(startVal);
        const y2 = getY(endVal);
        const barY = Math.min(y1, y2);
        const barH = Math.max(4, Math.abs(y1 - y2));

        if (i > 0) {
          const bridge = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          bridge.setAttribute('x1', x - gap);
          bridge.setAttribute('y1', y1);
          bridge.setAttribute('x2', x);
          bridge.setAttribute('y2', y1);
          bridge.setAttribute('stroke', '#94a3b8');
          bridge.setAttribute('stroke-dasharray', '4 3');
          bridge.setAttribute('stroke-width', '1.5');
          svg.appendChild(bridge);
        }

        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('x', x);
        rect.setAttribute('y', barY);
        rect.setAttribute('width', colWidth);
        rect.setAttribute('height', barH);
        rect.setAttribute('rx', '4');
        rect.setAttribute('fill', s.color);
        rect.setAttribute('opacity', '0.9');
        svg.appendChild(rect);

        const valText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        valText.setAttribute('x', x + colWidth / 2);
        const textY = s.val >= 0 ? barY - 6 : barY + barH + 13;
        valText.setAttribute('y', textY);
        valText.setAttribute('text-anchor', 'middle');
        valText.setAttribute('font-size', '11');
        valText.setAttribute('font-weight', '700');
        valText.setAttribute('fill', s.color);
        valText.textContent = (s.val > 0 ? '+' : '') + fmt(s.val);
        svg.appendChild(valText);

        const labelText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        labelText.setAttribute('x', x + colWidth / 2);
        labelText.setAttribute('y', height - 15);
        labelText.setAttribute('text-anchor', 'middle');
        labelText.setAttribute('font-size', '10.5');
        labelText.setAttribute('font-weight', '600');
        labelText.setAttribute('fill', '#475569');
        labelText.textContent = s.label;
        svg.appendChild(labelText);
      });
    }

    function updateScaleComparisonBars(m) {
      const gross = parseFloat(m.gross_revenue || 0);
      const cogs = parseFloat(m.landed_cogs || 0);
      const opex = parseFloat(m.total_operating_expenses || 0);

      let dailyRev = gross, dailyCogs = cogs, dailyOpex = opex;
      const cur = currentWaterfallPeriod;
      if (cur === 'weekly') {
        dailyRev = gross / 7; dailyCogs = cogs / 7; dailyOpex = opex / 7;
      } else if (cur === 'monthly') {
        dailyRev = gross / 30; dailyCogs = cogs / 30; dailyOpex = opex / 30;
      } else if (cur === 'quarterly') {
        dailyRev = gross / 90; dailyCogs = cogs / 90; dailyOpex = opex / 90;
      } else if (cur === 'yearly') {
        dailyRev = gross / 365; dailyCogs = cogs / 365; dailyOpex = opex / 365;
      }

      const mults = {
        Daily: 1,
        Weekly: 7,
        Monthly: 30,
        Quarterly: 90,
        Yearly: 365
      };

      const maxYearly = Math.max(dailyRev * 365, dailyCogs * 365, 100);

      Object.keys(mults).forEach(k => {
        const factor = mults[k];
        const r = dailyRev * factor;
        const c = dailyCogs * factor;
        const o = (dailyOpex || 666.67) * factor;

        const hR = Math.min(100, Math.max(6, (r / maxYearly) * 100));
        const hC = Math.min(100, Math.max(6, (c / maxYearly) * 100));
        const hO = Math.min(100, Math.max(4, (o / maxYearly) * 100));

        const elR = document.getElementById('barScale' + k + 'Rev');
        const elC = document.getElementById('barScale' + k + 'Cogs');
        const elO = document.getElementById('barScale' + k + 'Opex');
        const lbl = document.getElementById('lblScale' + k);

        if (elR) elR.style.height = hR + '%';
        if (elC) elC.style.height = hC + '%';
        if (elO) elO.style.height = hO + '%';

        if (lbl) {
          const kRev = r >= 1000000 ? (r / 1000000).toFixed(2) + 'M' : (r >= 1000 ? (r / 1000).toFixed(1) + 'k' : r.toFixed(0));
          lbl.textContent = '₱' + kRev;
        }
      });
    }

    function runErpBreakEvenSimulation() {
      const markupInput = document.getElementById('erpSimMarkupInput');
      const opexInput = document.getElementById('erpSimOpexInput');
      const basketInput = document.getElementById('erpSimBasketInput');
      if (!markupInput || !opexInput || !basketInput) return;

      const markup = parseFloat(markupInput.value) || 25;
      const opex = parseFloat(opexInput.value) || 20000;
      const basket = parseFloat(basketInput.value) || 1200;

      const fmt = (n) => '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      document.getElementById('erpSimMarkupVal').textContent = markup.toFixed(1) + '%';
      document.getElementById('erpSimOpexVal').textContent = fmt(opex);
      document.getElementById('erpSimBasketVal').textContent = fmt(basket);

      const markupDecimal = markup / 100;
      const marginRatio = markupDecimal / (1 + markupDecimal);

      const monthlyBreakEven = opex / marginRatio;
      const dailyBreakEven = monthlyBreakEven / 30;
      const ordersPerDay = Math.ceil(dailyBreakEven / basket);

      document.getElementById('erpSimMonthlyBe').textContent = fmt(monthlyBreakEven);
      document.getElementById('erpSimDailyBe').textContent = fmt(dailyBreakEven) + ' / day';
      document.getElementById('erpSimDailyOrders').textContent = ordersPerDay + ' Orders / Day';
    }

    let currentCatalogFilter = 'critical'; // Default: display only critical status items
    let catalogCurrentPage = 1;
    let catalogPageSize = 25;

    let currentWarehouseFilter = 'critical';
    let warehouseCurrentPage = 1;
    let warehousePageSize = 25;

    function escapeHtml(str) {
      if (str === null || str === undefined) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function onCatalogSearchInput() {
      catalogCurrentPage = 1;
      filterCatalogStockTable();
    }

    function onCatalogPageSizeChange() {
      const sel = document.getElementById('catalogPageSize');
      if (sel) {
        const val = sel.value;
        catalogPageSize = (val === 'all') ? 'all' : parseInt(val, 10);
        catalogCurrentPage = 1;
        filterCatalogStockTable();
      }
    }

    function goToCatalogPage(page) {
      catalogCurrentPage = page;
      filterCatalogStockTable();
      const tableEl = document.getElementById('catalogStockTable');
      if (tableEl) {
        const cardEl = tableEl.closest('.card');
        if (cardEl && cardEl.getBoundingClientRect().top < 0) {
          cardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }

    function setCatalogFilter(mode) {
      currentCatalogFilter = mode;
      catalogCurrentPage = 1;
      const btnCrit = document.getElementById('catBtnFilterCritical');
      const btnLow = document.getElementById('catBtnFilterLow');
      const btnAll = document.getElementById('catBtnFilterAll');
      const alertEl = document.getElementById('catalogCriticalAlert');
      const labelEl = document.getElementById('catalogFilterLabel');
      const descEl = document.getElementById('catalogFilterDesc');
      const badgeEl = document.getElementById('criticalBadgeNotice');

      if (btnCrit) {
        btnCrit.className = mode === 'critical' ? 'btn btn-danger active fw-bold' : 'btn btn-outline-danger fw-bold';
      }
      if (btnLow) {
        btnLow.className = mode === 'low' ? 'btn btn-warning active fw-bold text-dark' : 'btn btn-outline-warning fw-bold text-dark';
      }
      if (btnAll) {
        btnAll.className = mode === 'all' ? 'btn btn-secondary active fw-bold' : 'btn btn-outline-secondary fw-bold';
      }

      if (alertEl) {
        if (mode === 'critical') {
          alertEl.className = 'alert alert-danger py-2 px-3 d-flex align-items-center justify-content-between mb-3 small';
          alertEl.style.display = 'flex';
          if (labelEl) labelEl.textContent = 'Displaying Critical Status Items:';
          if (descEl) descEl.textContent = 'Showing items at or below 50% safety stock or out of stock requiring immediate replenishment. Modify stock, safety thresholds, or prices inline and click Save (or press Enter).';
          if (badgeEl) { badgeEl.className = 'badge bg-danger ms-2 text-nowrap'; badgeEl.textContent = 'Critical Filter Active'; }
        } else if (mode === 'low') {
          alertEl.className = 'alert alert-warning py-2 px-3 d-flex align-items-center justify-content-between mb-3 small';
          alertEl.style.display = 'flex';
          if (labelEl) labelEl.textContent = 'Displaying Reorder & Low Stock Items:';
          if (descEl) descEl.textContent = 'Showing all items needing replenishment (at or below safety stock level). Modify values inline and save.';
          if (badgeEl) { badgeEl.className = 'badge bg-warning text-dark ms-2 text-nowrap'; badgeEl.textContent = 'Low Stock Filter'; }
        } else {
          alertEl.className = 'alert alert-light border py-2 px-3 d-flex align-items-center justify-content-between mb-3 small';
          alertEl.style.display = 'flex';
          if (labelEl) labelEl.textContent = 'Displaying All Store Catalog Items:';
          if (descEl) descEl.textContent = 'Showing entire active store inventory across all categories. Inline editing enabled across all products.';
          if (badgeEl) { badgeEl.className = 'badge bg-secondary ms-2 text-nowrap'; badgeEl.textContent = 'All Products'; }
        }
      }

      filterCatalogStockTable();
    }

    function renderCatalogPagination(totalMatching, totalPages, startIdx, endIdx) {
      const infoEl = document.getElementById('catalogPaginationInfo');
      const controlsEl = document.getElementById('catalogPaginationControls');
      if (!infoEl || !controlsEl) return;

      if (totalMatching === 0) {
        infoEl.textContent = 'Showing 0 products';
        controlsEl.innerHTML = '';
        return;
      }

      const displayStart = startIdx + 1;
      const displayEnd = Math.min(startIdx + (catalogPageSize === 'all' ? totalMatching : catalogPageSize), totalMatching);
      infoEl.textContent = 'Showing ' + displayStart + ' to ' + displayEnd + ' of ' + totalMatching.toLocaleString() + ' products';

      if (catalogPageSize === 'all' || totalPages <= 1) {
        controlsEl.innerHTML = '<li class="page-item active"><span class="page-link">1</span></li>';
        return;
      }

      let html = '';

      // First & Previous buttons
      if (catalogCurrentPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToCatalogPage(1)" title="First Page">&laquo;</a></li>';
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToCatalogPage(' + (catalogCurrentPage - 1) + ')" title="Previous Page">&lsaquo;</a></li>';
      } else {
        html += '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        html += '<li class="page-item disabled"><span class="page-link">&lsaquo;</span></li>';
      }

      // Page numbers window
      let pagesToShow = [];
      if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pagesToShow.push(i);
      } else {
        if (catalogCurrentPage <= 4) {
          pagesToShow = [1, 2, 3, 4, 5, '...', totalPages];
        } else if (catalogCurrentPage >= totalPages - 3) {
          pagesToShow = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
          pagesToShow = [1, '...', catalogCurrentPage - 1, catalogCurrentPage, catalogCurrentPage + 1, '...', totalPages];
        }
      }

      for (const p of pagesToShow) {
        if (p === '...') {
          html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        } else if (p === catalogCurrentPage) {
          html += '<li class="page-item active"><span class="page-link fw-bold">' + p + '</span></li>';
        } else {
          html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToCatalogPage(' + p + ')">' + p + '</a></li>';
        }
      }

      // Next & Last buttons
      if (catalogCurrentPage < totalPages) {
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToCatalogPage(' + (catalogCurrentPage + 1) + ')" title="Next Page">&rsaquo;</a></li>';
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToCatalogPage(' + totalPages + ')" title="Last Page">&raquo;</a></li>';
      } else {
        html += '<li class="page-item disabled"><span class="page-link">&rsaquo;</span></li>';
        html += '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
      }

      controlsEl.innerHTML = html;
    }

    function filterCatalogStockTable() {
      const q = (document.getElementById('catalogSearchInput')?.value || '').trim().toLowerCase();
      const rows = document.querySelectorAll('#catalogStockTable tr');
      const matchingRows = [];

      rows.forEach(tr => {
        if (tr.id === 'catalogEmptyRow') return;

        const isCritical = tr.getAttribute('data-critical') === 'true';
        const isLow = tr.getAttribute('data-low') === 'true';
        const searchHaystack = (tr.getAttribute('data-search') || tr.textContent).toLowerCase();

        // Status filter condition
        let matchesStatus = false;
        if (currentCatalogFilter === 'critical') {
          matchesStatus = isCritical;
        } else if (currentCatalogFilter === 'low') {
          matchesStatus = isCritical || isLow;
        } else {
          matchesStatus = true; // 'all'
        }

        // Search text condition
        const matchesSearch = !q || searchHaystack.includes(q);

        if (matchesStatus && matchesSearch) {
          matchingRows.push(tr);
        } else {
          tr.style.display = 'none';
        }
      });

      const totalMatching = matchingRows.length;
      const pageSize = (catalogPageSize === 'all') ? (totalMatching || 1) : parseInt(catalogPageSize, 10);
      const totalPages = (pageSize > 0 && totalMatching > 0) ? Math.ceil(totalMatching / pageSize) : 1;

      if (catalogCurrentPage > totalPages) {
        catalogCurrentPage = totalPages;
      }
      if (catalogCurrentPage < 1) {
        catalogCurrentPage = 1;
      }

      const startIdx = (catalogCurrentPage - 1) * pageSize;
      const endIdx = startIdx + pageSize;

      matchingRows.forEach((tr, idx) => {
        if (catalogPageSize === 'all' || (idx >= startIdx && idx < endIdx)) {
          tr.style.display = '';
        } else {
          tr.style.display = 'none';
        }
      });

      let emptyRow = document.getElementById('catalogEmptyRow');
      if (totalMatching === 0) {
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.id = 'catalogEmptyRow';
          const catBody = document.getElementById('catalogStockTable');
          if (catBody) catBody.appendChild(emptyRow);
        }
        emptyRow.style.display = '';
        const filterDesc = currentCatalogFilter === 'critical' ? 'critical status' : (currentCatalogFilter === 'low' ? 'low stock' : '');
        emptyRow.innerHTML = '<td colspan="13" class="text-center text-muted py-4">' +
          '<i class="fa fa-info-circle fa-lg text-secondary mb-2 d-block"></i>' +
          'No ' + filterDesc + ' products match ' + (q ? ('search &quot;' + escapeHtml(q) + '&quot;') : 'the active filter') + '.' +
          '<br><button class="btn btn-sm btn-outline-secondary mt-2" onclick="setCatalogFilter(&apos;all&apos;)">View All Catalog Items</button>' +
        '</td>';
      } else if (emptyRow) {
        emptyRow.style.display = 'none';
      }

      renderCatalogPagination(totalMatching, totalPages, startIdx, endIdx);
    }

    function onWarehouseSearchInput() {
      warehouseCurrentPage = 1;
      filterWarehouseStockTable();
    }

    function onWarehousePageSizeChange() {
      const sel = document.getElementById('warehousePageSize');
      if (sel) {
        const val = sel.value;
        warehousePageSize = (val === 'all') ? 'all' : parseInt(val, 10);
        warehouseCurrentPage = 1;
        filterWarehouseStockTable();
      }
    }

    function goToWarehousePage(page) {
      warehouseCurrentPage = page;
      filterWarehouseStockTable();
      const tableEl = document.getElementById('warehouseStockTable');
      if (tableEl) {
        const cardEl = tableEl.closest('.card');
        if (cardEl && cardEl.getBoundingClientRect().top < 0) {
          cardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }

    function setWarehouseFilter(mode) {
      currentWarehouseFilter = mode;
      warehouseCurrentPage = 1;
      const btnCrit = document.getElementById('whBtnFilterCritical');
      const btnLow = document.getElementById('whBtnFilterLow');
      const btnAll = document.getElementById('whBtnFilterAll');
      const alertEl = document.getElementById('warehouseCriticalAlert');
      const labelEl = document.getElementById('warehouseFilterLabel');
      const descEl = document.getElementById('warehouseFilterDesc');
      const badgeEl = document.getElementById('warehouseCriticalBadgeNotice');

      if (btnCrit) {
        btnCrit.className = mode === 'critical' ? 'btn btn-danger active fw-bold' : 'btn btn-outline-danger fw-bold';
      }
      if (btnLow) {
        btnLow.className = mode === 'low' ? 'btn btn-warning active fw-bold text-dark' : 'btn btn-outline-warning fw-bold text-dark';
      }
      if (btnAll) {
        btnAll.className = mode === 'all' ? 'btn btn-secondary active fw-bold' : 'btn btn-outline-secondary fw-bold';
      }

      if (alertEl) {
        if (mode === 'critical') {
          alertEl.className = 'alert alert-danger py-2 px-3 d-flex align-items-center justify-content-between mb-3 small';
          alertEl.style.display = 'flex';
          if (labelEl) labelEl.textContent = 'Displaying Critical Status Items:';
          if (descEl) descEl.textContent = 'Showing warehouse items at or below 50% safety stock or out of stock requiring immediate replenishment.';
          if (badgeEl) { badgeEl.className = 'badge bg-danger ms-2 text-nowrap'; badgeEl.textContent = 'Critical Filter Active'; }
        } else if (mode === 'low') {
          alertEl.className = 'alert alert-warning py-2 px-3 d-flex align-items-center justify-content-between mb-3 small';
          alertEl.style.display = 'flex';
          if (labelEl) labelEl.textContent = 'Displaying Reorder & Low Stock Items:';
          if (descEl) descEl.textContent = 'Showing all warehouse items needing replenishment (at or below safety stock level).';
          if (badgeEl) { badgeEl.className = 'badge bg-warning text-dark ms-2 text-nowrap'; badgeEl.textContent = 'Low Stock Filter'; }
        } else {
          alertEl.className = 'alert alert-light border py-2 px-3 d-flex align-items-center justify-content-between mb-3 small';
          alertEl.style.display = 'flex';
          if (labelEl) labelEl.textContent = 'Displaying All Warehouse Items:';
          if (descEl) descEl.textContent = 'Showing entire warehouse inventory across all categories. Inline editing enabled across all products.';
          if (badgeEl) { badgeEl.className = 'badge bg-secondary ms-2 text-nowrap'; badgeEl.textContent = 'All Items'; }
        }
      }

      filterWarehouseStockTable();
    }

    function renderWarehousePagination(totalMatching, totalPages, startIdx, endIdx) {
      const infoEl = document.getElementById('warehousePaginationInfo');
      const controlsEl = document.getElementById('warehousePaginationControls');
      if (!infoEl || !controlsEl) return;

      if (totalMatching === 0) {
        infoEl.textContent = 'Showing 0 products';
        controlsEl.innerHTML = '';
        return;
      }

      const displayStart = startIdx + 1;
      const displayEnd = Math.min(startIdx + (warehousePageSize === 'all' ? totalMatching : warehousePageSize), totalMatching);
      infoEl.textContent = 'Showing ' + displayStart + ' to ' + displayEnd + ' of ' + totalMatching + ' products';

      if (totalPages <= 1) {
        controlsEl.innerHTML = '';
        return;
      }

      let html = '';
      if (warehouseCurrentPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToWarehousePage(1)" title="First Page">&laquo;</a></li>';
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToWarehousePage(' + (warehouseCurrentPage - 1) + ')" title="Previous Page">&lsaquo;</a></li>';
      } else {
        html += '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        html += '<li class="page-item disabled"><span class="page-link">&lsaquo;</span></li>';
      }

      let pagesToShow = [];
      if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pagesToShow.push(i);
      } else {
        if (warehouseCurrentPage <= 4) {
          pagesToShow = [1, 2, 3, 4, 5, '...', totalPages];
        } else if (warehouseCurrentPage >= totalPages - 3) {
          pagesToShow = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
          pagesToShow = [1, '...', warehouseCurrentPage - 1, warehouseCurrentPage, warehouseCurrentPage + 1, '...', totalPages];
        }
      }

      pagesToShow.forEach(p => {
        if (p === '...') {
          html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        } else if (p === warehouseCurrentPage) {
          html += '<li class="page-item active"><span class="page-link fw-bold">' + p + '</span></li>';
        } else {
          html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToWarehousePage(' + p + ')">' + p + '</a></li>';
        }
      });

      if (warehouseCurrentPage < totalPages) {
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToWarehousePage(' + (warehouseCurrentPage + 1) + ')" title="Next Page">&rsaquo;</a></li>';
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToWarehousePage(' + totalPages + ')" title="Last Page">&raquo;</a></li>';
      } else {
        html += '<li class="page-item disabled"><span class="page-link">&rsaquo;</span></li>';
        html += '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
      }

      controlsEl.innerHTML = html;
    }

    function filterWarehouseStockTable() {
      const q = (document.getElementById('warehouseSearchInput')?.value || '').trim().toLowerCase();
      const rows = document.querySelectorAll('#warehouseStockTable tr');
      const matchingRows = [];

      rows.forEach(tr => {
        if (tr.id === 'warehouseEmptyRow') return;

        const isCritical = tr.getAttribute('data-critical') === 'true';
        const isLow = tr.getAttribute('data-low') === 'true';
        const searchHaystack = (tr.getAttribute('data-search') || tr.textContent).toLowerCase();

        let matchesStatus = false;
        if (currentWarehouseFilter === 'critical') {
          matchesStatus = isCritical;
        } else if (currentWarehouseFilter === 'low') {
          matchesStatus = isLow || isCritical;
        } else {
          matchesStatus = true;
        }

        let matchesQuery = true;
        if (q) {
          matchesQuery = searchHaystack.includes(q);
        }

        if (matchesStatus && matchesQuery) {
          matchingRows.push(tr);
        } else {
          tr.style.display = 'none';
        }
      });

      const totalMatching = matchingRows.length;
      const pageSize = (warehousePageSize === 'all') ? totalMatching : warehousePageSize;
      const totalPages = Math.max(1, Math.ceil(totalMatching / (pageSize || 1)));

      if (warehouseCurrentPage > totalPages) {
        warehouseCurrentPage = totalPages;
      }
      if (warehouseCurrentPage < 1) {
        warehouseCurrentPage = 1;
      }

      const startIdx = (warehouseCurrentPage - 1) * pageSize;
      const endIdx = startIdx + pageSize;

      matchingRows.forEach((tr, idx) => {
        if (warehousePageSize === 'all' || (idx >= startIdx && idx < endIdx)) {
          tr.style.display = '';
        } else {
          tr.style.display = 'none';
        }
      });

      let emptyRow = document.getElementById('warehouseEmptyRow');
      if (totalMatching === 0) {
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.id = 'warehouseEmptyRow';
          const whBody = document.getElementById('warehouseStockTable');
          if (whBody) whBody.appendChild(emptyRow);
        }
        emptyRow.style.display = '';
        const filterDesc = currentWarehouseFilter === 'critical' ? 'critical status' : (currentWarehouseFilter === 'low' ? 'low stock' : '');
        emptyRow.innerHTML = '<td colspan="15" class="text-center text-muted py-4">' +
          '<i class="fa fa-info-circle fa-lg text-secondary mb-2 d-block"></i>' +
          'No ' + filterDesc + ' products match ' + (q ? ('search &quot;' + escapeHtml(q) + '&quot;') : 'the active filter') + '.' +
          '<br><button class="btn btn-sm btn-outline-secondary mt-2" onclick="setWarehouseFilter(&apos;all&apos;)">View All Warehouse Items</button>' +
        '</td>';
      } else if (emptyRow) {
        emptyRow.style.display = 'none';
      }

      renderWarehousePagination(totalMatching, totalPages, startIdx, endIdx);
    }

    let categoriesList = [];

    async function loadCategories() {
      if (categoriesList && categoriesList.length > 0) return categoriesList;
      try {
        const res = await fetch('/api/categories');
        if (res.ok) {
          categoriesList = await res.json();
        }
      } catch (err) {
        console.error('Failed to load categories:', err);
      }
      return categoriesList || [];
    }

    let currentPhotoBase64 = null;

    function getProductPhotoUrl(photo) {
      if (!photo) return '/assets/uploads/product_photos/general_products.png';
      if (photo.startsWith('http://') || photo.startsWith('https://') || photo.startsWith('/')) return photo;
      return '/assets/uploads/' + photo;
    }

    function openEditPhotoModal(productId) {
      const prod = (productsList || []).find(p => p.p_id === productId);
      const hiddenInput = document.getElementById('inlinePhoto_' + productId);
      const currentPhoto = hiddenInput ? hiddenInput.value : (prod ? prod.p_featured_photo : '');

      document.getElementById('photoModalPid').value = productId;
      document.getElementById('photoModalTitle').textContent = prod ? ('#' + prod.p_id + ' - ' + prod.p_name) : ('Product #' + productId);
      document.getElementById('photoModalPathInput').value = currentPhoto || '';
      document.getElementById('photoModalFileInput').value = '';
      document.getElementById('photoModalPreview').src = getProductPhotoUrl(currentPhoto);
      currentPhotoBase64 = null;

      const modalEl = document.getElementById('productPhotoModal');
      if (window.bootstrap && modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
      } else {
        promptEditProductPhoto(productId);
      }
    }

    function previewSelectedPhotoFile(event) {
      const file = event.target.files && event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          currentPhotoBase64 = e.target.result;
          document.getElementById('photoModalPreview').src = currentPhotoBase64;
        };
        reader.readAsDataURL(file);
      }
    }

    function previewEnteredPhotoPath() {
      const val = document.getElementById('photoModalPathInput').value.trim();
      currentPhotoBase64 = null;
      document.getElementById('photoModalPreview').src = getProductPhotoUrl(val);
    }

    async function saveProductPhotoFromModal() {
      const pid = parseInt(document.getElementById('photoModalPid').value, 10);
      const pathVal = document.getElementById('photoModalPathInput').value.trim();
      const btn = document.getElementById('btnSavePhotoModal');
      
      if (!pid) return;

      if (currentPhotoBase64) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';
        try {
          const fileInput = document.getElementById('photoModalFileInput');
          const filename = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : '';
          const res = await fetch('/api/products/upload-photo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              p_id: pid,
              filename: filename,
              image_base64: currentPhotoBase64
            })
          });
          const data = await res.json();
          if (res.ok && data.success) {
            const newPhoto = data.p_featured_photo;
            const hiddenInput = document.getElementById('inlinePhoto_' + pid);
            if (hiddenInput) hiddenInput.value = newPhoto;
            const imgEl = document.getElementById('inlineImg_' + pid);
            if (imgEl) imgEl.src = getProductPhotoUrl(newPhoto);
            markCatalogRowDirty(pid);
            showErpToast('Photo Uploaded', 'New photo saved for product #' + pid, 'success');
            
            const modalEl = document.getElementById('productPhotoModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
          } else {
            showErpToast('Upload Failed', data.error || 'Could not upload photo', 'danger');
          }
        } catch (err) {
          showErpToast('Error', err.message, 'danger');
        } finally {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-check"></i> Apply Photo';
        }
      } else {
        const hiddenInput = document.getElementById('inlinePhoto_' + pid);
        if (hiddenInput) hiddenInput.value = pathVal;
        const imgEl = document.getElementById('inlineImg_' + pid);
        if (imgEl) imgEl.src = getProductPhotoUrl(pathVal);
        markCatalogRowDirty(pid);
        
        const modalEl = document.getElementById('productPhotoModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
    }

    function promptEditProductPhoto(productId) {
      const currentPhotoInput = document.getElementById('inlinePhoto_' + productId);
      const currentPhoto = currentPhotoInput ? currentPhotoInput.value : '';
      const newPhoto = prompt('Enter image filename or path in assets/uploads/ (e.g. rotary_hammer.jpg, product-featured-56.jpg):', currentPhoto);
      if (newPhoto !== null) {
        const cleanPhoto = newPhoto.trim();
        if (currentPhotoInput) currentPhotoInput.value = cleanPhoto;
        const imgEl = document.getElementById('inlineImg_' + productId);
        if (imgEl) imgEl.src = getProductPhotoUrl(cleanPhoto);
        markCatalogRowDirty(productId);
      }
    }

    let parsedExcelProducts = [];

    function openExcelUploadModal() {
      parsedExcelProducts = [];
      const fileInput = document.getElementById('excelProductFileInput');
      if (fileInput) fileInput.value = '';
      
      const prevSection = document.getElementById('excelPreviewSection');
      if (prevSection) prevSection.style.display = 'none';

      const alertEl = document.getElementById('excelImportResultAlert');
      if (alertEl) alertEl.style.display = 'none';

      const btnExec = document.getElementById('btnExecuteExcelImport');
      if (btnExec) {
        btnExec.disabled = true;
        btnExec.className = 'btn btn-success btn-sm fw-bold px-3';
        btnExec.innerHTML = '<i class="fa fa-database me-1"></i> Apply Updates to PostgreSQL (1-Click)';
      }

      const modalEl = document.getElementById('excelUploadModal');
      if (window.bootstrap && modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
      }
    }

    function downloadCurrentCatalogExcel() {
      window.location.href = '/api/products/export-excel?supplier_id=' + currentSupplierId;
    }

    function handleExcelFileSelect(event) {
      const file = event.target.files && event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function(e) {
        try {
          const data = new Uint8Array(e.target.result);
          if (typeof XLSX === 'undefined') {
            alert('Spreadsheet parser library is loading. Please try again in a moment.');
            return;
          }
          const workbook = XLSX.read(data, { type: 'array' });
          const firstSheetName = workbook.SheetNames[0];
          const worksheet = workbook.Sheets[firstSheetName];
          const rawRows = XLSX.utils.sheet_to_json(worksheet, { defval: '' });

          if (!rawRows || rawRows.length === 0) {
            alert('No data rows found in the selected Excel sheet.');
            return;
          }

          // Build fast lookup set of current products in store
          const currentIds = new Set((productsList || []).map(p => p.p_id));
          const currentSkus = new Set((productsList || []).filter(p => p.p_sku).map(p => p.p_sku.trim().toLowerCase()));
          const currentNames = new Set((productsList || []).filter(p => p.p_name).map(p => p.p_name.trim().toLowerCase()));

          parsedExcelProducts = [];
          let matchCount = 0;
          let newCount = 0;

          rawRows.forEach(row => {
            let rawPid = '';
            let p_sku = '';
            let p_name = '';
            let category = '';
            let p_qty = '';
            let p_new_qty = '';
            let p_s_level = '';
            let p_capital_price = '';
            let p_markup = '';
            let markup_percent = '';
            let p_current_price = '';
            let p_featured_photo = '';
            let unit = '';
            let supplier = '';

            for (const key of Object.keys(row)) {
              const cleanKey = key.trim().toLowerCase().replace(/[\\s_]+/g, ' ');
              const rawVal = row[key];
              const val = (rawVal !== null && rawVal !== undefined) ? String(rawVal).trim() : '';

              if (['id', 'p id', 'product id', '#', 'code', 'product code', 'item code'].indexOf(cleanKey) !== -1) {
                if (!rawPid) rawPid = val;
                if (!p_sku) p_sku = val;
              } else if (['sku', 'p sku', 'barcode', 'item sku'].indexOf(cleanKey) !== -1) {
                p_sku = val;
              } else if (['product name', 'name', 'p name', 'title', 'description', 'item description', 'desc', 'product description'].indexOf(cleanKey) !== -1) {
                p_name = val;
              } else if (['category', 'category name', 'ecat id', 'cat', 'end category'].indexOf(cleanKey) !== -1) {
                category = val;
              } else if (['available stock', 'available stock (q)', 'stock', 'qty', 'quantity', 'q', 'p qty', 'current stock', 'on hand'].indexOf(cleanKey) !== -1) {
                p_qty = val;
              } else if (['new batch', 'new batch (nq)', 'nq', 'p new qty', 'incoming', 'incoming stock'].indexOf(cleanKey) !== -1) {
                p_new_qty = val;
              } else if (['minimum stock', 'min stock', 'safety level', 'safety level (s)', 'safety stock', 'alert level', 's', 's level', 'p s level'].indexOf(cleanKey) !== -1) {
                p_s_level = val;
              } else if (['capital cost', 'landed cost', 'cost', 'cost price', 'capital price', 'p capital price', 'ca', 'unit cost'].indexOf(cleanKey) !== -1) {
                p_capital_price = val;
              } else if (['markup percent', 'markup %', 'markup percentage'].indexOf(cleanKey) !== -1) {
                markup_percent = val;
              } else if (['markup', 'markup (php)', 'markup (₱)', 'p markup', 'markup amount'].indexOf(cleanKey) !== -1) {
                p_markup = val;
              } else if (['selling price', 'retail price', 'retail', 'current price', 'price', 'p current price', 'srp'].indexOf(cleanKey) !== -1) {
                p_current_price = val;
              } else if (['unit', 'unit of measure', 'uom'].indexOf(cleanKey) !== -1) {
                unit = val;
              } else if (['supplier', 'vendor', 'brand', 'supplier name'].indexOf(cleanKey) !== -1) {
                supplier = val;
              } else if (['photo', 'image', 'featured photo', 'p featured photo', 'picture'].indexOf(cleanKey) !== -1) {
                p_featured_photo = val;
              }
            }

            const pidNum = parseInt(String(rawPid).replace(/[^0-9]/g, ''), 10) || 0;
            const cleanName = String(p_name || '').trim();
            const cleanSku = String(p_sku || '').trim();

            if (!cleanName && !pidNum) return;

            let costVal = parseFloat(String(p_capital_price).replace(/[^0-9.-]/g, '')) || 0;
            let retailVal = parseFloat(String(p_current_price).replace(/[^0-9.-]/g, '')) || 0;
            let markupVal = parseFloat(String(p_markup).replace(/[^0-9.-]/g, '')) || 0;
            const markupPctVal = parseFloat(String(markup_percent).replace(/[^0-9.-]/g, '')) || 0;

            if (retailVal > 0 && costVal > 0 && markupVal === 0) {
              markupVal = retailVal - costVal;
            } else if (retailVal === 0 && costVal > 0 && markupPctVal > 0) {
              markupVal = costVal * (markupPctVal / 100);
              retailVal = costVal + markupVal;
            } else if (retailVal === 0 && costVal > 0 && markupVal > 0) {
              retailVal = costVal + markupVal;
            } else if (markupVal === 0 && costVal > 0 && markupPctVal > 0) {
              markupVal = costVal * (markupPctVal / 100);
            }

            const isMatched = (pidNum > 0 && currentIds.has(pidNum)) ||
                              (cleanSku && currentSkus.has(cleanSku.toLowerCase())) ||
                              (cleanName && currentNames.has(cleanName.toLowerCase()));

            if (isMatched) {
              matchCount++;
            } else {
              newCount++;
            }

            parsedExcelProducts.push({
              p_id: pidNum,
              p_sku: cleanSku,
              p_name: cleanName,
              category: String(category || '').trim(),
              p_qty: p_qty,
              p_new_qty: p_new_qty,
              p_s_level: p_s_level,
              p_capital_price: costVal,
              p_markup: markupVal,
              markup_percent: markupPctVal,
              p_current_price: retailVal,
              unit: unit,
              supplier: supplier,
              p_featured_photo: String(p_featured_photo || '').trim(),
              is_matched: isMatched
            });
          });

          // Update Preview Stats
          document.getElementById('excelStatTotal').textContent = parsedExcelProducts.length.toLocaleString();
          document.getElementById('excelStatUpdate').textContent = matchCount.toLocaleString();
          document.getElementById('excelStatInsert').textContent = newCount.toLocaleString();

          // Render first 6 preview rows
          const tbody = document.getElementById('excelPreviewTableBody');
          const previewRows = parsedExcelProducts.slice(0, 6);
          let previewHtml = '';

          previewRows.forEach((r, idx) => {
            const costVal = parseFloat(r.p_capital_price) || 0;
            const markupVal = parseFloat(r.p_markup) || 0;
            const retailVal = parseFloat(r.p_current_price) || 0;
            const badge = r.is_matched
              ? '<span class="badge bg-success bg-opacity-10 text-success border border-success">Update Existing</span>'
              : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Insert New</span>';

            previewHtml += '<tr>' +
              '<td>' + (idx + 1) + '</td>' +
              '<td><code>' + (r.p_id ? ('#' + r.p_id) : '—') + '</code></td>' +
              '<td><span class="font-monospace small">' + escapeHtml(r.p_sku || '—') + '</span></td>' +
              '<td><strong>' + escapeHtml(r.p_name || '—') + '</strong></td>' +
              '<td>' + escapeHtml(r.category || 'General') + '</td>' +
              '<td class="text-center fw-bold">' + (r.p_qty || 0) + '</td>' +
              '<td class="text-end">₱' + costVal.toFixed(2) + '</td>' +
              '<td class="text-end text-success">+₱' + markupVal.toFixed(2) + '</td>' +
              '<td class="text-end text-primary fw-bold">₱' + retailVal.toFixed(2) + '</td>' +
              '<td>' + badge + '</td>' +
            '</tr>';
          });

          tbody.innerHTML = previewHtml;

          document.getElementById('excelPreviewSection').style.display = 'block';
          const btnExec = document.getElementById('btnExecuteExcelImport');
          btnExec.disabled = false;
          btnExec.innerHTML = '<i class="fa fa-database me-1"></i> Apply Updates to PostgreSQL (' + parsedExcelProducts.length + ' Items)';
        } catch (err) {
          console.error('[Excel Parse Error]:', err);
          alert('Error parsing Excel file: ' + err.message);
        }
      };
      reader.readAsArrayBuffer(file);
    }

    async function executeBatchExcelImport() {
      if (!parsedExcelProducts || parsedExcelProducts.length === 0) {
        alert('Please choose an Excel file to import first.');
        return;
      }

      const btnExec = document.getElementById('btnExecuteExcelImport');
      const alertEl = document.getElementById('excelImportResultAlert');
      const updateExisting = document.getElementById('chkExcelUpdateExisting')?.checked ?? true;
      const insertNew = document.getElementById('chkExcelInsertNew')?.checked ?? true;

      btnExec.disabled = true;
      btnExec.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Updating PostgreSQL Database...';

      try {
        const res = await fetch('/api/products/batch-import-excel', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            supplier_id: currentSupplierId,
            products: parsedExcelProducts,
            update_existing: updateExisting,
            insert_new: insertNew
          })
        });

        const data = await res.json();
        if (res.ok && data.success) {
          alertEl.className = 'alert alert-success mt-3 small shadow-sm';
          alertEl.innerHTML = '<div class="fw-bold"><i class="fa fa-check-circle fa-lg me-1"></i> PostgreSQL Database Updated Successfully!</div>' +
            '<div class="mt-1">' + data.message + '</div>' +
            '<div class="mt-2 text-muted" style="font-size: 11px;">Store inventory and POS stock thresholds have been synchronized in active database table.</div>';
          alertEl.style.display = 'block';

          btnExec.className = 'btn btn-secondary btn-sm';
          btnExec.innerHTML = '<i class="fa fa-check me-1"></i> Completed';

          showErpToast('Excel Import Success', data.message, 'success');

          // Refresh catalog table
          await loadProducts();
        } else {
          alertEl.className = 'alert alert-danger mt-3 small';
          alertEl.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i> ' + (data.error || 'Failed to update database.');
          alertEl.style.display = 'block';
          btnExec.disabled = false;
          btnExec.innerHTML = '<i class="fa fa-database me-1"></i> Retry Import';
        }
      } catch (err) {
        alertEl.className = 'alert alert-danger mt-3 small';
        alertEl.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i> Network error: ' + err.message;
        alertEl.style.display = 'block';
        btnExec.disabled = false;
        btnExec.innerHTML = '<i class="fa fa-database me-1"></i> Retry Import';
      }
    }

    function onCatalogInlineChange(productId) {
      const qtyInput = document.getElementById('inlineQty_' + productId);
      const sInput = document.getElementById('inlineSLevel_' + productId);
      if (!qtyInput || !sInput) return;

      const qty = parseInt(qtyInput.value || 0, 10);
      const sLevel = parseInt(sInput.value || 10, 10);
      const threshold = sLevel > 0 ? sLevel : 10;

      const isCrit = (qty <= 0) || (qty <= Math.ceil(threshold * 0.5));
      const isLow = (qty > Math.ceil(threshold * 0.5)) && (qty <= threshold);

      let statusBadge = '';
      if (qty <= 0) {
        statusBadge = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i> Out of Stock (0)</span>';
      } else if (qty <= Math.ceil(threshold * 0.5)) {
        statusBadge = '<span class="badge bg-danger bg-opacity-75"><i class="fa fa-exclamation-circle"></i> Critical (' + qty + ')</span>';
      } else if (qty <= threshold) {
        statusBadge = '<span class="badge bg-warning text-dark"><i class="fa fa-bell-o"></i> Low Stock (' + qty + ')</span>';
      } else {
        statusBadge = '<span class="badge bg-success"><i class="fa fa-check-circle"></i> In Stock (' + qty + ')</span>';
      }

      const statusEl = document.getElementById('inlineStatus_' + productId);
      if (statusEl) statusEl.innerHTML = statusBadge;

      const tr = document.getElementById('catalogRow_' + productId);
      if (tr) {
        tr.setAttribute('data-critical', isCrit ? 'true' : 'false');
        tr.setAttribute('data-low', isLow ? 'true' : 'false');
      }

      if (qty <= 0) {
        qtyInput.className = 'form-control form-control-sm text-center fw-bold text-danger border-danger';
      } else if (isCrit) {
        qtyInput.className = 'form-control form-control-sm text-center fw-bold text-danger';
      } else {
        qtyInput.className = 'form-control form-control-sm text-center fw-bold text-dark';
      }

      markCatalogRowDirty(productId);
    }

    function onCatalogCapChange(productId) {
      const capInput = document.getElementById('inlineCap_' + productId);
      const markupInput = document.getElementById('inlineMarkup_' + productId);
      const retailInput = document.getElementById('inlineRetail_' + productId);
      if (!capInput || !markupInput || !retailInput) return;

      const cap = parseFloat(capInput.value || 0);
      const markup = parseFloat(markupInput.value || 0);
      const retail = Math.max(0, cap + markup);
      retailInput.value = retail.toFixed(2);
      markCatalogRowDirty(productId);
    }

    function onCatalogMarkupChange(productId) {
      const capInput = document.getElementById('inlineCap_' + productId);
      const markupInput = document.getElementById('inlineMarkup_' + productId);
      const retailInput = document.getElementById('inlineRetail_' + productId);
      if (!capInput || !markupInput || !retailInput) return;

      const cap = parseFloat(capInput.value || 0);
      const markup = parseFloat(markupInput.value || 0);
      const retail = Math.max(0, cap + markup);
      retailInput.value = retail.toFixed(2);
      markCatalogRowDirty(productId);
    }

    function onCatalogRetailChange(productId) {
      const capInput = document.getElementById('inlineCap_' + productId);
      const markupInput = document.getElementById('inlineMarkup_' + productId);
      const retailInput = document.getElementById('inlineRetail_' + productId);
      if (!capInput || !markupInput || !retailInput) return;

      const cap = parseFloat(capInput.value || 0);
      const retail = parseFloat(retailInput.value || 0);
      const markup = Math.max(0, retail - cap);
      markupInput.value = markup.toFixed(2);
      markCatalogRowDirty(productId);
    }

    function markCatalogRowDirty(productId) {
      const btn = document.getElementById('btnSaveInline_' + productId);
      if (btn && !btn.disabled) {
        btn.className = 'btn btn-success btn-sm py-0 px-2 text-nowrap shadow-sm';
        btn.innerHTML = '<i class="fa fa-save"></i> Save *';
      }
    }

    async function saveCatalogInline(productId) {
      const btn = document.getElementById('btnSaveInline_' + productId);
      const nameInput = document.getElementById('inlineName_' + productId);
      const skuInput = document.getElementById('inlineSku_' + productId);
      const photoInput = document.getElementById('inlinePhoto_' + productId);
      const ecatInput = document.getElementById('inlineEcat_' + productId);
      const qtyInput = document.getElementById('inlineQty_' + productId);
      const nqInput = document.getElementById('inlineNq_' + productId);
      const sInput = document.getElementById('inlineSLevel_' + productId);
      const capInput = document.getElementById('inlineCap_' + productId);
      const markupInput = document.getElementById('inlineMarkup_' + productId);
      const retInput = document.getElementById('inlineRetail_' + productId);

      if (!btn || !qtyInput || !sInput || !capInput || !retInput) return;

      const item = (productsList || []).find(p => p.p_id === productId) || {};
      const p_name = nameInput ? nameInput.value.trim() : (item.p_name || '');
      const p_sku = skuInput ? skuInput.value.trim() : (item.p_sku || '');
      const p_featured_photo = photoInput ? photoInput.value.trim() : (item.p_featured_photo || '');
      const ecat_id = ecatInput ? parseInt(ecatInput.value || 0, 10) : (item.ecat_id || 0);
      const p_qty = parseInt(qtyInput.value || 0, 10);
      const p_new_qty = nqInput ? parseInt(nqInput.value || 0, 10) : 0;
      const p_s_level = parseInt(sInput.value || 10, 10);
      const p_capital_price = parseFloat(capInput.value || 0);
      const p_markup = markupInput ? parseFloat(markupInput.value || 0) : Math.max(0, parseFloat(retInput.value || 0) - p_capital_price);
      const p_current_price = parseFloat(retInput.value || 0);

      btn.disabled = true;
      btn.className = 'btn btn-secondary btn-sm py-0 px-2 text-nowrap';
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

      try {
        const res = await fetch('/api/products/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            p_id: productId,
            p_name,
            p_sku,
            p_featured_photo,
            ecat_id,
            p_qty,
            p_new_qty,
            p_s_level,
            p_capital_price,
            p_markup,
            p_current_price
          })
        });

        const data = await res.json();
        if (res.ok && data.success) {
          const item = (productsList || []).find(p => p.p_id === productId);
          if (item) {
            item.p_name = p_name;
            item.p_sku = p_sku;
            item.p_featured_photo = p_featured_photo;
            item.ecat_id = ecat_id;
            item.p_qty = p_qty;
            item.p_new_qty = p_new_qty;
            item.p_s_level = p_s_level;
            item.p_capital_price = p_capital_price.toFixed(2);
            item.p_markup = p_markup.toFixed(2);
            item.p_current_price = p_current_price.toFixed(2);

            const catObj = (categoriesList || []).find(c => c.ecat_id === ecat_id);
            const catName = catObj ? catObj.ecat_name : '';
            item.ecat_name = catName;
            const searchTokens = (productId + ' ' + p_name + ' ' + p_sku + ' ' + catName).toLowerCase();
            const tr = document.getElementById('catalogRow_' + productId);
            if (tr) tr.setAttribute('data-search', searchTokens);
          }

          btn.className = 'btn btn-success btn-sm py-0 px-2 text-nowrap';
          btn.innerHTML = '<i class="fa fa-check"></i> Saved';

          showErpToast('Catalog Updated', 'Product #' + productId + ' updated in active POS database.', 'success');

          setTimeout(() => {
            btn.disabled = false;
            btn.className = 'btn btn-outline-success btn-sm py-0 px-2 text-nowrap';
            btn.innerHTML = '<i class="fa fa-save"></i> Save';
          }, 1500);

          updateCatalogFilterCounters();
        } else {
          btn.disabled = false;
          btn.className = 'btn btn-danger btn-sm py-0 px-2 text-nowrap';
          btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Retry';
          showErpToast('Update Failed', data.error || 'Could not save updates.', 'danger');
        }
      } catch (err) {
        btn.disabled = false;
        btn.className = 'btn btn-danger btn-sm py-0 px-2 text-nowrap';
        btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Retry';
        showErpToast('Network Error', err.message || 'Connection error while saving.', 'danger');
      }
    }

    function updateCatalogFilterCounters() {
      if (!productsList) return;
      let critCount = 0;
      let lowCount = 0;

      for (const p of productsList) {
        const qty = parseInt(p.p_qty || 0, 10);
        const sLevel = parseInt(p.p_s_level || 10, 10);
        const threshold = sLevel > 0 ? sLevel : 10;
        if (qty <= 0 || qty <= Math.ceil(threshold * 0.5)) {
          critCount++;
        } else if (qty <= threshold) {
          lowCount++;
        }
      }

      const elCrit = document.getElementById('catCountCritical');
      if (elCrit) elCrit.textContent = critCount.toLocaleString();

      const elLow = document.getElementById('catCountLow');
      if (elLow) elLow.textContent = lowCount.toLocaleString();

      const elAll = document.getElementById('catCountAll');
      if (elAll) elAll.textContent = productsList.length.toLocaleString();
    }

    function updateWarehouseFilterCounters() {
      if (!productsList) return;
      let critCount = 0;
      let lowCount = 0;

      for (const p of productsList) {
        const qty = parseInt(p.p_qty || 0, 10);
        const sLevel = parseInt(p.p_s_level || 10, 10);
        const threshold = sLevel > 0 ? sLevel : 10;
        if (qty <= 0 || qty <= Math.ceil(threshold * 0.5)) {
          critCount++;
        } else if (qty <= threshold) {
          lowCount++;
        }
      }

      const elCrit = document.getElementById('whCountCritical');
      if (elCrit) elCrit.textContent = critCount.toLocaleString();

      const elLow = document.getElementById('whCountLow');
      if (elLow) elLow.textContent = lowCount.toLocaleString();

      const elAll = document.getElementById('whCountAll');
      if (elAll) elAll.textContent = productsList.length.toLocaleString();
    }

    function renderWarehouseStockTable() {
      const whBody = document.getElementById('warehouseStockTable');
      if (!whBody) return;
      if (!productsList || productsList.length === 0) {
        whBody.innerHTML = '<tr><td colspan="15" class="text-center text-muted py-3">No warehouse items found for this store.</td></tr>';
        updateWarehouseFilterCounters();
        return;
      }
      updateWarehouseFilterCounters();
      let html = '';

      for (const p of productsList) {
        const qty = parseInt(p.p_qty || 0, 10);
        const nq = parseInt(p.p_new_qty || 0, 10);
        const sLevel = parseInt(p.p_s_level || 10, 10);
        const capCost = parseFloat(p.p_capital_price || 0);
        const retailPrice = parseFloat(p.p_current_price || 0);

        let markup = parseFloat(p.p_markup || 0);
        if (!markup && retailPrice > capCost) {
          markup = retailPrice - capCost;
        }

        const safetyThreshold = sLevel > 0 ? sLevel : 10;
        const isCrit = (qty <= 0) || (qty <= Math.ceil(safetyThreshold * 0.5));
        const isLow = (qty > Math.ceil(safetyThreshold * 0.5)) && (qty <= safetyThreshold);

        let statusBadge = '';
        if (qty <= 0) {
          statusBadge = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i> Out of Stock (0)</span>';
        } else if (qty <= Math.ceil(safetyThreshold * 0.5)) {
          statusBadge = '<span class="badge bg-danger bg-opacity-75"><i class="fa fa-exclamation-circle"></i> Critical (' + qty + ')</span>';
        } else if (qty <= safetyThreshold) {
          statusBadge = '<span class="badge bg-warning text-dark"><i class="fa fa-bell-o"></i> Low Stock (' + qty + ')</span>';
        } else {
          statusBadge = '<span class="badge bg-success"><i class="fa fa-check-circle"></i> In Stock (' + qty + ')</span>';
        }

        const photoPath = p.p_featured_photo || '';
        const imgSrc = getProductPhotoUrl(photoPath);

        const imageCell = '<div class="d-inline-block text-center" style="width: 50px;">' +
          '<img id="whInlineImg_' + p.p_id + '" src="' + escapeHtml(imgSrc) + '" class="rounded border shadow-sm" style="width: 44px; height: 44px; object-fit: cover; background: #fff;" alt="' + escapeHtml(p.p_name || 'Product') + '" onerror="this.onerror=null;this.src=&apos;/assets/uploads/product_photos/general_products.png&apos;;">' +
          '<input type="hidden" id="whInlinePhoto_' + p.p_id + '" value="' + escapeHtml(photoPath) + '">' +
        '</div>';

        const capValStr = capCost.toFixed(2);
        const retValStr = retailPrice.toFixed(2);
        const markupValStr = markup.toFixed(2);
        const sLevelVal = sLevel > 0 ? sLevel : 10;
        const searchTokens = (p.p_id + ' ' + (p.p_name || '') + ' ' + (p.p_sku || '') + ' ' + (p.ecat_name || '')).toLowerCase();

        const costAValStr = parseFloat(p.cost_storage_a !== null && p.cost_storage_a !== undefined ? p.cost_storage_a : 0).toFixed(2);
        const costBValStr = parseFloat(p.cost_storage_b !== null && p.cost_storage_b !== undefined ? p.cost_storage_b : 0).toFixed(2);
        const costCValStr = parseFloat(p.cost_storage_c !== null && p.cost_storage_c !== undefined ? p.cost_storage_c : 0).toFixed(2);
        const costNqValStr = parseFloat(p.cost_storage_nq !== null && p.cost_storage_nq !== undefined ? p.cost_storage_nq : 0).toFixed(2);

        html += '<tr id="warehouseRow_' + p.p_id + '" data-pid="' + p.p_id + '" data-critical="' + isCrit + '" data-low="' + isLow + '" data-search="' + escapeHtml(searchTokens) + '">' +
          '<td class="text-center align-middle">' + imageCell + '</td>' +
          '<td>' +
            '<span class="font-monospace text-dark fw-semibold" style="font-size: 12px;">' + escapeHtml(p.p_sku || '-') + '</span>' +
            '<input type="hidden" id="whInlineSku_' + p.p_id + '" value="' + escapeHtml(p.p_sku || '') + '">' +
          '</td>' +
          '<td>' +
            '<span class="fw-bold text-dark" style="min-width: 180px; font-size: 13px; line-height: 1.35; display: inline-block;">' + escapeHtml(p.p_name || 'Unnamed Product') + '</span>' +
            '<input type="hidden" id="whInlineName_' + p.p_id + '" value="' + escapeHtml(p.p_name || '') + '">' +
          '</td>' +
          '<td class="text-center wh-col-group-a" style="background-color: #e6e0d1 !important;">' +
            '<input type="text" id="whInlineStorageA_' + p.p_id + '" class="form-control form-control-sm text-center bg-white" style="width: 75px; margin: 0 auto;" value="' + escapeHtml(p.storage_a !== null && p.storage_a !== undefined ? p.storage_a : '0') + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" placeholder="0" title="Storage A">' +
          '</td>' +
          '<td class="text-end wh-col-group-a" style="background-color: #e6e0d1 !important;">' +
            '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
              '<span class="input-group-text px-1 text-muted bg-white" style="font-size: 11px;">₱</span>' +
              '<input type="number" step="0.01" id="whInlineCostA_' + p.p_id + '" class="form-control form-control-sm text-end px-1 bg-white" value="' + costAValStr + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Landed Cost (A)">' +
            '</div>' +
          '</td>' +
          '<td class="text-center wh-col-group-b" style="background-color: #c2d0ed !important;">' +
            '<input type="text" id="whInlineStorageB_' + p.p_id + '" class="form-control form-control-sm text-center bg-white" style="width: 75px; margin: 0 auto;" value="' + escapeHtml(p.storage_b !== null && p.storage_b !== undefined ? p.storage_b : '0') + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" placeholder="0" title="Storage B">' +
          '</td>' +
          '<td class="text-end wh-col-group-b" style="background-color: #c2d0ed !important;">' +
            '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
              '<span class="input-group-text px-1 text-muted bg-white" style="font-size: 11px;">₱</span>' +
              '<input type="number" step="0.01" id="whInlineCostB_' + p.p_id + '" class="form-control form-control-sm text-end px-1 bg-white" value="' + costBValStr + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Landed Cost (B)">' +
            '</div>' +
          '</td>' +
          '<td class="text-center wh-col-group-c" style="background-color: #d1cfcd !important;">' +
            '<input type="text" id="whInlineStorageC_' + p.p_id + '" class="form-control form-control-sm text-center bg-white" style="width: 75px; margin: 0 auto;" value="' + escapeHtml(p.storage_c !== null && p.storage_c !== undefined ? p.storage_c : '0') + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" placeholder="0" title="Storage C">' +
          '</td>' +
          '<td class="text-end wh-col-group-c" style="background-color: #d1cfcd !important;">' +
            '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
              '<span class="input-group-text px-1 text-muted bg-white" style="font-size: 11px;">₱</span>' +
              '<input type="number" step="0.01" id="whInlineCostC_' + p.p_id + '" class="form-control form-control-sm text-end px-1 bg-white" value="' + costCValStr + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Landed Cost (C)">' +
            '</div>' +
          '</td>' +
          '<td class="text-center wh-col-group-nq" style="background-color: #d1e6d6 !important;">' +
            '<input type="number" id="whInlineNq_' + p.p_id + '" class="form-control form-control-sm text-center bg-white" style="width: 75px; margin: 0 auto;" value="' + nq + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Reserve Batch (NQ)">' +
          '</td>' +
          '<td class="text-end wh-col-group-nq" style="background-color: #d1e6d6 !important;">' +
            '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
              '<span class="input-group-text px-1 text-muted bg-white" style="font-size: 11px;">₱</span>' +
              '<input type="number" step="0.01" id="whInlineCostNq_' + p.p_id + '" class="form-control form-control-sm text-end px-1 bg-white" value="' + costNqValStr + '" oninput="markWarehouseRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Landed Cost (NQ)">' +
            '</div>' +
          '</td>' +
          '<td class="text-center">' +
            '<input type="number" id="whInlineQty_' + p.p_id + '" class="form-control form-control-sm text-center fw-bold ' + (qty <= 0 ? 'text-danger border-danger' : 'text-dark') + '" style="width: 80px; margin: 0 auto;" value="' + qty + '" oninput="onWarehouseInlineChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Store Stock(Q)">' +
          '</td>' +
          '<td class="text-end">' +
            '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
              '<span class="input-group-text px-1 text-muted" style="font-size: 11px;">₱</span>' +
              '<input type="number" step="0.01" id="whInlineCap_' + p.p_id + '" class="form-control form-control-sm text-end px-1" value="' + capValStr + '" oninput="onWarehouseCapChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveWarehouseInline(' + p.p_id + ')" title="Landed Cost">' +
            '</div>' +
          '</td>' +
          '<td class="text-center"><span id="whInlineStatus_' + p.p_id + '">' + statusBadge + '</span></td>' +
          '<td class="text-center">' +
            '<div class="d-flex align-items-center justify-content-center gap-1">' +
              '<button id="btnSaveWhInline_' + p.p_id + '" class="btn btn-outline-success btn-sm py-0 px-2 text-nowrap" onclick="saveWarehouseInline(' + p.p_id + ')" title="Save changes"><i class="fa fa-save"></i> Save</button>' +
              '<button class="btn btn-outline-primary btn-sm py-0 px-2 text-nowrap" onclick="jumpToGrnForProduct(' + p.p_id + ')" title="Restock item"><i class="fa fa-plus"></i></button>' +
            '</div>' +
          '</td>' +
        '</tr>';
      }

      whBody.innerHTML = html;
      warehouseCurrentPage = 1;
      filterWarehouseStockTable();
    }

    function markWarehouseRowDirty(productId) {
      const btn = document.getElementById('btnSaveWhInline_' + productId);
      if (btn) {
        btn.className = 'btn btn-primary btn-sm py-0 px-2 text-nowrap';
        btn.innerHTML = '<i class="fa fa-save"></i> Save *';
      }
    }

    function onWarehouseInlineChange(productId) {
      markWarehouseRowDirty(productId);
      const qtyEl = document.getElementById('whInlineQty_' + productId);
      const statusEl = document.getElementById('whInlineStatus_' + productId);
      const tr = document.getElementById('warehouseRow_' + productId);
      if (!qtyEl || !statusEl || !tr) return;

      const item = (productsList || []).find(p => p.p_id === productId) || {};
      const qty = parseInt(qtyEl.value || 0, 10);
      const sLevel = parseInt(item.p_s_level || 10, 10);
      const threshold = sLevel > 0 ? sLevel : 10;
      const isCrit = (qty <= 0) || (qty <= Math.ceil(threshold * 0.5));
      const isLow = (qty > Math.ceil(threshold * 0.5)) && (qty <= threshold);

      tr.setAttribute('data-critical', String(isCrit));
      tr.setAttribute('data-low', String(isLow));

      if (qty <= 0) {
        qtyEl.className = 'form-control form-control-sm text-center fw-bold text-danger border-danger';
        statusEl.innerHTML = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i> Out of Stock (0)</span>';
      } else if (qty <= Math.ceil(threshold * 0.5)) {
        qtyEl.className = 'form-control form-control-sm text-center fw-bold text-danger';
        statusEl.innerHTML = '<span class="badge bg-danger bg-opacity-75"><i class="fa fa-exclamation-circle"></i> Critical (' + qty + ')</span>';
      } else if (qty <= threshold) {
        qtyEl.className = 'form-control form-control-sm text-center fw-bold text-warning';
        statusEl.innerHTML = '<span class="badge bg-warning text-dark"><i class="fa fa-bell-o"></i> Low Stock (' + qty + ')</span>';
      } else {
        qtyEl.className = 'form-control form-control-sm text-center fw-bold text-dark';
        statusEl.innerHTML = '<span class="badge bg-success"><i class="fa fa-check-circle"></i> In Stock (' + qty + ')</span>';
      }
    }

    function onWarehouseCapChange(productId) {
      markWarehouseRowDirty(productId);
    }

    async function saveWarehouseInline(productId) {
      const btn = document.getElementById('btnSaveWhInline_' + productId);
      const nameInput = document.getElementById('whInlineName_' + productId);
      const skuInput = document.getElementById('whInlineSku_' + productId);
      const photoInput = document.getElementById('whInlinePhoto_' + productId);
      const ecatInput = document.getElementById('whInlineEcat_' + productId);
      const storageAInput = document.getElementById('whInlineStorageA_' + productId);
      const storageBInput = document.getElementById('whInlineStorageB_' + productId);
      const storageCInput = document.getElementById('whInlineStorageC_' + productId);
      const costAInput = document.getElementById('whInlineCostA_' + productId);
      const costBInput = document.getElementById('whInlineCostB_' + productId);
      const costCInput = document.getElementById('whInlineCostC_' + productId);
      const costNqInput = document.getElementById('whInlineCostNq_' + productId);
      const qtyInput = document.getElementById('whInlineQty_' + productId);
      const nqInput = document.getElementById('whInlineNq_' + productId);
      const capInput = document.getElementById('whInlineCap_' + productId);

      if (!btn || !qtyInput || !capInput) return;

      const item = (productsList || []).find(p => p.p_id === productId) || {};
      const p_name = nameInput ? nameInput.value.trim() : (item.p_name || '');
      const p_sku = skuInput ? skuInput.value.trim() : (item.p_sku || '');
      const p_featured_photo = photoInput ? photoInput.value.trim() : (item.p_featured_photo || '');
      const ecat_id = ecatInput ? parseInt(ecatInput.value || 0, 10) : (item.ecat_id || 0);
      const storage_a = storageAInput ? storageAInput.value.trim() : (item.storage_a || '0');
      const storage_b = storageBInput ? storageBInput.value.trim() : (item.storage_b || '0');
      const storage_c = storageCInput ? storageCInput.value.trim() : (item.storage_c || '0');
      const cost_storage_a = costAInput ? parseFloat(costAInput.value || 0).toFixed(2) : (item.cost_storage_a || '0.00');
      const cost_storage_b = costBInput ? parseFloat(costBInput.value || 0).toFixed(2) : (item.cost_storage_b || '0.00');
      const cost_storage_c = costCInput ? parseFloat(costCInput.value || 0).toFixed(2) : (item.cost_storage_c || '0.00');
      const cost_storage_nq = costNqInput ? parseFloat(costNqInput.value || 0).toFixed(2) : (item.cost_storage_nq || '0.00');
      const p_qty = parseInt(qtyInput.value || 0, 10);
      const p_new_qty = nqInput ? parseInt(nqInput.value || 0, 10) : (item.p_new_qty || 0);
      const p_capital_price = parseFloat(capInput.value || 0);
      const p_s_level = item.p_s_level !== undefined ? parseInt(item.p_s_level, 10) : 10;
      const p_markup = item.p_markup !== undefined ? String(item.p_markup) : '20';
      const p_current_price = item.p_current_price !== undefined ? parseFloat(item.p_current_price) : 0;

      btn.disabled = true;
      btn.className = 'btn btn-secondary btn-sm py-0 px-2 text-nowrap';
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

      try {
        const res = await fetch('/api/products/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            p_id: productId,
            p_name,
            p_sku,
            p_featured_photo,
            ecat_id,
            storage_a,
            storage_b,
            storage_c,
            cost_storage_a,
            cost_storage_b,
            cost_storage_c,
            cost_storage_nq,
            p_qty,
            p_new_qty,
            p_s_level,
            p_capital_price,
            p_markup,
            p_current_price
          })
        });

        const data = await res.json();
        if (res.ok && data.success) {
          if (item) {
            item.p_name = p_name;
            item.p_sku = p_sku;
            item.p_featured_photo = p_featured_photo;
            item.ecat_id = ecat_id;
            item.storage_a = storage_a;
            item.storage_b = storage_b;
            item.storage_c = storage_c;
            item.cost_storage_a = cost_storage_a;
            item.cost_storage_b = cost_storage_b;
            item.cost_storage_c = cost_storage_c;
            item.cost_storage_nq = cost_storage_nq;
            item.p_qty = p_qty;
            item.p_new_qty = p_new_qty;
            item.p_capital_price = p_capital_price.toFixed(2);

            const catObj = (categoriesList || []).find(c => c.ecat_id === ecat_id);
            const catName = catObj ? catObj.ecat_name : '';
            item.ecat_name = catName;
            const searchTokens = (productId + ' ' + p_name + ' ' + p_sku + ' ' + catName + ' ' + storage_a + ' ' + storage_b + ' ' + storage_c).toLowerCase();
            const whTr = document.getElementById('warehouseRow_' + productId);
            if (whTr) whTr.setAttribute('data-search', searchTokens);
            const catTr = document.getElementById('catalogRow_' + productId);
            if (catTr) catTr.setAttribute('data-search', searchTokens);
          }

          btn.disabled = false;
          btn.className = 'btn btn-success btn-sm py-0 px-2 text-nowrap';
          btn.innerHTML = '<i class="fa fa-check"></i> Saved';
          showErpToast('Warehouse Stock Updated', 'Product #' + productId + ' updated successfully in PostgreSQL.', 'success');

          setTimeout(() => {
            btn.className = 'btn btn-outline-success btn-sm py-0 px-2 text-nowrap';
            btn.innerHTML = '<i class="fa fa-save"></i> Save';
          }, 1500);

          updateWarehouseFilterCounters();
          updateCatalogFilterCounters();
        } else {
          btn.disabled = false;
          btn.className = 'btn btn-danger btn-sm py-0 px-2 text-nowrap';
          btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Retry';
          showErpToast('Update Failed', data.error || 'Could not save updates.', 'danger');
        }
      } catch (err) {
        btn.disabled = false;
        btn.className = 'btn btn-danger btn-sm py-0 px-2 text-nowrap';
        btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Retry';
        showErpToast('Network Error', err.message || 'Connection error while saving.', 'danger');
      }
    }

    function jumpToGrnForProduct(productId) {
      if (typeof activateSidebarItem === 'function') {
        activateSidebarItem('nav-li-materials', 'tab-materials', 'Receive Materials (GRN)');
      } else {
        const link = document.getElementById('tab-materials-link');
        if (link) link.click();
      }
      const sel = document.getElementById('grnProduct');
      if (sel) {
        sel.value = productId;
        updateProductCurrentStock();
      }
      const qtyInput = document.getElementById('grnQty');
      if (qtyInput) {
        qtyInput.focus();
        qtyInput.select();
      }
    }

    async function loadProducts() {
      await loadCategories();
      const res = await fetch('/api/products?supplier_id=' + currentSupplierId);
      productsList = await res.json();
      const sel = document.getElementById('grnProduct');
      if (sel) {
        sel.innerHTML = '<option value="">Select Catalog Item...</option>';
        for (const p of productsList) {
          const opt = document.createElement('option');
          opt.value = p.p_id;
          opt.textContent = p.p_name + ' (Stock: ' + p.p_qty + ')';
          sel.appendChild(opt);
        }
      }

      const catBody = document.getElementById('catalogStockTable');
      if (catBody) {
        if (!productsList || productsList.length === 0) {
          catBody.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-3">No products found for this store.</td></tr>';
          updateCatalogFilterCounters();
        } else {
          updateCatalogFilterCounters();
          let html = '';

          for (const p of productsList) {
            const qty = parseInt(p.p_qty || 0, 10);
            const nq = parseInt(p.p_new_qty || 0, 10);
            const sLevel = parseInt(p.p_s_level || 10, 10);
            const capCost = parseFloat(p.p_capital_price || 0);
            const retailPrice = parseFloat(p.p_current_price || 0);

            let markup = parseFloat(p.p_markup || 0);
            if (!markup && retailPrice > capCost) {
              markup = retailPrice - capCost;
            }

            const safetyThreshold = sLevel > 0 ? sLevel : 10;
            const isCrit = (qty <= 0) || (qty <= Math.ceil(safetyThreshold * 0.5));
            const isLow = (qty > Math.ceil(safetyThreshold * 0.5)) && (qty <= safetyThreshold);

            let statusBadge = '';
            if (qty <= 0) {
              statusBadge = '<span class="badge bg-danger"><i class="fa fa-times-circle"></i> Out of Stock (0)</span>';
            } else if (qty <= Math.ceil(safetyThreshold * 0.5)) {
              statusBadge = '<span class="badge bg-danger bg-opacity-75"><i class="fa fa-exclamation-circle"></i> Critical (' + qty + ')</span>';
            } else if (qty <= safetyThreshold) {
              statusBadge = '<span class="badge bg-warning text-dark"><i class="fa fa-bell-o"></i> Low Stock (' + qty + ')</span>';
            } else {
              statusBadge = '<span class="badge bg-success"><i class="fa fa-check-circle"></i> In Stock (' + qty + ')</span>';
            }

            const photoPath = p.p_featured_photo || '';
            const imgSrc = getProductPhotoUrl(photoPath);

            const imageCell = '<div class="position-relative d-inline-block text-center" style="width: 50px;">' +
              '<img id="inlineImg_' + p.p_id + '" src="' + escapeHtml(imgSrc) + '" class="rounded border shadow-sm" style="width: 44px; height: 44px; object-fit: cover; cursor: pointer; background: #fff;" onclick="openEditPhotoModal(' + p.p_id + ')" title="Click to view or change photo" onerror="this.onerror=null;this.src=&apos;/assets/uploads/product_photos/general_products.png&apos;;">' +
              '<input type="hidden" id="inlinePhoto_' + p.p_id + '" value="' + escapeHtml(photoPath) + '">' +
              '<button type="button" class="btn btn-light btn-sm p-0 position-absolute bottom-0 end-0 rounded-circle border shadow-sm" style="width: 18px; height: 18px; line-height: 16px; font-size: 10px;" onclick="openEditPhotoModal(' + p.p_id + ')" title="Change photo">' +
                '<i class="fa fa-camera text-secondary" style="font-size: 9px;"></i>' +
              '</button>' +
            '</div>';

            let catOptions = '<option value="">-- No Category --</option>';
            for (const cat of categoriesList) {
              const isSelected = (cat.ecat_id === p.ecat_id) ? ' selected' : '';
              catOptions += '<option value="' + cat.ecat_id + '"' + isSelected + '>' + escapeHtml(cat.ecat_name) + ' (#' + cat.ecat_id + ')</option>';
            }

            const capValStr = capCost.toFixed(2);
            const retValStr = retailPrice.toFixed(2);
            const markupValStr = markup.toFixed(2);
            const sLevelVal = sLevel > 0 ? sLevel : 10;
            const searchTokens = (p.p_id + ' ' + (p.p_name || '') + ' ' + (p.p_sku || '') + ' ' + (p.ecat_name || '')).toLowerCase();

            html += '<tr id="catalogRow_' + p.p_id + '" data-pid="' + p.p_id + '" data-critical="' + isCrit + '" data-low="' + isLow + '" data-search="' + escapeHtml(searchTokens) + '">' +
              '<td><code>#' + p.p_id + '</code></td>' +
              '<td class="text-center align-middle">' + imageCell + '</td>' +
              '<td>' +
                '<input type="text" id="inlineSku_' + p.p_id + '" class="form-control form-control-sm font-monospace" style="min-width: 95px; font-size: 12px;" value="' + escapeHtml(p.p_sku || '') + '" oninput="markCatalogRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" placeholder="SKU">' +
              '</td>' +
              '<td>' +
                '<input type="text" id="inlineName_' + p.p_id + '" class="form-control form-control-sm fw-bold" style="min-width: 180px; font-size: 13px;" value="' + escapeHtml(p.p_name || '') + '" oninput="markCatalogRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" placeholder="Product Name">' +
              '</td>' +
              '<td>' +
                '<select id="inlineEcat_' + p.p_id + '" class="form-select form-select-sm" style="min-width: 135px; font-size: 12px;" onchange="markCatalogRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')">' +
                  catOptions +
                '</select>' +
              '</td>' +
              '<td class="text-center">' +
                '<input type="number" id="inlineQty_' + p.p_id + '" class="form-control form-control-sm text-center fw-bold ' + (qty <= 0 ? 'text-danger border-danger' : 'text-dark') + '" style="width: 80px; margin: 0 auto;" value="' + qty + '" oninput="onCatalogInlineChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" title="Available Stock (Q)">' +
              '</td>' +
              '<td class="text-center">' +
                '<input type="number" id="inlineNq_' + p.p_id + '" class="form-control form-control-sm text-center" style="width: 75px; margin: 0 auto;" value="' + nq + '" oninput="markCatalogRowDirty(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" title="New Batch Pending Stock (NQ)">' +
              '</td>' +
              '<td class="text-center">' +
                '<input type="number" id="inlineSLevel_' + p.p_id + '" class="form-control form-control-sm text-center" style="width: 75px; margin: 0 auto;" value="' + sLevelVal + '" oninput="onCatalogInlineChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" title="Safety Threshold Level (S)">' +
              '</td>' +
              '<td class="text-end">' +
                '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
                  '<span class="input-group-text px-1 text-muted" style="font-size: 11px;">₱</span>' +
                  '<input type="number" step="0.01" id="inlineCap_' + p.p_id + '" class="form-control form-control-sm text-end px-1" value="' + capValStr + '" oninput="onCatalogCapChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" title="Landed / Capital Cost">' +
                '</div>' +
              '</td>' +
              '<td class="text-end">' +
                '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
                  '<span class="input-group-text px-1 text-success fw-bold" style="font-size: 11px;">+₱</span>' +
                  '<input type="number" step="0.01" id="inlineMarkup_' + p.p_id + '" class="form-control form-control-sm text-end px-1 text-success fw-bold" value="' + markupValStr + '" oninput="onCatalogMarkupChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" title="Markup in ₱">' +
                '</div>' +
              '</td>' +
              '<td class="text-end">' +
                '<div class="input-group input-group-sm ms-auto" style="width: 105px;">' +
                  '<span class="input-group-text px-1 text-primary fw-bold" style="font-size: 11px;">₱</span>' +
                  '<input type="number" step="0.01" id="inlineRetail_' + p.p_id + '" class="form-control form-control-sm text-end fw-bold text-primary px-1" value="' + retValStr + '" oninput="onCatalogRetailChange(' + p.p_id + ')" onkeydown="if(event.keyCode===13)saveCatalogInline(' + p.p_id + ')" title="POS Retail Price">' +
                '</div>' +
              '</td>' +
              '<td class="text-center"><span id="inlineStatus_' + p.p_id + '">' + statusBadge + '</span></td>' +
              '<td class="text-center">' +
                '<div class="d-flex align-items-center justify-content-center gap-1">' +
                  '<button id="btnSaveInline_' + p.p_id + '" class="btn btn-outline-success btn-sm py-0 px-2 text-nowrap" onclick="saveCatalogInline(' + p.p_id + ')" title="Save inline changes"><i class="fa fa-save"></i> Save</button>' +
                  '<button class="btn btn-outline-primary btn-sm py-0 px-2 text-nowrap" onclick="jumpToGrnForProduct(' + p.p_id + ')" title="Restock item"><i class="fa fa-plus"></i></button>' +
                '</div>' +
              '</td>' +
            '</tr>';
          }

          catBody.innerHTML = html;

          catalogCurrentPage = 1;
          // Apply active filter (defaults to 'critical' so only critical status items display)
          filterCatalogStockTable();
        }
      }

      renderWarehouseStockTable();
    }

    function updateProductCurrentStock() {
      const pid = parseInt(document.getElementById('grnProduct').value, 10);
      const prod = productsList.find(p => p.p_id === pid);
      if (prod) {
        document.getElementById('currentStockNote').innerHTML = 'Current POS Stock: <strong>' + prod.p_qty + ' units</strong> | Current Capital: ₱' + prod.p_capital_price;
        document.getElementById('grnPrice').value = prod.p_capital_price || '';
      } else {
        document.getElementById('currentStockNote').textContent = 'Current Stock: --';
      }
    }

    async function submitGrn(e) {
      e.preventDefault();
      const pid = parseInt(document.getElementById('grnProduct').value, 10);
      const prod = productsList.find(p => p.p_id === pid);
      if (!prod) {
        showErpToast('Product Required', 'Please select a catalog product before confirming receipt.', 'warning');
        return;
      }

      const payload = {
        supplier_id: currentSupplierId,
        vendor_name: document.getElementById('grnVendor').value,
        reference_number: document.getElementById('grnRef').value,
        receipt_date: new Date().toISOString().split('T')[0],
        freight_handling_cost: parseFloat(document.getElementById('grnFreight').value || 0),
        items: [{
          product_id: prod.p_id,
          product_name: prod.p_name,
          quantity_received: parseInt(document.getElementById('grnQty').value, 10),
          unit_purchase_price: parseFloat(document.getElementById('grnPrice').value)
        }]
      };

      const btn = document.getElementById('btnSubmitGrn');
      btn.disabled = true;
      btn.textContent = 'Updating POS Stock...';
      try {
        const res = await fetch('/api/materials/receive', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          showErpToast('Stock Received & Updated', data.message, 'success');
          document.getElementById('grnForm').reset();
          document.getElementById('currentStockNote').textContent = 'Current Stock: --';
          await loadProducts();
          await loadGrnHistory();
          await loadWaterfall();
        } else {
          showErpToast('Stock Receipt Error', data.error || 'Could not record stock receipt.', 'danger');
        }
      } catch (err) {
        showErpToast('Network Error', err.message || 'Could not connect to server.', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm Receipt & Update POS Stock';
      }
    }

    async function loadGrnHistory() {
      const res = await fetch('/api/materials/history?supplier_id=' + currentSupplierId);
      const list = await res.json();
      const tbody = document.getElementById('grnHistoryTable');
      if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No materials received yet.</td></tr>';
        return;
      }
      let html = '';
      for (const g of list) {
        html += '<tr>' +
          '<td>' + g.receipt_date + '</td>' +
          '<td><strong>' + g.vendor_name + '</strong></td>' +
          '<td><code>' + g.reference_number + '</code></td>' +
          '<td>' + g.item_count + ' items</td>' +
          '<td class="text-primary fw-bold">₱' + parseFloat(g.total_landed_cost).toLocaleString() + '</td>' +
        '</tr>';
      }
      tbody.innerHTML = html;
    }

    let currentExpensesList = [];
    let currentExpenseFilterCategory = 'ALL';

    const EXPENSE_CATEGORY_META = {
      ELECTRICITY: {
        label: 'Electricity / Power',
        shortLabel: 'Electricity',
        icon: 'fa-bolt',
        bg: '#fef3c7',
        color: '#b45309',
        border: '#fcd34d',
        headerGrad: 'linear-gradient(135deg, #d97706, #b45309)',
        titlePlaceholder: 'e.g. October 2026 Electric Bill',
        vendorPlaceholder: 'e.g. Meralco Commercial Accounts'
      },
      RENT: {
        label: 'Store & Warehouse Rent',
        shortLabel: 'Rent',
        icon: 'fa-building-o',
        bg: '#eff6ff',
        color: '#1d4ed8',
        border: '#bfdbfe',
        headerGrad: 'linear-gradient(135deg, #2563eb, #1d4ed8)',
        titlePlaceholder: 'e.g. October 2026 Store & Warehouse Lease',
        vendorPlaceholder: 'e.g. Commercial Property Lessor'
      },
      WATER: {
        label: 'Water Utility',
        shortLabel: 'Water',
        icon: 'fa-tint',
        bg: '#ecfeff',
        color: '#0e7490',
        border: '#a5f3fc',
        headerGrad: 'linear-gradient(135deg, #0891b2, #0e7490)',
        titlePlaceholder: 'e.g. October 2026 Water Utility Bill',
        vendorPlaceholder: 'e.g. Maynilad / Manila Water'
      },
      DELIVERY_LOGISTICS: {
        label: 'Delivery / Courier Logistics',
        shortLabel: 'Logistics',
        icon: 'fa-truck',
        bg: '#f0fdf4',
        color: '#15803d',
        border: '#bbf7d0',
        headerGrad: 'linear-gradient(135deg, #16a34a, #15803d)',
        titlePlaceholder: 'e.g. Delivery Truck Fuel & Toll Fees',
        vendorPlaceholder: 'e.g. Petron Fleet / Lalamove Corporate'
      },
      MAINTENANCE: {
        label: 'Store Repairs & Maintenance',
        shortLabel: 'Maintenance',
        icon: 'fa-wrench',
        bg: '#f3e8ff',
        color: '#6d28d9',
        border: '#ddd6fe',
        headerGrad: 'linear-gradient(135deg, #7c3aed, #6d28d9)',
        titlePlaceholder: 'e.g. Warehouse Forklift & Rack Maintenance',
        vendorPlaceholder: 'e.g. Industrial Equipment Services'
      },
      MISC_OPEX: {
        label: 'Miscellaneous Expense',
        shortLabel: 'Misc OPEX',
        icon: 'fa-cube',
        bg: '#f1f5f9',
        color: '#334155',
        border: '#cbd5e1',
        headerGrad: 'linear-gradient(135deg, #334155, #1e293b)',
        titlePlaceholder: 'e.g. Store Supplies & Packaging Materials',
        vendorPlaceholder: 'e.g. Office & Store Supply Vendor'
      },
      PAYROLL: {
        label: 'Staff Payroll & Commissions',
        shortLabel: 'Payroll',
        icon: 'fa-users',
        bg: '#dcfce7',
        color: '#166534',
        border: '#86efac',
        headerGrad: 'linear-gradient(135deg, #059669, #047857)',
        titlePlaceholder: 'e.g. Store Staff Payroll Disbursement',
        vendorPlaceholder: 'e.g. Store Staff & Cashiers'
      }
    };

    function getExpenseCategoryBadgeHtml(catKey) {
      const m = EXPENSE_CATEGORY_META[catKey] || {
        shortLabel: catKey || 'OPEX',
        icon: 'fa-tag',
        bg: '#f1f5f9',
        color: '#334155',
        border: '#cbd5e1'
      };
      return '<span class="badge" style="background:' + m.bg + '; color:' + m.color + '; border:1px solid ' + m.border + '; font-size:11px; font-weight:700;">' +
        '<i class="fa ' + m.icon + ' me-1"></i>' + m.shortLabel +
      '</span>';
    }

    async function loadExpenses() {
      const res = await fetch('/api/expenses?supplier_id=' + currentSupplierId);
      currentExpensesList = (await res.json()) || [];
      renderExpensesTable();
    }

    function filterExpensesByCategory(cat) {
      currentExpenseFilterCategory = cat || 'ALL';
      const bar = document.getElementById('opexCategoryFilterBar');
      if (bar) {
        bar.querySelectorAll('button[data-cat]').forEach(btn => {
          const bCat = btn.getAttribute('data-cat');
          btn.className = (bCat === currentExpenseFilterCategory)
            ? 'btn btn-dark btn-sm py-1 px-2 fw-bold'
            : 'btn btn-outline-secondary btn-sm py-1 px-2 fw-bold';
        });
      }
      renderExpensesTable();
    }

    function renderExpensesTable() {
      const tbody = document.getElementById('expenseTable');
      if (!tbody) return;
      const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      // Update per-category counts
      const counts = {
        ALL: currentExpensesList.length,
        ELECTRICITY: 0,
        RENT: 0,
        WATER: 0,
        DELIVERY_LOGISTICS: 0,
        MAINTENANCE: 0,
        MISC_OPEX: 0,
        PAYROLL: 0
      };
      for (const e of currentExpensesList) {
        const c = e.category || 'MISC_OPEX';
        if (counts[c] !== undefined) counts[c]++;
      }
      Object.keys(counts).forEach(k => {
        const el = document.getElementById('opexCount-' + k);
        if (el) el.textContent = String(counts[k]);
      });

      const filtered = currentExpenseFilterCategory === 'ALL'
        ? currentExpensesList
        : currentExpensesList.filter(e => (e.category || 'MISC_OPEX') === currentExpenseFilterCategory);

      let filteredSum = 0;
      for (const e of filtered) {
        filteredSum += parseFloat(e.amount || 0);
      }
      const totEl = document.getElementById('opexFilteredTotal');
      if (totEl) totEl.textContent = '₱' + fmt(filteredSum);

      if (filtered.length === 0) {
        const catMeta = EXPENSE_CATEGORY_META[currentExpenseFilterCategory];
        const emptyLabel = currentExpenseFilterCategory === 'ALL'
          ? 'No expenses recorded yet.'
          : 'No expenses recorded under ' + (catMeta ? catMeta.label : currentExpenseFilterCategory) + ' yet.';
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">' + emptyLabel + '</td></tr>';
        return;
      }

      let html = '';
      for (const e of filtered) {
        const start = new Date(e.billing_period_start);
        const end = new Date(e.billing_period_end);
        const days = Math.max(1, Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1);
        const daily = (parseFloat(e.amount) / days).toFixed(2);

        html += '<tr>' +
          '<td>' + getExpenseCategoryBadgeHtml(e.category) + '</td>' +
          '<td><strong>' + e.title + '</strong><br><small class="text-muted">' + (e.vendor_name || '') + '</small></td>' +
          '<td>' + e.billing_period_start + ' to ' + e.billing_period_end + ' (' + days + 'd)</td>' +
          '<td>₱' + daily + '/day</td>' +
          '<td class="text-danger fw-bold">₱' + fmt(e.amount) + '</td>' +
          '<td><span class="badge bg-success">' + e.payment_status + '</span></td>' +
        '</tr>';
      }
      tbody.innerHTML = html;
    }

    function selectExpenseCategory(catKey) {
      const cat = EXPENSE_CATEGORY_META[catKey] ? catKey : 'ELECTRICITY';
      const meta = EXPENSE_CATEGORY_META[cat];
      const catInput = document.getElementById('expCategory');
      if (catInput) catInput.value = cat;

      const headerEl = document.getElementById('expModalHeader');
      if (headerEl) headerEl.style.background = meta.headerGrad;

      const titleEl = document.getElementById('expModalTitle');
      if (titleEl) {
        titleEl.innerHTML = '<i class="fa ' + meta.icon + ' me-1"></i> Log Store Bill &mdash; ' + meta.label;
      }

      const badgeEl = document.getElementById('expCategoryActiveBadge');
      if (badgeEl) {
        badgeEl.textContent = meta.label;
        badgeEl.style.background = meta.bg;
        badgeEl.style.color = meta.color;
        badgeEl.style.border = '1px solid ' + meta.border;
      }

      const expTitleInput = document.getElementById('expTitle');
      if (expTitleInput) expTitleInput.placeholder = meta.titlePlaceholder;

      const expVendorInput = document.getElementById('expVendor');
      if (expVendorInput) expVendorInput.placeholder = meta.vendorPlaceholder;

      const btnContainer = document.getElementById('expModalCategoryButtons');
      if (btnContainer) {
        btnContainer.querySelectorAll('button[data-exp-cat]').forEach(btn => {
          const bCat = btn.getAttribute('data-exp-cat');
          const bMeta = EXPENSE_CATEGORY_META[bCat];
          if (bCat === cat && bMeta) {
            btn.style.background = bMeta.bg;
            btn.style.color = bMeta.color;
            btn.style.border = '1.5px solid ' + bMeta.color;
            btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.08)';
          } else {
            btn.style.background = '#ffffff';
            btn.style.color = '#475569';
            btn.style.border = '1px solid #cbd5e1';
            btn.style.boxShadow = 'none';
          }
        });
      }
    }

    function openExpenseModal(categoryKey) {
      const targetCat = categoryKey || (currentExpenseFilterCategory !== 'ALL' && currentExpenseFilterCategory !== 'PAYROLL' ? currentExpenseFilterCategory : 'ELECTRICITY');
      selectExpenseCategory(targetCat);
      expenseModal.show();
    }

    async function submitExpense() {
      const catVal = document.getElementById('expCategory').value || 'ELECTRICITY';
      const payload = {
        supplier_id: currentSupplierId,
        category: catVal,
        title: document.getElementById('expTitle').value,
        vendor_name: document.getElementById('expVendor').value,
        amount: parseFloat(document.getElementById('expAmount').value),
        billing_period_start: document.getElementById('expStart').value,
        billing_period_end: document.getElementById('expEnd').value
      };
      if (!payload.title || isNaN(payload.amount)) {
        showErpToast('Missing Fields', 'Please enter an expense title and valid amount.', 'warning');
        return;
      }

      await fetch('/api/expenses', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      expenseModal.hide();
      document.getElementById('expForm').reset();
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('expStart').value = today;
      document.getElementById('expEnd').value = today;
      selectExpenseCategory(catVal);
      showErpToast('Expense Logged', 'Recorded ' + payload.title + ' (' + (EXPENSE_CATEGORY_META[catVal] ? EXPENSE_CATEGORY_META[catVal].shortLabel : catVal) + ') in Store OPEX.', 'success');
      await loadExpenses();
      await loadWaterfall();
    }

    let currentStaffList = [];
    let currentPayrollLedger = [];
    let payrollCalMode = 'current'; // 'current' (Current Date -> Previous), 'schedule', or 'alldays'
    let isPayrollPaymentBusy = false;

    function getLocalTodayIso() {
      const now = new Date();
      const yyyy = now.getFullYear();
      const mm = String(now.getMonth() + 1).padStart(2, '0');
      const dd = String(now.getDate()).padStart(2, '0');
      return yyyy + '-' + mm + '-' + dd;
    }

    function isStaffPaidOnDate(staffId, payDate) {
      return currentPayrollLedger.some(
        rec => parseInt(rec.staff_id, 10) === parseInt(staffId, 10) && rec.pay_date === payDate && rec.status === 'PAID'
      );
    }

    function syncStaffRowPayButtons() {
      const todayIso = getLocalTodayIso();
      const payDateEl = document.getElementById('payrollPayDate');
      const payDate = (payDateEl && payDateEl.value) ? payDateEl.value : todayIso;
      for (const s of currentStaffList) {
        const btn = document.getElementById('btnPayStaff-' + s.id);
        if (!btn) continue;
        const alreadyPaid = isStaffPaidOnDate(s.id, payDate);
        if (alreadyPaid) {
          btn.className = 'btn btn-success btn-sm py-0 px-2';
          btn.disabled = true;
          btn.title = s.full_name + ' is already marked PAID on ' + payDate;
          btn.innerHTML = '<i class="fa fa-check-circle"></i> Paid';
        } else {
          btn.className = 'btn btn-outline-success btn-sm py-0 px-2';
          btn.disabled = isPayrollPaymentBusy;
          btn.title = 'Disburse Payroll to ' + s.full_name + ' for ' + payDate;
          btn.innerHTML = '<i class="fa fa-check"></i> Pay';
        }
      }
    }

    async function loadPayroll() {
      const todayIso = getLocalTodayIso();
      const payDateEl = document.getElementById('payrollPayDate');
      if (payDateEl && !payDateEl.value) payDateEl.value = todayIso;
      const calMonthEl = document.getElementById('payrollCalMonth');
      if (calMonthEl && !calMonthEl.value) calMonthEl.value = todayIso.slice(0, 7);

      const res = await fetch('/api/payroll/staff?supplier_id=' + currentSupplierId);
      const data = await res.json();
      currentStaffList = data.staff || [];
      currentPayrollLedger = data.payroll_ledger || [];
      const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      const sBody = document.getElementById('staffTable');
      if (currentStaffList.length === 0) {
        sBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No staff found for this store.</td></tr>';
      } else {
        let html = '';
        for (const s of currentStaffList) {
          const baseSalNum = parseFloat(s.base_salary || 0);
          const taxPctNum = parseFloat(s.tax_pct || 0);
          const taxAmtNum = Math.round(baseSalNum * (taxPctNum / 100) * 100) / 100;
          const sssNum = parseFloat(s.sss_contribution || 0);
          const advNum = parseFloat(s.advance_payment || 0);
          const bonusCommNum = parseFloat(s.bonus_commission || 0);
          const posCommNum = parseFloat(s.pos_commission || 0);
          const totalDedNum = taxAmtNum + sssNum + advNum;
          const netPayout = Math.max(0, (baseSalNum - totalDedNum) + bonusCommNum + posCommNum);

          const statusBadge = (s.status || '').toLowerCase() === 'active'
            ? '<span class="badge bg-success" style="font-size:10px;">Active</span>'
            : '<span class="badge bg-secondary" style="font-size:10px;">' + s.status + '</span>';

          html += '<tr data-staff-id="' + s.id + '">' +
            '<td><strong>' + s.full_name + '</strong><br><small class="text-muted">' + s.email + '</small> ' + statusBadge + '</td>' +
            '<td><span class="badge bg-info text-dark">' + s.role + '</span></td>' +
            '<td>' +
              '<div class="input-group input-group-sm">' +
                '<span class="input-group-text px-1">₱</span>' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm fw-bold" id="staffBase-' + s.id + '" value="' + baseSalNum.toFixed(2) + '" oninput="recalcPayrollTotals()">' +
              '</div>' +
            '</td>' +
            '<td>' +
              '<div class="input-group input-group-sm">' +
                '<input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm text-danger fw-bold" id="staffTaxPct-' + s.id + '" value="' + taxPctNum.toFixed(2) + '" oninput="recalcPayrollTotals()">' +
                '<span class="input-group-text px-1">%</span>' +
              '</div>' +
              '<div class="text-danger" style="font-size:10px;" id="staffTaxAmtLabel-' + s.id + '">-₱' + fmt(taxAmtNum) + '</div>' +
            '</td>' +
            '<td>' +
              '<div class="input-group input-group-sm">' +
                '<span class="input-group-text px-1 text-danger">-₱</span>' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm text-danger fw-bold" id="staffSss-' + s.id + '" value="' + sssNum.toFixed(2) + '" oninput="recalcPayrollTotals()">' +
              '</div>' +
            '</td>' +
            '<td>' +
              '<div class="input-group input-group-sm">' +
                '<span class="input-group-text px-1 text-danger">-₱</span>' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm text-danger fw-bold" id="staffAdvance-' + s.id + '" value="' + advNum.toFixed(2) + '" oninput="recalcPayrollTotals()">' +
              '</div>' +
            '</td>' +
            '<td>' +
              '<div class="input-group input-group-sm">' +
                '<span class="input-group-text px-1">₱</span>' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm text-primary fw-bold" id="staffBonus-' + s.id + '" value="' + bonusCommNum.toFixed(2) + '" oninput="recalcPayrollTotals()">' +
              '</div>' +
            '</td>' +
            '<td><span class="badge bg-light text-success border" id="staffPosComm-' + s.id + '" data-pos-comm="' + posCommNum.toFixed(2) + '">₱' + fmt(posCommNum) + '</span></td>' +
            '<td>' +
              '<strong class="text-dark" id="staffNet-' + s.id + '">₱' + fmt(netPayout) + '</strong>' +
              '<div class="text-danger" style="font-size:10px;" id="staffDedTotal-' + s.id + '">Ded: -₱' + fmt(totalDedNum) + '</div>' +
            '</td>' +
            '<td class="text-end text-nowrap">' +
              '<button class="btn btn-outline-primary btn-sm py-0 px-2" title="Save Salary, Deductions &amp; Bonus" onclick="saveSingleStaff(' + s.id + ', this)"><i class="fa fa-floppy-o"></i> Save</button>' +
            '</td>' +
          '</tr>';
        }
        sBody.innerHTML = html;
      }

      const cBox = document.getElementById('commissionBox');
      if (!data.commissions || data.commissions.length === 0) {
        cBox.innerHTML = '<div class="text-muted small py-2">No pending POS sales commissions. All caught up!</div>';
      } else {
        let html = '';
        for (const c of data.commissions) {
          html += '<div class="d-flex justify-content-between align-items-center py-1 border-bottom small">' +
            '<span>' + c.cashier_name + ' (' + c.order_count + ' orders)</span>' +
            '<strong class="text-success">₱' + parseFloat(c.total_incentive).toFixed(2) + '</strong>' +
          '</div>';
        }
        cBox.innerHTML = html;
      }

      recalcPayrollTotals();
      renderPayrollCalendar();
    }

    function setPayrollCalMode(mode) {
      payrollCalMode = mode;
      const btnCur = document.getElementById('btnCalMode-current');
      const btnSched = document.getElementById('btnCalMode-schedule');
      const btnAll = document.getElementById('btnCalMode-alldays');
      if (btnCur) btnCur.className = (mode === 'current') ? 'btn btn-dark fw-bold' : 'btn btn-outline-dark fw-bold';
      if (btnSched) btnSched.className = (mode === 'schedule') ? 'btn btn-dark fw-bold' : 'btn btn-outline-dark fw-bold';
      if (btnAll) btnAll.className = (mode === 'alldays') ? 'btn btn-dark fw-bold' : 'btn btn-outline-dark fw-bold';
      renderPayrollCalendar();
    }

    function renderPayrollCalendar() {
      const headEl = document.getElementById('payrollCalendarHead');
      const bodyEl = document.getElementById('payrollCalendarBody');
      if (!headEl || !bodyEl) return;

      const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const todayIso = getLocalTodayIso();
      const currentYm = todayIso.slice(0, 7);
      const todayDay = parseInt(todayIso.slice(8, 10), 10);

      const calMonthEl = document.getElementById('payrollCalMonth');
      const ym = (calMonthEl && calMonthEl.value) ? calMonthEl.value : currentYm;
      const [yearStr, monthStr] = ym.split('-');
      const year = parseInt(yearStr, 10);
      const month = parseInt(monthStr, 10); // 1..12
      const daysInMonth = new Date(year, month, 0).getDate();
      const monthNamesShort = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
      const dayNamesShort = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const mShort = monthNamesShort[month - 1] || 'MON';

      // Build list of horizontal date columns (YYYY-MM-DD) for the selected calendar month
      // Ordered from Current Date to Previous (descending)
      const dateSet = new Set();
      const maxDayForCurrentMode = (ym === currentYm) ? Math.min(todayDay, daysInMonth) : daysInMonth;

      if (payrollCalMode === 'alldays') {
        for (let d = daysInMonth; d >= 1; d--) {
          dateSet.add(ym + '-' + String(d).padStart(2, '0'));
        }
      } else if (payrollCalMode === 'schedule') {
        const stdDays = [1, 5, 10, 15, 20, 25, daysInMonth];
        for (const d of stdDays) {
          if (d <= maxDayForCurrentMode) {
            dateSet.add(ym + '-' + String(d).padStart(2, '0'));
          }
        }
        if (ym === currentYm) {
          dateSet.add(todayIso);
        }
        for (const rec of currentPayrollLedger) {
          if (rec.pay_date && rec.pay_date.startsWith(ym)) {
            dateSet.add(rec.pay_date);
          }
        }
      } else {
        // Default 'current': Current Date down to 1st of the current calendar month
        for (let d = maxDayForCurrentMode; d >= 1; d--) {
          dateSet.add(ym + '-' + String(d).padStart(2, '0'));
        }
        for (const rec of currentPayrollLedger) {
          if (rec.pay_date && rec.pay_date.startsWith(ym)) {
            dateSet.add(rec.pay_date);
          }
        }
      }

      // Sort descending: Current Date to Previous (newest date first on the left)
      const dateCols = Array.from(dateSet).sort((a, b) => b.localeCompare(a));

      // Build fast lookup map: staff_id_pay_date -> ledger record
      const ledgerMap = {};
      for (const rec of currentPayrollLedger) {
        ledgerMap[rec.staff_id + '_' + rec.pay_date] = rec;
      }

      // 1. Render Horizontal Header Row with Dates on Top (Current Date -> Previous)
      let headHtml = '<tr>' +
        '<th style="min-width: 195px; position: sticky; left: 0; background: #f8fafc; z-index: 6;">Employee / Role</th>' +
        '<th style="min-width: 195px; background: #f8fafc;">Net Compensation Package<br><small class="text-muted fw-normal">Base - Deductions + Bonus/Comm</small></th>';

      for (const dt of dateCols) {
        const dayNum = parseInt(dt.slice(8, 10), 10);
        const dObj = new Date(year, month - 1, dayNum);
        const dow = dayNamesShort[dObj.getDay()];
        const isCutoff = (dayNum === 15 || dayNum === daysInMonth);
        const isToday = (dt === todayIso);
        let thStyle = 'min-width: 128px; text-align: center; vertical-align: middle;';
        let tagBadge = '<div class="text-muted fw-normal" style="font-size:10px;">' + dow + '</div>';

        if (isToday) {
          thStyle += ' background: #ecfdf5; border-bottom: 2px solid #10b981;';
          tagBadge = '<span class="badge bg-success" style="font-size:9px;">Current (' + dow + ')</span>';
        } else if (isCutoff) {
          thStyle += ' background: #eff6ff; border-bottom: 2px solid #3b82f6;';
          tagBadge = '<span class="badge" style="background:#dbeafe; color:#1d4ed8; font-size:9px;">' + (dayNum === 15 ? '1st Cutoff' : '2nd Cutoff') + '</span>';
        }

        headHtml += '<th style="' + thStyle + '">' +
          '<div class="fw-bold" style="font-size: 12px; color: #0f172a;">' + mShort + ' ' + String(dayNum).padStart(2, '0') + '</div>' +
          tagBadge +
        '</th>';
      }

      headHtml += '<th style="min-width: 165px; text-align: right; background: #f8fafc;">Month Summary<br><small class="text-muted fw-normal">Paid Status &amp; Total</small></th></tr>';
      headEl.innerHTML = headHtml;

      // 2. Render Employee Rows
      if (currentStaffList.length === 0) {
        bodyEl.innerHTML = '<tr><td colspan="' + (dateCols.length + 3) + '" class="text-center text-muted py-3">No staff found for this store.</td></tr>';
        return;
      }

      let bodyHtml = '';
      for (const s of currentStaffList) {
        const baseInput = document.getElementById('staffBase-' + s.id);
        const taxPctInput = document.getElementById('staffTaxPct-' + s.id);
        const sssInput = document.getElementById('staffSss-' + s.id);
        const advInput = document.getElementById('staffAdvance-' + s.id);
        const bonusInput = document.getElementById('staffBonus-' + s.id);
        const posEl = document.getElementById('staffPosComm-' + s.id);

        const curBase = baseInput ? Math.max(0, parseFloat(baseInput.value || 0)) : parseFloat(s.base_salary || 0);
        const curTaxPct = taxPctInput ? Math.max(0, Math.min(100, parseFloat(taxPctInput.value || 0))) : parseFloat(s.tax_pct || 0);
        const curTaxAmt = Math.round(curBase * (curTaxPct / 100) * 100) / 100;
        const curSss = sssInput ? Math.max(0, parseFloat(sssInput.value || 0)) : parseFloat(s.sss_contribution || 0);
        const curAdv = advInput ? Math.max(0, parseFloat(advInput.value || 0)) : parseFloat(s.advance_payment || 0);
        const curBonus = bonusInput ? Math.max(0, parseFloat(bonusInput.value || 0)) : parseFloat(s.bonus_commission || 0);
        const curPos = posEl ? parseFloat(posEl.getAttribute('data-pos-comm') || 0) : parseFloat(s.pos_commission || 0);
        const curDed = curTaxAmt + curSss + curAdv;
        const curNet = Math.max(0, (curBase - curDed) + curBonus + curPos);

        let monthPaidCount = 0;
        let monthPaidTotal = 0;

        bodyHtml += '<tr>' +
          '<td style="position: sticky; left: 0; background: #ffffff; z-index: 4;">' +
            '<strong>' + s.full_name + '</strong><br>' +
            '<span class="badge bg-info text-dark" style="font-size:10px;">' + s.role + '</span>' +
          '</td>' +
          '<td class="bg-light">' +
            '<div class="fw-bold text-dark">₱' + fmt(curNet) + '</div>' +
            '<div class="text-muted" style="font-size: 10px;">Sal: ₱' + fmt(curBase) + ' | <span class="text-danger">Ded: -₱' + fmt(curDed) + '</span></div>' +
            '<div class="text-muted" style="font-size: 10px;">B&amp;C: <span class="text-primary fw-bold">+₱' + fmt(curBonus + curPos) + '</span></div>' +
          '</td>';

        for (const dt of dateCols) {
          const rec = ledgerMap[s.id + '_' + dt];
          if (rec && rec.status === 'PAID') {
            monthPaidCount++;
            const paidTot = parseFloat(rec.total_paid || 0);
            const paidSal = parseFloat(rec.base_salary || 0);
            const paidDed = parseFloat(rec.tax_amount || 0) + parseFloat(rec.sss_contribution || 0) + parseFloat(rec.advance_payment || 0);
            const paidBC = parseFloat(rec.bonus_commission || 0) + parseFloat(rec.pos_commission || 0);
            monthPaidTotal += paidTot;

            bodyHtml += '<td class="text-center p-1" style="background: #f0fdf4; cursor: pointer;" title="PAID on ' + dt + '. Click to view/reprint payslip or revert status." data-staff-id="' + s.id + '" data-pay-date="' + dt + '" onclick="toggleStaffCalendarDate(this, this.getAttribute(&quot;data-staff-id&quot;), this.getAttribute(&quot;data-pay-date&quot;), true)">' +
              '<div class="border border-success rounded p-1" style="background: #dcfce7;">' +
                '<div class="fw-bold text-success" style="font-size: 11px;"><i class="fa fa-check-circle"></i> PAID</div>' +
                '<div class="fw-bold text-dark" style="font-size: 11.5px;">₱' + fmt(paidTot) + '</div>' +
                '<div class="text-muted" style="font-size: 9px;">Sal: ₱' + Math.round(paidSal).toLocaleString() + ' | <span class="text-danger">-₱' + Math.round(paidDed).toLocaleString() + '</span> | +₱' + Math.round(paidBC).toLocaleString() + '</div>' +
                '<div class="mt-1">' +
                  '<span class="badge bg-white text-success border border-success" style="font-size: 9px; cursor: pointer;" title="Print Official Payslip" onclick="event.stopPropagation(); printStaffPayslipByDate(' + s.id + ', &quot;' + dt + '&quot;)"><i class="fa fa-print"></i> Print Slip</span>' +
                '</div>' +
              '</div>' +
            '</td>';
          } else {
            bodyHtml += '<td class="text-center p-1" style="cursor: pointer;" title="Click to pay &amp; print payslip for ' + s.full_name.replace(/"/g, '&quot;') + ' on ' + dt + '" data-staff-id="' + s.id + '" data-pay-date="' + dt + '" onclick="toggleStaffCalendarDate(this, this.getAttribute(&quot;data-staff-id&quot;), this.getAttribute(&quot;data-pay-date&quot;), false)">' +
              '<div class="border rounded py-2 px-1 text-muted" style="background: #fafafa; border-style: dashed !important; font-size: 11px;">' +
                '<i class="fa fa-circle-o"></i> Unpaid' +
              '</div>' +
            '</td>';
          }
        }

        const summaryBadge = monthPaidCount > 0
          ? '<span class="badge bg-success mb-1"><i class="fa fa-check"></i> PAID (' + monthPaidCount + 'x)</span><div class="fw-bold text-success" style="font-size:12.5px;">₱' + fmt(monthPaidTotal) + '</div>'
          : '<span class="badge bg-warning text-dark">Pending Payment</span><div class="text-muted small">₱0.00 paid</div>';

        bodyHtml += '<td class="text-end bg-light">' + summaryBadge + '</td></tr>';
      }

      bodyEl.innerHTML = bodyHtml;
    }

    function formatFriendlyDate(isoDate) {
      if (!isoDate) return '';
      const parts = String(isoDate).split('-');
      if (parts.length !== 3) return isoDate;
      const y = parseInt(parts[0], 10);
      const m = parseInt(parts[1], 10);
      const d = parseInt(parts[2], 10);
      const dt = new Date(y, m - 1, d);
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      return (months[m - 1] || '') + ' ' + String(d).padStart(2, '0') + ', ' + y + ' (' + (days[dt.getDay()] || '') + ')';
    }

    function getStaffInitials(name) {
      const parts = String(name || 'Staff').trim().split(' ').filter(Boolean);
      if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
      }
      return String(name || 'ST').slice(0, 2).toUpperCase();
    }

    function getStoreDisplayName() {
      return currentSupplierName || 'eConstruction Store ERP';
    }

    function buildSlipNumber(staffId, payDate) {
      const cleanDate = String(payDate || getLocalTodayIso()).split('-').join('');
      const cleanId = String(staffId || 0).padStart(3, '0');
      return 'PS-' + cleanDate + '-' + cleanId;
    }

    let currentPayslipConfig = null;

    function printPayslipDocument(cfg) {
      if (!cfg) return;
      const iframe = document.getElementById('payslipPrintIframe');
      if (!iframe) return;
      const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      const storeName = cfg.storeName || getStoreDisplayName();
      const slipNo = cfg.slipNo || buildSlipNumber(cfg.staffId, cfg.payDate);
      const payDateFmt = formatFriendlyDate(cfg.payDate);
      const payDateIso = cfg.payDate || getLocalTodayIso();
      const staffName = cfg.staffName || 'Staff Member';
      const staffCode = cfg.staffCode || ('EMP-' + String(cfg.staffId || 1).padStart(3, '0'));
      const staffRole = cfg.staffRole || 'Staff';
      const staffEmail = cfg.staffEmail || 'N/A';
      const statusLabel = cfg.statusLabel || 'PAID';

      const baseSalary = Number(cfg.baseSalary || 0);
      const bonusCommission = Number(cfg.bonusCommission || 0);
      const posCommission = Number(cfg.posCommission || 0);
      const grossEarnings = baseSalary + bonusCommission + posCommission;

      const taxPct = Number(cfg.taxPct || 0);
      const taxAmount = Number(cfg.taxAmount || 0);
      const sssContribution = Number(cfg.sssContribution || 0);
      const advancePayment = Number(cfg.advancePayment || 0);
      const totalDeductions = taxAmount + sssContribution + advancePayment;

      const netBaseSalary = baseSalary - totalDeductions;
      const totalAddPay = bonusCommission + posCommission;
      const netPayout = Math.max(0, netBaseSalary + totalAddPay);
      const printedAt = new Date().toLocaleString();

      const buildSlipSectionHtml = (copyTitle) => {
        return '<div class="payslip-sheet">' +
          '<div class="slip-header">' +
            '<div>' +
              '<div class="store-title">' + storeName + '</div>' +
              '<div class="doc-subtitle">OFFICIAL EMPLOYEE SALARY &amp; COMMISSION PAYSLIP</div>' +
            '</div>' +
            '<div class="slip-meta">' +
              '<div class="copy-badge">' + copyTitle + '</div>' +
              '<div><strong>Payslip No:</strong> ' + slipNo + '</div>' +
              '<div><strong>Pay Date:</strong> ' + payDateFmt + ' (' + payDateIso + ')</div>' +
            '</div>' +
          '</div>' +
          '<div class="emp-bar">' +
            '<div class="emp-col">' +
              '<div><span class="lbl">Employee Name:</span> <strong>' + staffName + '</strong></div>' +
              '<div><span class="lbl">Employee ID:</span> ' + staffCode + ' &nbsp;|&nbsp; <span class="lbl">Email:</span> ' + staffEmail + '</div>' +
            '</div>' +
            '<div class="emp-col right">' +
              '<div><span class="lbl">Designation / Role:</span> <strong>' + staffRole + '</strong></div>' +
              '<div><span class="lbl">Payment Status:</span> <span class="status-pill">' + statusLabel + '</span></div>' +
            '</div>' +
          '</div>' +
          '<div class="tables-grid">' +
            '<div class="table-box">' +
              '<div class="box-head earn-head">EARNINGS &amp; INCENTIVES (PHP)</div>' +
              '<table class="line-table">' +
                '<tr><td>Basic / Base Salary</td><td class="amt">₱' + fmt(baseSalary) + '</td></tr>' +
                '<tr><td>Bonus / Incentive Pay</td><td class="amt">+₱' + fmt(bonusCommission) + '</td></tr>' +
                '<tr><td>Accrued POS Sales Commission</td><td class="amt">+₱' + fmt(posCommission) + '</td></tr>' +
                '<tr class="sub-row"><td><strong>Gross Earnings</strong></td><td class="amt"><strong>₱' + fmt(grossEarnings) + '</strong></td></tr>' +
              '</table>' +
            '</div>' +
            '<div class="table-box">' +
              '<div class="box-head ded-head">DEDUCTIONS FROM BASE SALARY (PHP)</div>' +
              '<table class="line-table">' +
                '<tr><td>Withholding Tax (' + taxPct.toFixed(2) + '%)</td><td class="amt ded">-₱' + fmt(taxAmount) + '</td></tr>' +
                '<tr><td>SSS Contribution</td><td class="amt ded">-₱' + fmt(sssContribution) + '</td></tr>' +
                '<tr><td>Cash Advance / Vale</td><td class="amt ded">-₱' + fmt(advancePayment) + '</td></tr>' +
                '<tr class="sub-row ded-sub"><td><strong>Total Deductions</strong></td><td class="amt ded"><strong>-₱' + fmt(totalDeductions) + '</strong></td></tr>' +
              '</table>' +
            '</div>' +
          '</div>' +
          '<div class="net-banner">' +
            '<div>' +
              '<div class="net-title">NET TAKE-HOME PAY (NET PAYOUT)</div>' +
              '<div class="net-formula">Net Base Salary (₱' + fmt(baseSalary) + ' - ₱' + fmt(totalDeductions) + ' = ₱' + fmt(netBaseSalary) + ') + Bonus/Commission (₱' + fmt(totalAddPay) + ')</div>' +
            '</div>' +
            '<div class="net-amount">₱' + fmt(netPayout) + '</div>' +
          '</div>' +
          '<div class="sig-grid">' +
            '<div class="sig-box">' +
              '<div class="sig-line"></div>' +
              '<div class="sig-label">Prepared &amp; Approved By (Authorized Signatory)</div>' +
            '</div>' +
            '<div class="sig-box">' +
              '<div class="sig-line"></div>' +
              '<div class="sig-label">Received By: <strong>' + staffName + '</strong> (Signature &amp; Date)</div>' +
            '</div>' +
          '</div>' +
          '<div class="slip-foot">' +
            '<span>Acknowledgement: I hereby acknowledge receipt of the above Net Take-Home Pay in full settlement for the stated pay date.</span>' +
            '<span>Printed: ' + printedAt + '</span>' +
          '</div>' +
        '</div>';
      };

      const docHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Payslip - ' + staffName + ' (' + payDateIso + ')</title>' +
        '<style>' +
          '@page { size: A4 portrait; margin: 10mm; }' +
          '* { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }' +
          'body { font-family: "Segoe UI", Arial, sans-serif; color: #0f172a; margin: 0; padding: 0; font-size: 11.5px; background: #fff; }' +
          '.payslip-sheet { border: 1.5px solid #0f172a; border-radius: 6px; padding: 12px 14px; margin-bottom: 10px; page-break-inside: avoid; }' +
          '.slip-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 8px; }' +
          '.store-title { font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: 0.3px; }' +
          '.doc-subtitle { font-size: 10.5px; font-weight: 700; color: #1d4ed8; letter-spacing: 0.8px; margin-top: 2px; }' +
          '.slip-meta { text-align: right; font-size: 10.5px; line-height: 1.4; }' +
          '.copy-badge { display: inline-block; background: #0f172a; color: #fff; font-weight: 700; font-size: 9.5px; padding: 2px 7px; border-radius: 3px; letter-spacing: 0.6px; margin-bottom: 3px; }' +
          '.emp-bar { display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 7px 10px; margin-bottom: 8px; font-size: 11px; line-height: 1.45; }' +
          '.emp-col.right { text-align: right; }' +
          '.lbl { color: #475569; }' +
          '.status-pill { display: inline-block; background: #16a34a; color: #fff; font-weight: 700; font-size: 9.5px; padding: 1px 6px; border-radius: 3px; }' +
          '.tables-grid { display: flex; gap: 10px; margin-bottom: 8px; }' +
          '.table-box { flex: 1; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }' +
          '.box-head { font-size: 10px; font-weight: 800; padding: 5px 8px; letter-spacing: 0.5px; border-bottom: 1px solid #cbd5e1; }' +
          '.earn-head { background: #f0fdf4; color: #166534; }' +
          '.ded-head { background: #fef2f2; color: #991b1b; }' +
          '.line-table { width: 100%; border-collapse: collapse; font-size: 11px; }' +
          '.line-table td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }' +
          '.line-table td.amt { text-align: right; font-weight: 600; }' +
          '.line-table td.ded { color: #dc2626; }' +
          '.line-table tr.sub-row td { background: #f8fafc; border-top: 1px solid #cbd5e1; border-bottom: none; padding: 6px 8px; }' +
          '.line-table tr.ded-sub td { background: #fef2f2; }' +
          '.net-banner { display: flex; justify-content: space-between; align-items: center; background: #ecfdf5; border: 1.5px solid #16a34a; border-radius: 5px; padding: 8px 12px; margin-bottom: 12px; }' +
          '.net-title { font-size: 12px; font-weight: 800; color: #065f46; letter-spacing: 0.4px; }' +
          '.net-formula { font-size: 10px; color: #334155; margin-top: 2px; }' +
          '.net-amount { font-size: 18px; font-weight: 800; color: #15803d; }' +
          '.sig-grid { display: flex; gap: 24px; margin-top: 16px; margin-bottom: 6px; }' +
          '.sig-box { flex: 1; text-align: center; }' +
          '.sig-line { border-bottom: 1px solid #334155; height: 22px; margin: 0 15px 4px 15px; }' +
          '.sig-label { font-size: 10px; color: #475569; }' +
          '.slip-foot { display: flex; justify-content: space-between; font-size: 9px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 4px; }' +
          '.cut-line { text-align: center; font-size: 10px; color: #64748b; margin: 10px 0; border-top: 1px dashed #94a3b8; padding-top: 3px; letter-spacing: 1px; }' +
        '</style></head><body>' +
        buildSlipSectionHtml('EMPLOYEE COPY') +
        '<div class="cut-line">&#9986; DETACH HERE &mdash; EMPLOYER FILE COPY BELOW &#9986;</div>' +
        buildSlipSectionHtml('EMPLOYER FILE COPY') +
        '</body></html>';

      const doc = iframe.contentWindow.document;
      doc.open();
      doc.write(docHtml);
      doc.close();
      setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      }, 180);
    }

    function printCurrentModalPayslip() {
      if (!currentPayslipConfig) return;
      printPayslipDocument(currentPayslipConfig);
    }

    function printStaffPayslipByDate(staffId, payDate) {
      staffId = parseInt(staffId, 10);
      const staff = currentStaffList.find(s => s.id === staffId);
      if (!staff) return;

      const rec = currentPayrollLedger.find(r => Number(r.staff_id) === staffId && r.pay_date === payDate && r.status === 'PAID');
      let baseSalary, taxPct, taxAmount, sssContribution, advancePayment, bonusCommission, posCommission, total;

      if (rec) {
        baseSalary = parseFloat(rec.base_salary || 0);
        taxPct = parseFloat(rec.tax_pct || 0);
        taxAmount = parseFloat(rec.tax_amount || 0);
        sssContribution = parseFloat(rec.sss_contribution || 0);
        advancePayment = parseFloat(rec.advance_payment || 0);
        bonusCommission = parseFloat(rec.bonus_commission || 0);
        posCommission = parseFloat(rec.pos_commission || 0);
        total = parseFloat(rec.total_paid || 0);
      } else {
        const baseInput = document.getElementById('staffBase-' + staffId);
        const taxPctInput = document.getElementById('staffTaxPct-' + staffId);
        const sssInput = document.getElementById('staffSss-' + staffId);
        const advInput = document.getElementById('staffAdvance-' + staffId);
        const bonusInput = document.getElementById('staffBonus-' + staffId);
        const posEl = document.getElementById('staffPosComm-' + staffId);

        baseSalary = baseInput ? Math.max(0, parseFloat(baseInput.value || 0)) : parseFloat(staff.base_salary || 0);
        taxPct = taxPctInput ? Math.max(0, Math.min(100, parseFloat(taxPctInput.value || 0))) : parseFloat(staff.tax_pct || 0);
        taxAmount = Math.round(baseSalary * (taxPct / 100) * 100) / 100;
        sssContribution = sssInput ? Math.max(0, parseFloat(sssInput.value || 0)) : parseFloat(staff.sss_contribution || 0);
        advancePayment = advInput ? Math.max(0, parseFloat(advInput.value || 0)) : parseFloat(staff.advance_payment || 0);
        bonusCommission = bonusInput ? Math.max(0, parseFloat(bonusInput.value || 0)) : parseFloat(staff.bonus_commission || 0);
        posCommission = posEl ? parseFloat(posEl.getAttribute('data-pos-comm') || 0) : parseFloat(staff.pos_commission || 0);
        total = Math.max(0, (baseSalary - (taxAmount + sssContribution + advancePayment)) + bonusCommission + posCommission);
      }

      printPayslipDocument({
        storeName: getStoreDisplayName(),
        slipNo: buildSlipNumber(staffId, payDate),
        staffId: staffId,
        staffCode: 'EMP-' + String(staffId).padStart(3, '0'),
        staffName: staff.full_name,
        staffEmail: staff.email || 'N/A',
        staffRole: staff.role || 'Staff',
        payDate: payDate,
        statusLabel: rec ? 'PAID' : 'DRAFT / UNPAID',
        baseSalary: baseSalary,
        taxPct: taxPct,
        taxAmount: taxAmount,
        sssContribution: sssContribution,
        advancePayment: advancePayment,
        bonusCommission: bonusCommission,
        posCommission: posCommission,
        total: total
      });
    }

    function showErpToast(title, message, variant) {
      const container = document.getElementById('erpToastContainer');
      if (!container) return;
      const isError = (variant === 'danger' || variant === 'error');
      const isWarn = (variant === 'warning');
      const borderColor = isError ? '#ef4444' : (isWarn ? '#f59e0b' : '#10b981');
      const iconHtml = isError
        ? '<i class="fa fa-exclamation-circle text-danger me-2" style="font-size:16px;"></i>'
        : (isWarn
            ? '<i class="fa fa-undo text-warning me-2" style="font-size:16px;"></i>'
            : '<i class="fa fa-check-circle text-success me-2" style="font-size:16px;"></i>');

      const toastEl = document.createElement('div');
      toastEl.className = 'bg-white shadow rounded p-3 border-start border-4';
      toastEl.style.borderLeftColor = borderColor;
      toastEl.style.transition = 'opacity 0.25s ease';
      toastEl.innerHTML =
        '<div class="d-flex justify-content-between align-items-start">' +
          '<div class="d-flex align-items-start">' +
            iconHtml +
            '<div>' +
              '<div class="fw-bold text-dark" style="font-size: 12.5px;">' + title + '</div>' +
              '<div class="text-muted" style="font-size: 11.5px;">' + message + '</div>' +
            '</div>' +
          '</div>' +
          '<button type="button" class="btn-close" style="font-size: 10px;" onclick="this.closest(&quot;.shadow&quot;).remove()"></button>' +
        '</div>';
      container.appendChild(toastEl);
      setTimeout(() => {
        if (toastEl.parentNode) {
          toastEl.style.opacity = '0';
          setTimeout(() => { if (toastEl.parentNode) toastEl.remove(); }, 260);
        }
      }, 4000);
    }

    function openPayrollConfirmModal(cfg, actionFn) {
      if (isPayrollPaymentBusy) return;
      const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      pendingPayrollConfirmAction = actionFn;

      const storeName = getStoreDisplayName();
      const slipNo = buildSlipNumber(cfg.staffId || 0, cfg.payDate);
      const staffCode = cfg.staffId ? ('EMP-' + String(cfg.staffId).padStart(3, '0')) : 'BULK-PAY';

      const baseSalary = Number(cfg.baseSalary || 0);
      const taxPct = Number(cfg.taxPct || 0);
      const taxAmount = Number(cfg.taxAmount || 0);
      const sssContribution = Number(cfg.sssContribution || 0);
      const advancePayment = Number(cfg.advancePayment || 0);
      const bonusCommission = Number(cfg.bonusCommission || 0);
      const posCommission = Number(cfg.posCommission || 0);
      const grossEarnings = baseSalary + bonusCommission + posCommission;
      const totalDeductions = taxAmount + sssContribution + advancePayment;
      const netBaseSalary = baseSalary - totalDeductions;
      const totalAddPay = bonusCommission + posCommission;
      const totalNet = Number(cfg.total || 0);

      currentPayslipConfig = {
        storeName: storeName,
        slipNo: slipNo,
        staffId: cfg.staffId || 0,
        staffCode: staffCode,
        staffName: cfg.staffName || 'Staff Member',
        staffEmail: cfg.staffEmail || 'N/A',
        staffRole: cfg.staffRole || 'Staff',
        payDate: cfg.payDate,
        statusLabel: 'PAID',
        baseSalary: baseSalary,
        taxPct: taxPct,
        taxAmount: taxAmount,
        sssContribution: sssContribution,
        advancePayment: advancePayment,
        bonusCommission: bonusCommission,
        posCommission: posCommission,
        total: totalNet
      };

      const headerEl = document.getElementById('pcmHeader');
      const titleEl = document.getElementById('pcmTitle');
      const storeNameEl = document.getElementById('pcmStoreName');
      const slipNoEl = document.getElementById('pcmSlipNo');
      const initialsEl = document.getElementById('pcmInitials');
      const nameEl = document.getElementById('pcmStaffName');
      const codeEl = document.getElementById('pcmStaffCode');
      const emailEl = document.getElementById('pcmStaffEmail');
      const roleEl = document.getElementById('pcmStaffRole');
      const transEl = document.getElementById('pcmStatusTransition');
      const dateFmtEl = document.getElementById('pcmPayDateFormatted');
      const dateIsoEl = document.getElementById('pcmPayDateIso');
      const baseEl = document.getElementById('pcmBaseSalary');
      const taxPctLabelEl = document.getElementById('pcmTaxPctLabel');
      const taxAmtEl = document.getElementById('pcmTaxAmt');
      const sssAmtEl = document.getElementById('pcmSssAmt');
      const advAmtEl = document.getElementById('pcmAdvanceAmt');
      const bonusEl = document.getElementById('pcmBonusComm');
      const posRowEl = document.getElementById('pcmPosCommRow');
      const posEl = document.getElementById('pcmPosComm');
      const grossEl = document.getElementById('pcmGrossEarnings');
      const totDedEl = document.getElementById('pcmTotalDeductions');
      const netBaseEl = document.getElementById('pcmNetBaseSalary');
      const totBonusPosEl = document.getElementById('pcmTotalBonusPos');
      const totalLabelEl = document.getElementById('pcmTotalLabel');
      const totalEl = document.getElementById('pcmTotalPayout');
      const noteEl = document.getElementById('pcmNote');
      const confirmBtn = document.getElementById('pcmConfirmBtn');
      const confirmPrintBtn = document.getElementById('pcmConfirmPrintBtn');
      const printOnlyBtn = document.getElementById('pcmPrintOnlyBtn');
      const cancelBtn = document.getElementById('pcmCancelBtn');
      const closeIcon = document.getElementById('pcmCloseIcon');

      const isRevert = !!cfg.isRevert;
      if (headerEl) {
        headerEl.style.background = isRevert
          ? 'linear-gradient(135deg, #d97706, #b45309)'
          : 'linear-gradient(135deg, #0f172a, #1e293b)';
      }
      if (titleEl) {
        titleEl.innerHTML = isRevert
          ? '<i class="fa fa-undo me-1"></i> Payslip Voucher &amp; Revert PAID Status'
          : '<i class="fa fa-file-text-o me-1"></i> ' + (cfg.title || 'Official Employee Payslip &amp; Payment Voucher');
      }
      if (storeNameEl) storeNameEl.textContent = storeName;
      if (slipNoEl) slipNoEl.textContent = slipNo;
      if (initialsEl) {
        initialsEl.textContent = getStaffInitials(cfg.staffName);
        initialsEl.style.background = isRevert ? '#b45309' : '#0f172a';
      }
      if (nameEl) nameEl.textContent = cfg.staffName || 'Staff Member';
      if (codeEl) codeEl.textContent = staffCode;
      if (emailEl) emailEl.textContent = cfg.staffEmail || 'Store Staff';
      if (roleEl) roleEl.textContent = cfg.staffRole || 'Staff';
      if (transEl) {
        transEl.className = isRevert ? 'badge bg-warning text-dark' : 'badge bg-success';
        transEl.innerHTML = isRevert ? 'PAID &rarr; Unpaid' : 'Unpaid &rarr; PAID';
      }
      if (dateFmtEl) dateFmtEl.textContent = formatFriendlyDate(cfg.payDate);
      if (dateIsoEl) dateIsoEl.textContent = cfg.payDate || '';
      if (baseEl) baseEl.textContent = '₱' + fmt(baseSalary);
      if (taxPctLabelEl) taxPctLabelEl.textContent = taxPct.toFixed(2) + '%';
      if (taxAmtEl) taxAmtEl.textContent = '-₱' + fmt(taxAmount);
      if (sssAmtEl) sssAmtEl.textContent = '-₱' + fmt(sssContribution);
      if (advAmtEl) advAmtEl.textContent = '-₱' + fmt(advancePayment);
      if (bonusEl) bonusEl.textContent = '+₱' + fmt(bonusCommission);
      if (posEl) posEl.textContent = '+₱' + fmt(posCommission);
      if (posRowEl) posRowEl.style.display = 'flex';
      if (grossEl) grossEl.textContent = '₱' + fmt(grossEarnings);
      if (totDedEl) totDedEl.textContent = '-₱' + fmt(totalDeductions);
      if (netBaseEl) netBaseEl.textContent = '₱' + fmt(netBaseSalary);
      if (totBonusPosEl) totBonusPosEl.textContent = '+₱' + fmt(totalAddPay);

      if (totalLabelEl) {
        totalLabelEl.textContent = isRevert ? 'Paid Amount (To Revert)' : 'Net Take-Home Pay (Net Payout)';
      }
      if (totalEl) {
        totalEl.textContent = '₱' + fmt(totalNet);
        totalEl.className = isRevert ? 'text-warning fw-bold' : 'text-success fw-bold';
      }
      if (noteEl) {
        noteEl.innerHTML = cfg.noteHtml || (isRevert
          ? 'You can reprint the official payslip above, or click <strong>Confirm &amp; Set Unpaid</strong> to clear the PAID status for <strong>' + cfg.staffName + '</strong> on <strong>' + cfg.payDate + '</strong>.'
          : 'Click <strong>Confirm PAID &amp; Print Payslip</strong> to mark <strong>' + cfg.staffName + '</strong> as <strong>PAID</strong> on <strong>' + cfg.payDate + '</strong> and automatically print their official payslip.');
      }
      if (printOnlyBtn) {
        printOnlyBtn.disabled = false;
      }
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.className = isRevert ? 'btn btn-sm btn-warning text-dark fw-bold px-3' : 'btn btn-sm btn-outline-success fw-bold px-2';
        confirmBtn.innerHTML = isRevert
          ? '<i class="fa fa-undo me-1"></i> Confirm &amp; Set Unpaid'
          : '<i class="fa fa-check-circle me-1"></i> Mark PAID Only';
      }
      if (confirmPrintBtn) {
        confirmPrintBtn.disabled = false;
        confirmPrintBtn.style.display = isRevert ? 'none' : 'inline-block';
        confirmPrintBtn.innerHTML = '<i class="fa fa-print me-1"></i> ' + (cfg.confirmPrintLabel || 'Confirm PAID &amp; Print Payslip');
      }
      if (cancelBtn) cancelBtn.disabled = false;
      if (closeIcon) closeIcon.disabled = false;

      if (payrollConfirmModal) {
        payrollConfirmModal.show();
      }
    }

    async function executePayrollModalConfirm(shouldPrintPayslip) {
      if (isPayrollPaymentBusy || typeof pendingPayrollConfirmAction !== 'function') return;
      const confirmBtn = document.getElementById('pcmConfirmBtn');
      const confirmPrintBtn = document.getElementById('pcmConfirmPrintBtn');
      const printOnlyBtn = document.getElementById('pcmPrintOnlyBtn');
      const cancelBtn = document.getElementById('pcmCancelBtn');
      const closeIcon = document.getElementById('pcmCloseIcon');
      const slipSnapshot = currentPayslipConfig ? Object.assign({}, currentPayslipConfig, { statusLabel: 'PAID' }) : null;

      isPayrollPaymentBusy = true;
      syncStaffRowPayButtons();
      if (shouldPrintPayslip && confirmPrintBtn) {
        confirmPrintBtn.disabled = true;
        confirmPrintBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Paying &amp; Printing...';
      } else if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
      }
      if (confirmBtn) confirmBtn.disabled = true;
      if (confirmPrintBtn) confirmPrintBtn.disabled = true;
      if (printOnlyBtn) printOnlyBtn.disabled = true;
      if (cancelBtn) cancelBtn.disabled = true;
      if (closeIcon) closeIcon.disabled = true;

      try {
        await pendingPayrollConfirmAction();
        if (shouldPrintPayslip && slipSnapshot) {
          printPayslipDocument(slipSnapshot);
        }
      } finally {
        isPayrollPaymentBusy = false;
        pendingPayrollConfirmAction = null;
        if (payrollConfirmModal) {
          payrollConfirmModal.hide();
        }
        syncStaffRowPayButtons();
      }
    }

    async function toggleStaffCalendarDate(cellEl, staffId, payDate, isCurrentlyPaid) {
      if (isPayrollPaymentBusy) return;
      staffId = parseInt(staffId, 10);
      const staff = currentStaffList.find(s => s.id === staffId);
      if (!staff) return;

      const baseInput = document.getElementById('staffBase-' + staffId);
      const taxPctInput = document.getElementById('staffTaxPct-' + staffId);
      const sssInput = document.getElementById('staffSss-' + staffId);
      const advInput = document.getElementById('staffAdvance-' + staffId);
      const bonusInput = document.getElementById('staffBonus-' + staffId);
      const posEl = document.getElementById('staffPosComm-' + staffId);

      const baseSalary = baseInput ? Math.max(0, parseFloat(baseInput.value || 0)) : parseFloat(staff.base_salary || 0);
      const taxPct = taxPctInput ? Math.max(0, Math.min(100, parseFloat(taxPctInput.value || 0))) : parseFloat(staff.tax_pct || 0);
      const taxAmount = Math.round(baseSalary * (taxPct / 100) * 100) / 100;
      const sssContribution = sssInput ? Math.max(0, parseFloat(sssInput.value || 0)) : parseFloat(staff.sss_contribution || 0);
      const advancePayment = advInput ? Math.max(0, parseFloat(advInput.value || 0)) : parseFloat(staff.advance_payment || 0);
      const bonusCommission = bonusInput ? Math.max(0, parseFloat(bonusInput.value || 0)) : parseFloat(staff.bonus_commission || 0);
      const posCommission = posEl ? parseFloat(posEl.getAttribute('data-pos-comm') || 0) : parseFloat(staff.pos_commission || 0);
      const total = Math.max(0, (baseSalary - (taxAmount + sssContribution + advancePayment)) + bonusCommission + posCommission);

      openPayrollConfirmModal({
        isRevert: isCurrentlyPaid,
        title: isCurrentlyPaid ? 'Official Payslip &amp; Revert Status' : 'Official Employee Payslip &amp; Payment Voucher',
        staffId: staffId,
        staffName: staff.full_name,
        staffEmail: staff.email || 'N/A',
        staffRole: staff.role || 'Staff',
        payDate: payDate,
        baseSalary: baseSalary,
        taxPct: taxPct,
        taxAmount: taxAmount,
        sssContribution: sssContribution,
        advancePayment: advancePayment,
        bonusCommission: bonusCommission,
        posCommission: posCommission,
        total: total,
        confirmPrintLabel: 'Confirm PAID &amp; Print Payslip'
      }, async () => {
        if (cellEl) {
          cellEl.style.pointerEvents = 'none';
          cellEl.style.cursor = 'wait';
          cellEl.innerHTML =
            '<div class="border border-primary rounded py-2 px-1 text-primary fw-bold" style="background: #eff6ff; font-size: 11px;">' +
              '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
              (isCurrentlyPaid ? 'Updating...' : 'Paying...') +
            '</div>';
        }

        try {
          const res = await fetch('/api/payroll/calendar/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              supplier_id: currentSupplierId,
              staff_id: staffId,
              employee_name: staff.full_name,
              pay_date: payDate,
              base_salary: baseSalary,
              tax_pct: taxPct,
              tax_amount: taxAmount,
              sss_contribution: sssContribution,
              advance_payment: advancePayment,
              bonus_commission: bonusCommission,
              pos_commission: posCommission,
              status: isCurrentlyPaid ? 'UNPAID' : 'PAID'
            })
          });
          const data = await res.json();
          await loadPayroll();
          if (data.success) {
            showErpToast(
              isCurrentlyPaid ? 'Payment Status Reverted' : 'Marked as PAID',
              data.message,
              isCurrentlyPaid ? 'warning' : 'success'
            );
          } else {
            showErpToast('Update Failed', data.error || 'Could not update calendar', 'danger');
          }
        } catch (err) {
          await loadPayroll();
          showErpToast('Network Error', 'Could not update payment status.', 'danger');
        }
      });
    }

    function recalcPayrollTotals() {
      const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      let totalBase = 0;
      let totalTaxDed = 0;
      let totalSssDed = 0;
      let totalAdvDed = 0;
      let totalBonus = 0;
      let totalPos = 0;
      let grandNet = 0;

      for (const s of currentStaffList) {
        const baseInput = document.getElementById('staffBase-' + s.id);
        const taxPctInput = document.getElementById('staffTaxPct-' + s.id);
        const taxAmtLabel = document.getElementById('staffTaxAmtLabel-' + s.id);
        const sssInput = document.getElementById('staffSss-' + s.id);
        const advInput = document.getElementById('staffAdvance-' + s.id);
        const bonusInput = document.getElementById('staffBonus-' + s.id);
        const posEl = document.getElementById('staffPosComm-' + s.id);
        const netEl = document.getElementById('staffNet-' + s.id);
        const dedTotalEl = document.getElementById('staffDedTotal-' + s.id);

        const baseVal = baseInput ? Math.max(0, parseFloat(baseInput.value || 0)) : 0;
        const taxPctVal = taxPctInput ? Math.max(0, Math.min(100, parseFloat(taxPctInput.value || 0))) : 0;
        const taxAmtVal = Math.round(baseVal * (taxPctVal / 100) * 100) / 100;
        const sssVal = sssInput ? Math.max(0, parseFloat(sssInput.value || 0)) : 0;
        const advVal = advInput ? Math.max(0, parseFloat(advInput.value || 0)) : 0;
        const bonusVal = bonusInput ? Math.max(0, parseFloat(bonusInput.value || 0)) : 0;
        const posVal = posEl ? parseFloat(posEl.getAttribute('data-pos-comm') || 0) : 0;

        const rowDed = taxAmtVal + sssVal + advVal;
        const rowNet = Math.max(0, (baseVal - rowDed) + bonusVal + posVal);

        if (taxAmtLabel) taxAmtLabel.textContent = '-₱' + fmt(taxAmtVal);
        if (netEl) netEl.textContent = '₱' + fmt(rowNet);
        if (dedTotalEl) dedTotalEl.textContent = 'Ded: -₱' + fmt(rowDed);

        totalBase += baseVal;
        totalTaxDed += taxAmtVal;
        totalSssDed += sssVal;
        totalAdvDed += advVal;
        totalBonus += bonusVal;
        totalPos += posVal;
        grandNet += rowNet;
      }

      const elBase = document.getElementById('sumBaseSalaries');
      const elTax = document.getElementById('sumTaxDed');
      const elSss = document.getElementById('sumSssDed');
      const elAdv = document.getElementById('sumAdvanceDed');
      const elBonus = document.getElementById('sumBonusComm');
      const elPos = document.getElementById('sumPosComm');
      const elGrand = document.getElementById('sumGrandPayroll');
      if (elBase) elBase.textContent = '₱' + fmt(totalBase);
      if (elTax) elTax.textContent = '-₱' + fmt(totalTaxDed);
      if (elSss) elSss.textContent = '-₱' + fmt(totalSssDed);
      if (elAdv) elAdv.textContent = '-₱' + fmt(totalAdvDed);
      if (elBonus) elBonus.textContent = '₱' + fmt(totalBonus);
      if (elPos) elPos.textContent = '₱' + fmt(totalPos);
      if (elGrand) elGrand.textContent = '₱' + fmt(grandNet);

      renderPayrollCalendar();
    }

    async function saveSingleStaff(staffId, btnEl) {
      const baseInput = document.getElementById('staffBase-' + staffId);
      const taxPctInput = document.getElementById('staffTaxPct-' + staffId);
      const sssInput = document.getElementById('staffSss-' + staffId);
      const advInput = document.getElementById('staffAdvance-' + staffId);
      const bonusInput = document.getElementById('staffBonus-' + staffId);

      const baseSalary = baseInput ? parseFloat(baseInput.value || 0) : 0;
      const taxPct = taxPctInput ? parseFloat(taxPctInput.value || 0) : 0;
      const sssContribution = sssInput ? parseFloat(sssInput.value || 0) : 0;
      const advancePayment = advInput ? parseFloat(advInput.value || 0) : 0;
      const bonusCommission = bonusInput ? parseFloat(bonusInput.value || 0) : 0;

      if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...';
      }
      try {
        const res = await fetch('/api/payroll/staff/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            supplier_id: currentSupplierId,
            staff_id: staffId,
            base_salary: baseSalary,
            tax_pct: taxPct,
            sss_contribution: sssContribution,
            advance_payment: advancePayment,
            bonus_commission: bonusCommission
          })
        });
        const data = await res.json();
        if (data.success) {
          showErpToast('Compensation & Deductions Saved', data.message, 'success');
          await loadPayroll();
        } else {
          showErpToast('Save Failed', data.error || 'Unknown error', 'danger');
        }
      } finally {
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = '<i class="fa fa-floppy-o"></i> Save';
        }
      }
    }

    async function saveAllStaffCompensation() {
      const updates = currentStaffList.map(s => {
        const baseInput = document.getElementById('staffBase-' + s.id);
        const taxPctInput = document.getElementById('staffTaxPct-' + s.id);
        const sssInput = document.getElementById('staffSss-' + s.id);
        const advInput = document.getElementById('staffAdvance-' + s.id);
        const bonusInput = document.getElementById('staffBonus-' + s.id);
        return {
          staff_id: s.id,
          base_salary: baseInput ? parseFloat(baseInput.value || 0) : 0,
          tax_pct: taxPctInput ? parseFloat(taxPctInput.value || 0) : 0,
          sss_contribution: sssInput ? parseFloat(sssInput.value || 0) : 0,
          advance_payment: advInput ? parseFloat(advInput.value || 0) : 0,
          bonus_commission: bonusInput ? parseFloat(bonusInput.value || 0) : 0
        };
      });

      const btn = document.getElementById('btnSaveAllStaff');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
      }
      try {
        const res = await fetch('/api/payroll/staff/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            supplier_id: currentSupplierId,
            staff_updates: updates
          })
        });
        const data = await res.json();
        if (data.success) {
          showErpToast('All Salaries & Deductions Saved', data.message, 'success');
          await loadPayroll();
        } else {
          showErpToast('Save Failed', data.error || 'Unknown error', 'danger');
        }
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-floppy-o"></i> Save All Salaries &amp; Deductions';
        }
      }
    }

    async function disburseSingleStaff(staffId, btnEl) {
      if (isPayrollPaymentBusy) return;
      const staff = currentStaffList.find(s => s.id === staffId);
      if (!staff) return;
      const baseInput = document.getElementById('staffBase-' + staffId);
      const taxPctInput = document.getElementById('staffTaxPct-' + staffId);
      const sssInput = document.getElementById('staffSss-' + staffId);
      const advInput = document.getElementById('staffAdvance-' + staffId);
      const bonusInput = document.getElementById('staffBonus-' + staffId);
      const posEl = document.getElementById('staffPosComm-' + staffId);
      const payDateEl = document.getElementById('payrollPayDate');
      const payDate = (payDateEl && payDateEl.value) ? payDateEl.value : getLocalTodayIso();

      if (isStaffPaidOnDate(staffId, payDate)) {
        showErpToast('Already Paid', staff.full_name + ' is already marked as PAID on ' + payDate + '.', 'warning');
        syncStaffRowPayButtons();
        return;
      }

      const baseSalary = baseInput ? Math.max(0, parseFloat(baseInput.value || 0)) : 0;
      const taxPct = taxPctInput ? Math.max(0, Math.min(100, parseFloat(taxPctInput.value || 0))) : 0;
      const taxAmount = Math.round(baseSalary * (taxPct / 100) * 100) / 100;
      const sssContribution = sssInput ? Math.max(0, parseFloat(sssInput.value || 0)) : 0;
      const advancePayment = advInput ? Math.max(0, parseFloat(advInput.value || 0)) : 0;
      const bonusCommission = bonusInput ? Math.max(0, parseFloat(bonusInput.value || 0)) : 0;
      const posCommission = posEl ? parseFloat(posEl.getAttribute('data-pos-comm') || 0) : 0;
      const total = Math.max(0, (baseSalary - (taxAmount + sssContribution + advancePayment)) + bonusCommission + posCommission);

      openPayrollConfirmModal({
        isRevert: false,
        title: 'Confirm Staff Payroll Disbursement',
        staffName: staff.full_name,
        staffRole: staff.role || 'Staff',
        payDate: payDate,
        baseSalary: baseSalary,
        taxPct: taxPct,
        taxAmount: taxAmount,
        sssContribution: sssContribution,
        advancePayment: advancePayment,
        bonusCommission: bonusCommission,
        posCommission: posCommission,
        total: total,
        confirmLabel: 'Disburse &amp; Mark PAID',
        noteHtml: 'This will log a payroll expense in OPEX, settle accrued POS commissions, and mark <strong>' + staff.full_name + '</strong> as <strong>PAID</strong> on <strong>' + payDate + '</strong>.'
      }, async () => {
        if (btnEl) {
          btnEl.disabled = true;
          btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Paying...';
        }
        const calCell = document.querySelector('#payrollCalendarBody td[data-staff-id="' + staffId + '"][data-pay-date="' + payDate + '"]');
        if (calCell) {
          calCell.style.pointerEvents = 'none';
          calCell.innerHTML =
            '<div class="border border-primary rounded py-2 px-1 text-primary fw-bold" style="background: #eff6ff; font-size: 11px;">' +
              '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Paying...' +
            '</div>';
        }

        await fetch('/api/payroll/staff/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            supplier_id: currentSupplierId,
            staff_id: staffId,
            base_salary: baseSalary,
            tax_pct: taxPct,
            sss_contribution: sssContribution,
            advance_payment: advancePayment,
            bonus_commission: bonusCommission
          })
        });

        const res = await fetch('/api/payroll/disburse', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            supplier_id: currentSupplierId,
            staff_id: staffId,
            employee_name: staff.full_name,
            pay_date: payDate,
            base_salary: baseSalary,
            tax_pct: taxPct,
            tax_amount: taxAmount,
            sss_contribution: sssContribution,
            advance_payment: advancePayment,
            bonus_commission: bonusCommission,
            commission_amount: posCommission
          })
        });
        const data = await res.json();
        await loadPayroll();
        await loadExpenses();
        await loadWaterfall();
        if (data.success) {
          showErpToast('Payroll Disbursed', data.message, 'success');
        } else {
          showErpToast('Disbursement Blocked', data.error || 'Could not disburse payment.', 'danger');
        }
      });
    }

    async function disbursePayroll(btnEl) {
      if (isPayrollPaymentBusy) return;
      let totalBase = 0;
      let totalTaxAmt = 0;
      let totalSss = 0;
      let totalAdv = 0;
      let totalBonus = 0;
      let totalPos = 0;
      let grandTotal = 0;
      const updates = [];
      const staffItems = [];
      const payDateEl = document.getElementById('payrollPayDate');
      const payDate = (payDateEl && payDateEl.value) ? payDateEl.value : getLocalTodayIso();

      for (const s of currentStaffList) {
        const baseInput = document.getElementById('staffBase-' + s.id);
        const taxPctInput = document.getElementById('staffTaxPct-' + s.id);
        const sssInput = document.getElementById('staffSss-' + s.id);
        const advInput = document.getElementById('staffAdvance-' + s.id);
        const bonusInput = document.getElementById('staffBonus-' + s.id);
        const posEl = document.getElementById('staffPosComm-' + s.id);

        const b = baseInput ? Math.max(0, parseFloat(baseInput.value || 0)) : 0;
        const tp = taxPctInput ? Math.max(0, Math.min(100, parseFloat(taxPctInput.value || 0))) : 0;
        const ta = Math.round(b * (tp / 100) * 100) / 100;
        const ss = sssInput ? Math.max(0, parseFloat(sssInput.value || 0)) : 0;
        const ad = advInput ? Math.max(0, parseFloat(advInput.value || 0)) : 0;
        const bn = bonusInput ? Math.max(0, parseFloat(bonusInput.value || 0)) : 0;
        const p = posEl ? parseFloat(posEl.getAttribute('data-pos-comm') || 0) : 0;
        const net = Math.max(0, (b - (ta + ss + ad)) + bn + p);

        totalBase += b;
        totalTaxAmt += ta;
        totalSss += ss;
        totalAdv += ad;
        totalBonus += bn;
        totalPos += p;
        grandTotal += net;

        updates.push({
          staff_id: s.id,
          base_salary: b,
          tax_pct: tp,
          sss_contribution: ss,
          advance_payment: ad,
          bonus_commission: bn
        });
        staffItems.push({
          staff_id: s.id,
          employee_name: s.full_name,
          base_salary: b,
          tax_pct: tp,
          tax_amount: ta,
          sss_contribution: ss,
          advance_payment: ad,
          bonus_commission: bn,
          pos_commission: p
        });
      }

      const avgTaxPct = totalBase > 0 ? (totalTaxAmt / totalBase) * 100 : 0;
      openPayrollConfirmModal({
        isRevert: false,
        title: 'Disburse All Store Payroll',
        staffName: 'All Store Staff (' + currentStaffList.length + ' Employees)',
        staffRole: 'Bulk Payroll Run',
        payDate: payDate,
        baseSalary: totalBase,
        taxPct: avgTaxPct,
        taxAmount: totalTaxAmt,
        sssContribution: totalSss,
        advancePayment: totalAdv,
        bonusCommission: totalBonus,
        posCommission: totalPos,
        total: grandTotal,
        confirmLabel: 'Disburse All &amp; Mark PAID',
        noteHtml: 'This will disburse net wages &amp; commissions for all <strong>' + currentStaffList.length + ' staff members</strong> and mark them <strong>PAID</strong> on <strong>' + payDate + '</strong>.'
      }, async () => {
        const btn = btnEl || document.getElementById('btnDisburseAllPayroll');
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Disbursing Payroll...';
        }
        try {
          if (updates.length > 0) {
            await fetch('/api/payroll/staff/update', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ supplier_id: currentSupplierId, staff_updates: updates })
            });
          }

          const res = await fetch('/api/payroll/disburse', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              supplier_id: currentSupplierId,
              employee_name: 'Store Staff & Cashiers (' + currentStaffList.length + ' staff)',
              pay_date: payDate,
              base_salary: totalBase,
              tax_pct: avgTaxPct,
              tax_amount: totalTaxAmt,
              sss_contribution: totalSss,
              advance_payment: totalAdv,
              bonus_commission: totalBonus,
              commission_amount: totalPos,
              staff_items: staffItems
            })
          });
          const data = await res.json();
          await loadPayroll();
          await loadExpenses();
          await loadWaterfall();
          if (data.success) {
            showErpToast('Bulk Payroll Disbursed', data.message, 'success');
          } else {
            showErpToast('Disbursement Error', data.error || 'Could not disburse payroll.', 'danger');
          }
        } finally {
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cash"></i> Disburse All Wages &amp; Settle Commissions';
          }
        }
      });
    }

    function showPosSyncNotification(results) {
      const container = document.getElementById('erpToastContainer');
      if (!container) return;

      const syncedPay = Number((results && results.syncedPayments) || 0);
      const syncedOrd = Number((results && results.syncedOrders) || 0);
      const syncedFee = Number((results && results.syncedFees) || 0);
      const hasNew = syncedPay > 0;
      const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

      const statusBadge = hasNew
        ? '<span class="badge bg-success" style="font-size: 10px;">+' + syncedPay + ' New Synced</span>'
        : '<span class="badge bg-primary" style="font-size: 10px;"><i class="fa fa-check me-1"></i>Up to Date</span>';

      const subtitleText = hasNew
        ? 'Imported <strong>' + syncedPay + '</strong> new POS payment(s) and updated P&amp;L &amp; commissions.'
        : 'All POS sales, gateway fees &amp; staff commissions are already synchronized.';

      const cardEl = document.createElement('div');
      cardEl.className = 'bg-white shadow-lg rounded p-3 border-start border-4';
      cardEl.style.borderLeftColor = hasNew ? '#10b981' : '#0284c7';
      cardEl.style.transition = 'opacity 0.25s ease';
      cardEl.innerHTML =
        '<div class="d-flex justify-content-between align-items-start mb-2">' +
          '<div class="d-flex align-items-center gap-2">' +
            '<div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 30px; height: 30px; background: ' + (hasNew ? '#10b981' : '#0284c7') + '; font-size: 13px;">' +
              '<i class="fa fa-refresh"></i>' +
            '</div>' +
            '<div>' +
              '<div class="fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 12.5px;">' +
                '<span>POS &rarr; ERP Sync Complete</span>' +
                statusBadge +
              '</div>' +
              '<div class="text-muted" style="font-size: 10.5px;"><i class="fa fa-building-o me-1"></i>' + getStoreDisplayName() + ' &bull; ' + nowTime + '</div>' +
            '</div>' +
          '</div>' +
          '<button type="button" class="btn-close" style="font-size: 10px;" onclick="this.closest(&quot;.shadow-lg&quot;).remove()"></button>' +
        '</div>' +
        '<div class="text-secondary mb-2" style="font-size: 11.5px;">' + subtitleText + '</div>' +
        '<div class="row g-1 text-center border rounded p-1 mx-0" style="background: #f8fafc; font-size: 10.5px;">' +
          '<div class="col-4 border-end">' +
            '<div class="text-muted">Payments</div>' +
            '<strong class="text-dark">' + syncedPay + '</strong>' +
          '</div>' +
          '<div class="col-4 border-end">' +
            '<div class="text-muted">Order Lines</div>' +
            '<strong class="text-dark">' + syncedOrd + '</strong>' +
          '</div>' +
          '<div class="col-4">' +
            '<div class="text-muted">Fees/Comm.</div>' +
            '<strong class="text-success">' + syncedFee + '</strong>' +
          '</div>' +
        '</div>';

      container.appendChild(cardEl);
      setTimeout(() => {
        if (cardEl.parentNode) {
          cardEl.style.opacity = '0';
          setTimeout(() => { if (cardEl.parentNode) cardEl.remove(); }, 260);
        }
      }, 4800);
    }

    async function triggerSync() {
      const btn = document.getElementById('btnSync');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Syncing...';
      try {
        const res = await fetch('/api/sync/run', { method: 'POST' });
        const data = await res.json();
        await loadAllData();
        showPosSyncNotification(data.results || {});
      } catch (err) {
        showErpToast('POS Sync Error', err.message || 'Could not synchronize POS transactions.', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Sync POS';
      }
    }
  </script>
</body>
</html>`;
}

tcpServer.listen(port, () => {
  console.log(`=======================================================`);
  console.log(`  eConstructionSite ERP Tenant Hub running on port ${port}`);
  console.log(`  Mode: ${httpsServer ? 'Dual HTTPS & HTTP (SSL Active)' : 'Plain HTTP (Local Dev)'}`);
  console.log(`  Targeting PostgreSQL: ${process.env.DB_HOST}:${process.env.DB_PORT}`);
  console.log(`  Tenant Admin Dashboard: ${httpsServer ? 'https' : 'http'}://localhost:${port}`);
  console.log(`=======================================================`);
});

// Run initial sync worker on startup
runPosSyncWorker()
  .then(res => console.log('[ERP Initial Sync]:', res))
  .catch(err => console.error('[ERP Initial Sync Error]:', err.message));
