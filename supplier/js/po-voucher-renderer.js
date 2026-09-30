/**
 * ============================================================================
 * UNIFIED POS VOUCHER & RECEIPT RENDERER ENGINE (UNIVERSAL ESC/POS + 1-BIT HTML)
 * Standardized Thermal Print Engine Supporting:
 * 1. Native ESC/POS Hardware Binary Streaming (JK-5802H, Epson, Xprinter, POS-58/80)
 * 2. High-Contrast 1-Bit Thermal Browser/OS Print Dialog (Zero Grayscale Blur)
 * 3. Multi-Printer Model Auto-Detection & Preset Profiles (58mm / 80mm / A4)
 * 4. Thermal Head Darkness & Clarity Controls (100% Normal, 120% Dark, 140% Ultra Dark)
 * 5. Hardware Peripheral Automation (Cash Drawer Kick, Auto-Cutter, Buzzer Beep)
 * ============================================================================
 */

(function(window, document) {
    'use strict';

    /**
     * ESC/POS Control Codes & Constants
     */
    const ESCPOS = {
        INIT: '\x1B\x40',                  // ESC @ (Initialize printer)
        ALIGN_LEFT: '\x1B\x61\x00',         // ESC a 0 (Left align)
        ALIGN_CENTER: '\x1B\x61\x01',       // ESC a 1 (Center align)
        ALIGN_RIGHT: '\x1B\x61\x02',        // ESC a 2 (Right align)
        FONT_A: '\x1B\x4D\x00',             // ESC M 0 (Font A 12x24 standard)
        FONT_B: '\x1B\x4D\x01',             // ESC M 1 (Font B 9x17 condensed)
        BOLD_ON: '\x1B\x45\x01',            // ESC E 1 (Emphasized/Bold on)
        BOLD_OFF: '\x1B\x45\x00',           // ESC E 0 (Emphasized/Bold off)
        DOUBLE_STRIKE_ON: '\x1B\x47\x01',   // ESC G 1 (Double-strike on for max density)
        DOUBLE_STRIKE_OFF: '\x1B\x47\x00',  // ESC G 0 (Double-strike off)
        SIZE_NORMAL: '\x1D\x21\x00',        // GS ! 0 (Normal size)
        SIZE_DOUBLE_H: '\x1D\x21\x01',      // GS ! 1 (Double height)
        SIZE_DOUBLE_W: '\x1D\x21\x10',      // GS ! 16 (Double width)
        SIZE_DOUBLE: '\x1D\x21\x11',        // GS ! 17 (Double width & height)
        DRAWER_KICK: '\x1B\x70\x00\x19\xFA',// ESC p 0 25 250 (Cash drawer pin 2 pulse)
        BUZZER: '\x1B\x42\x02\x02',         // ESC B 2 2 (Buzzer beep 2 times)
        CUT_PARTIAL: '\x1D\x56\x42\x00',    // GS V 66 0 (Feed and partial cut)
        CUT_FULL: '\x1D\x56\x00',           // GS V 0 (Full cut)
        FEED_LINES: function(n) {
            return '\x1B\x64' + String.fromCharCode(Math.max(1, Math.min(10, n || 3)));
        }
    };

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

        getSettings: function() {
            let s = {
                printerId: 'jk_5802h',
                printerName: 'JK-5802H 58mm Thermal (USB / Bluetooth)',
                printEngine: 'browser',
                paperWidthMm: 58,
                printContentWidthMm: 53,
                printColumns: 32,
                hardwareFont: 'font_a',
                thermalDensity: 120,
                thermalFontName: 'Courier New',
                thermalDefaultFontSize: 10.5,
                thermalLineHeight: 1.25,
                autoCashDrawer: true,
                autoCutter: true,
                buzzerBeep: false,
                escposDaemonUrl: 'http://127.0.0.1:9100/print',
                copies: 1
            };
            try {
                const raw = localStorage.getItem('pos_printer_settings');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    s = Object.assign(s, parsed);
                }
            } catch(e) {}
            return s;
        },

        showToast: function(message, type) {
            let container = document.getElementById('pos-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'pos-toast-container';
                container.style.position = 'fixed';
                container.style.top = '20px';
                container.style.right = '20px';
                container.style.zIndex = '99999';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.gap = '8px';
                container.style.pointerEvents = 'none';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            const bg = (type === 'success') ? '#059669' : (type === 'info' ? '#0284c7' : '#d97706');
            toast.style.background = bg;
            toast.style.color = '#ffffff';
            toast.style.padding = '10px 16px';
            toast.style.borderRadius = '6px';
            toast.style.fontSize = '12.5px';
            toast.style.fontWeight = '700';
            toast.style.boxShadow = '0 4px 14px rgba(0,0,0,0.25)';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.25s ease';
            toast.innerHTML = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            }, 10);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 300);
            }, 3000);
        },

        /**
         * String Formatting Helpers for ESC/POS Column Layouts
         */
        padRight: function(str, len) {
            str = String(str || '');
            if (str.length >= len) return str.substring(0, len);
            return str + ' '.repeat(len - str.length);
        },

        padLeft: function(str, len) {
            str = String(str || '');
            if (str.length >= len) return str.substring(0, len);
            return ' '.repeat(len - str.length) + str;
        },

        twoColumnLine: function(leftStr, rightStr, totalCols) {
            leftStr = String(leftStr || '');
            rightStr = String(rightStr || '');
            if (!rightStr) {
                return leftStr + '\n';
            }
            const needed = leftStr.length + rightStr.length + 1;
            if (needed <= totalCols) {
                const spaces = totalCols - leftStr.length - rightStr.length;
                return leftStr + ' '.repeat(spaces) + rightStr + '\n';
            }
            return leftStr + '\n' + POVoucherRenderer.padLeft(rightStr, totalCols) + '\n';
        },

        dividerLine: function(char, totalCols) {
            return (char || '-').repeat(totalCols) + '\n';
        },

        /**
         * Generate ESC/POS Binary/String Payload for Official Receipt (PAID)
         */
        generateESCPOSReceipt: function(data, format, options) {
            const d = this.normalizeReceiptData(data);
            const opts = Object.assign({}, this.getSettings(), options || {});
            const is80 = (format === '80' || format === '80mm' || format === 80 || opts.paperWidthMm === 80);
            const totalCols = is80 ? 48 : (opts.printColumns || 32);

            let buffer = '';

            // 1. Initialize
            buffer += ESCPOS.INIT;

            // 2. Hardware Density / Darkness / Font
            if (opts.thermalDensity >= 120 || opts.hardwareFont === 'font_a_bold') {
                buffer += ESCPOS.DOUBLE_STRIKE_ON;
            } else {
                buffer += ESCPOS.DOUBLE_STRIKE_OFF;
            }

            if (opts.hardwareFont === 'font_b') {
                buffer += ESCPOS.FONT_B;
            } else {
                buffer += ESCPOS.FONT_A;
            }

            // 3. Cash Drawer Kick (if configured)
            if (opts.autoCashDrawer) {
                buffer += ESCPOS.DRAWER_KICK;
            }

            // 4. Header (Centered)
            buffer += ESCPOS.ALIGN_CENTER;
            buffer += ESCPOS.SIZE_DOUBLE;
            buffer += (d.store_name || 'SAM & INRI CONSTRUCTION SUPPLY').toUpperCase() + '\n';
            buffer += ESCPOS.SIZE_NORMAL;
            if (d.store_address) {
                buffer += d.store_address + '\n';
            }
            buffer += 'Tel: ' + (d.store_phone || '09612735733') + '\n';
            buffer += ESCPOS.BOLD_ON;
            buffer += 'P.O.  RECEIPT\n(PAID)\n';
            buffer += ESCPOS.BOLD_OFF;
            buffer += this.dividerLine('=', totalCols);

            // 5. Metadata Table (Left-aligned)
            buffer += ESCPOS.ALIGN_LEFT;
            buffer += this.twoColumnLine('P.O. Receipt No:', '', totalCols);
            buffer += this.twoColumnLine('  ' + d.payment_id, '', totalCols);
            if (d.cashier_name) {
                buffer += this.twoColumnLine('CASHIER   :', d.cashier_name, totalCols);
            }
            buffer += this.twoColumnLine('CUSTOMER  :', d.customer_name, totalCols);
            buffer += this.twoColumnLine('PAYMENT   :', d.payment_method + ' (PAID)', totalCols);
            buffer += this.twoColumnLine('DATE      :', d.payment_date, totalCols);
            buffer += this.dividerLine('-', totalCols);

            // 6. Table Header
            buffer += this.twoColumnLine('ITEM DESCRIPTION', 'AMOUNT', totalCols);
            buffer += this.dividerLine('-', totalCols);

            // 7. Line Items
            d.items.forEach((item) => {
                const isSp = (item.item_type === 'SPECIAL_ORDER');
                const name = (isSp ? '[SPECIAL] ' : '') + item.product_name;
                const unitLabel = item.quantity > 1 ? 'pcs' : 'pc';
                const leftDetail = '  ' + item.quantity + ' ' + unitLabel + ' @ ' + POVoucherRenderer.formatMoney(item.unit_price);
                const rightAmount = POVoucherRenderer.formatMoney(item.line_net);

                buffer += ESCPOS.BOLD_ON;
                buffer += name + '\n';
                buffer += ESCPOS.BOLD_OFF;
                buffer += POVoucherRenderer.twoColumnLine(leftDetail, rightAmount, totalCols);
            });
            buffer += this.dividerLine('-', totalCols);

            // 8. Financial Summary & Totals
            buffer += this.twoColumnLine('Subtotal:', POVoucherRenderer.formatMoney(d.gross_subtotal), totalCols);
            if (d.delivery_cost > 0) {
                buffer += this.twoColumnLine('Delivery Fee:', POVoucherRenderer.formatMoney(d.delivery_cost), totalCols);
            }
            buffer += ESCPOS.BOLD_ON;
            buffer += this.twoColumnLine('TOTAL PAID:', 'PHP ' + POVoucherRenderer.formatMoney(d.grand_total), totalCols);
            buffer += ESCPOS.BOLD_OFF;

            if (d.amount_tendered !== undefined && d.amount_tendered !== null) {
                buffer += this.twoColumnLine('Tendered:', POVoucherRenderer.formatMoney(d.amount_tendered), totalCols);
                buffer += this.twoColumnLine('Change:', POVoucherRenderer.formatMoney(d.change_amount), totalCols);
            }
            buffer += this.dividerLine('=', totalCols);

            // 9. Footer (Centered)
            buffer += ESCPOS.ALIGN_CENTER;
            buffer += ESCPOS.BOLD_ON;
            buffer += '*** OFFICIAL RECEIPT ***\n';
            buffer += ESCPOS.BOLD_OFF;
            buffer += 'THANK YOU FOR YOUR PURCHASE!\n';
            buffer += 'eConstruction Supply POS\n';
            buffer += this.dividerLine('=', totalCols);

            // 10. Feed Lines, Buzzer & Cut
            buffer += ESCPOS.FEED_LINES(4);
            if (opts.buzzerBeep) {
                buffer += ESCPOS.BUZZER;
            }
            if (opts.autoCutter) {
                buffer += ESCPOS.CUT_PARTIAL;
            }

            return buffer;
        },

        /**
         * Generate ESC/POS Binary/String Payload for Purchase Order Voucher (UNPAID)
         */
        generateESCPOSVoucher: function(data, format, options) {
            const d = this.normalizePOData(data);
            const opts = Object.assign({}, this.getSettings(), options || {});
            const is80 = (format === '80' || format === '80mm' || format === 80 || opts.paperWidthMm === 80);
            const totalCols = is80 ? 48 : (opts.printColumns || 32);

            let buffer = '';

            // 1. Initialize
            buffer += ESCPOS.INIT;

            // 2. Darkness & Font
            if (opts.thermalDensity >= 120 || opts.hardwareFont === 'font_a_bold') {
                buffer += ESCPOS.DOUBLE_STRIKE_ON;
            } else {
                buffer += ESCPOS.DOUBLE_STRIKE_OFF;
            }

            if (opts.hardwareFont === 'font_b') {
                buffer += ESCPOS.FONT_B;
            } else {
                buffer += ESCPOS.FONT_A;
            }

            // 3. Header (Centered)
            buffer += ESCPOS.ALIGN_CENTER;
            buffer += ESCPOS.SIZE_DOUBLE;
            buffer += (d.store_name || 'SAM & INRI CONSTRUCTION SUPPLY').toUpperCase() + '\n';
            buffer += ESCPOS.SIZE_NORMAL;
            if (d.store_address) {
                buffer += d.store_address + '\n';
            }
            buffer += 'Tel: ' + (d.store_phone || '09612735733') + '\n';
            buffer += ESCPOS.BOLD_ON;
            buffer += 'PURCHASE ORDER VOUCHER\n(UNPAID)\n';
            buffer += ESCPOS.BOLD_OFF;
            buffer += this.dividerLine('=', totalCols);

            // 4. Metadata Table (Left-aligned)
            buffer += ESCPOS.ALIGN_LEFT;
            buffer += this.twoColumnLine('PO NO   :', d.po_number, totalCols);
            if (d.cashier_name) {
                buffer += this.twoColumnLine('CASHIER   :', d.cashier_name, totalCols);
            }
            buffer += this.twoColumnLine('CUSTOMER:', d.customer_name, totalCols);
            buffer += this.twoColumnLine('STATUS  :', 'AWAITING PAYMENT', totalCols);
            buffer += this.twoColumnLine('DATE    :', d.po_date, totalCols);
            buffer += this.dividerLine('-', totalCols);

            // 5. Table Header
            buffer += this.twoColumnLine('ITEM DESCRIPTION', 'AMOUNT', totalCols);
            buffer += this.dividerLine('-', totalCols);

            // 6. Line Items
            d.items.forEach((item) => {
                const isSp = (item.item_type === 'SPECIAL_ORDER');
                const name = (isSp ? '[SPECIAL] ' : '') + item.product_name;
                const unitLabel = item.quantity > 1 ? 'pcs' : 'pc';
                const leftDetail = '  ' + item.quantity + ' ' + unitLabel + ' @ ' + POVoucherRenderer.formatMoney(item.unit_price);
                const rightAmount = POVoucherRenderer.formatMoney(item.line_net);

                buffer += ESCPOS.BOLD_ON;
                buffer += name + '\n';
                buffer += ESCPOS.BOLD_OFF;
                buffer += POVoucherRenderer.twoColumnLine(leftDetail, rightAmount, totalCols);
            });
            buffer += this.dividerLine('-', totalCols);

            // 7. Totals
            buffer += this.twoColumnLine('Subtotal:', POVoucherRenderer.formatMoney(d.gross_subtotal), totalCols);
            if (d.total_discount_savings > 0) {
                buffer += this.twoColumnLine('Discount:', '-' + POVoucherRenderer.formatMoney(d.total_discount_savings), totalCols);
            }
            if (d.delivery_cost > 0) {
                buffer += this.twoColumnLine('Delivery Fee:', POVoucherRenderer.formatMoney(d.delivery_cost), totalCols);
            }
            buffer += ESCPOS.BOLD_ON;
            buffer += this.twoColumnLine('TOTAL DUE:', 'PHP ' + POVoucherRenderer.formatMoney(d.grand_total), totalCols);
            buffer += ESCPOS.BOLD_OFF;
            buffer += this.dividerLine('=', totalCols);

            // 8. Footer (Centered)
            buffer += ESCPOS.ALIGN_CENTER;
            buffer += ESCPOS.BOLD_ON;
            buffer += '*** PROCEED TO CASHIER ***\n';
            buffer += 'FOR PAYMENT\n';
            buffer += ESCPOS.BOLD_OFF;
            buffer += 'Thank you for your business!\n';
            buffer += 'eConstruction Supply POS\n';
            buffer += this.dividerLine('=', totalCols);

            // 9. Feed Lines, Buzzer & Cut
            buffer += ESCPOS.FEED_LINES(4);
            if (opts.buzzerBeep) {
                buffer += ESCPOS.BUZZER;
            }
            if (opts.autoCutter) {
                buffer += ESCPOS.CUT_PARTIAL;
            }

            return buffer;
        },

        /**
         * Generate ESC/POS Diagnostic Test Ticket
         */
        generateESCPOSDiagnosticTicket: function(options) {
            const opts = Object.assign({}, this.getSettings(), options || {});
            const totalCols = (opts.paperWidthMm === 80) ? 48 : (opts.printColumns || 32);

            let buffer = '';
            buffer += ESCPOS.INIT;
            if (opts.thermalDensity >= 120) buffer += ESCPOS.DOUBLE_STRIKE_ON;
            buffer += (opts.hardwareFont === 'font_b') ? ESCPOS.FONT_B : ESCPOS.FONT_A;

            if (opts.autoCashDrawer) buffer += ESCPOS.DRAWER_KICK;

            buffer += ESCPOS.ALIGN_CENTER;
            buffer += ESCPOS.SIZE_DOUBLE;
            buffer += 'POS PRINTER TEST\n';
            buffer += ESCPOS.SIZE_NORMAL;
            buffer += 'HARDWARE DIAGNOSTIC TICKET\n';
            buffer += this.dividerLine('=', totalCols);

            buffer += ESCPOS.ALIGN_LEFT;
            buffer += this.twoColumnLine('Printer Model:', opts.printerName || 'JK-5802H', totalCols);
            buffer += this.twoColumnLine('Print Mode   :', (opts.printEngine || 'browser').toUpperCase(), totalCols);
            buffer += this.twoColumnLine('Roll Width   :', (opts.paperWidthMm || 58) + ' mm', totalCols);
            buffer += this.twoColumnLine('Content Width:', (opts.printContentWidthMm || 53) + ' mm', totalCols);
            buffer += this.twoColumnLine('Total Columns:', totalCols + ' chars', totalCols);
            buffer += this.twoColumnLine('Heat Density :', (opts.thermalDensity || 120) + '%', totalCols);
            buffer += this.twoColumnLine('Timestamp    :', new Date().toLocaleTimeString(), totalCols);
            buffer += this.dividerLine('-', totalCols);

            buffer += ESCPOS.ALIGN_CENTER;
            buffer += 'COLUMN ALIGNMENT RULER:\n';
            buffer += '12345678901234567890123456789012'.substring(0, totalCols) + '\n';
            buffer += this.dividerLine('-', totalCols);

            buffer += 'HIGH DENSITY HEAT TEST:\n';
            buffer += '################################'.substring(0, totalCols) + '\n';
            buffer += '================================'.substring(0, totalCols) + '\n';
            buffer += ESCPOS.BOLD_ON;
            buffer += '*** ESC/POS TEST PASSED ***\n';
            buffer += ESCPOS.BOLD_OFF;
            buffer += this.dividerLine('=', totalCols);

            buffer += ESCPOS.FEED_LINES(4);
            if (opts.buzzerBeep) buffer += ESCPOS.BUZZER;
            if (opts.autoCutter) buffer += ESCPOS.CUT_PARTIAL;

            return buffer;
        },

        /**
         * Send ESC/POS payload to local daemon (localhost:9100) with fallback
         */
        sendToESCPOSDaemon: function(escposString, callback) {
            const s = this.getSettings();
            const daemonUrl = s.escposDaemonUrl || 'http://127.0.0.1:9100/print';
            
            // Base64 encode the binary/ascii payload
            let base64Data = '';
            try {
                base64Data = btoa(unescape(encodeURIComponent(escposString)));
            } catch(e) {
                base64Data = btoa(escposString);
            }

            const payload = {
                printer: s.printerId || 'default',
                data: base64Data,
                raw: escposString,
                copies: s.copies || 1
            };

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 1800);

            fetch(daemonUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: controller.signal
            })
            .then(function(res) {
                clearTimeout(timeoutId);
                if (res.ok) {
                    POVoucherRenderer.showToast('✅ Sent to ESC/POS Thermal Hardware Printer', 'success');
                    if (typeof callback === 'function') callback(true);
                } else {
                    throw new Error('Daemon status: ' + res.status);
                }
            })
            .catch(function(err) {
                clearTimeout(timeoutId);
                console.warn('ESC/POS Daemon offline, falling back to Browser Print Dialog:', err);
                POVoucherRenderer.showToast('ℹ️ ESC/POS local daemon offline — opening high-contrast print preview', 'info');
                if (typeof callback === 'function') callback(false);
            });
        },

        /**
         * Execute print job via silent hidden iframe directly to Chrome's native print dialog
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
         * Wrap an HTML element or snippet in a thermal printable page with 1-bit high-contrast rendering
         */
        wrapThermalHtml: function(innerHtml, format) {
            const is80 = (format === '80' || format === '80mm' || format === 80);
            const isA4 = (format === 'a4' || format === 'pdfA4' || format === 210 || format === '210');
            
            const savedSettings = this.getSettings();
            const paperWidthMm = isA4 ? 210 : (is80 ? 80 : (savedSettings.paperWidthMm || 58));
            const printableWidthMm = isA4 
                ? (savedSettings.printWidthA4Mm || 195)
                : (is80 
                    ? (savedSettings.printContentWidthMm_80 || 72) 
                    : (savedSettings.printContentWidthMm || parseFloat(localStorage.getItem('pos_printer_content_width')) || 53));
                    
            const fontName = savedSettings.thermalFontName || localStorage.getItem('pos_printer_font_name') || 'Courier New';
            const fontSizeVal = savedSettings.thermalDefaultFontSize || localStorage.getItem('pos_printer_font_size') || (is80 ? 11.5 : (isA4 ? 12 : 10.5));
            const fontSizePt = (typeof fontSizeVal === 'string' && fontSizeVal.endsWith('pt')) ? fontSizeVal : (fontSizeVal + 'pt');
            const lineHeight = savedSettings.thermalLineHeight || localStorage.getItem('pos_printer_line_height') || '1.25';
            const thermalDensity = parseInt(savedSettings.thermalDensity, 10) || 120;

            // Thermal Darkness & Stroke Simulation
            let densityCss = '';
            if (!isA4) {
                if (thermalDensity >= 140) {
                    densityCss = '-webkit-text-stroke: 0.2px #000000; text-shadow: 0 0 0.35px #000000; font-weight: 700;';
                } else if (thermalDensity >= 120) {
                    densityCss = '-webkit-text-stroke: 0.1px #000000; text-shadow: 0 0 0.15px #000000;';
                }
            }

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
            color: #000000 !important;
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
            font-family: '${fontName}', Consolas, 'Liberation Mono', monospace !important;
            font-size: ${fontSizePt} !important;
            line-height: ${lineHeight} !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            -webkit-font-smoothing: none !important;
            font-smooth: never !important;
            text-rendering: geometricPrecision !important;
            ${densityCss}
        }
        .thermal-print-container {
            width: ${printableWidthMm}mm !important;
            max-width: ${printableWidthMm}mm !important;
            margin: 0 auto !important;
            padding: 2mm 0 !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
            ${densityCss}
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-family: inherit !important;
            font-size: inherit !important;
            line-height: inherit !important;
            ${densityCss}
        }
        th, td {
            vertical-align: top !important;
            color: #000000 !important;
            font-family: inherit !important;
            ${densityCss}
        }
        strong, b, th {
            font-weight: 800 !important;
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
         * Normalize PO Data Object
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
                cashier_name: data.cashier_name || data.cashier || (data.supplier && data.supplier.cashier) || '',
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
                        <strong style="font-weight: 900; font-size: 11pt; color: #000;">${item.quantity} ${item.quantity > 1 ? 'pcs' : 'pc'}</strong> @ ${POVoucherRenderer.formatMoney(item.unit_price)}
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

        /**
         * Print PO Voucher with Smart Dispatcher (ESC/POS vs Browser)
         */
        print: function(dataOrElement, format, options) {
            const s = this.getSettings();
            const opts = Object.assign({}, s, options || {});
            const fmt = format || (opts.paperWidthMm ? String(opts.paperWidthMm) : '58');

            // ESC/POS Direct Hardware Stream
            if (opts.printEngine === 'escpos' && dataOrElement && typeof dataOrElement === 'object' && dataOrElement.nodeType === undefined) {
                const escposData = this.generateESCPOSVoucher(dataOrElement, fmt, opts);
                this.sendToESCPOSDaemon(escposData, (success) => {
                    if (!success) {
                        const voucherHtml = this.renderThermalPO(dataOrElement, fmt);
                        const html = this.wrapThermalHtml(voucherHtml, fmt);
                        this.executeIframePrint(html, 'Purchase Order Voucher');
                    }
                });
                return;
            }

            // High-Contrast Browser Print
            if (typeof dataOrElement === 'string' && document.getElementById(dataOrElement)) {
                const el = document.getElementById(dataOrElement);
                const html = this.wrapThermalHtml(el.innerHTML, fmt);
                this.executeIframePrint(html, 'Purchase Order Voucher');
            } else if (dataOrElement && dataOrElement.nodeType === 1) {
                const html = this.wrapThermalHtml(dataOrElement.innerHTML, fmt);
                this.executeIframePrint(html, 'Purchase Order Voucher');
            } else if (dataOrElement && typeof dataOrElement === 'object') {
                const voucherHtml = this.renderThermalPO(dataOrElement, fmt);
                const html = this.wrapThermalHtml(voucherHtml, fmt);
                this.executeIframePrint(html, 'Purchase Order Voucher');
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
                cashier_name: data.cashier_name || data.cashier || (data.supplier && data.supplier.cashier) || '',
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

        /**
         * Render HTML for Official Receipt from structured data object
         */
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
                        <strong style="font-weight: 900; font-size: 11pt; color: #000;">${item.quantity} ${item.quantity > 1 ? 'pcs' : 'pc'}</strong> @ ${POVoucherRenderer.formatMoney(item.unit_price)}
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
                    <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; color: #000;">P.O.  RECEIPT</div>
                    <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000;">(PAID)</div>
                </div>
                <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-top: 3px; margin-bottom: 4px; overflow: hidden; white-space: nowrap;">================================</div>

                <table style="width: 100%; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin-bottom: 2px; border-collapse: collapse;">
                    <tr>
                        <td colspan="2" style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">P.O. Receipt No:</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 0 0 2px 8px; vertical-align: top; font-weight: bold;">&nbsp;&nbsp;${POVoucherRenderer.escapeHtml(d.payment_id)}</td>
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

        /**
         * Print Official Receipt with Smart Dispatcher (ESC/POS vs Browser)
         */
        printReceiptData: function(dataOrElement, format, options) {
            const s = this.getSettings();
            const opts = Object.assign({}, s, options || {});
            const fmt = format || (opts.paperWidthMm ? String(opts.paperWidthMm) : '58');

            // ESC/POS Direct Hardware Stream
            if (opts.printEngine === 'escpos' && dataOrElement && typeof dataOrElement === 'object' && dataOrElement.nodeType === undefined) {
                const escposData = this.generateESCPOSReceipt(dataOrElement, fmt, opts);
                this.sendToESCPOSDaemon(escposData, (success) => {
                    if (!success) {
                        const receiptHtml = this.renderThermalReceipt(dataOrElement, fmt);
                        const html = this.wrapThermalHtml(receiptHtml, fmt);
                        this.executeIframePrint(html, 'Official Receipt');
                    }
                });
                return;
            }

            // High-Contrast Browser Print
            if (typeof dataOrElement === 'string' && document.getElementById(dataOrElement)) {
                const el = document.getElementById(dataOrElement);
                const html = this.wrapThermalHtml(el.innerHTML, fmt);
                this.executeIframePrint(html, 'Official Receipt');
            } else if (dataOrElement && dataOrElement.nodeType === 1) {
                const html = this.wrapThermalHtml(dataOrElement.innerHTML, fmt);
                this.executeIframePrint(html, 'Official Receipt');
            } else if (dataOrElement && typeof dataOrElement === 'object') {
                const receiptHtml = this.renderThermalReceipt(dataOrElement, fmt);
                const html = this.wrapThermalHtml(receiptHtml, fmt);
                this.executeIframePrint(html, 'Official Receipt');
            }
        }
    };

    // Global bindings
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
     * Print PO Voucher directly
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
     * Print Official Receipt directly
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
