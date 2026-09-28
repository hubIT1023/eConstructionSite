/**
 * ============================================================================
 * UNIFIED POS VOUCHER & RECEIPT RENDERER ENGINE (PURE NATIVE HTML/CSS)
 * Standardized Thermal Print Formatting for:
 * 1. Customer Purchase Order (PO) Vouchers (5 Modals across portal)
 * 2. Official Sales Receipts (2 Modals across portal)
 * Native support for JK-5802H 58mm (48mm printable area / 12pt) & 80mm rolls
 * ============================================================================
 */

(function(window, document) {
    'use strict';

    const POVoucherRenderer = {
        escapeHtml: function(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        formatMoney: function(num) {
            const val = parseFloat(num) || 0;
            return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        /**
         * Execute print job via a silent hidden iframe directly to Chrome's native print dialog
         */
        executeIframePrint: function(htmlContent, title) {
            let iframe = document.getElementById('thermal-print-frame');
            if (iframe) {
                iframe.parentNode.removeChild(iframe);
            }
            iframe = document.createElement('iframe');
            iframe.id = 'thermal-print-frame';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.style.zIndex = '-9999';
            document.body.appendChild(iframe);

            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(htmlContent);
            doc.close();

            iframe.contentWindow.focus();
            setTimeout(function() {
                try {
                    iframe.contentWindow.print();
                } catch (e) {
                    console.error('Thermal Print error:', e);
                }
            }, 250);
        },

        /**
         * Wrap an HTML element or snippet in a thermal printable page
         */
        wrapThermalHtml: function(innerHtml, format) {
            const is80 = (format === '80' || format === '80mm' || format === 80);
            const paperWidthMm = is80 ? 80 : 58;
            const printableWidthMm = is80 ? 72 : 53;
            const fontSizePt = is80 ? '11.5pt' : '11pt';

            return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Document</title>
    <style>
        * {
            box-sizing: border-box !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: ${paperWidthMm}mm auto;
            margin: 0 !important;
        }
        html, body {
            width: ${paperWidthMm}mm !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: 'Courier New', Consolas, monospace !important;
            font-size: ${fontSizePt} !important;
            line-height: 1.25 !important;
            -webkit-font-smoothing: antialiased;
        }
        .thermal-print-container {
            width: ${printableWidthMm}mm !important;
            max-width: ${printableWidthMm}mm !important;
            margin: 0 auto !important;
            padding: 2mm 0 !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-family: inherit !important;
            font-size: inherit !important;
        }
        th, td {
            vertical-align: top !important;
            color: #000000 !important;
            font-family: inherit !important;
        }
    </style>
</head>
<body>
    <div class="thermal-print-container">
        ${innerHtml}
    </div>
</body>
</html>`;
        },

        /**
         * Normalize PO Data Object for JS-rendered POs
         */
        normalizePOData: function(data) {
            if (!data || typeof data !== 'object') data = {};
            const items = Array.isArray(data.items) ? data.items : [];
            let calcSubtotal = 0;
            const normalizedItems = items.map(function(it, idx) {
                const qty = parseInt(it.quantity || it.qty || 1, 10) || 1;
                const price = parseFloat(it.unit_price || it.price || 0) || 0;
                const discount = parseFloat(it.discount_amount || 0) || 0;
                const lineNet = (it.line_net !== undefined && it.line_net !== null)
                    ? parseFloat(it.line_net)
                    : Math.max(0, (qty * price) - discount);
                calcSubtotal += lineNet;
                return {
                    index: idx + 1,
                    product_name: it.product_name || it.name || 'Unnamed Item',
                    size: (it.size && it.size !== '-') ? it.size : '',
                    color: (it.color && it.color !== '-') ? it.color : '',
                    item_type: it.item_type || '',
                    quantity: qty,
                    unit_price: price,
                    discount_amount: discount,
                    line_net: lineNet
                };
            });

            const grossSubtotal = parseFloat(data.gross_subtotal || data.subtotal || calcSubtotal) || calcSubtotal;
            const discountSavings = parseFloat(data.total_discount_savings || data.discount_total || data.discount || 0) || 0;
            const deliveryCost = parseFloat(data.delivery_cost || data.delivery_fee || data.delivery || 0) || 0;
            const grandTotal = parseFloat(data.grand_total || data.total_amount || data.paid_amount || (grossSubtotal - discountSavings + deliveryCost)) || 0;

            return {
                po_number: data.po_number || data.po_id || data.payment_id || data.txnid || 'PO-PENDING',
                po_date: data.po_date || data.payment_date || data.date || new Date().toLocaleString(),
                customer_name: (data.customer && data.customer.name) || data.customer_name || 'Walk-in Customer',
                customer_phone: (data.customer && data.customer.phone) || data.customer_phone || '',
                store_name: (data.supplier && data.supplier.name) || data.store_name || 'SAM & INRI CONSTRUCTION SUPPLY',
                store_phone: (data.supplier && data.supplier.phone) || data.store_phone || '09612735733',
                store_address: (data.supplier && data.supplier.address) || data.store_address || '',
                items: normalizedItems,
                gross_subtotal: grossSubtotal,
                total_discount_savings: discountSavings,
                delivery_cost: deliveryCost,
                grand_total: grandTotal
            };
        },

        /**
         * Render HTML for PO Voucher from structured data object
         */
        renderThermalPO: function(data, format) {
            const d = this.normalizePOData(data);
            const is80 = (format === '80' || format === '80mm' || format === 80);
            const maxWidth = is80 ? '72mm' : '53mm';

            let itemsHtml = '';
            d.items.forEach(function(item) {
                const isSp = (item.item_type === 'SPECIAL_ORDER');
                let specs = '';
                if (item.size) specs += 'Size: ' + POVoucherRenderer.escapeHtml(item.size) + ' ';
                if (item.color) specs += 'Color: ' + POVoucherRenderer.escapeHtml(item.color);

                itemsHtml += `
                <tr>
                    <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                        ${isSp ? '[SPECIAL ORDER] ' : ''}${POVoucherRenderer.escapeHtml(item.product_name)}
                        ${specs ? `<div style="font-size: 9pt; font-weight: normal; margin-top: 1px;">${specs}</div>` : ''}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                        ${item.quantity} ${item.quantity > 1 ? 'pcs' : 'pc'} @ ${POVoucherRenderer.formatMoney(item.unit_price)}
                    </td>
                    <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                        ${POVoucherRenderer.formatMoney(item.line_net)}
                    </td>
                </tr>`;
            });

            return `
            <div class="thermal-print-container" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; font-family: 'Courier New', Consolas, monospace; color: #000; font-size: 11pt; line-height: 1.25; width: 100%; max-width: ${maxWidth}; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-bottom: 3px; overflow: hidden; white-space: nowrap;">================================</div>
                <div style="text-align: center;">
                    <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase; color: #000; line-height: 1.2;">${POVoucherRenderer.escapeHtml(d.store_name.toUpperCase())}</div>
                    ${d.store_address ? `<div style="font-size: 9.5pt; margin-top: 2px; color: #000;">${POVoucherRenderer.escapeHtml(d.store_address)}</div>` : ''}
                    <div style="font-size: 10pt; margin-top: 2px; color: #000;">Tel: ${POVoucherRenderer.escapeHtml(d.store_phone)}</div>
                    <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; color: #000;">PURCHASE ORDER VOUCHER</div>
                    <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000;">(UNPAID)</div>
                </div>
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-top: 3px; margin-bottom: 4px; overflow: hidden; white-space: nowrap;">================================</div>

                <table style="width: 100%; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin-bottom: 2px; border-collapse: collapse;">
                    <tr>
                        <td style="width: 28%; font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">PO NO   :</td>
                        <td style="padding: 1px 0; vertical-align: top; font-weight: bold;">${POVoucherRenderer.escapeHtml(d.po_number)}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CUSTOMER:</td>
                        <td style="padding: 1px 0; vertical-align: top;">${POVoucherRenderer.escapeHtml(d.customer_name)}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">STATUS  :</td>
                        <td style="padding: 1px 0; vertical-align: top; font-weight: bold;">AWAITING PAYMENT</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">DATE    :</td>
                        <td style="padding: 1px 0; vertical-align: top;">${POVoucherRenderer.escapeHtml(d.po_date)}</td>
                    </tr>
                </table>

                <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 1px 0; font-weight: bold; width: 68%;">ITEM DESCRIPTION</th>
                            <th style="text-align: right; padding: 1px 0; font-weight: bold; width: 32%;">AMOUNT</th>
                        </tr>
                    </thead>
                </table>
                <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
                <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>

                <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin: 2px 0;">
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Subtotal:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${POVoucherRenderer.formatMoney(d.gross_subtotal)}</td>
                    </tr>
                    ${d.total_discount_savings > 0 ? `
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Discount:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">-${POVoucherRenderer.formatMoney(d.total_discount_savings)}</td>
                    </tr>` : ''}
                    ${d.delivery_cost > 0 ? `
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Delivery Fee:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${POVoucherRenderer.formatMoney(d.delivery_cost)}</td>
                    </tr>` : ''}
                    <tr style="font-weight: bold;">
                        <td style="text-align: left; padding: 2px 0; font-size: 1.08em;">TOTAL DUE:</td>
                        <td style="text-align: right; padding: 2px 0; font-size: 1.08em; white-space: nowrap;">PHP ${POVoucherRenderer.formatMoney(d.grand_total)}</td>
                    </tr>
                </table>

                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
                <div style="text-align: center; line-height: 1.35; padding: 2px 0;">
                    <div style="font-weight: bold;">*** PROCEED TO CASHIER ***</div>
                    <div style="font-weight: bold;">FOR PAYMENT</div>
                    <div style="margin-top: 3px;">Thank you for your business!</div>
                    <div style="font-size: 9pt; margin-top: 2px;">eConstruction Supply POS</div>
                </div>
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
            </div>`;
        },

        render: function(data, format) {
            return this.renderThermalPO(data, format);
        },

        print: function(dataOrElement, format) {
            const fmt = format || '58';
            if (typeof dataOrElement === 'string' && document.getElementById(dataOrElement)) {
                const el = document.getElementById(dataOrElement);
                const html = this.wrapThermalHtml(el.innerHTML, fmt);
                this.executeIframePrint(html);
            } else if (dataOrElement && dataOrElement.nodeType === 1) {
                const html = this.wrapThermalHtml(dataOrElement.innerHTML, fmt);
                this.executeIframePrint(html);
            } else if (dataOrElement && typeof dataOrElement === 'object') {
                const voucherHtml = this.renderThermalPO(dataOrElement, fmt);
                const html = this.wrapThermalHtml(voucherHtml, fmt);
                this.executeIframePrint(html);
            }
        },

        /**
         * Normalize Receipt Data
         */
        normalizeReceiptData: function(data) {
            if (!data || typeof data !== 'object') data = {};
            const items = Array.isArray(data.items) ? data.items : [];
            let calcSubtotal = 0;
            const normalizedItems = items.map(function(it, idx) {
                const qty = parseInt(it.quantity || it.qty || 1, 10) || 1;
                const price = parseFloat(it.unit_price || it.price || 0) || 0;
                const discount = parseFloat(it.discount_amount || 0) || 0;
                const lineNet = (it.line_net !== undefined && it.line_net !== null)
                    ? parseFloat(it.line_net)
                    : Math.max(0, (qty * price) - discount);
                calcSubtotal += lineNet;
                return {
                    index: idx + 1,
                    product_name: it.product_name || it.name || 'Unnamed Item',
                    size: (it.size && it.size !== '-') ? it.size : '',
                    color: (it.color && it.color !== '-') ? it.color : '',
                    item_type: it.item_type || '',
                    quantity: qty,
                    unit_price: price,
                    discount_amount: discount,
                    line_net: lineNet
                };
            });

            const grossSubtotal = parseFloat(data.gross_subtotal || data.subtotal || calcSubtotal) || calcSubtotal;
            const deliveryCost = parseFloat(data.delivery_cost || data.delivery_fee || data.delivery || 0) || 0;
            const grandTotal = parseFloat(data.grand_total || data.total_amount || data.paid_amount || (grossSubtotal + deliveryCost)) || 0;

            return {
                payment_id: data.payment_id || data.txnid || 'OR-PAID',
                payment_date: data.payment_date || data.date || new Date().toLocaleString(),
                payment_method: data.payment_method || 'Cash',
                cashier_name: data.cashier_name || data.cashier || '',
                customer_name: (data.customer && data.customer.name) || data.customer_name || 'Walk-in Customer',
                store_name: (data.supplier && data.supplier.name) || data.store_name || 'SAM & INRI CONSTRUCTION SUPPLY',
                store_phone: (data.supplier && data.supplier.phone) || data.store_phone || '09612735733',
                store_address: (data.supplier && data.supplier.address) || data.store_address || '',
                items: normalizedItems,
                gross_subtotal: grossSubtotal,
                delivery_cost: deliveryCost,
                grand_total: grandTotal,
                amount_tendered: parseFloat(data.amount_tendered || grandTotal) || grandTotal,
                change_amount: parseFloat(data.change_amount || 0) || 0
            };
        },

        renderThermalReceipt: function(data, format) {
            const d = this.normalizeReceiptData(data);
            const is80 = (format === '80' || format === '80mm' || format === 80);
            const maxWidth = is80 ? '72mm' : '53mm';

            let itemsHtml = '';
            d.items.forEach(function(item) {
                const isSp = (item.item_type === 'SPECIAL_ORDER');
                let specs = '';
                if (item.size) specs += 'Size: ' + POVoucherRenderer.escapeHtml(item.size) + ' ';
                if (item.color) specs += 'Color: ' + POVoucherRenderer.escapeHtml(item.color);

                itemsHtml += `
                <tr>
                    <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                        ${isSp ? '[SPECIAL ORDER] ' : ''}${POVoucherRenderer.escapeHtml(item.product_name)}
                        ${specs ? `<div style="font-size: 9pt; font-weight: normal; margin-top: 1px;">${specs}</div>` : ''}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                        ${item.quantity} ${item.quantity > 1 ? 'pcs' : 'pc'} @ ${POVoucherRenderer.formatMoney(item.unit_price)}
                    </td>
                    <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                        ${POVoucherRenderer.formatMoney(item.line_net)}
                    </td>
                </tr>`;
            });

            return `
            <div class="thermal-print-container" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; font-family: 'Courier New', Consolas, monospace; color: #000; font-size: 11pt; line-height: 1.25; width: 100%; max-width: ${maxWidth}; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-bottom: 3px; overflow: hidden; white-space: nowrap;">================================</div>
                <div style="text-align: center;">
                    <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase; color: #000; line-height: 1.2;">${POVoucherRenderer.escapeHtml(d.store_name.toUpperCase())}</div>
                    ${d.store_address ? `<div style="font-size: 9.5pt; margin-top: 2px; color: #000;">${POVoucherRenderer.escapeHtml(d.store_address)}</div>` : ''}
                    <div style="font-size: 10pt; margin-top: 2px; color: #000;">Tel: ${POVoucherRenderer.escapeHtml(d.store_phone)}</div>
                    <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; color: #000;">OFFICIAL SALES RECEIPT</div>
                    <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000;">(PAID)</div>
                </div>
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-top: 3px; margin-bottom: 4px; overflow: hidden; white-space: nowrap;">================================</div>

                <table style="width: 100%; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin-bottom: 2px; border-collapse: collapse;">
                    <tr>
                        <td style="width: 28%; font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">OR NO   :</td>
                        <td style="padding: 1px 0; vertical-align: top; font-weight: bold;">${POVoucherRenderer.escapeHtml(d.payment_id)}</td>
                    </tr>
                    ${d.cashier_name ? `
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CASHIER :</td>
                        <td style="padding: 1px 0; vertical-align: top;">${POVoucherRenderer.escapeHtml(d.cashier_name)}</td>
                    </tr>` : ''}
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CUSTOMER:</td>
                        <td style="padding: 1px 0; vertical-align: top;">${POVoucherRenderer.escapeHtml(d.customer_name)}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">PAY METH:</td>
                        <td style="padding: 1px 0; vertical-align: top;">${POVoucherRenderer.escapeHtml(d.payment_method)}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">STATUS  :</td>
                        <td style="padding: 1px 0; vertical-align: top; font-weight: bold;">PAID</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">DATE    :</td>
                        <td style="padding: 1px 0; vertical-align: top;">${POVoucherRenderer.escapeHtml(d.payment_date)}</td>
                    </tr>
                </table>

                <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 1px 0; font-weight: bold; width: 68%;">ITEM DESCRIPTION</th>
                            <th style="text-align: right; padding: 1px 0; font-weight: bold; width: 32%;">AMOUNT</th>
                        </tr>
                    </thead>
                </table>
                <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
                <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>

                <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin: 2px 0;">
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Subtotal:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${POVoucherRenderer.formatMoney(d.gross_subtotal)}</td>
                    </tr>
                    ${d.delivery_cost > 0 ? `
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Delivery Fee:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${POVoucherRenderer.formatMoney(d.delivery_cost)}</td>
                    </tr>` : ''}
                    <tr style="font-weight: bold;">
                        <td style="text-align: left; padding: 2px 0; font-size: 1.08em;">TOTAL PAID:</td>
                        <td style="text-align: right; padding: 2px 0; font-size: 1.08em; white-space: nowrap;">PHP ${POVoucherRenderer.formatMoney(d.grand_total)}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Tendered:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${POVoucherRenderer.formatMoney(d.amount_tendered)}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 0;">Change:</td>
                        <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${POVoucherRenderer.formatMoney(d.change_amount)}</td>
                    </tr>
                </table>

                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
                <div style="text-align: center; line-height: 1.35; padding: 2px 0;">
                    <div style="font-weight: bold;">*** OFFICIAL RECEIPT ***</div>
                    <div style="margin-top: 3px;">THANK YOU FOR YOUR PURCHASE!</div>
                    <div style="font-size: 9pt; margin-top: 2px;">eConstruction Supply POS</div>
                </div>
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
            </div>`;
        },

        renderReceipt: function(data, format) {
            return this.renderThermalReceipt(data, format);
        },

        printReceiptData: function(dataOrElement, format) {
            const fmt = format || '58';
            if (typeof dataOrElement === 'string' && document.getElementById(dataOrElement)) {
                const el = document.getElementById(dataOrElement);
                const html = this.wrapThermalHtml(el.innerHTML, fmt);
                this.executeIframePrint(html);
            } else if (dataOrElement && dataOrElement.nodeType === 1) {
                const html = this.wrapThermalHtml(dataOrElement.innerHTML, fmt);
                this.executeIframePrint(html);
            } else if (dataOrElement && typeof dataOrElement === 'object') {
                const receiptHtml = this.renderThermalReceipt(dataOrElement, fmt);
                const html = this.wrapThermalHtml(receiptHtml, fmt);
                this.executeIframePrint(html);
            }
        }
    };

    // Global helper bindings
    window.POVoucherRenderer = POVoucherRenderer;
    window.POReceiptRenderer = {
        normalizeData: POVoucherRenderer.normalizeReceiptData.bind(POVoucherRenderer),
        render: POVoucherRenderer.renderReceipt.bind(POVoucherRenderer),
        print: POVoucherRenderer.printReceiptData.bind(POVoucherRenderer)
    };

    /**
     * Switch PO Voucher Paper Preview width (58mm / 80mm)
     */
    window.switchPOVoucherPreview = function(poId, format) {
        const fmt = String(format || '58');
        try {
            localStorage.setItem('pos_preferred_thermal_format', fmt);
        } catch(e) {}

        const printable = document.getElementById('po-printable-voucher-' + poId) ||
                          document.getElementById('poPrintVoucher-' + poId);
        if (printable) {
            printable.style.maxWidth = (fmt === '80' ? '72mm' : '53mm');
            return;
        }

        const container = document.getElementById('po-print-area-' + poId);
        if (container && window.poOrderRegistry && window.poOrderRegistry[poId]) {
            container.innerHTML = POVoucherRenderer.render(window.poOrderRegistry[poId], fmt);
        }
    };

    /**
     * Print PO Voucher directly to Chrome's native print preview
     */
    window.printPOVoucher = function(poId, format) {
        const fmt = format || '58';

        const thermalEl = document.getElementById('po-printable-thermal-' + poId) ||
                          document.getElementById('po-printable-voucher-' + poId) ||
                          document.getElementById('poPrintVoucher-' + poId);
        if (thermalEl) {
            POVoucherRenderer.print(thermalEl, fmt);
            return;
        }

        const container = document.getElementById('po-print-area-' + poId);
        if (container && container.firstElementChild) {
            POVoucherRenderer.print(container, fmt);
            return;
        }

        if (window.poOrderRegistry && window.poOrderRegistry[poId]) {
            POVoucherRenderer.print(window.poOrderRegistry[poId], fmt);
        }
    };

    /**
     * Switch Receipt Paper Preview width (Compatibility)
     */
    window.switchReceiptPreview = function(receiptId, format) {
        const fmt = String(format || '58');
        try {
            localStorage.setItem('pos_preferred_thermal_format', fmt);
        } catch(e) {}

        const printable = document.getElementById('receipt-printable-' + receiptId);
        if (printable) {
            printable.style.maxWidth = (fmt === '80' ? '72mm' : '53mm');
            return;
        }

        const container = document.getElementById('receipt-print-area-' + receiptId);
        if (container && window.poReceiptRegistry && window.poReceiptRegistry[receiptId]) {
            container.innerHTML = POVoucherRenderer.renderReceipt(window.poReceiptRegistry[receiptId], fmt);
        }
    };

    /**
     * Print Official Receipt directly to Chrome's native print preview
     */
    window.printReceiptModal = function(receiptId, format) {
        const fmt = format || '58';

        const thermalEl = document.getElementById('receipt-printable-thermal-' + receiptId) ||
                          document.getElementById('receipt-printable-' + receiptId);
        if (thermalEl) {
            POVoucherRenderer.printReceiptData(thermalEl, fmt);
            return;
        }

        const container = document.getElementById('receipt-print-area-' + receiptId);
        if (container && container.firstElementChild) {
            POVoucherRenderer.printReceiptData(container, fmt);
            return;
        }

        if (window.poReceiptRegistry && window.poReceiptRegistry[receiptId]) {
            POVoucherRenderer.printReceiptData(window.poReceiptRegistry[receiptId], fmt);
        }
    };

    /**
     * Compatibility wrapper for po-created.php reprint button
     */
    window.reprintPOCreated = function(elementId, requestedWidthMm) {
        let fmt = requestedWidthMm ? String(requestedWidthMm) : (localStorage.getItem('pos_preferred_thermal_format') || '58');
        if (fmt.indexOf('80') !== -1) fmt = '80';
        else fmt = '58';

        const el = document.getElementById(elementId);
        if (el) {
            POVoucherRenderer.print(el, fmt);
        }
    };

    // Auto-sync format selector when modal opens
    if (typeof $ !== 'undefined') {
        $(document).on('shown.bs.modal', function(e) {
            const modal = $(e.target);
            const savedFmt = localStorage.getItem('pos_preferred_thermal_format') || '58';
            
            const poSelect = modal.find('select[id^="po-format-"]');
            if (poSelect.length) {
                poSelect.val(savedFmt);
                const poId = poSelect.attr('id').replace('po-format-', '');
                window.switchPOVoucherPreview(poId, savedFmt);
            }

            const rcptSelect = modal.find('select[id^="receipt-format-"]');
            if (rcptSelect.length) {
                rcptSelect.val(savedFmt);
                const rcptId = rcptSelect.attr('id').replace('receipt-format-', '');
                window.switchReceiptPreview(rcptId, savedFmt);
            }
        });
    }

})(window, document);
