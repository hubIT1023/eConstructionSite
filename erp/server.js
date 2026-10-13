const http = require('http');
const url = require('url');
const { Pool } = require('pg');
const path = require('path');
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
const server = http.createServer(async (req, res) => {
  const parsedUrl = url.parse(req.url, true);
  const pathname = parsedUrl.pathname;
  const method = req.method;

  if (method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    });
    return res.end();
  }

  try {
    // Health Check
    if (pathname === '/api/health' && method === 'GET') {
      return sendJson(res, 200, { status: 'UP', service: 'eConstructionSite ERP Tenant Hub', port });
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

    // 2. Products List (for Stock Receiving)
    if (pathname === '/api/products' && method === 'GET') {
      const supplierId = parseInt(parsedUrl.query.supplier_id || '1', 10);
      const client = await pool.connect();
      try {
        const q = await client.query(
          `SELECT p_id, p_name, p_qty, p_capital_price, p_current_price 
           FROM public.tbl_product 
           WHERE supplier_id = $1 
           ORDER BY p_name ASC`,
          [supplierId]
        );
        return sendJson(res, 200, q.rows);
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
        daily:     { days: 1,  label: 'Daily',     opexFactor: 1 / 30 },
        weekly:    { days: 7,  label: 'Weekly',    opexFactor: 7 / 30 },
        monthly:   { days: 30, label: 'Monthly',   opexFactor: 1.0 },
        quarterly: { days: 90, label: 'Quarterly', opexFactor: 3.0 }
      };
      const periodKey = periodConfig[rawPeriod] ? rawPeriod : 'daily';
      const cfg = periodConfig[periodKey];

      const client = await pool.connect();
      try {
        // Anchor to latest active POS trading date for this supplier (or CURRENT_DATE if none)
        const anchorRes = await client.query(
          `SELECT COALESCE(MAX(SUBSTRING(payment_date, 1, 10)::date), CURRENT_DATE)::text AS anchor_date,
                  (COALESCE(MAX(SUBSTRING(payment_date, 1, 10)::date), CURRENT_DATE) - ($2::int - 1))::text AS start_date
           FROM public.tbl_payment WHERE supplier_id = $1`,
          [supplierId, cfg.days]
        );
        const anchorDate = anchorRes.rows[0].anchor_date;
        const startDate = anchorRes.rows[0].start_date;

        const salesRes = await client.query(
          `SELECT COUNT(DISTINCT p.id) as total_tx_count, COALESCE(SUM(p.paid_amount), 0) as gross_revenue
           FROM public.tbl_payment p
           WHERE p.supplier_id = $1
             AND SUBSTRING(p.payment_date, 1, 10)::date BETWEEN $2::date AND $3::date`,
          [supplierId, startDate, anchorDate]
        );
        const feesRes = await client.query(
          `SELECT COALESCE(SUM(f.calculated_fee), 0) as total_bank_fees
           FROM erp.payment_fee_transactions f
           JOIN public.tbl_payment p ON f.payment_id = p.id
           WHERE f.supplier_id = $1
             AND SUBSTRING(p.payment_date, 1, 10)::date BETWEEN $2::date AND $3::date`,
          [supplierId, startDate, anchorDate]
        );
        const incRes = await client.query(
          `SELECT COALESCE(SUM(s.incentive_amount), 0) as total_incentives
           FROM erp.sales_incentives s
           JOIN public.tbl_payment p ON s.payment_id = p.payment_id
           WHERE s.supplier_id = $1
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
      <!-- Top KPI Strip -->
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

      <div class="tab-content" id="mainTabsContent">

        <!-- TAB 1: P&L WATERFALL -->
        <div class="tab-pane fade show active" id="tab-pnl">
          <div class="row">
            <div class="col-lg-7">
              <div class="card p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                  <div>
                    <h5 class="mb-1 fw-bold"><i class="bi bi-cash-stack text-success"></i> P&amp;L Waterfall</h5>
                    <p class="text-muted small mb-1">Live reconciliation connecting POS orders on Port 9090 against landed inventory costs and store overhead.</p>
                    <span id="wfPeriodSummary" class="badge bg-light text-secondary border" style="font-size: 11px;">Period: Daily</span>
                  </div>

                  <!-- Daily / Weekly / Monthly / Quarterly Period Selector -->
                  <div class="btn-group btn-group-sm" role="group" aria-label="P&L Waterfall Period">
                    <button type="button" id="btnPeriod-daily" class="btn btn-primary fw-bold" onclick="setWaterfallPeriod('daily')">Daily</button>
                    <button type="button" id="btnPeriod-weekly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('weekly')">Weekly</button>
                    <button type="button" id="btnPeriod-monthly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('monthly')">Monthly</button>
                    <button type="button" id="btnPeriod-quarterly" class="btn btn-outline-primary fw-bold" onclick="setWaterfallPeriod('quarterly')">Quarterly</button>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0 fw-bold"><i class="fa fa-cubes text-info"></i> Store Catalog &amp; Available Stock</h5>
                <p class="text-muted small mb-0">Live inventory levels synchronized with Port 9090 POS registers and landed capital costs.</p>
              </div>
              <button class="btn btn-primary btn-sm" onclick="document.getElementById('tab-materials-link').click()">
                <i class="fa fa-truck"></i> Receive Delivery (GRN)
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover small align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Available POS Stock</th>
                    <th>Landed / Capital Cost</th>
                    <th>Retail Price</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="catalogStockTable">
                  <tr><td colspan="7" class="text-center text-muted">Loading catalog...</td></tr>
                </tbody>
              </table>
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
      ['daily', 'weekly', 'monthly', 'quarterly'].forEach(p => {
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

      const isLocalHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
      const posReturnTarget = isLocalHost
        ? 'http://localhost:9090/supplier/index.php'
        : (window.location.protocol + '//' + window.location.hostname + '/supplier/index.php');
      const posLink = document.getElementById('posReturnLink');
      if (posLink) posLink.href = posReturnTarget;
      const logoLink = document.getElementById('logoReturnLink');
      if (logoLink) logoLink.href = posReturnTarget;

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
      } catch (err) {
        console.error(err);
      }
    }

    async function loadProducts() {
      const res = await fetch('/api/products?supplier_id=' + currentSupplierId);
      productsList = await res.json();
      const sel = document.getElementById('grnProduct');
      sel.innerHTML = '<option value="">Select Catalog Item...</option>';
      for (const p of productsList) {
        const opt = document.createElement('option');
        opt.value = p.p_id;
        opt.textContent = p.p_name + ' (Stock: ' + p.p_qty + ')';
        sel.appendChild(opt);
      }

      const catBody = document.getElementById('catalogStockTable');
      if (catBody) {
        if (productsList.length === 0) {
          catBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No products found for this store.</td></tr>';
        } else {
          let html = '';
          for (const p of productsList) {
            const qty = parseInt(p.p_qty || 0, 10);
            const badge = qty <= 10
              ? '<span class="badge bg-danger">Low Stock (' + qty + ')</span>'
              : '<span class="badge bg-success">In Stock (' + qty + ')</span>';
            html += '<tr>' +
              '<td><code>#' + p.p_id + '</code></td>' +
              '<td><strong>' + p.p_name + '</strong></td>' +
              '<td><strong>' + qty + ' units</strong></td>' +
              '<td>₱' + parseFloat(p.p_capital_price || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '</td>' +
              '<td class="text-primary fw-bold">₱' + parseFloat(p.p_current_price || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '</td>' +
              '<td>' + badge + '</td>' +
              '<td><button class="btn btn-outline-primary btn-sm py-0 px-2" onclick="jumpToGrnForProduct(' + p.p_id + ')"><i class="fa fa-plus"></i> Restock</button></td>' +
            '</tr>';
          }
          catBody.innerHTML = html;
        }
      }
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

server.listen(port, () => {
  console.log(`=======================================================`);
  console.log(`  eConstructionSite ERP Tenant Hub running on localhost:${port}`);
  console.log(`  Targeting PostgreSQL: ${process.env.DB_HOST}:${process.env.DB_PORT}`);
  console.log(`  Tenant Admin Dashboard: http://localhost:${port}`);
  console.log(`=======================================================`);
});

// Run initial sync worker on startup
runPosSyncWorker()
  .then(res => console.log('[ERP Initial Sync]:', res))
  .catch(err => console.error('[ERP Initial Sync Error]:', err.message));
